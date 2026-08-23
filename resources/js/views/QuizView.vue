<script setup>
import { computed, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import { useQuizStore } from '@/stores/quizStore';
import { useUiStore } from '@/stores/uiStore';
import { resolveProgressMessage, SKIP_ENCOURAGEMENT_MESSAGE, SKIP_ENCOURAGEMENT_THRESHOLD } from '@/config/progressMessages';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';
import PmTag from '@/components/ui/PmTag.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmModal from '@/components/ui/PmModal.vue';

const router = useRouter();
const quizStore = useQuizStore();
const uiStore = useUiStore();

const initializing = ref(true);
const completing = ref(false);
const retrying = ref(false);
const showExplanation = ref(false);
const showLeaveConfirm = ref(false);
let pendingLeaveNext = null;

// Échelle de Likert complète à 5 niveaux (-2..+2), pas seulement les
// extrêmes + neutre : le modèle de données et toutes les formules
// (ScoreCalculator, PoliticalAxisCalculator) sont conçus pour exploiter les
// 5 valeurs entières de la plage, conformément au document de l'expert
// politique — une échelle à 3 boutons (comme avant cette correction)
// perdait silencieusement la nuance -1/+1 que l'utilisateur n'avait tout
// simplement aucun moyen d'exprimer. Toujours pas de geste de swipe à
// intensité variable (pas de librairie de gestes installée, cf. §8.2) :
// 5 boutons discrets couvrent exactement la plage attendue, restent
// pleinement accessibles au clavier, et suivent le même principe de
// gradation qu'un test politique de référence (BlossomUp, RTBF) sans en
// reprendre le design visuel.
const CHOICES = [
    { label: "Pas du tout d'accord", score: -2, variant: 'disagree-strong' },
    { label: "Pas d'accord", score: -1, variant: 'disagree' },
    { label: 'Neutre', score: 0, variant: 'neutral-vote' },
    { label: "D'accord", score: 1, variant: 'agree' },
    { label: "Tout à fait d'accord", score: 2, variant: 'agree-strong' },
];

// Numéro 1-based de la question affichée à l'écran en ce moment précis,
// borné au nombre réel de questions (même valeur que celle affichée sur la
// barre "Question N / total" ci-dessous, cf. template) — jamais un
// pourcentage de réponses déjà données, qui peut diverger de la question
// réellement visible (cf. progressMessages.js pour le détail du bug corrigé).
const displayedQuestionNumber = computed(() => Math.min(quizStore.currentQuestionIndex + 1, quizStore.questions.length));

// Le message motivationnel "récompense" une vraie progression : il ne doit
// jamais s'afficher juste après un "Passer" : sinon un utilisateur qui ne
// fait que passer les questions verrait quand même défiler "Tu avances
// bien", "Presque fini"... `consecutiveSkips` sert de garde : tant que la
// dernière action est un "Passer", ce message reste masqué, jamais recalculé
// sur un total de réponses différent (cf. progressMessages.js pour pourquoi
// ce n'est délibérément pas un simple changement du nombre passé à
// resolveProgressMessage : ça réintroduirait un décalage d'affichage entre
// la question affichée et la question réellement validée). Le message de
// complétion, lui, reste toujours affiché
// même si la toute dernière question a été passée : terminer un quiz reste
// terminer un quiz, ce n'est pas la même notion de "récompense" que les
// paliers intermédiaires.
const motivationalMessage = computed(() => {
    if (quizStore.consecutiveSkips > 0 && !quizStore.isComplete) {
        return null;
    }

    return resolveProgressMessage(
        displayedQuestionNumber.value,
        quizStore.questions.length,
        quizStore.isComplete,
    );
});

// Encouragement distinct, propre déclencheur (5 "Passer" d'affilée) : jamais
// mélangé à la grille de paliers ci-dessus, jamais culpabilisant. Persiste
// tant que le compteur reste au-dessus du seuil (pas un toast à un coup),
// disparaît dès qu'une vraie réponse remet `consecutiveSkips` à 0.
const skipEncouragementMessage = computed(() => (
    quizStore.consecutiveSkips >= SKIP_ENCOURAGEMENT_THRESHOLD ? SKIP_ENCOURAGEMENT_MESSAGE : null
));

async function initialize() {
    initializing.value = true;

    if (!quizStore.currentQuizUuid) {
        router.replace('/start');

        return;
    }

    // Reprise ciblée déjà chargée par resumeSkippedOnly() juste avant la
    // navigation vers /quiz (ResultsView::handleResumeSkipped) : un
    // resumeState() complet ici écraserait la liste de questions déjà
    // filtrée (uniquement celles passées) avec les 30 questions habituelles.
    if (quizStore.resumeMode === 'skipped') {
        initializing.value = false;

        return;
    }

    try {
        await quizStore.resumeState();
    } catch {
        // 403/404 (session/quiz invalide) ou coupure réseau au tout premier
        // chargement : rien à reprendre, on repart proprement plutôt que
        // d'afficher un écran de quiz cassé.
        quizStore.discardCurrentQuiz();
        uiStore.showToast("Ton quiz précédent n'est plus accessible. On recommence.", 'error');
        router.replace('/start');

        return;
    }

    if (quizStore.status === 'completed') {
        router.replace(`/results/${quizStore.currentQuizUuid}`);

        return;
    }

    initializing.value = false;
}

onMounted(initialize);

async function answer(score, wasSkipped = false) {
    const questionId = quizStore.currentQuestion?.id;
    if (!questionId) {
        return;
    }

    showExplanation.value = false;
    try {
        await quizStore.submitAnswer({ questionId, userScore: score, wasSkipped });
    } catch {
        uiStore.showToast(quizStore.error ?? "La réponse n'a pas pu être enregistrée.", 'error');
    }
}

function skip() {
    answer(0, true);
}

const previousAnswer = computed(() => {
    const questionId = quizStore.currentQuestion?.id;

    return questionId ? (quizStore.answers[questionId] ?? null) : null;
});

function goToPrevious() {
    showExplanation.value = false;
    quizStore.goToPreviousQuestion();
}

onBeforeRouteLeave((to, from, next) => {
    if (quizStore.answeredCount === 0 || quizStore.isComplete) {
        next();

        return;
    }

    pendingLeaveNext = next;
    showLeaveConfirm.value = true;
});

function confirmLeave() {
    showLeaveConfirm.value = false;
    pendingLeaveNext?.();
    pendingLeaveNext = null;
}

function cancelLeave() {
    showLeaveConfirm.value = false;
    pendingLeaveNext?.(false);
    pendingLeaveNext = null;
}

async function handleComplete() {
    completing.value = true;
    try {
        const data = await quizStore.completeQuiz();
        router.push(`/results/${data.quiz_uuid}`);
    } catch {
        uiStore.showToast(quizStore.error ?? "Impossible de finaliser le quiz pour l'instant.", 'error');
    } finally {
        completing.value = false;
    }
}

async function refreshState() {
    try {
        await quizStore.resumeState();
        if (quizStore.status === 'completed') {
            router.replace(`/results/${quizStore.currentQuizUuid}`);
        }
    } catch {
        uiStore.showToast('Impossible de vérifier le statut du quiz.', 'error');
    }
}

async function handleRetry() {
    retrying.value = true;
    try {
        const data = await quizStore.retryQuiz();
        router.push(`/results/${data.quiz_uuid}`);
    } catch {
        uiStore.showToast(quizStore.error ?? 'Le calcul a de nouveau échoué.', 'error');
    } finally {
        retrying.value = false;
    }
}
</script>

<template>
    <div v-if="initializing" class="flex justify-center py-16">
        <PmLoader label="Chargement du quiz" />
    </div>

    <PmCard v-else-if="quizStore.status === 'computing'">
        <h1 class="font-display text-lg font-semibold text-ink">Calcul en cours</h1>
        <p class="mt-2 text-sm text-gray-500">
            Tes résultats sont en cours de calcul. Cela ne prend normalement que quelques secondes.
        </p>
        <PmButton class="mt-4" @click="refreshState">Rafraîchir</PmButton>
    </PmCard>

    <PmCard v-else-if="quizStore.status === 'failed'">
        <h1 class="font-display text-lg font-semibold text-ink">Un problème est survenu</h1>
        <p class="mt-2 text-sm text-gray-500">
            Le calcul de tes résultats a échoué. Tes réponses sont conservées : tu peux relancer le calcul sans tout
            recommencer.
        </p>
        <PmButton class="mt-4" :loading="retrying" @click="handleRetry">Relancer le calcul</PmButton>
    </PmCard>

    <div v-else class="flex flex-col gap-4">
        <div>
            <div class="mb-1 flex items-center justify-between text-sm text-gray-500">
                <span>Question {{ displayedQuestionNumber }} / {{ quizStore.questions.length }}</span>
                <span>{{ quizStore.progress }}%</span>
            </div>
            <!-- Remplissage tricolore (noir → or → bordeaux) plutôt qu'une
                 couleur unique : le liseré de la direction artistique
                 réemployé ici comme repère de progression. Le dégradé
                 complet est peint une fois sur toute la largeur de la
                 piste ; un cache
                 (bg-stone-100) recouvre la portion pas encore atteinte et se
                 réduit par la droite au fil des réponses — les trois teintes
                 gardent ainsi des proportions fixes, ancrées sur la largeur
                 totale de la barre plutôt que sur celle, changeante, de la
                 portion déjà remplie. -->
            <div class="relative h-1.5 w-full overflow-hidden rounded-full" role="progressbar" :aria-valuenow="quizStore.progress" aria-valuemin="0" aria-valuemax="100">
                <div class="absolute inset-0" style="background: var(--gradient-tricolore);" />
                <div class="absolute inset-y-0 right-0 bg-stone-100 transition-all duration-300 ease-out" :style="{ width: (100 - quizStore.progress) + '%' }" />
            </div>
            <p v-if="motivationalMessage" class="mt-2 text-center text-xs font-medium text-bordeaux-600">{{ motivationalMessage }}</p>
        </div>

        <Transition name="pm-question-slide" mode="out-in">
            <PmCard v-if="quizStore.currentQuestion" :key="quizStore.currentQuestion.id">
                <PmTag v-if="quizStore.currentQuestion.theme" variant="brand">{{ quizStore.currentQuestion.theme.name }}</PmTag>
                <p class="mt-3 font-reading text-xl font-medium text-ink">{{ quizStore.currentQuestion.label }}</p>

                <!-- Contenu pédagogique réel (30 explications rédigées par
                     l'expert politique, quelques phrases chacune), jamais
                     raccourci pour tenir dans un composant trop petit.
                     aria-expanded/aria-controls : un vrai motif "disclosure"
                     doit les porter pour un lecteur d'écran. -->
                <button
                    v-if="quizStore.currentQuestion.explanation"
                    type="button"
                    class="mt-2 flex items-center gap-1.5 text-sm font-medium text-bordeaux-600 hover:text-bordeaux-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    :aria-expanded="showExplanation"
                    :aria-controls="`explanation-${quizStore.currentQuestion.id}`"
                    @click="showExplanation = !showExplanation"
                >
                    <svg
                        class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
                        :class="{ 'rotate-90': showExplanation }"
                        viewBox="0 0 20 20"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path d="M7 5l6 5-6 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ showExplanation ? "Masquer l'explication" : 'Pourquoi cette question ?' }}
                </button>
                <Transition name="pm-explanation">
                    <p
                        v-if="showExplanation && quizStore.currentQuestion.explanation"
                        :id="`explanation-${quizStore.currentQuestion.id}`"
                        class="mt-2 rounded-xl bg-bordeaux-50 p-3 text-sm leading-relaxed text-gray-600"
                    >
                        {{ quizStore.currentQuestion.explanation }}
                    </p>
                </Transition>

                <!-- Chaque bouton reste plein-largeur (jamais de grille 2/3
                     colonnes ici) : sur un petit écran, une zone cliquable
                     compressée horizontalement pour tenir 5 choix sur une
                     ligne descendrait sous la cible tactile minimale
                     recommandée (44px) — la pile verticale, quitte à
                     défiler un peu, reste pleinement utilisable au pouce. -->
                <p v-if="previousAnswer?.was_skipped" class="mt-4 text-sm text-gray-500">
                    Tu avais passé cette question.
                </p>

                <div class="mt-6 flex flex-col gap-2">
                    <PmButton
                        v-for="choice in CHOICES"
                        :key="choice.label"
                        :variant="choice.variant"
                        :disabled="quizStore.submitting"
                        :class="{ 'ring-2 ring-ink ring-offset-2': previousAnswer && !previousAnswer.was_skipped && previousAnswer.user_score === choice.score }"
                        @click="answer(choice.score)"
                    >
                        {{ choice.label }}
                    </PmButton>
                </div>

                <!-- Séparé visuellement des 5 niveaux de l'échelle (marge +
                     bouton discret "ghost") : "Passer" n'est pas une 6e
                     position sur le spectre d'accord/désaccord, c'est
                     l'absence de position (was_skipped = true). -->
                <PmButton variant="ghost" size="sm" class="mt-3 w-full" :disabled="quizStore.submitting" @click="skip">
                    Passer
                </PmButton>
                <!-- Incitation, jamais une contrainte : le bouton "Passer"
                     ci-dessus reste pleinement cliquable après ce message,
                     rien ne bloque la progression (cf. quizStore.consecutiveSkips). -->
                <p v-if="skipEncouragementMessage" class="mt-2 text-center text-xs text-gray-500">{{ skipEncouragementMessage }}</p>

                <PmButton
                    v-if="quizStore.currentQuestionIndex > 0"
                    variant="ghost"
                    size="sm"
                    class="mt-2 w-full"
                    :disabled="quizStore.submitting"
                    @click="goToPrevious"
                >
                    Question précédente
                </PmButton>
            </PmCard>

            <PmCard v-else key="done" accent>
                <h2 class="font-display text-lg font-semibold text-ink">Ton profil politique t'attend.</h2>
                <!-- Nombre de réponses interpolé depuis quizStore.questions.length,
                     jamais "30" codé en dur dans le texte. -->
                <p class="mt-2 text-sm text-gray-500">Tes {{ quizStore.questions.length }} réponses sont bien enregistrées. Il ne reste plus qu'à découvrir ce qu'elles disent de toi.</p>
                <PmButton size="lg" class="mt-4 w-full" :loading="completing" @click="handleComplete">
                    Voir mes résultats
                </PmButton>
            </PmCard>
        </Transition>

        <PmModal v-model="showLeaveConfirm" title="Quitter le quiz ?">
            <p class="mb-6 text-sm text-gray-600">
                Tes réponses sont déjà enregistrées. Il faudra retraverser les écrans d'introduction pour revenir
                directement où tu en étais.
            </p>
            <div class="flex gap-3">
                <PmButton variant="ghost" class="flex-1" @click="cancelLeave">Continuer le quiz</PmButton>
                <PmButton variant="danger" class="flex-1" @click="confirmLeave">Quitter</PmButton>
            </div>
        </PmModal>
    </div>
</template>

<style scoped>
/* Glissement latéral léger entre deux questions (cahier des charges §7 :
   animations douces, jamais lourdes) — pas de vraie physique de swipe, cf.
   commentaire sur CHOICES plus haut. */
.pm-question-slide-enter-active,
.pm-question-slide-leave-active {
    transition: all 0.18s ease;
}
.pm-question-slide-enter-from {
    opacity: 0;
    transform: translateX(16px);
}
.pm-question-slide-leave-to {
    opacity: 0;
    transform: translateX(-16px);
}

/* Ouverture/fermeture douce de l'explication : max-height plutôt que height,
   seule propriété transitionnable en CSS pur pour un contenu de hauteur
   variable. 400px couvre largement le texte le plus long des 30
   explications sans jamais rogner le contenu une fois l'animation terminée
   (Vue retire les classes -to/-from une fois la transition finie, la
   hauteur redevient alors naturelle, non contrainte). */
.pm-explanation-enter-active,
.pm-explanation-leave-active {
    overflow: hidden;
    transition: max-height 0.25s ease, opacity 0.2s ease;
}
.pm-explanation-enter-from,
.pm-explanation-leave-to {
    max-height: 0;
    opacity: 0;
}
.pm-explanation-enter-to,
.pm-explanation-leave-from {
    max-height: 400px;
    opacity: 1;
}
</style>
