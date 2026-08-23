<script setup>
// Bloc "icône + titre + texte" réutilisé par IntroView (3 cartes) et
// MethodologyView (points de méthodologie) plutôt que dupliqué deux fois.
defineProps({
    title: { type: String, required: true },
    // Numéro d'étape optionnel (IntroView) : affiché à la place de l'icône
    // par défaut si fourni, sans dupliquer le composant pour ce seul besoin.
    step: { type: [Number, String], default: null },
});
</script>

<template>
    <div class="flex gap-3">
        <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-bordeaux-700"
            aria-hidden="true"
        >
            <template v-if="step !== null">{{ step }}</template>
            <slot v-else name="icon">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                </svg>
            </slot>
        </span>
        <div>
            <p class="font-medium text-gray-900">{{ title }}</p>
            <p class="text-sm text-gray-500"><slot /></p>
        </div>
    </div>
</template>
