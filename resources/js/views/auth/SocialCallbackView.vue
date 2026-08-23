<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useUiStore } from '@/stores/uiStore';
import PmLoader from '@/components/ui/PmLoader.vue';

// Point d'atterrissage après l'aller-retour OAuth plein-page (Google, via
// Socialite) : SocialAuthController encode le résultat en query string
// (status=authenticated|linked|account_exists|error, cf. backend), jamais
// en JSON puisqu'il s'agit d'une redirection navigateur, pas d'un appel
// axios. Le cookie de session Sanctum est déjà posé côté serveur à ce
// stade — d'où le garde de route "requiresAuth" sur /oauth/callback
// (cf. router/index.js) : fetchMe() ici ne fait que réconcilier l'état
// Pinia avec une session qui existe déjà.
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const messagesByStatus = {
    authenticated: 'Connexion réussie via Google.',
    linked: 'Compte Google lié avec succès.',
    account_exists: 'Un compte existe déjà avec cette adresse e-mail. Connecte-toi avec ton mot de passe.',
    error: "La connexion via Google a échoué. Réessaie ou utilise ton e-mail et ton mot de passe.",
};

onMounted(async () => {
    const status = route.query.status;
    uiStore.showToast(messagesByStatus[status] ?? messagesByStatus.error, status === 'error' || status === 'account_exists' ? 'error' : 'success');

    if (status === 'authenticated' || status === 'linked') {
        await authStore.fetchMe();
        router.replace('/dashboard');
    } else {
        router.replace('/auth/login');
    }
});
</script>

<template>
    <div class="flex justify-center py-10">
        <PmLoader label="Finalisation de la connexion" />
    </div>
</template>
