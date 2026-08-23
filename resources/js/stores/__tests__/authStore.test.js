import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia, storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/authStore';
import { apiAuth } from '@/services/apiAuth';
import { apiUsers } from '@/services/apiUsers';

// apiAuth mocké entièrement : fetchMe() est le seul appel exercé par ces
// tests, mais authStore.js référence les autres méthodes ailleurs dans le
// fichier — un mock complet évite tout crash d'import.
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

vi.mock('@/services/apiUsers', () => ({
    apiUsers: {
        results: vi.fn(),
        updateAvatar: vi.fn(),
    },
}));

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
});

describe('authStore.fetchMe', () => {
    /**
     * Régression exacte du bug signalé : un visiteur non connecté (le cas le
     * plus fréquent) reçoit un 401 sur GET /api/auth/me. Ça doit rester un
     * état normal — jamais une exception qui remonte, jamais un état
     * `loading`/`authChecked` qui reste bloqué, jamais un message d'erreur
     * affiché à l'utilisateur pour quelque chose qui n'est pas une erreur.
     */
    it('treats a 401 as "not authenticated", never as a rejection or a displayed error', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 401, data: { message: 'Unauthenticated.' } } });
        const store = useAuthStore();

        await expect(store.fetchMe()).resolves.toBeUndefined();

        expect(store.isAuthenticated).toBe(false);
        expect(store.user).toBeNull();
        expect(store.error).toBeNull();
        expect(store.loading).toBe(false);
        expect(store.authChecked).toBe(true);
    });

    it('sets user and isAuthenticated on a successful /me', async () => {
        apiAuth.me.mockResolvedValue({ data: { user: { id: 1, username: 'randy' } } });
        const store = useAuthStore();

        await store.fetchMe();

        expect(store.isAuthenticated).toBe(true);
        expect(store.user).toEqual({ id: 1, username: 'randy' });
        expect(store.authChecked).toBe(true);
    });

    it('does display an error message for a real failure (500), unlike a 401', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 500, data: {} } });
        const store = useAuthStore();

        await store.fetchMe();

        expect(store.isAuthenticated).toBe(false);
        expect(store.error).not.toBeNull();
    });

    it('memoizes concurrent calls into a single in-flight request (boot + route guard can both call it)', async () => {
        let resolveCall;
        apiAuth.me.mockImplementation(() => new Promise((resolve) => {
            resolveCall = resolve;
        }));
        const store = useAuthStore();

        const first = store.fetchMe();
        const second = store.fetchMe();
        expect(apiAuth.me).toHaveBeenCalledTimes(1);

        resolveCall({ data: { user: null } });
        await Promise.all([first, second]);
    });

    /**
     * `storeToRefs(authStore)` (utilisé par TopBar/AppLayout/ToastContainer/
     * DashboardView) énumère TOUTES les propriétés du store via `for...in`
     * et teste `value.effect` sur chacune pour distinguer les getters : une
     * propriété mémoïsante placée directement sur l'instance du store (hors
     * `state()`) et remise à `null` une fois résolue ferait lever
     * `TypeError: Cannot read properties of null (reading 'effect')` sur ce
     * `null.effect`, provoquant un écran blanc au tout premier chargement de
     * l'app. La mémoïsation de fetchMe() vit dans une fermeture de module
     * (`fetchMePromise`), jamais sur `this`, précisément pour éviter ce cas.
     */
    it('does not break storeToRefs() after fetchMe() has resolved', async () => {
        apiAuth.me.mockRejectedValue({ response: { status: 401, data: {} } });
        const store = useAuthStore();

        await store.fetchMe();

        expect(() => storeToRefs(store)).not.toThrow();
    });
});

describe('authStore.updateAvatar', () => {
    it('persists the new seed and replaces the local user with the server response', async () => {
        apiUsers.updateAvatar.mockResolvedValue({ data: { user: { id: 1, username: 'randy', avatar_seed: 'new-seed' } } });
        const store = useAuthStore();
        store.user = { id: 1, username: 'randy', avatar_seed: 'old-seed' };

        await store.updateAvatar('new-seed');

        expect(apiUsers.updateAvatar).toHaveBeenCalledWith('new-seed');
        expect(store.user.avatar_seed).toBe('new-seed');
    });

    it('sets a readable error and rethrows on failure, without touching the current user', async () => {
        apiUsers.updateAvatar.mockRejectedValue({ response: { status: 422, data: { message: 'Format invalide.' } } });
        const store = useAuthStore();
        store.user = { id: 1, avatar_seed: 'old-seed' };

        await expect(store.updateAvatar('bad-seed')).rejects.toBeTruthy();

        expect(store.error).toBe('Format invalide.');
        expect(store.user.avatar_seed).toBe('old-seed');
    });
});
