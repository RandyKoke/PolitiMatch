<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useResultsStore } from '@/stores/resultsStore';
import { useAuthStore } from '@/stores/authStore';
import { useQuizStore } from '@/stores/quizStore';
import { useUiStore } from '@/stores/uiStore';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmTag from '@/components/ui/PmTag.vue';
import PoliticalAxisChart from '@/components/results/PoliticalAxisChart.vue';
import QuizReliabilityNotice from '@/components/results/QuizReliabilityNotice.vue';
import ResultBackground from '@/components/results/ResultBackground.vue';

const route = useRoute();
const router = useRouter();
const resultsStore = useResultsStore();
const authStore = useAuthStore();
const quizStore = useQuizStore();
const uiStore = useUiStore();

const loading = ref(true);
const sharing = ref(false);
const retrying = ref(false);
const resumingSkipped = ref(false);

// Un résultat Completed dont la fiabilité est bloquante (aucune réponse
// réelle, ou trop peu) n'a ni classement de partis, ni graphique de
// positionnement, ni partage possible : un écran dédié remplace entièrement
// l'affichage normal plutôt que de masquer des morceaux un par un.
const isBlocked = computed(() => resultsStore.reliability?.state === 'empty' || resultsStore.reliability?.state === 'too_few');

// Ce résultat est-il le quiz courant du visiteur/utilisateur (celui suivi par
// quizStore) ? Sert à savoir si "reprendre"/"relancer le calcul" a un sens —
// consulter le résultat d'un vieux lien qui n'est plus le quiz actif ne doit
// jamais proposer une action qui échouerait de toute façon (403).
const isOwnActiveQuiz = computed(() => quizStore.currentQuizUuid === route.params.uuid);

const allScoresNull = computed(() => resultsStore.compatibilityScores.length > 0
    && resultsStore.compatibilityScores.every((s) => s.compatibility_score === null));

// Réutilise les partis déjà chargés avec le résultat (party_scores[].party,
// relation Eloquent sérialisée en entier — inclut donc déjà ideological_x/y
// depuis leur ajout) plutôt qu'un second appel à GET /api/parties : ce
// sont exactement les 6 mêmes partis, une requête réseau de moins.
const chartParties = computed(() => resultsStore.compatibilityScores
    .map((entry) => entry.party)
    .filter(Boolean));

async function load() {
    loading.value = true;
    try {
        await resultsStore.loadResults(route.params.uuid);
    } catch {
        // notReadyStatus / error déjà posés par le store ; rien de plus à faire ici.
    } finally {
        loading.value = false;
    }
}

onMounted(load);

async function handleShare() {
    sharing.value = true;
    try {
        const token = await resultsStore.createShareLink(route.params.uuid);
        router.push(`/share/${token}`);
    } catch {
        uiStore.showToast(resultsStore.error ?? 'Impossible de générer le lien de partage.', 'error');
    } finally {
        sharing.value = false;
    }
}

async function handleRetry() {
    retrying.value = true;
    try {
        const data = await quizStore.retryQuiz();
        await resultsStore.loadResults(data.quiz_uuid);
    } catch {
        uiStore.showToast(quizStore.error ?? 'Le calcul a de nouveau échoué.', 'error');
    } finally {
        retrying.value = false;
    }
}

// État "0 réponse" : aucune donnée à conserver, on relance un quiz
// entièrement neuf, même geste que "Recommencer un nouveau quiz" sur
// StartAccessView (l'ancien QuizResult vide reste simplement orphelin en
// base).
function handleRestart() {
    quizStore.discardCurrentQuiz();
    router.push('/start');
}

// État "0 < réponses < 15" : réouvre ce QuizResult précis côté backend puis
// navigue vers /quiz, qui affichera alors UNIQUEMENT les questions
// précédemment passées (cf. quizStore.resumeSkippedOnly).
async function handleResumeSkipped() {
    resumingSkipped.value = true;
    try {
        await quizStore.resumeSkippedOnly(route.params.uuid);
        router.push('/quiz');
    } catch {
        uiStore.showToast(quizStore.error ?? 'Impossible de reprendre ce quiz.', 'error');
    } finally {
        resumingSkipped.value = false;
    }
}

function scorePercent(score) {
    return Math.round(Number(score));
}
</script>

