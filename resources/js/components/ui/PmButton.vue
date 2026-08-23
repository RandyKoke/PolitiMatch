<script setup>
import { computed } from 'vue';

const props = defineProps({
    // primary | secondary | danger | ghost | agree | agree-strong | disagree
    // | disagree-strong | neutral-vote
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' }, // sm | md | lg
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const variantClasses = {
    // Or plein + texte noir (jamais blanc) : le contraste noir-sur-or est
    // excellent (7.3:1, AAA) et surtout c'est le bordeaux qui reste réservé
    // aux accents secondaires, jamais aux CTA, pour ne pas connoter "alerte".
    primary: 'bg-brand-600 text-ink hover:bg-brand-700 active:bg-brand-800 disabled:bg-brand-300 shadow-[0_1px_2px_rgba(26,26,26,.08),0_6px_16px_-6px_rgba(212,160,23,.55)] hover:shadow-[0_2px_4px_rgba(26,26,26,.1),0_12px_24px_-8px_rgba(212,160,23,.65)] hover:-translate-y-0.5 active:translate-y-0 active:scale-[.97]',
    // Fond or très clair + texte noir (pas une nuance d'or plus foncée en
    // texte : bg-brand-50/text-brand-700 tombe à 3.1:1, sous le seuil AA —
    // vérifié, cf. journal).
    secondary: 'bg-brand-50 text-ink hover:bg-brand-100 active:bg-brand-200 disabled:text-brand-300',
    danger: 'bg-danger-500 text-white hover:bg-danger-600 active:bg-danger-600 disabled:bg-red-200',
    ghost: 'bg-transparent text-gray-700 hover:bg-gray-100 active:bg-gray-200 disabled:text-gray-300',
    // Réponses du quiz : mêmes tokens que le comparateur (vert/rouge/gris,
    // cf. cahier des charges §5.1) — un accord/désaccord n'est jamais
    // "positif/négatif" au sens UI (pas de rouge = erreur ici), seulement
    // le même code couleur symétrique appliqué au moment de répondre.
    //
    // Échelle de Likert à 5 niveaux (-2..+2, cf. QuizView) : les variantes
    // "-strong" (fond plein) marquent les positions extrêmes ("Pas du tout
    // d'accord" / "Tout à fait d'accord"), les variantes de base (contour)
    // les positions modérées — la même logique de couleur/intensité que
    // "primary" (plein) vs "secondary" (clair) ailleurs dans l'app, pas un
    // nouveau langage visuel.
    agree: 'border-2 border-vote-agree bg-vote-agree-bg text-vote-agree hover:bg-green-100 disabled:opacity-50',
    'agree-strong': 'bg-vote-agree text-white hover:bg-green-700 active:bg-green-800 disabled:opacity-50',
    disagree: 'border-2 border-vote-disagree bg-vote-disagree-bg text-vote-disagree hover:bg-red-100 disabled:opacity-50',
    'disagree-strong': 'bg-vote-disagree text-white hover:bg-red-700 active:bg-red-800 disabled:opacity-50',
    'neutral-vote': 'border-2 border-vote-neutral bg-vote-neutral-bg text-vote-neutral hover:bg-gray-100 disabled:opacity-50',
};

const sizeClasses = {
    sm: 'text-sm px-3 py-1.5 gap-1.5',
    md: 'text-base px-4 py-2.5 gap-2',
    lg: 'text-lg px-6 py-3 gap-2',
};

// disabled couvre aussi l'état loading : un clic pendant une requête en
// cours ne doit jamais déclencher une double soumission (cf. quizStore).
const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        :aria-busy="loading"
        class="inline-flex items-center justify-center rounded-xl font-medium transition-[color,background-color,box-shadow,transform] duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink disabled:cursor-not-allowed"
        :class="[variantClasses[variant], sizeClasses[size]]"
    >
        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z" />
        </svg>
        <slot />
    </button>
</template>
