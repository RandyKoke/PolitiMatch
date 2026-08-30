import { defineStore } from 'pinia';
import { apiResults } from '@/services/apiResults';
import { apiCompare } from '@/services/apiCompare';
import { apiShare } from '@/services/apiShare';
import { apiUsers } from '@/services/apiUsers';
import { toUserMessage } from '@/services/apiClient';
import { useAuthStore } from '@/stores/authStore';

export const useResultsStore = defineStore('results', {
    state: () => ({
        quizUuid: null,
        compatibilityScores: [], // [{ party_id, compatibility_score, rank, party }] — toujours issu de GET /results ou /share, jamais du raw ranked_scores de POST /quiz/complete (voir loadResults).
        axes: { x: null, y: null },
        profileLabel: null,
        profileDescription: null,
        // { state, real_answers_count, skipped_count, total_questions },
        // calculé côté backend (QuizReliabilityService), null tant qu'aucun
        // résultat n'a été chargé. Jamais recalculé côté frontend :
        // une seule source de vérité, cf. QuizReliabilityNotice.vue.
        reliability: null,
        // Statut d'un résultat consulté mais pas encore 'completed' (pending/
        // computing/failed) : distinct de `error`, ce n'est pas une panne,
        // juste un résultat pas encore prêt — voir loadResults.
        notReadyStatus: null,
        compareData: null, // réponse brute de GET /compare (questions/parties/positions)
        shareToken: null,
        history: [], // GET /api/user/results — tableau de bord
        loading: false,
        error: null,
    }),

    actions: {
        /**
         * Alimenté directement par quizStore après POST /quiz/complete ou
         * /quiz/{uuid}/retry. Ne contient PAS les informations de parti
         * (nom/couleur/logo) : POST /quiz/complete renvoie `ranked_scores`
         * (juste party_id/score/rank, cf. MatchingService::computeMatching),
         * une forme différente de GET /results/{uuid} qui, lui, inclut le
         * parti complet (party_scores[].party). Plutôt que de dupliquer la
         * logique d'enrichissement côté frontend ou d'exposer deux formes
         * différentes dans ResultsView, cette action ne sert qu'à fixer
         * `quizUuid` tout de suite pour une navigation immédiate.
         * ResultsView appelle ensuite systématiquement loadResults() à son
         * montage, qui reste la seule source de vérité pour l'affichage
         * complet (un aller-retour réseau supplémentaire est nécessaire pour
         * obtenir les données parti).
         */
        setFromMatchingResponse(data) {
            this.quizUuid = data.quiz_uuid;
            this.profileLabel = data.profile_label;
            this.profileDescription = data.profile_description;
            this.axes = { x: data.axis_x, y: data.axis_y };
        },

        /**
         * Chargement complet (arrivée sur /results/:uuid, au montage de la
         * vue — que ce soit juste après avoir terminé le quiz ou via un lien
         * direct/rechargement de page).
         *
         * Un résultat pas encore 'completed' n'est pas une erreur réseau :
         * l'API renvoie 409 avec `{ status }` (cf. ResultController::show).
         * Ce cas alimente `notReadyStatus`, pas `error`, pour que ResultsView
         * puisse distinguer "en attente" d'une vraie panne.
         */
        async loadResults(uuid) {
            this.loading = true;
            this.error = null;
            this.notReadyStatus = null;
            try {
                const { data } = await apiResults.show(uuid, useAuthStore().sessionToken);
                this.applyResultPayload(data);

                return data;
            } catch (error) {
                if (error.response?.status === 409 && error.response.data?.status) {
                    this.notReadyStatus = error.response.data.status;
                } else {
                    this.error = toUserMessage(error);
                }
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Consultation publique d'un résultat partagé (/share/:token) : même
         * forme de réponse que loadResults (ShareController::show réutilise
         * QuizResult::toResultPayload(), la même méthode que ResultController),
         * mais jamais de session_token/auth envoyé — voir apiShare.show.
         */
        async loadShareData(token) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiShare.show(token);
                this.applyResultPayload(data);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        applyResultPayload(data) {
            this.quizUuid = data.quiz_uuid;
            this.compatibilityScores = data.party_scores;
            this.axes = { x: data.political_axis_x, y: data.political_axis_y };
            this.profileLabel = data.profile_label;
            this.profileDescription = data.profile_description;
            this.reliability = data.reliability ?? null;
        },

        async loadCompareData(uuid, { themeId, axeIdeologique } = {}) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiCompare.index({
                    quizResultUuid: uuid,
                    sessionToken: useAuthStore().sessionToken,
                    themeId,
                    axeIdeologique,
                });
                this.compareData = data;

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Active le partage et renvoie le share_token (idempotent côté
         * backend — cf. ShareController::create). N'écrase `this.error` que
         * sur un vrai échec : un appel réussi ne doit pas effacer un message
         * d'erreur déjà affiché ailleurs sur l'écran par mégarde... en
         * pratique ce store est mono-écran à la fois, donc surtout pour
         * rester cohérent avec les autres actions.
         */
        async createShareLink(quizUuid) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiShare.create(quizUuid, useAuthStore().sessionToken);
                this.shareToken = data.share_token;

                return data.share_token;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Historique du tableau de bord (utilisateur connecté uniquement).
         */
        async loadHistory() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiUsers.results();
                this.history = data.quiz_results;

                return data.quiz_results;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Retire l'entrée de `history` seulement après confirmation du
         * serveur, jamais avant : une suppression échouée ne doit pas faire
         * disparaître une ligne encore bien présente en base.
         */
        async deleteHistoryEntry(uuid) {
            this.error = null;
            try {
                await apiUsers.deleteResult(uuid);
                this.history = this.history.filter((entry) => entry.uuid !== uuid);
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            }
        },
    },
});
