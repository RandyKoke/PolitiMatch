<script setup>
// Formulations centralisées ici pour les deux variantes de l'état bloquant
// (state = 'empty' | 'too_few'), réutilisé tel quel par
// ResultsView, ShareView et CompareView, pour que le même wording ne
// diverge jamais silencieusement d'une page à l'autre.
defineProps({
    state: { type: String, required: true },
});
</script>

<template>
    <div>
        <h1 v-if="state === 'empty'" class="font-display text-lg font-semibold text-ink">Aucune réponse enregistrée</h1>
        <h1 v-else class="font-display text-lg font-semibold text-ink">Trop peu de réponses pour un profil fiable</h1>

        <p v-if="state === 'empty'" class="mt-2 text-sm leading-relaxed text-gray-600">
            Tu n'as répondu à aucune question — il est impossible d'établir un profil politique. Refais le quiz pour
            découvrir tes résultats.
        </p>
        <p v-else class="mt-2 text-sm leading-relaxed text-gray-600">
            Tu as répondu à trop peu de questions pour obtenir un profil fiable. Reprends le quiz pour répondre aux
            questions que tu as passées.
        </p>
    </div>
</template>
