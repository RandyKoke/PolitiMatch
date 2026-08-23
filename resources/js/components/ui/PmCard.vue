<script setup>
defineProps({
    padded: { type: Boolean, default: true },
    // "Climax" uniquement (résultat, migration de compte), jamais toutes les
    // cartes, pour que ça reste un signal de moment fort et pas un habillage
    // systématique : liseré tricolore en haut + un seul coin étiré en courbe
    // organique (clin d'œil Art Nouveau bruxellois, discret).
    accent: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="relative overflow-hidden border border-gray-100 bg-white shadow-sm"
        :class="[padded ? 'p-5' : '', accent ? 'rounded-2xl rounded-tr-[2.75rem]' : 'rounded-2xl']"
    >
        <div v-if="accent" class="absolute inset-x-0 top-0 h-1" style="background: var(--gradient-tricolore);" aria-hidden="true" />
        <div v-if="$slots.title" class="mb-3 flex items-center justify-between" :class="accent ? 'mt-1' : ''">
            <h2 class="font-display text-lg font-semibold text-ink">
                <slot name="title" />
            </h2>
            <slot name="actions" />
        </div>
        <slot />
    </div>
</template>
