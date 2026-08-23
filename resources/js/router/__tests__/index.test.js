import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import { routes, resolveNavigation } from '@/router';
import { apiAuth } from '@/services/apiAuth';
import { useAuthStore } from '@/stores/authStore';

// apiAuth mocké entièrement (même approche que authStore.test.js) : seul
// me() est exercé par ces tests, mais authStore.js référence les autres
// méthodes ailleurs dans le fichier — un mock complet évite tout crash
// d'import.
vi.mock('@/services/apiAuth', () => ({
    apiAuth: {
        me: vi.fn(),
        register: vi.fn(),
        login: vi.fn(),
        logout: vi.fn(),
        forgotPassword: vi.fn(),
        resetPassword: vi.fn(),
        socialRedirectUrl: vi.fn(),
    },
}));

// Routeur jetable par test, avec `createMemoryHistory()` (pas la vraie
// `createWebHistory()` du singleton de l'app) et la MÊME configuration de
// routes + le MÊME garde `resolveNavigation` que la production — jamais une
// copie qui pourrait diverger. `createWebHistory()` sous jsdom déclenche sa
// propre navigation initiale de façon peu fiable (bloque indéfiniment
// `router.isReady()` dans ce contexte de test), d'où ce contournement.
function createTestRouter() {
    const testRouter = createRouter({ history: createMemoryHistory(), routes });
    testRouter.beforeEach(resolveNavigation);

    return testRouter;
}

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
});

describe('garde guestOnly (/ et /start réservés aux visiteurs non connectés)', () => {
    it('redirige un utilisateur déjà authentifié qui tape / vers /dashboard', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1, username: 'randy' } } });
        const testRouter = createTestRouter();

        await testRouter.push('/');

        expect(testRouter.currentRoute.value.name).toBe('dashboard');
    });

    it('redirige un utilisateur déjà authentifié qui tape /start vers /dashboard', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1, username: 'randy' } } });
        const testRouter = createTestRouter();

        await testRouter.push('/start');

        expect(testRouter.currentRoute.value.name).toBe('dashboard');
    });

    it('laisse / inchangé pour un visiteur non connecté (401)', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 401, data: {} } });
        const testRouter = createTestRouter();

        await testRouter.push('/');

        expect(testRouter.currentRoute.value.name).toBe('home');
    });

    it('laisse /start inchangé pour un visiteur non connecté (401)', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 401, data: {} } });
        const testRouter = createTestRouter();

        await testRouter.push('/start');

        expect(testRouter.currentRoute.value.name).toBe('start');
    });

    /**
     * La vérification d'authentification (fetchMe()) est asynchrone et
     * mémoïsée
     * (authStore.authChecked). Une seconde navigation guestOnly ne doit pas
     * redéclencher un appel réseau — le garde doit réutiliser l'état déjà
     * résolu, pas re-vérifier à chaque fois.
     */
    it("ne rappelle GET /auth/me qu'une seule fois pour deux navigations guestOnly successives", async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1 } } });
        const testRouter = createTestRouter();

        await testRouter.push('/');
        await testRouter.push('/start');

        expect(apiAuth.me).toHaveBeenCalledTimes(1);
    });

    /**
     * Cas limite plausible : un utilisateur authentifié tape /quiz sans quiz
     * en cours. Le garde requiresActiveQuiz le renvoie d'abord vers /start,
     * qui est lui-même guestOnly — la redirection doit donc "cascader"
     * jusqu'à /dashboard plutôt que de s'arrêter sur /start.
     */
    it('fait cascader un utilisateur authentifié sans quiz en cours (/quiz → /start → /dashboard)', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1 } } });
        const testRouter = createTestRouter();

        await testRouter.push('/quiz');

        expect(testRouter.currentRoute.value.name).toBe('dashboard');
    });

    it('ne redirige pas un utilisateur authentifié qui navigue vers une route sans guestOnly (ex. /intro)', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1 } } });
        const testRouter = createTestRouter();

        await testRouter.push('/intro');

        expect(testRouter.currentRoute.value.name).toBe('intro');
    });
});

