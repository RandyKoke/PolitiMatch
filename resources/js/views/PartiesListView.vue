<script setup>
import { onMounted, ref } from 'vue';
import { apiParties } from '@/services/apiParties';
import PmCard from '@/components/ui/PmCard.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmButton from '@/components/ui/PmButton.vue';

const loading = ref(true);
const error = ref(null);
const parties = ref([]);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await apiParties.index();
        parties.value = data.parties;
    } catch {
        error.value = 'Impossible de charger la liste des partis.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <PmCard>
            <h1 class="font-display text-xl font-semibold text-ink">Les partis</h1>
            <p class="mt-1 text-sm text-gray-500">
                Consulte la fiche de chaque parti actif : idéologie, slogan de campagne et positions clés.
            </p>
        </PmCard>

        <div v-if="loading" class="flex justify-center py-16">
            <PmLoader label="Chargement des partis" />
        </div>

        <PmCard v-else-if="error">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <PmButton class="mt-4" variant="secondary" @click="load">Réessayer</PmButton>
        </PmCard>

        <!-- Jamais de paramètre ?quiz= ici : cet annuaire est accessible sans
             aucun contexte de résultat (cf. PartyDetailView, qui gère déjà
             nativement son absence). -->
        <ul v-else class="flex flex-col gap-3">
            <li v-for="party in parties" :key="party.id">
                <router-link
                    :to="`/parties/${party.id}`"
                    class="flex items-center gap-3 rounded-xl border border-stone-200 bg-white p-3 transition-colors hover:border-bordeaux-200 hover:bg-bordeaux-50/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                >
                    <img v-if="party.logo_url" :src="party.logo_url" :alt="`Logo ${party.name}`" class="h-11 w-11 shrink-0 rounded-full object-contain">
                    <span
                        v-else
                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full px-1 font-semibold text-white"
                        :class="party.abbreviation.length > 5 ? 'text-[9px]' : 'text-xs'"
                        :style="{ backgroundColor: party.color_hex ?? '#9ca3af' }"
                        aria-hidden="true"
                    >
                        {{ party.abbreviation }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900">{{ party.name }}</p>
                        <p class="truncate text-sm text-gray-500">
                            {{ party.slogan ?? party.description }}
                        </p>
                    </div>
                </router-link>
            </li>
        </ul>
    </div>
</template>
