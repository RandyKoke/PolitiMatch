<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useUiStore } from '@/stores/uiStore';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';

const authStore = useAuthStore();
const uiStore = useUiStore();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const submitting = ref(false);

async function handleSubmit() {
    submitting.value = true;
    try {
        await authStore.login(form);
        uiStore.showToast('Connexion réussie.', 'success');
        router.push('/dashboard');
    } catch {
        // authStore.error contient déjà un message lisible, affiché ci-dessous.
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <PmCard>
        <h1 class="mb-4 font-display text-xl font-semibold text-ink">Connexion</h1>

        <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
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
                    autocomplete="current-password"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                >
            </label>

            <p v-if="authStore.error" class="text-sm text-danger-500" role="alert">{{ authStore.error }}</p>

            <PmButton type="submit" size="lg" :loading="submitting">Se connecter</PmButton>

            <router-link to="/auth/forgot-password" class="text-center text-sm text-gray-500 hover:text-bordeaux-600">
                Mot de passe oublié ?
            </router-link>
        </form>

        <button
            type="button"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 px-6 py-3 text-lg font-medium text-gray-700 hover:bg-gray-50"
            @click="authStore.loginWithGoogle"
        >
            Continuer avec Google
        </button>

        <p class="mt-6 text-center text-sm text-gray-500">
            Pas encore de compte ?
            <router-link to="/auth/register" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">S'inscrire</router-link>
        </p>
    </PmCard>
</template>
