<script setup>
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';
import PmFeatureItem from '@/components/ui/PmFeatureItem.vue';

const steps = [
    { title: 'Réponds à des questions équilibrées', text: 'Des questions sur des enjeux politiques belges actuels, formulées avec un expert.' },
    { title: 'Découvre ton profil politique', text: 'Un profil personnalisé, construit à partir de tes réponses.' },
    { title: 'Compare-toi avec les partis', text: 'Un classement de compatibilité et un comparateur détaillé, sans jargon.' },
];
</script>

<template>
    <PmCard>
        <h1 class="mb-4 text-xl font-semibold text-gray-900">Bienvenue !</h1>

        <ol class="flex flex-col gap-4">
            <li v-for="(step, index) in steps" :key="step.title">
                <PmFeatureItem :title="step.title" :step="index + 1">{{ step.text }}</PmFeatureItem>
            </li>
        </ol>

        <PmButton size="lg" class="mt-6 w-full" @click="$router.push('/method')">Suivant</PmButton>
    </PmCard>
</template>
