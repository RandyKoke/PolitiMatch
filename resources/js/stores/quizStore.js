import { defineStore } from 'pinia';
import { apiQuiz } from '@/services/apiQuiz';
import { toUserMessage } from '@/services/apiClient';
import { useAuthStore } from '@/stores/authStore';
import { useResultsStore } from '@/stores/resultsStore';

// Persisté pour survivre à un rechargement de page en plein quiz (cahier des
// charges §5.2). Le session_token, lui, est déjà persisté par authStore —
// c'est délibérément là qu'il vit (cf. commentaire dans authStore.js), ce
// store le lit plutôt que de le dupliquer.
const QUIZ_UUID_STORAGE_KEY = 'politimatch.quiz_result_uuid';

export const useQuizStore = defineStore('quiz', {
    state: () => ({
        currentQuizUuid: localStorage.getItem(QUIZ_UUID_STORAGE_KEY),
        // Alias volontairement absent : la spec initiale distinguait
        // "quizResultId" de "currentQuizUuid", mais l'API n'expose jamais
        // d'identifiant numérique côté client (uniquement l'UUID, y compris
        // pour le model binding Laravel {quizResult:uuid}) — un second champ
        // dupliquerait la même valeur sans utilité, donc un seul existe ici.
        status: null, // 'pending' | 'computing' | 'completed' | 'failed' | null (jamais interrogé)
        questions: [],
        currentQuestionIndex: 0,
        answers: {}, // { [question_id]: { user_score, was_skipped } } — objet plutôt que Map, sérialisable tel quel (devtools, debug).
        // Nombre de "Passer" cliqués d'affilée, remis à 0 dès qu'une vraie
        // réponse est soumise (cf. submitAnswer). Volontairement PAS
        // reconstruit dans resumeState() à partir des réponses déjà en base :
        // l'API ne garantit pas leur ordre de renvoi (cf. commentaire sur
        // resumeState ci-dessous, même prudence multi-onglets), et ce
        // compteur ne sert qu'à un nudge ponctuel pendant la session en
        // cours — repartir de 0 après un rechargement de page est un choix
        // délibéré, pas un oubli (pire cas : un utilisateur qui rechargerait
        // juste après une série de "Passer" devrait cliquer deux fois de
        // plus qu'attendu avant de revoir le message, sans aucune
        // conséquence fonctionnelle).
        consecutiveSkips: 0,
        // 'skipped' pendant une reprise ciblée (uniquement les questions
        // passées d'un résultat "too_few", cf. resumeSkippedOnly),
        // null sinon. QuizView s'appuie dessus pour NE PAS écraser la
        // liste de questions déjà filtrée avec un resumeState() complet à
        // son montage.
        resumeMode: null,
        loading: false, // opérations "de fond" : start, loadQuestions, resumeState, completeQuiz, retryQuiz
        // Distinct de `loading` : verrou spécifique à submitAnswer(), pour
        // désactiver uniquement les boutons de réponse pendant une requête
        // et empêcher un double clic, sans faire clignoter le reste de
        // l'écran (barre de progression, etc.) à chaque question.
        submitting: false,
        error: null,
    }),

    getters: {
        // Dérivé plutôt que stocké : une valeur "progress" séparée pourrait
        // diverger du nombre réel de réponses (ex. si une réponse échoue
        // silencieusement) — un seul calcul, une seule source de vérité.
        progress: (state) => {
            if (state.questions.length === 0) {
                return 0;
            }

            return Math.round((Object.keys(state.answers).length / state.questions.length) * 100);
        },

        answeredCount: (state) => Object.keys(state.answers).length,

        currentQuestion: (state) => state.questions[state.currentQuestionIndex] ?? null,

        isComplete: (state) => state.questions.length > 0 && Object.keys(state.answers).length >= state.questions.length,
    },

    actions: {
        setQuizUuid(uuid) {
            this.currentQuizUuid = uuid;
            if (uuid) {
                localStorage.setItem(QUIZ_UUID_STORAGE_KEY, uuid);
            } else {
                localStorage.removeItem(QUIZ_UUID_STORAGE_KEY);
            }
        },

        /**
         * Abandonne toute référence à un quiz précédent (ex. l'utilisateur
         * choisit explicitement "recommencer" plutôt que "reprendre" sur
         * StartAccessView). Aucun appel API : il n'existe pas d'endpoint de
         * suppression, et ce n'est pas nécessaire — le QuizResult abandonné
         * reste simplement en base, sans effet de bord (déjà documenté comme
         * limite connue : purge des sessions non converties pas encore
         * implémentée, cf. cahier des charges §5.2).
         */
        discardCurrentQuiz() {
            this.setQuizUuid(null);
            this.status = null;
            this.questions = [];
            this.currentQuestionIndex = 0;
            this.answers = {};
            this.consecutiveSkips = 0;
            this.resumeMode = null;
            this.error = null;
        },

        async startQuiz() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiQuiz.start();
                this.setQuizUuid(data.quiz_result_uuid);
                if (data.session_token) {
                    useAuthStore().setSessionToken(data.session_token);
                }
                this.status = 'pending';
                this.currentQuestionIndex = 0;
                this.answers = {};
                this.consecutiveSkips = 0;
                this.resumeMode = null;

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadQuestions() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiQuiz.questions();
                this.questions = data.questions;

                return data.questions;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Reprise après rechargement de page ou nouvelle visite de /quiz :
         * reconstruit questions + réponses déjà données + position courante
         * en un seul appel (GET /api/quiz/{uuid}/state). Repositionne
         * `currentQuestionIndex` sur la première question sans réponse plutôt
         * que sur le nombre de réponses (les deux coïncident tant qu'on
         * répond dans l'ordre, mais ne pas présupposer l'ordre protège contre
         * un scénario multi-onglets où les réponses arriveraient dans un
         * ordre différent).
         */
        async resumeState() {
            if (!this.currentQuizUuid) {
                return null;
            }

            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiQuiz.state(this.currentQuizUuid, useAuthStore().sessionToken);

                this.status = data.quiz_result.status;
                this.questions = data.questions;
                this.answers = Object.fromEntries(
                    data.answers.map((answer) => [answer.question_id, {
                        user_score: answer.user_score,
                        was_skipped: answer.was_skipped,
                    }]),
                );

                const firstUnanswered = this.questions.findIndex((question) => this.answers[question.id] === undefined);
                this.currentQuestionIndex = firstUnanswered === -1 ? this.questions.length : firstUnanswered;

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Reprise ciblée : réouvre le QuizResult "too_few" côté backend
         * (POST /resume-skipped), puis reconstruit l'état à partir de GET
         * /state comme resumeState(), mais filtre `questions` pour
         * ne garder QUE celles marquées `was_skipped` dans les réponses déjà
         * en base. Tous les getters dérivés (progress, isComplete,
         * currentQuestion) opèrent ensuite naturellement sur ce sous-
         * ensemble, sans aucun changement nécessaire à QuizView.vue.
         * `resumeMode = 'skipped'` empêche QuizView d'écraser ce filtrage
         * avec son propre resumeState() complet à son montage.
         */
        async resumeSkippedOnly(quizResultUuid) {
            this.loading = true;
            this.error = null;
            try {
                await apiQuiz.resumeSkipped(quizResultUuid, {
                    session_token: useAuthStore().sessionToken,
                });
                const { data } = await apiQuiz.state(quizResultUuid, useAuthStore().sessionToken);

                this.setQuizUuid(quizResultUuid);
                this.status = data.quiz_result.status;
                const skippedQuestionIds = new Set(
                    data.answers.filter((answer) => answer.was_skipped).map((answer) => answer.question_id),
                );
                this.questions = data.questions.filter((question) => skippedQuestionIds.has(question.id));
                this.answers = {};
                this.currentQuestionIndex = 0;
                this.consecutiveSkips = 0;
                this.resumeMode = 'skipped';

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Sérialisée par construction : `submitting` sert de verrou, tout
         * appel pendant qu'une soumission est déjà en cours est ignoré plutôt
         * que mis en file — cohérent avec le choix backend documenté de 30
         * requêtes séquentielles (spec technique §8), jamais en parallèle.
         * `currentQuestionIndex` n'avance qu'après confirmation du serveur :
         * une perte réseau laisse l'utilisateur sur la même question, jamais
         * dans un état "avancé mais pas vraiment enregistré".
         */
        async submitAnswer({ questionId, userScore, wasSkipped = false }) {
            if (this.submitting) {
                return;
            }

            this.submitting = true;
            this.error = null;
            try {
                await apiQuiz.submitAnswer({
                    quiz_result_uuid: this.currentQuizUuid,
                    question_id: questionId,
                    user_score: userScore,
                    was_skipped: wasSkipped,
                    session_token: useAuthStore().sessionToken,
                });
                this.answers[questionId] = { user_score: userScore, was_skipped: wasSkipped };
                // Remis à 0 sur une vraie réponse, incrémenté sur "Passer" :
                // seul signal utilisé par QuizView pour (1) suspendre le
                // message motivationnel habituel tant que la dernière action
                // n'est pas une vraie réponse et (2) déclencher le message
                // d'encouragement dédié après plusieurs "Passer" d'affilée.
                this.consecutiveSkips = wasSkipped ? this.consecutiveSkips + 1 : 0;
                if (this.currentQuestionIndex < this.questions.length) {
                    this.currentQuestionIndex += 1;
                }
            } catch (error) {
                // Message dédié pour une coupure réseau pure (pas de réponse
                // HTTP du tout) : plus actionnable que le message générique
                // de toUserMessage() dans ce contexte précis.
                this.error = error.response
                    ? toUserMessage(error)
                    : "Problème de connexion : ta réponse n'a pas été enregistrée. Réessaie.";
                throw error;
            } finally {
                this.submitting = false;
            }
        },

        goToPreviousQuestion() {
            if (this.currentQuestionIndex > 0) {
                this.currentQuestionIndex -= 1;
            }
        },

        /**
         * Le résultat (scores, axes, profil) est délégué à resultsStore :
         * une seule source de vérité pour ces données, partagée par l'écran
         * de résultat et par ce qui suit immédiatement complete() (cahier
         * des charges : écran "Calcul" → résultat principal).
         *
         * Se resynchronise via resumeState() juste avant de finaliser :
         * mitigation légère du scénario multi-onglets. Si un autre onglet a
         * répondu à des questions entre-temps, on complète
         * sur la base des réponses réellement en base, pas d'un état local
         * potentiellement périmé.
         */
        async completeQuiz() {
            await this.resumeState();

            if (!this.isComplete) {
                this.error = `Il reste des questions sans réponse (${this.answeredCount}/${this.questions.length}).`;
                throw new Error('quiz incomplete');
            }

            const resultsStore = useResultsStore();

            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiQuiz.complete({
                    quiz_result_uuid: this.currentQuizUuid,
                    session_token: useAuthStore().sessionToken,
                });
                this.status = 'completed';
                this.resumeMode = null;
                resultsStore.setFromMatchingResponse(data);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async retryQuiz() {
            const resultsStore = useResultsStore();

            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiQuiz.retry(this.currentQuizUuid, {
                    session_token: useAuthStore().sessionToken,
                });
                this.status = 'completed';
                resultsStore.setFromMatchingResponse(data);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
