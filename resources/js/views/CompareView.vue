<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useResultsStore } from '@/stores/resultsStore';
import { useUiStore } from '@/stores/uiStore';
import PmCard from '@/components/ui/PmCard.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmButton from '@/components/ui/PmButton.vue';
import QuizReliabilityNotice from '@/components/results/QuizReliabilityNotice.vue';

const route = useRoute();
const resultsStore = useResultsStore();
const uiStore = useUiStore();

const loading = ref(true);
// 'all' | nom de thème : filtrage entièrement côté client une fois les
// données chargées une seule fois (pas de round-trip serveur par filtre) —
// GET /api/compare accepte bien theme_id côté backend, mais sa réponse
// n'expose que theme_name (pas l'id), donc le frontend ne peut de toute
// façon pas reconstruire un theme_id pour ce filtre serveur sans un appel
// supplémentaire. Filtrer en mémoire est à la fois plus simple et plus
// réactif (changement de filtre instantané, sans état de chargement).
const selectedTheme = ref('all');
const selectedAxis = ref('all');

async function load() {
    loading.value = true;
    try {
        await resultsStore.loadCompareData(route.params.uuid);
    } catch {
        uiStore.showToast(resultsStore.error ?? 'Impossible de charger le comparateur.', 'error');
    } finally {
        loading.value = false;
    }
}

onMounted(load);

const themes = computed(() => {
    if (!resultsStore.compareData) {
        return [];
    }

    return [...new Set(resultsStore.compareData.questions.map((q) => q.theme_name))];
});

const filteredQuestions = computed(() => {
    if (!resultsStore.compareData) {
        return [];
    }

    return resultsStore.compareData.questions.filter((q) => {
        const themeOk = selectedTheme.value === 'all' || q.theme_name === selectedTheme.value;
        const axisOk = selectedAxis.value === 'all' || q.axe_ideologique === selectedAxis.value;

        return themeOk && axisOk;
    });
});

function cellClass(score) {
    if (score === null || score === undefined) {
        return 'bg-gray-50 text-gray-300';
    }
    if (score > 0) {
        return 'bg-vote-agree-bg text-vote-agree';
    }
    if (score < 0) {
        return 'bg-vote-disagree-bg text-vote-disagree';
    }

    return 'bg-vote-neutral-bg text-vote-neutral';
}

function cellLabel(score) {
    if (score === null || score === undefined) {
        return '–';
    }

    return score > 0 ? '✓' : score < 0 ? '✗' : '•';
}
</script>

<template>
    <div v-if="loading" class="flex justify-center py-16">
        <PmLoader label="Chargement du comparateur" />
    </div>

    <PmCard v-else-if="!resultsStore.compareData">
        <h1 class="text-lg font-semibold text-gray-900">Comparateur indisponible</h1>
        <p class="mt-2 text-sm text-gray-500">{{ resultsStore.error ?? 'Une erreur est survenue.' }}</p>
        <PmButton class="mt-4" @click="load">Réessayer</PmButton>
    </PmCard>

    <!-- Comparateur inaccessible pour un QuizResult Completed dont la
         fiabilité est bloquante (même message explicatif que la page de
         résultat), jamais pour un quiz encore en cours (Pending), qui reste
         volontairement accessible (cf. CompareController::index). -->
    <PmCard v-else-if="resultsStore.compareData.blocked">
        <QuizReliabilityNotice :state="resultsStore.compareData.reliability.state" />
    </PmCard>

    <div v-else class="flex flex-col gap-4">
        <PmCard>
            <h1 class="text-lg font-semibold text-gray-900">Comparateur</h1>
            <p class="mt-1 text-sm text-gray-500">Tes réponses face aux positions des partis, question par question.</p>

            <p v-if="!resultsStore.compareData.has_answers" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-500">
                Tu n'as pas encore répondu à ce quiz, mais les positions des partis restent consultables ci-dessous.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <select v-model="selectedTheme" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                    <option value="all">Toutes les thématiques</option>
                    <option v-for="theme in themes" :key="theme" :value="theme">{{ theme }}</option>
                </select>
                <select v-model="selectedAxis" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                    <option value="all">Tous les axes</option>
                    <option value="economique">Axe économique</option>
                    <option value="societal">Axe sociétal</option>
                </select>
            </div>
        </PmCard>

        <PmCard :padded="false">
            <!-- overflow-x-auto : sur mobile, un tableau à 7 colonnes (question +
                 utilisateur + 6 partis) ne rentre jamais dans un écran étroit sans
                 soit le vider de son sens (vue "simplifiée" perdrait la comparaison
                 côte à côte, qui est justement l'intérêt du comparateur), soit le
                 rendre illisible en le compressant. Le défilement horizontal garde
                 le tableau intact et lisible, au prix d'un geste de swipe — choix
                 documenté plutôt que deviné. -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="sticky left-0 bg-white p-3 text-left font-medium text-gray-500">Question</th>
                            <th class="p-3 text-center font-medium text-gray-500">Toi</th>
                            <th v-for="party in resultsStore.compareData.parties" :key="party.id" class="p-3 text-center font-medium text-gray-500">
                                <router-link :to="`/parties/${party.id}?quiz=${route.params.uuid}`" class="hover:underline">
                                    {{ party.name }}
                                </router-link>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="question in filteredQuestions" :key="question.id" class="border-b border-gray-50">
                            <td class="sticky left-0 max-w-[220px] bg-white p-3 text-gray-700">{{ question.label }}</td>
                            <td class="p-2 text-center">
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full font-semibold"
                                    :class="cellClass(resultsStore.compareData.user.scores[question.id])"
                                >
                                    {{ cellLabel(resultsStore.compareData.user.scores[question.id]) }}
                                </span>
                            </td>
                            <td v-for="party in resultsStore.compareData.parties" :key="party.id" class="p-2 text-center">
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full font-semibold"
                                    :class="cellClass(party.positions[question.id]?.score)"
                                    :title="party.positions[question.id]?.justification ?? 'Position non documentée'"
                                >
                                    {{ cellLabel(party.positions[question.id]?.score) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="filteredQuestions.length === 0" class="p-4 text-center text-sm text-gray-500">
                Aucune question ne correspond à ce filtre.
            </p>
        </PmCard>
    </div>
</template>
