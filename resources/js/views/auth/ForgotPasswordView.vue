<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import PmButton from '@/components/ui/PmButton.vue';
import PmCard from '@/components/ui/PmCard.vue';

const authStore = useAuthStore();

const form = reactive({ email: '' });
const submitting = ref(false);
// Le backend renvoie toujours le même message générique, que le compte
// existe ou non (anti-énumération, cf. PasswordResetController) : cet état
// remplace le formulaire par ce message plutôt que d'afficher un simple
// toast, pour qu'il reste visible tant que l'utilisateur n'a pas quitté la
// page (utile puisqu'il doit ensuite aller consulter sa boîte mail).
const sent = ref(false);

async function handleSubmit() {
    submitting.value = true;
    try {
        await authStore.forgotPassword(form);
        sent.value = true;
    } catch {
        // authStore.error contient déjà un message lisible, affiché ci-dessous.
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <PmCard>
        <template v-if="sent">
            <h1 class="mb-4 font-display text-xl font-semibold text-ink">Vérifie ta boîte mail</h1>
            <p class="text-sm text-gray-600">
                Si un compte existe avec l'adresse <strong>{{ form.email }}</strong>, un lien de
                réinitialisation vient de lui être envoyé. Le lien expire dans 60 minutes.
            </p>
        </template>

        <template v-else>
            <h1 class="mb-1 font-display text-xl font-semibold text-ink">Mot de passe oublié</h1>
            <p class="mb-4 text-sm text-gray-500">
                Indique ton adresse e-mail, on t'envoie un lien pour choisir un nouveau mot de passe.
            </p>

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

                <p v-if="authStore.error" class="text-sm text-danger-500" role="alert">{{ authStore.error }}</p>

                <PmButton type="submit" size="lg" :loading="submitting">Envoyer le lien</PmButton>
            </form>
        </template>

        <p class="mt-6 text-center text-sm text-gray-500">
            <router-link to="/auth/login" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">Retour à la connexion</router-link>
        </p>
    </PmCard>
</template>
