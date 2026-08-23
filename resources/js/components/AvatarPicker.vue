<script setup>
import { onMounted, ref } from 'vue';
import { apiAvatars } from '@/services/apiAvatars';
import PmLoader from '@/components/ui/PmLoader.vue';

const props = defineProps({
    modelValue: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

const avatars = ref([]);
const loading = ref(true);
const error = ref(null);

/**
 * Sujet sensible traité par le choix, jamais la déduction : on ne demande
 * jamais à l'utilisateur un trait personnel (teint de peau, origine...), on
 * lui propose une grille neutre de possibilités générées aléatoirement et
 * c'est lui seul qui choisit. Repli de sécurité si l'utilisateur ne clique
 * sur rien : présélection du premier avatar de la grille dès qu'elle charge,
 * avec exactement le même traitement visuel qu'un choix manuel (pas mis en
 * avant avant coup) — l'inscription n'est jamais bloquée pour cette raison,
 * cf. AuthController::register qui retombe de toute façon sur un seed
 * aléatoire côté serveur si jamais rien n'est transmis.
 */
async function fetchSuggestions() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await apiAvatars.suggestions();
        avatars.value = data.avatars;
        if (!props.modelValue && avatars.value.length > 0) {
            emit('update:modelValue', avatars.value[0].seed);
        }
    } catch {
        error.value = "Impossible de charger des propositions d'avatar.";
    } finally {
        loading.value = false;
    }
}

function select(seed) {
    emit('update:modelValue', seed);
}

onMounted(fetchSuggestions);
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-6">
            <PmLoader size="sm" label="Chargement des propositions d'avatar" />
        </div>

        <div v-else-if="error" class="flex flex-col items-center gap-2 py-4 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button
                type="button"
                class="text-sm font-medium text-bordeaux-600 hover:text-bordeaux-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                @click="fetchSuggestions"
            >
                Réessayer
            </button>
        </div>

        <div v-else>
            <!-- role="group" + aria-pressed (pattern "toggle button"), pas
                 role="radiogroup" : la navigation clavier demandée (tabulation
                 + Entrée/Espace) est déjà celle d'un <button> natif, pas
                 besoin d'un roving-tabindex à gérer à la main. -->
            <div role="group" aria-label="Choisis ton avatar" class="grid grid-cols-3 justify-items-center gap-3">
                <button
                    v-for="(avatar, index) in avatars"
                    :key="avatar.seed"
                    type="button"
                    :aria-pressed="modelValue === avatar.seed"
                    :aria-label="`Avatar ${index + 1} sur ${avatars.length}`"
                    class="relative flex items-center justify-center rounded-full p-1 transition-shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    :class="modelValue === avatar.seed ? 'ring-2 ring-bordeaux-600' : 'ring-1 ring-gray-200 hover:ring-gray-300'"
                    @click="select(avatar.seed)"
                >
                    <img :src="avatar.url" alt="" class="h-14 w-14 rounded-full bg-gray-50">
                    <span
                        v-if="modelValue === avatar.seed"
                        class="absolute -right-1 -bottom-1 flex h-5 w-5 items-center justify-center rounded-full bg-bordeaux-600 text-white"
                        aria-hidden="true"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M4 10l4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </button>
            </div>

            <button
                type="button"
                class="mt-3 text-sm font-medium text-bordeaux-600 hover:text-bordeaux-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                @click="fetchSuggestions"
            >
                Voir d'autres propositions
            </button>
        </div>
    </div>
</template>
