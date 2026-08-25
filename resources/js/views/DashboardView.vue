<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/authStore';
import { useResultsStore } from '@/stores/resultsStore';
import { useQuizStore } from '@/stores/quizStore';
import { useUiStore } from '@/stores/uiStore';
import PmCard from '@/components/ui/PmCard.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmButton from '@/components/ui/PmButton.vue';
import PmTag from '@/components/ui/PmTag.vue';
import PmModal from '@/components/ui/PmModal.vue';
import AvatarPicker from '@/components/AvatarPicker.vue';
import { avatarUrl } from '@/utils/avatar';

const router = useRouter();
const authStore = useAuthStore();
const { user } = storeToRefs(authStore);
const resultsStore = useResultsStore();
const quizStore = useQuizStore();
const uiStore = useUiStore();

const loading = ref(true);
const startingNew = ref(false);
const consentChecked = ref(false);
// Le quiz "pending" le plus récent, s'il y en a un : celui qu'un nouveau clic
// sur le bouton principal doit reprendre plutôt qu'abandonner au profit d'un
// nouveau QuizResult. loadHistory() trie déjà par created_at décroissant.
const pendingHistoryEntry = computed(() => resultsStore.history.find((entry) => entry.status === 'pending') ?? null);
const startQuizLabel = computed(() => {
    if (pendingHistoryEntry.value) {
        return 'Reprendre le quiz';
    }

    return resultsStore.history.length === 0 ? 'Commencer le quiz' : 'Refaire le quiz';
});
const avatarModalOpen = ref(false);
const newAvatarSeed = ref(null);
const savingAvatar = ref(false);

const deleteModalOpen = ref(false);
const deleteTarget = ref(null);
const deleting = ref(false);
const deleteWarningText = computed(() => (deleteTarget.value?.status === 'completed'
    ? "Ce résultat et son éventuel lien de partage disparaîtront définitivement. Cette action est irréversible."
    : "Les réponses déjà données à ce quiz seront perdues. Cette action est irréversible."));

function openAvatarModal() {
    newAvatarSeed.value = null;
    avatarModalOpen.value = true;
}

async function confirmAvatar() {
    if (!newAvatarSeed.value) {
        return;
    }
    savingAvatar.value = true;
    try {
        await authStore.updateAvatar(newAvatarSeed.value);
        avatarModalOpen.value = false;
        uiStore.showToast('Avatar mis à jour.', 'success');
    } catch {
        uiStore.showToast(authStore.error ?? "Impossible de changer l'avatar.", 'error');
    } finally {
        savingAvatar.value = false;
    }
}

onMounted(async () => {
    try {
        await resultsStore.loadHistory();
    } catch {
        uiStore.showToast(resultsStore.error ?? "Impossible de charger l'historique.", 'error');
    } finally {
        loading.value = false;
    }
});

const statusLabels = {
    pending: 'En cours',
    computing: 'Calcul en cours',
    completed: null, // pas de badge nécessaire : le lien "Voir le résultat" suffit.
    failed: 'Échec du calcul',
};

// pending/computing restent neutres ("patiente encore", rien à faire) ;
// failed distingué en rouge sobre (même variant que le comparateur pour un
// désaccord, déjà dans la palette) puisque c'est le seul statut réellement
// actionnable par l'utilisateur (relancer le calcul depuis ResultsView).
const statusVariants = {
    pending: 'neutral',
    computing: 'neutral',
    failed: 'disagree',
};

async function startNewQuiz() {
    startingNew.value = true;
    try {
        if (pendingHistoryEntry.value) {
            quizStore.setQuizUuid(pendingHistoryEntry.value.uuid);
            router.push('/quiz');

            return;
        }

        await quizStore.startQuiz();
        router.push('/intro');
    } catch {
        uiStore.showToast(quizStore.error ?? 'Impossible de démarrer un nouveau quiz.', 'error');
    } finally {
        startingNew.value = false;
    }
}

/**
 * router.push seul ne suffit pas pour un résultat qui n'est pas encore
 * "completed" : ResultsView (isOwnActiveQuiz) ne propose les actions de
 * reprise/relance que si quizStore.currentQuizUuid correspond au résultat
 * consulté. Or currentQuizUuid ne pointe en général que vers le dernier
 * quiz démarré sur cet appareil, pas nécessairement celui-ci si l'entrée
 * vient d'une session précédente ou d'un autre appareil : il faut donc
 * l'aligner explicitement ici.
 */
function openHistoryEntry(entry) {
    if (entry.status !== 'completed') {
        quizStore.setQuizUuid(entry.uuid);
    }
    router.push(`/results/${entry.uuid}`);
}

