<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useQuizStore } from '@/stores/quizStore';
import { useUiStore } from '@/stores/uiStore';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';
import PmModal from '@/components/ui/PmModal.vue';

const router = useRouter();
const quizStore = useQuizStore();
const uiStore = useUiStore();

// null tant qu'on vérifie s'il existe un quiz en cours ; ensuite 'none',
// 'resumable' (pending/computing/failed) ou 'completed'. Évite de proposer
// silencieusement un nouveau quiz par-dessus un précédent, ce qui créerait
// des QuizResult orphelins à chaque retour sur cet écran.
const existingQuizState = ref(null);
const checkingExisting = ref(true);
const startingGuest = ref(false);
const showRestartConfirm = ref(false);
const consentChecked = ref(false);

onMounted(async () => {
    if (!quizStore.currentQuizUuid) {
        existingQuizState.value = 'none';
        checkingExisting.value = false;

        return;
    }

    try {
        await quizStore.resumeState();
        existingQuizState.value = quizStore.status === 'completed' ? 'completed' : 'resumable';
    } catch {
        // UUID/session_token périmé ou invalide (403/404) : rien à proposer,
        // on nettoie silencieusement plutôt que d'afficher une erreur pour
        // un cas qui n'en est pas vraiment un du point de vue utilisateur.
        quizStore.discardCurrentQuiz();
        existingQuizState.value = 'none';
    } finally {
        checkingExisting.value = false;
    }
});

async function startGuestQuiz() {
    startingGuest.value = true;
    try {
        await quizStore.startQuiz();
        router.push('/intro');
    } catch {
        uiStore.showToast(quizStore.error ?? "Impossible de démarrer le quiz.", 'error');
    } finally {
        startingGuest.value = false;
    }
}

function confirmRestart() {
    showRestartConfirm.value = false;
    quizStore.discardCurrentQuiz();
    existingQuizState.value = 'none';
}
</script>

<template>
    <!--
        Un seul root ici, volontairement (div englobant) : <PmModal> vit à
        côté de la chaîne v-if/v-else-if/v-else, et un Teleport laisse un
        nœud "ancre" même quand il est fermé. Sans ce wrapper, ce composant
        a deux racines simultanées (la carte active + l'ancre du Teleport),
        ce qu'AppLayout — qui enveloppe chaque vue dans <Transition> — ne
        peut pas animer : Vue affiche alors un avertissement
        "renders non-element root node" et, en pratique, n'affiche RIEN du
        tout à cet endroit plutôt que juste perdre l'animation, un écran
        /start vide plutôt qu'un simple défaut d'animation.
    -->
    <div>
        <PmCard v-if="checkingExisting">
            <p class="text-center text-sm text-gray-500">Vérification en cours…</p>
        </PmCard>

        <PmCard v-else-if="existingQuizState === 'completed'">
            <h1 class="mb-1 font-display text-xl font-semibold text-ink">Tu as déjà un résultat</h1>
            <p class="mb-6 text-sm text-gray-500">Ton dernier quiz est terminé : tu peux le consulter ou en refaire un nouveau.</p>

            <div class="flex flex-col gap-3">
                <PmButton size="lg" @click="router.push(`/results/${quizStore.currentQuizUuid}`)">Voir mon résultat</PmButton>
                <PmButton variant="secondary" size="lg" @click="showRestartConfirm = true">Refaire le quiz</PmButton>
            </div>
        </PmCard>

        <PmCard v-else-if="existingQuizState === 'resumable'">
            <h1 class="mb-1 font-display text-xl font-semibold text-ink">Tu as un quiz en cours</h1>
            <p class="mb-6 text-sm text-gray-500">
                {{ quizStore.answeredCount }} question{{ quizStore.answeredCount > 1 ? 's' : '' }} déjà répondue{{ quizStore.answeredCount > 1 ? 's' : '' }}.
                Tu peux continuer où tu en étais, ou repartir de zéro.
            </p>

            <div class="flex flex-col gap-3">
                <PmButton size="lg" @click="router.push('/quiz')">Reprendre mon quiz</PmButton>
                <PmButton variant="ghost" size="sm" @click="showRestartConfirm = true">Recommencer un nouveau quiz</PmButton>
            </div>
        </PmCard>

        <PmCard v-else>
            <h1 class="mb-1 font-display text-xl font-semibold text-ink">Prêt à commencer ?</h1>
            <p class="mb-6 text-sm text-gray-500">
                Le quiz est accessible sans compte. Tu pourras créer un compte ensuite, à tout moment, pour sauvegarder
                ton historique. Rien n'est perdu si tu commences en invité.
            </p>

            <div class="mb-6 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <h2 class="mb-1 text-sm font-semibold text-ink">Une précision nécessaire avant de commencer</h2>
                <p class="mb-3 text-sm text-gray-600">
                    Ce quiz te demande ton avis sur des sujets politiques. La loi (le RGPD) classe ce type d'information
                    dans une catégorie à part, au même titre que la santé ou les convictions religieuses, parce qu'elle
                    peut te désavantager si elle tombe entre de mauvaises mains. Concrètement, ça veut dire qu'on n'a pas
                    le droit d'enregistrer tes réponses sans ton accord explicite : ce n'est pas une case à cocher pour
                    la forme, c'est une condition légale pour que le quiz puisse fonctionner.
                </p>
                <p class="mb-3 text-sm text-gray-600">
                    En contrepartie, tu gardes le contrôle : tu peux supprimer ton résultat, et donc tes réponses, à
                    tout moment depuis ton tableau de bord.
                </p>
                <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-700">
                    <input
                        v-model="consentChecked"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 accent-bordeaux-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    >
                    <span>
                        Je confirme avoir au moins 13 ans (âge minimum fixé par la loi belge pour ce type de consentement)
                        et j'accepte que mes réponses à ce quiz soient enregistrées.
                    </span>
                </label>
            </div>

            <div class="flex flex-col gap-3">
                <PmButton size="lg" :loading="startingGuest" :disabled="!consentChecked" @click="startGuestQuiz">
                    Continuer en invité
                </PmButton>
                <PmButton variant="secondary" size="lg" :disabled="startingGuest" @click="router.push('/auth/register')">
                    Créer un compte
                </PmButton>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500">
                Déjà un compte ?
                <router-link to="/auth/login" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">Se connecter</router-link>
            </p>
        </PmCard>

        <PmModal v-model="showRestartConfirm" title="Recommencer le quiz ?">
            <p class="mb-6 text-sm text-gray-600">
                Tes réponses actuelles ne seront plus accessibles depuis cet écran. Cette action est irréversible.
            </p>
            <div class="flex gap-3">
                <PmButton variant="ghost" class="flex-1" @click="showRestartConfirm = false">Annuler</PmButton>
                <PmButton variant="danger" class="flex-1" @click="confirmRestart">Recommencer</PmButton>
            </div>
        </PmModal>
    </div>
</template>
