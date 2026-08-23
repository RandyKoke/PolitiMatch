<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useQuizStore } from '@/stores/quizStore';
import { useUiStore } from '@/stores/uiStore';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';
import AvatarPicker from '@/components/AvatarPicker.vue';

const authStore = useAuthStore();
const quizStore = useQuizStore();
const uiStore = useUiStore();
const router = useRouter();

const form = reactive({ username: '', email: '', password: '', password_confirmation: '' });
const avatarSeed = ref(null);
const submitting = ref(false);

// Un quiz invité en cours n'est migrable que s'il existe un session_token —
// authStore en reste la seule source de vérité (cf. authStore.js), quizStore
// ne fait que confirmer qu'un quiz est effectivement associé à cette session.
const hasQuizInProgress = computed(() => Boolean(quizStore.currentQuizUuid && authStore.sessionToken));

async function handleSubmit() {
    submitting.value = true;
    try {
        const data = await authStore.register({ ...form, avatar_seed: avatarSeed.value });

        if (data.migrated) {
            uiStore.showToast('Compte créé : ton quiz en cours a été conservé.', 'success');
        } else if (data.reason && data.reason !== 'no_session_token') {
            // Compte bien créé, mais la migration a échoué pour une raison
            // réelle (session expirée, erreur de migration) : message honnête
            // plutôt qu'un succès silencieux qui masquerait la perte du résultat,
            // ou une erreur bloquante qui laisserait croire que l'inscription
            // elle-même a échoué (elle a réussi, cf. cahier des charges §5.2 :
            // un échec de migration ne doit jamais faire perdre le compte créé).
            uiStore.showToast("Ton compte est créé, mais on n'a pas pu récupérer ton résultat précédent.", 'error');
        } else {
            uiStore.showToast('Compte créé avec succès.', 'success');
        }

        router.push('/dashboard');
    } catch {
        // authStore.error contient déjà un message lisible (422 → erreurs de validation).
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <PmCard>
        <h1 class="mb-1 font-display text-xl font-semibold text-ink">Créer un compte</h1>
        <p v-if="hasQuizInProgress" class="mb-4 text-sm text-gray-500">
            Ton résultat en cours sera automatiquement rattaché à ton nouveau compte.
        </p>

        <form class="flex flex-col gap-4" :class="hasQuizInProgress ? '' : 'mt-4'" @submit.prevent="handleSubmit">
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Nom d'utilisateur
                <input
                    v-model="form.username"
                    type="text"
                    required
                    autocomplete="username"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                >
            </label>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Adresse e-mail
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                >
            </label>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Mot de passe
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                >
            </label>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Confirmer le mot de passe
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                >
            </label>

            <div>
                <p class="mb-2 text-sm font-medium text-gray-700">Ton avatar</p>
                <AvatarPicker v-model="avatarSeed" />
            </div>

            <p v-if="authStore.error" class="text-sm text-danger-500" role="alert">{{ authStore.error }}</p>

            <PmButton type="submit" size="lg" :loading="submitting">Créer mon compte</PmButton>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            Déjà un compte ?
            <router-link to="/auth/login" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">Se connecter</router-link>
        </p>
    </PmCard>
</template>
