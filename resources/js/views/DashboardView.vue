<script setup>
import { onMounted, ref } from 'vue';
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
const avatarModalOpen = ref(false);
const newAvatarSeed = ref(null);
const savingAvatar = ref(false);

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
        await quizStore.startQuiz();
        router.push('/intro');
    } catch {
        uiStore.showToast(quizStore.error ?? 'Impossible de démarrer un nouveau quiz.', 'error');
    } finally {
        startingNew.value = false;
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
            <PmButton class="mt-4 w-full" :loading="startingNew" @click="startNewQuiz">Refaire le quiz</PmButton>
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
                        <router-link
                            v-if="entry.status === 'completed'"
                            :to="`/results/${entry.uuid}`"
                            class="font-medium text-gray-900 hover:underline"
                        >
                            {{ entry.profile_label ?? 'Voir le résultat' }}
                        </router-link>
                        <span v-else class="font-medium text-gray-500">Quiz du {{ formatDate(entry.completed_at ?? entry.created_at) }}</span>
                        <p class="text-xs text-gray-400">{{ formatDate(entry.completed_at ?? entry.created_at) }}</p>
                    </div>
                    <PmTag v-if="statusLabels[entry.status]" :variant="statusVariants[entry.status] ?? 'neutral'">{{ statusLabels[entry.status] }}</PmTag>
                </li>
            </ul>
        </PmCard>
    </div>
</template>