function requestDelete(entry) {
    deleteTarget.value = entry;
    deleteModalOpen.value = true;
}

async function confirmDelete() {
    if (!deleteTarget.value) {
        return;
    }

    const uuid = deleteTarget.value.uuid;
    deleting.value = true;
    try {
        await resultsStore.deleteHistoryEntry(uuid);
        // Un quiz "pending" supprimé depuis ici ne doit plus être considéré
        // comme actif : sans ça, le bouton principal ou une navigation vers
        // /quiz tenterait de reprendre un QuizResult qui n'existe plus.
        if (quizStore.currentQuizUuid === uuid) {
            quizStore.discardCurrentQuiz();
        }
        deleteModalOpen.value = false;
        uiStore.showToast('Résultat supprimé.', 'success');
    } catch {
        uiStore.showToast(resultsStore.error ?? 'Impossible de supprimer ce résultat.', 'error');
    } finally {
        deleting.value = false;
    }
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-BE', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <PmCard>
            <div class="flex items-center gap-3">
                <img :src="avatarUrl(user?.avatar_seed)" :alt="`Avatar de ${user?.username}`" class="h-14 w-14 rounded-full bg-gray-50">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Bonjour {{ user?.username }}</h1>
                    <p class="text-sm text-gray-500">{{ user?.email }}</p>
                </div>
                <button
                    type="button"
                    class="ml-auto shrink-0 self-start text-sm font-medium whitespace-nowrap text-bordeaux-600 hover:text-bordeaux-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    @click="openAvatarModal"
                >
                    Changer mon avatar
                </button>
            </div>
            <div v-if="!pendingHistoryEntry" class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-3">
                <label class="flex cursor-pointer items-start gap-2 text-xs text-gray-600">
                    <input
                        v-model="consentChecked"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 accent-bordeaux-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    >
                    <span>
                        J'accepte que mes réponses à ce quiz (des opinions politiques, une catégorie de données
                        protégée par le RGPD) soient enregistrées. Je peux les supprimer à tout moment ci-dessous.
                    </span>
                </label>
            </div>
            <PmButton
                class="mt-4 w-full"
                :loading="startingNew"
                :disabled="!pendingHistoryEntry && !consentChecked"
                @click="startNewQuiz"
            >
                {{ startQuizLabel }}
            </PmButton>
        </PmCard>

        <PmModal v-model="avatarModalOpen" title="Changer mon avatar">
            <AvatarPicker v-model="newAvatarSeed" />
            <PmButton class="mt-4 w-full" :loading="savingAvatar" :disabled="!newAvatarSeed" @click="confirmAvatar">
                Confirmer
            </PmButton>
        </PmModal>

        <PmCard>
            <template #title>Ton historique</template>

            <div v-if="loading" class="flex justify-center py-8">
                <PmLoader label="Chargement de l'historique" />
            </div>

            <p v-else-if="resultsStore.history.length === 0" class="text-sm text-gray-500">
                Tu n'as pas encore fait de quiz. Lance-toi, ça prend moins de 5 minutes !
            </p>

            <ul v-else class="flex flex-col gap-3">
                <li
                    v-for="entry in resultsStore.history"
                    :key="entry.uuid"
                    class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-3"
                >
                    <div>
                        <button
                            type="button"
                            class="text-left font-medium text-gray-900 hover:underline"
                            @click="openHistoryEntry(entry)"
                        >
                            {{ entry.status === 'completed' ? (entry.profile_label ?? 'Voir le résultat') : `Quiz du ${formatDate(entry.completed_at ?? entry.created_at)}` }}
                        </button>
                        <p class="text-xs text-gray-400">{{ formatDate(entry.completed_at ?? entry.created_at) }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <PmTag v-if="statusLabels[entry.status]" :variant="statusVariants[entry.status] ?? 'neutral'">{{ statusLabels[entry.status] }}</PmTag>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-danger-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                            aria-label="Supprimer ce résultat"
                            @click="requestDelete(entry)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M4 6h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M5.5 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h4.8a1.5 1.5 0 0 0 1.5-1.4l.6-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ul>
        </PmCard>

        <PmModal v-model="deleteModalOpen" title="Supprimer ce résultat ?">
            <p class="mb-6 text-sm text-gray-600">{{ deleteWarningText }}</p>
            <div class="flex gap-3">
                <PmButton variant="ghost" class="flex-1" :disabled="deleting" @click="deleteModalOpen = false">Annuler</PmButton>
                <PmButton variant="danger" class="flex-1" :loading="deleting" @click="confirmDelete">Supprimer</PmButton>
            </div>
        </PmModal>
    </div>
</template>
