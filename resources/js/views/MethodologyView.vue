<script setup>
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';
import PmFeatureItem from '@/components/ui/PmFeatureItem.vue';

const points = [
    {
        title: 'Des questions neutres et validées',
        text: '30 questions, rédigées et relues par un politologue pour détecter tout biais de formulation.',
    },
    {
        title: 'Aucune bonne ou mauvaise réponse',
        text: "Tes réponses restent anonymes tant que tu ne crées pas de compte, et servent uniquement à te positionner.",
    },
    {
        title: 'Un calcul transparent',
        text: "Une distance pondérée entre tes réponses et les positions des partis. Chaque question a un poids qui reflète son caractère discriminant.",
    },
    {
        title: 'Un outil qui éclaire, pas qui décide',
        text: "Les résultats sont une aide à la réflexion, jamais une consigne de vote. Interprète-les avec ton propre jugement.",
    },
];

// Résumé condensé pour les utilisateurs pressés qui ne liraient pas les 4
// blocs ci-dessus, placé sur cette vue plutôt que sur IntroView, qui EST
// déjà elle-même un résumé en 3 étapes ; ajouter un second résumé là-bas
// serait redondant.
const summary = [
    '30 questions, ~3 minutes',
    'Contenu validé par un expert politique',
    'Résultat indicatif, pas une consigne de vote',
];
</script>

<template>
    <PmCard>
        <h1 class="mb-4 text-xl font-semibold text-gray-900">Comment ça marche ?</h1>

        <ul class="flex flex-col gap-4">
            <li v-for="point in points" :key="point.title">
                <PmFeatureItem :title="point.title">{{ point.text }}</PmFeatureItem>
            </li>
        </ul>

        <ul class="mt-6 flex flex-col gap-1.5 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">
            <li v-for="line in summary" :key="line" class="flex items-center gap-2">
                <span class="h-1 w-1 rounded-full bg-gray-400" aria-hidden="true" />
                {{ line }}
            </li>
        </ul>

        <PmButton size="lg" class="mt-6 w-full" @click="$router.push('/quiz')">Compris, commencer le quiz !</PmButton>
    </PmCard>
</template>
