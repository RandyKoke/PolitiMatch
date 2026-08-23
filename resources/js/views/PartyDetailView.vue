<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiParties } from '@/services/apiParties';
import { useResultsStore } from '@/stores/resultsStore';
import PmCard from '@/components/ui/PmCard.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmButton from '@/components/ui/PmButton.vue';

const route = useRoute();
const router = useRouter();
const resultsStore = useResultsStore();

const loading = ref(true);
const notFound = ref(false);
const party = ref(null);
const notablePositions = ref([]);

// Compatibilité contextuelle : uniquement si l'écran d'origine a transmis
// l'UUID du quiz en cours (?quiz=..., posé par ResultsView/CompareView sur
// leurs liens vers une fiche parti) — sans ce contexte (accès direct par
// URL, lien partagé nu), aucune compatibilité n'est affichée, ce qui est le
// comportement correct plutôt qu'une erreur.
const quizUuid = computed(() => (typeof route.query.quiz === 'string' ? route.query.quiz : null));
const compatibility = ref(null);

const LANGUAGE_COMMUNITY_LABELS = {
    FR: 'Francophone',
    NL: 'Néerlandophone',
    DE: 'Germanophone',
    FED: 'Fédéral',
};
const languageCommunityLabel = computed(() => LANGUAGE_COMMUNITY_LABELS[party.value?.language_community] ?? null);

// Mention textuelle plutôt qu'un graphique 2D complet (PoliticalAxisChart) :
// reproduire tout le graphique sur cette page demanderait de recharger
// l'ensemble des partis pour le contexte, pour un bénéfice limité ici (une
// seule fiche, pas une comparaison) — un texte qualitatif, avec le même
// vocabulaire d'axes déjà établi par le graphique (libéral/interventionniste,
// conservateur/progressiste), reste tout aussi informatif pour ce contexte.
// Seuil de 0,15 (sur une échelle de -1 à 1) : évite de qualifier de
// "plutôt" une valeur trop proche de zéro pour être significative.
function axisQualifier(value, negativeLabel, positiveLabel, neutralLabel) {
    const numeric = Number(value);
    if (numeric > 0.15) return positiveLabel;
    if (numeric < -0.15) return negativeLabel;
    return neutralLabel;
}
const ideologicalSummary = computed(() => {
    if (!party.value || party.value.ideological_x === null || party.value.ideological_y === null) {
        return null;
    }
    const economic = axisQualifier(party.value.ideological_x, 'plutôt libéral', 'plutôt interventionniste', 'modéré');
    const societal = axisQualifier(party.value.ideological_y, 'plutôt conservateur', 'plutôt progressiste', 'modéré');

    return `Positionnement calculé à partir de ses positions déclarées : ${economic} sur le plan économique, ${societal} sur le plan sociétal.`;
});

async function load() {
    loading.value = true;
    notFound.value = false;
    try {
        const { data } = await apiParties.show(route.params.id, quizUuid.value);
        party.value = data.party;
        notablePositions.value = data.notable_positions;
    } catch (error) {
        if (error.response?.status === 404) {
            notFound.value = true;
        } else {
            notFound.value = true; // dégradation identique : jamais d'écran cassé pour une fiche parti.
        }
    } finally {
        loading.value = false;
    }

    if (quizUuid.value) {
        try {
            const data = await resultsStore.loadResults(quizUuid.value);
            compatibility.value = data.party_scores.find((s) => s.party_id === Number(route.params.id)) ?? null;
        } catch {
            // Contexte de compatibilité facultatif : un échec ici n'empêche
            // jamais l'affichage de la fiche parti elle-même.
            compatibility.value = null;
        }
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="flex justify-center py-16">
        <PmLoader label="Chargement de la fiche parti" />
    </div>

    <PmCard v-else-if="notFound">
        <h1 class="text-lg font-semibold text-gray-900">Parti introuvable</h1>
        <p class="mt-2 text-sm text-gray-500">Ce parti n'existe pas ou n'est plus actif.</p>
        <PmButton class="mt-4" variant="secondary" @click="router.push('/')">Retour à l'accueil</PmButton>
    </PmCard>

    <div v-else class="flex flex-col gap-4">
        <PmCard>
            <div class="flex items-center gap-3">
                <img v-if="party.logo_url" :src="party.logo_url" :alt="`Logo ${party.name}`" class="h-12 w-12 rounded-full object-contain">
                <span
                    v-else
                    class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full px-1 font-semibold text-white"
                    :class="party.abbreviation.length > 5 ? 'text-[10px]' : 'text-sm'"
                    :style="{ backgroundColor: party.color_hex ?? '#9ca3af' }"
                    aria-hidden="true"
                >
                    {{ party.abbreviation }}
                </span>
                <div>
                    <h1 class="font-display text-xl font-semibold text-ink">{{ party.name }}</h1>
                    <p class="text-sm text-gray-500">
                        {{ party.abbreviation }}
                        <span v-if="languageCommunityLabel"> · {{ languageCommunityLabel }}</span>
                    </p>
                </div>
            </div>
            <!-- Slogan officiel de campagne : mis en avant en citation,
                 juste sous l'identité du parti (Fraunces italique, même
                 traitement que l'accroche de HomeView) — jamais confondu
                 avec la description factuelle qui suit. -->
            <p v-if="party.slogan" class="mt-3 font-display text-lg leading-relaxed text-bordeaux-600 italic">
                « {{ party.slogan }} »
            </p>
            <!-- Consultable depuis un ancien résultat même si le parti a
                 depuis été retiré du quiz actif : mention discrète plutôt
                 qu'un blocage complet, l'information
                 de la fiche reste factuellement valide, seule sa
                 disponibilité dans un NOUVEAU quiz a changé. -->
            <p v-if="party.is_active === false" class="mt-3 text-xs text-gray-400">
                Ce parti n'est plus actif dans le quiz actuel.
            </p>
            <p v-if="party.description" class="mt-4 text-sm text-gray-600">{{ party.description }}</p>
            <p v-if="ideologicalSummary" class="mt-3 text-xs text-gray-400">{{ ideologicalSummary }}</p>

            <div v-if="compatibility" class="mt-4 rounded-xl bg-brand-50 p-3">
                <p v-if="compatibility.compatibility_score !== null" class="text-sm font-medium text-bordeaux-700">
                    Ta compatibilité avec ce parti : {{ Math.round(Number(compatibility.compatibility_score)) }}%
                </p>
                <p v-else class="text-sm text-bordeaux-700">Données insuffisantes pour calculer ta compatibilité avec ce parti.</p>
            </div>
        </PmCard>

        <PmCard v-if="notablePositions.length > 0">
            <template #title>Positions clés</template>
            <ul class="flex flex-col gap-4">
                <li v-for="(position, index) in notablePositions" :key="index" class="border-b border-gray-50 pb-4 last:border-0 last:pb-0">
                    <p class="font-medium text-gray-900">{{ position.question_label }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ position.justification }}</p>
                    <p v-if="position.source_reference" class="mt-1 text-xs text-gray-400">Source : {{ position.source_reference }}</p>
                </li>
            </ul>
        </PmCard>
    </div>
</template>