<template>
    <div v-if="loading" class="flex justify-center py-16">
        <PmLoader label="Chargement de ton résultat" />
    </div>

    <PmCard v-else-if="resultsStore.notReadyStatus === 'pending'">
        <h1 class="text-lg font-semibold text-gray-900">Ce quiz n'est pas encore terminé</h1>
        <p class="mt-2 text-sm text-gray-500">Réponds à toutes les questions pour découvrir ton profil politique.</p>
        <PmButton v-if="isOwnActiveQuiz" class="mt-4" @click="router.push('/quiz')">Reprendre le quiz</PmButton>
    </PmCard>

    <PmCard v-else-if="resultsStore.notReadyStatus === 'computing'">
        <h1 class="text-lg font-semibold text-gray-900">Calcul en cours</h1>
        <p class="mt-2 text-sm text-gray-500">Ton résultat est en cours de calcul. Réessaie dans quelques instants.</p>
        <PmButton class="mt-4" @click="load">Rafraîchir</PmButton>
    </PmCard>

    <PmCard v-else-if="resultsStore.notReadyStatus === 'failed'">
        <h1 class="text-lg font-semibold text-gray-900">Un problème est survenu</h1>
        <p class="mt-2 text-sm text-gray-500">Le calcul de ce résultat a échoué.</p>
        <PmButton v-if="isOwnActiveQuiz" class="mt-4" :loading="retrying" @click="handleRetry">Relancer le calcul</PmButton>
        <PmButton v-else class="mt-4" variant="secondary" @click="router.push('/')">Retour à l'accueil</PmButton>
    </PmCard>

    <PmCard v-else-if="resultsStore.error">
        <h1 class="text-lg font-semibold text-gray-900">Résultat introuvable</h1>
        <p class="mt-2 text-sm text-gray-500">{{ resultsStore.error }}</p>
        <PmButton class="mt-4" variant="secondary" @click="load">Réessayer</PmButton>
    </PmCard>

    <!-- Résultat Completed mais fiabilité bloquante (empty/too_few) : ni
         classement, ni graphique, ni partage, ni proposition de sauvegarde
         de compte, un écran entièrement dédié plutôt que des morceaux
         masqués un par un dans l'écran normal ci-dessous. -->
    <PmCard v-else-if="isBlocked" accent>
        <QuizReliabilityNotice :state="resultsStore.reliability.state" />
        <PmButton
            v-if="isOwnActiveQuiz && resultsStore.reliability.state === 'empty'"
            size="lg"
            class="mt-4 w-full"
            @click="handleRestart"
        >
            Refaire le test
        </PmButton>
        <PmButton
            v-if="isOwnActiveQuiz && resultsStore.reliability.state === 'too_few'"
            size="lg"
            class="mt-4 w-full"
            :loading="resumingSkipped"
            @click="handleResumeSkipped"
        >
            Reprendre le quiz
        </PmButton>
    </PmCard>

    <div v-else class="relative z-[1] flex flex-col gap-4">
        <!-- Illustration de fond plein écran, cf. ResultBackground.vue pour
             le détail et la justification du positionnement (fixed,
             z-index 0, échappe au conteneur max-w-lg). Ce wrapper passe
             explicitement à z-[1] : chaque PmCard ci-dessous reste bg-white
             opaque, la lisibilité du texte ne dépend donc jamais du réglage
             d'opacité de l'illustration elle-même. -->
        <ResultBackground />

        <!-- Carte "climax" de l'écran : liseré tricolore + coin Art Nouveau
             (PmCard accent). Mise en scène progressive : cette carte
             apparaît en premier (reveal-1), puis le graphique (reveal-2),
             puis le classement (reveal-3). -->
        <PmCard accent class="reveal reveal-1">
            <div class="relative">
                <PmTag variant="brand">Ton profil politique</PmTag>
                <h1 class="mt-2 font-display text-xl font-semibold text-ink">{{ resultsStore.profileLabel }}</h1>
                <!-- leading-relaxed : la description combine le texte
                     générique du profil et plusieurs phrases générées à
                     partir des vrais scores par thème, un texte de 3-5
                     phrases a besoin de plus d'interligne qu'une seule
                     phrase courte pour rester confortable à lire. -->
                <p class="mt-3 text-sm leading-relaxed text-gray-600">{{ resultsStore.profileDescription }}</p>
                <!-- État non bloquant "partial" (>= 15 réponses réelles, au
                     moins une question passée) : mention purement
                     informative, jamais bloquante, absente dès que les 30
                     questions ont été répondues normalement. -->
                <p v-if="resultsStore.reliability?.state === 'partial'" class="mt-3 text-xs font-medium text-gray-500">
                    Ton profil est basé sur {{ resultsStore.reliability.real_answers_count }} réponses sur
                    {{ resultsStore.reliability.total_questions }}. Réponds à plus de questions pour un résultat
                    encore plus précis.
                </p>
            </div>
        </PmCard>

        <PmCard class="reveal reveal-2">
            <template #title>Ta position politique</template>
            <PoliticalAxisChart :axis-x="resultsStore.axes.x" :axis-y="resultsStore.axes.y" :parties="chartParties" />
        </PmCard>

        <PmCard class="reveal reveal-3">
            <template #title>Classement des partis</template>

            <p v-if="allScoresNull" class="text-sm text-gray-500">
                Pas assez de réponses pour calculer une compatibilité avec les partis - réponds à davantage de
                questions pour obtenir un classement.
            </p>

            <ul v-else class="flex flex-col gap-3">
                <li
                    v-for="entry in resultsStore.compatibilityScores"
                    :key="entry.party_id"
                    class="flex items-center gap-3 rounded-xl border border-stone-200 p-3"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                        :style="{ backgroundColor: entry.party?.color_hex ?? '#9ca3af' }"
                        aria-hidden="true"
                    >
                        {{ (entry.party?.abbreviation ?? '?').slice(0, 3) }}
                    </span>
                    <router-link :to="`/parties/${entry.party_id}?quiz=${route.params.uuid}`" class="flex-1 font-medium text-gray-900 hover:underline">
                        {{ entry.party?.name ?? 'Parti' }}
                    </router-link>
                    <span v-if="entry.compatibility_score === null" class="text-xs text-gray-400">Données insuffisantes</span>
                    <span v-else class="font-semibold text-bordeaux-700">{{ scorePercent(entry.compatibility_score) }}%</span>
                </li>
            </ul>
        </PmCard>

        <div class="flex flex-col gap-3">
            <PmButton size="lg" @click="router.push(`/compare/${route.params.uuid}`)">Voir le comparateur</PmButton>
            <PmButton variant="secondary" size="lg" :loading="sharing" @click="handleShare">Partager mon résultat</PmButton>
            <!-- Simple lien <a>, pas un appel JS + blob : le téléchargement
                 est entièrement géré par le navigateur via le
                 Content-Disposition: attachment renvoyé par l'API
                 (ResultController::downloadImage), aucune dépendance
                 frontend supplémentaire nécessaire. -->
            <a
                :href="`/api/results/${route.params.uuid}/download-image`"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-50 px-6 py-3 text-lg font-medium text-ink transition-[color,background-color,box-shadow,transform] duration-200 ease-out hover:bg-brand-100 active:bg-brand-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            >
                Télécharger mon résultat
            </a>

            <!-- Carte de migration invité → compte : elle aussi "climax" à sa
                 façon (dernière étape avant de perdre l'accès facile au
                 résultat) — même traitement accentué, CTA en or plein plutôt
                 que la variante secondaire pâle utilisée jusqu'ici. -->
            <PmCard v-if="!authStore.isAuthenticated" accent>
                <template #title>Ta position mérite d'être gardée</template>
                <p class="text-sm text-gray-600">
                    Crée un compte et reviens quand tu veux : tout est déjà là.
                </p>
                <PmButton class="mt-3 w-full" @click="router.push('/auth/register')">
                    Créer un compte
                </PmButton>
            </PmCard>
        </div>
    </div>
</template>

<style scoped>
/* Mise en scène progressive de l'écran de résultat : la carte de profil
   apparaît en premier, puis le graphique, puis le classement — un effet de
   révélation discret (fondu + légère translation), pas une animation
   spectaculaire (cahier des charges §7 : "sobre avant tout"). Respecte
   prefers-reduced-motion : sans cette préférence de mouvement réduit, les
   trois cartes sont simplement visibles d'emblée, sans délai ni décalage. */
@media (prefers-reduced-motion: no-preference) {
    .reveal {
        animation: pm-reveal 0.5s ease-out both;
    }
    .reveal-1 { animation-delay: 0ms; }
    .reveal-2 { animation-delay: 120ms; }
    .reveal-3 { animation-delay: 240ms; }
}

@keyframes pm-reveal {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