describe('garde requiresAuth (/dashboard, /oauth/callback réservés aux comptes connectés)', () => {
    it('redirige vers /auth/login un visiteur non authentifié qui tape /dashboard directement', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 401, data: {} } });
        const testRouter = createTestRouter();

        await testRouter.push('/dashboard');

        expect(testRouter.currentRoute.value.name).toBe('login');
    });

    it('laisse /dashboard accessible à un utilisateur réellement authentifié', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1, username: 'randy' } } });
        const testRouter = createTestRouter();

        await testRouter.push('/dashboard');

        expect(testRouter.currentRoute.value.name).toBe('dashboard');
    });

    /**
     * Régression directe du bug signalé (capture d'écran à l'appui) : après
     * déconnexion, /dashboard restait affiché. Une fois le bouton corrigé
     * pour déclencher une navigation (TopBar.vue), c'est CE garde qui doit
     * effectivement bloquer l'accès — testé ici indépendamment du bouton,
     * en simulant directement le changement d'état que authStore.logout()
     * produit, puis une nouvelle navigation vers /dashboard. Couvre aussi le
     * scénario "retour arrière du navigateur" : `testRouter.push()` réexécute
     * exactement le même `beforeEach` qu'un `popstate` déclencherait, sur le
     * même store Pinia (état d'authentification déjà réinitialisé, comme
     * après un vrai clic sur "Se déconnecter") — le garde ne doit jamais se
     * fier à un `authChecked` déjà vrai pour court-circuiter la vérification
     * de `isAuthenticated` lui-même.
     */
    it('bloque un retour sur /dashboard après déconnexion, même si authChecked est déjà résolu', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1, username: 'randy' } } });
        const testRouter = createTestRouter();

        await testRouter.push('/dashboard');
        expect(testRouter.currentRoute.value.name).toBe('dashboard');

        // Ce que authStore.logout() fait exactement (cf. authStore.js), puis
        // ce que TopBar.vue déclenche désormais après (redirection vers
        // l'accueil) : pas besoin de réinvoquer ces composants ici, ce test
        // cible le garde du routeur lui-même, à partir du même changement
        // d'état concret.
        const authStore = useAuthStore();
        authStore.user = null;
        authStore.isAuthenticated = false;
        await testRouter.push('/');
        expect(testRouter.currentRoute.value.name).toBe('home');

        // Simule le retour arrière du navigateur vers /dashboard : une
        // navigation depuis une AUTRE route (/, pas /dashboard lui-même) —
        // reproduit fidèlement un vrai `popstate` (Vue Router traite un push
        // vers la route déjà courante comme une navigation redondante sans
        // rejouer les gardes, ce qu'un vrai retour arrière ne fait jamais
        // puisqu'on ne s'y trouve plus au moment de revenir en arrière).
        await testRouter.push('/dashboard');

        expect(testRouter.currentRoute.value.name).toBe('login');
    });

    it('redirige vers /auth/login un visiteur non authentifié qui tape /oauth/callback directement', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 401, data: {} } });
        const testRouter = createTestRouter();

        await testRouter.push('/oauth/callback');

        expect(testRouter.currentRoute.value.name).toBe('login');
    });
});

/**
 * Régression directe d'un bug réel constaté en production : le lien envoyé
 * par ResetPasswordNotification (backend) pointait vers /reset-password,
 * mais aucune route de ce nom n'existait ici — Vue Router ne matchait rien,
 * page blanche (capture d'écran à l'appui). Ces deux routes ne portent
 * aucune garde (accessibles avec ou sans session active, cf. commentaire du
 * routeur), donc rien à tester côté resolveNavigation : seule la résolution
 * elle-même compte.
 */
describe('routes du mot de passe oublié (/auth/forgot-password et /reset-password)', () => {
    it('résout /auth/forgot-password vers ForgotPasswordView, jamais une page blanche', async () => {
        const testRouter = createTestRouter();

        await testRouter.push('/auth/forgot-password');

        expect(testRouter.currentRoute.value.name).toBe('forgot-password');
        expect(testRouter.currentRoute.value.matched.length).toBeGreaterThan(0);
    });

    it('résout /reset-password (avec token et email en query) vers ResetPasswordView, jamais une page blanche', async () => {
        const testRouter = createTestRouter();

        await testRouter.push('/reset-password?token=abc123&email=test%40example.com');

        expect(testRouter.currentRoute.value.name).toBe('reset-password');
        expect(testRouter.currentRoute.value.matched.length).toBeGreaterThan(0);
    });
});
