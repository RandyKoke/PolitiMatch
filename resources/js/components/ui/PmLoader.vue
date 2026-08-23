<script setup>
defineProps({
    size: { type: String, default: 'md' }, // sm | md | lg
    label: { type: String, default: 'Chargement en cours' },
});

const sizeClasses = { sm: 'h-5 w-5', md: 'h-8 w-8', lg: 'h-12 w-12' };
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3" role="status">
        <svg class="animate-spin text-ink" :class="sizeClasses[size]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z" />
        </svg>
        <!-- Visible seulement pour les lecteurs d'écran : le spinner est déjà
             assez explicite visuellement (cahier des charges §14). -->
        <span class="sr-only">{{ label }}</span>
    </div>
</template>
