import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useQuizStore } from '@/stores/quizStore';
import { useUiStore } from '@/stores/uiStore';

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

// Exporté (plutôt que gardé privé au module) uniquement pour permettre aux
// tests de construire un routeur jetable avec `createMemoryHistory()` — voir
// `__tests__/index.test.js`. `createWebHistory()` (utilisé ci-dessous pour la
// vraie app) déclenche sa propre navigation initiale de façon peu fiable
// sous jsdom, d'où ce contournement pour les tests plutôt que d'utiliser le
// routeur singleton réel.
export const routes = [
    // guestOnly : un utilisateur déjà authentifié n'a rien à faire sur ces
    // deux écrans d'entrée (page marketing "Commencer" / choix invité vs
    // compte) — il a déjà un compte, cf. router.beforeEach ci-dessous.
    { path: '/', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { guestOnly: true } },
    { path: '/start', name: 'start', component: () => import('@/views/StartAccessView.vue'), meta: { guestOnly: true } },
    { path: '/intro', name: 'intro', component: () => import('@/views/IntroView.vue') },
    { path: '/method', name: 'method', component: () => import('@/views/MethodologyView.vue') },
    // Pas de route /progress séparée : la barre de progression et les
    // messages motivationnels sont un composant intégré à QuizView, pas un
    // écran à part. Sortir du flux question-par-question pour un simple
    // indicateur casserait la continuité de l'expérience sans bénéfice réel.
    {
        path: '/quiz',
        name: 'quiz',
        component: () => import('@/views/QuizView.vue'),
        meta: { requiresActiveQuiz: true },
    },
    {
        path: '/results/:uuid',
        name: 'results',
        component: () => import('@/views/ResultsView.vue'),
        meta: { requiresQuizUuid: true },
    },
    {
        path: '/compare/:uuid',
        name: 'compare',
        component: () => import('@/views/CompareView.vue'),
        meta: { requiresQuizUuid: true },
    },
    // Annuaire public (sans contexte de quiz), et fiche détaillée derrière —
    // volontairement enregistrée avant '/parties/:id' pour la lisibilité,
    // même si les deux préfixes ne se recoupent pas (un id numérique ne
    // matche jamais littéralement "annuaire").
    { path: '/parties', name: 'parties-list', component: () => import('@/views/PartiesListView.vue') },
    { path: '/parties/:id', name: 'party-detail', component: () => import('@/views/PartyDetailView.vue') },
    // Pas de garde requiresQuizUuid ici, volontairement : :token est un
    // share_token public (cf. ShareController), pas l'UUID d'un quiz que le
    // visiteur possède — un visiteur qui reçoit juste un lien de partage
    // n'a par définition aucune session/quiz en cours à valider. Le format
    // (UUID) est validé côté backend (404 sinon), pas ici.
    { path: '/share/:token', name: 'share', component: () => import('@/views/ShareView.vue') },
    { path: '/auth/login', name: 'login', component: () => import('@/views/auth/LoginView.vue') },
    { path: '/auth/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue') },
    { path: '/auth/forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue') },
    // Chemin exact attendu par ResetPasswordNotification côté backend
    // (lien construit en dur dans l'email envoyé) : toute évolution ici doit
    // être répercutée côté backend et inversement, même remarque que pour
    // /oauth/callback ci-dessous.
    { path: '/reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPasswordView.vue') },
    {
        // Chemin exact attendu par SocialAuthController::FRONTEND_CALLBACK_PATH
        // côté backend (redirect() codé en dur après l'aller-retour OAuth) —
        // pas "/auth/social/callback" : les deux doivent rester synchronisés,
        // toute évolution ici doit être répercutée côté backend et inversement.
        path: '/oauth/callback',
        name: 'social-callback',
        component: () => import('@/views/auth/SocialCallbackView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: { requiresAuth: true },
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Extrait en fonction nommée exportée (plutôt que gardée inline) pour que
// les tests puissent l'enregistrer telle quelle sur un routeur jetable — la
// même logique exacte, jamais une copie qui pourrait diverger silencieusement
// de ce qui tourne réellement en production.
export async function resolveNavigation(to) {
    const authStore = useAuthStore();
    const quizStore = useQuizStore();
    const uiStore = useUiStore();

    // Vérification de format uniquement (pas d'appel réseau) : ces vues
    // sont accessibles par simple possession de l'UUID (même modèle que le
    // backend, cf. ResultController::show), la validation "existe vraiment
    // en base" reste à la charge de la vue elle-même au chargement des
    // données réelles (prompt suivant).
    if (to.meta.requiresQuizUuid && !UUID_REGEX.test(to.params.uuid ?? '')) {
        uiStore.showToast('Résultat introuvable.', 'error');

        return { name: 'home' };
    }

    // Vérification synchrone (lecture Pinia, pas d'appel réseau) : on ne
    // peut pas répondre à un quiz qui n'a jamais été démarré. La validité
    // réelle du quiz (existe-t-il encore, statut) est vérifiée séparément
    // par QuizView au montage via resumeState() (appel API), qui gère aussi
    // le cas d'un UUID périmé/invalide — ce garde-ci n'évite qu'un aller
    // inutile sur /quiz sans avoir jamais démarré de quiz du tout.
    if (to.meta.requiresActiveQuiz && !quizStore.currentQuizUuid) {
        uiStore.showToast("Commence d'abord le quiz.", 'error');

        return { name: 'start' };
    }

    if (to.meta.requiresAuth) {
        if (!authStore.authChecked) {
            await authStore.fetchMe();
        }

        if (!authStore.isAuthenticated) {
            uiStore.showToast('Connecte-toi pour accéder à cette page.', 'error');

            return { name: 'login' };
        }
    }

    // Symétrique du garde requiresAuth ci-dessus : un utilisateur déjà
    // authentifié qui atterrit sur / ou /start (page marketing "Commencer",
    // choix invité/compte) n'a rien à y faire, il a déjà un compte — direction
    // /dashboard, qui propose sa propre action "Refaire le quiz" (cf. journal
    // TFE). Même mécanisme d'attente que requiresAuth (authChecked +
    // fetchMe() mémoïsé) : la navigation reste en suspens tant que la
    // vérification n'a pas résolu, donc aucun flash de la page publique avant
    // la redirection (App.vue ne monte même pas avant que le routeur ait fini
    // de résoudre la navigation initiale, cf. app.js).
    if (to.meta.guestOnly) {
        if (!authStore.authChecked) {
            await authStore.fetchMe();
        }

        if (authStore.isAuthenticated) {
            return { name: 'dashboard' };
        }
    }

    return true;
}

router.beforeEach(resolveNavigation);
