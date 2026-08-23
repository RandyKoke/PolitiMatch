<script setup>
// variant "agree/disagree/neutral" reprend la palette --color-vote-* du
// comparateur (cahier des charges §5.1) ; "brand"/"neutral" servent au
// reste de l'interface, non partisane.
const props = defineProps({
    variant: { type: String, default: 'neutral' }, // brand | neutral | agree | disagree
});

const variantClasses = {
    brand: 'bg-brand-50 text-bordeaux-700',
    neutral: 'bg-vote-neutral-bg text-vote-neutral',
    agree: 'bg-vote-agree-bg text-vote-agree',
    disagree: 'bg-vote-disagree-bg text-vote-disagree',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="variantClasses[props.variant]"
    >
        <slot />
    </span>
</template>
