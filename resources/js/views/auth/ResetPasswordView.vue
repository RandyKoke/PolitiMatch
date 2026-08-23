<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useUiStore } from '@/stores/uiStore';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

// token/email voyagent en query string (lien d'email, cf.
// ResetPasswordNotification côté backend) : une visite directe sans l'un des
// deux (lien tronqué, copié à la main...) est un lien invalide, pas un
// formulaire vide à afficher — même message que le 422 "lien invalide ou
// expiré" renvoyé par le backend pour un token inconnu, pour rester cohérent
// aux yeux de l'utilisateur.
const email = typeof route.query.email === 'string' ? route.query.email : '';
const token = typeof route.query.token === 'string' ? route.query.token : '';
const linkIsValid = Boolean(email && token);

const form = reactive({ password: '', password_confirmation: '' });
const submitting = ref(false);

async function handleSubmit() {
    submitting.value = true;
    try {
        await authStore.resetPassword({ email, token, ...form });
        uiStore.showToast('Mot de passe réinitialisé. Tu peux te reconnecter.', 'success');
        router.push('/auth/login');
    } catch {
        // authStore.error contient déjà un message lisible, affiché ci-dessous.
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <PmCard>
        <template v-if="!linkIsValid">
            <h1 class="mb-4 font-display text-xl font-semibold text-ink">Lien invalide</h1>
            <p class="text-sm text-gray-600">
                Ce lien de réinitialisation est incomplet ou invalide. Redemande un lien de
                réinitialisation.
            </p>
        </template>

        <template v-else>
            <h1 class="mb-1 font-display text-xl font-semibold text-ink">Nouveau mot de passe</h1>
            <p class="mb-4 text-sm text-gray-500">Choisis un nouveau mot de passe pour {{ email }}.</p>

            <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Nouveau mot de passe
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

                <p v-if="authStore.error" class="text-sm text-danger-500" role="alert">{{ authStore.error }}</p>

                <PmButton type="submit" size="lg" :loading="submitting">Réinitialiser mon mot de passe</PmButton>
            </form>
        </template>

        <p class="mt-6 text-center text-sm text-gray-500">
            <router-link to="/auth/login" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">Retour à la connexion</router-link>
        </p>
    </PmCard>
</template>
