import { defineStore } from 'pinia';
import { apiAuth } from '@/services/apiAuth';
import { apiUsers } from '@/services/apiUsers';
import { toUserMessage } from '@/services/apiClient';

// Le session_token du Guest Flow doit survivre à un rechargement de page en
// plein quiz (cf. cahier des charges §5.2) : seul morceau d'état de ce store
// persisté en dehors de Pinia. Tout le reste (user, isAuthenticated) est
// reconstruit à chaque boot via fetchMe(), jamais mis en cache localement —
// un cookie de session Sanctum peut expirer côté serveur sans que le
// front en soit informé autrement.
const SESSION_TOKEN_STORAGE_KEY = 'politimatch.session_token';

// Mémoïsation de fetchMe() en dehors du store (fermeture de module), PAS en
// tant que propriété `this._fetchMePromise` : un stockage `null`/Promise sur
// l'instance du store se retrouve énuméré par `storeToRefs()` (utilisé par
// TopBar/AppLayout/ToastContainer/DashboardView), qui fait un `for...in` sur
// le store et teste `value.effect` sur chaque propriété pour distinguer les
// getters — `null.effect` lève `TypeError: Cannot read properties of null
// (reading 'effect')`. C'est le bug réel constaté au tout premier montage de
// l'app (avant que fetchMe() ait fini de résoudre) : reproduit et confirmé
// en isolant la cause exacte (script de reproduction Playwright + inspection
// du bundle + tests Pinia isolés), pas une simple supposition. Cf. journal
// TFE pour le détail complet de l'investigation.
let fetchMePromise = null;

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        isAuthenticated: false,
        sessionToken: localStorage.getItem(SESSION_TOKEN_STORAGE_KEY),
        loading: false,
        error: null,
        // true dès que fetchMe() a résolu une première fois (succès ou 401) —
        // utilisé par le garde de route pour attendre la vérification initiale
        // sans la relancer à chaque navigation (cf. router/index.js).
        authChecked: false,
    }),

    getters: {
        // Exposé pour debug/affichage éventuel ; axios (withXSRFToken) gère
        // déjà la transmission de l'en-tête lui-même, ce getter ne sert donc
        // qu'à inspecter l'état, jamais à construire une requête manuellement.
        //
        // Méthode raccourcie plutôt qu'arrow function : Pinia lie `this` sur
        // chaque getter, une arrow function l'ignorerait silencieusement —
        // bonne pratique recommandée par Pinia, même si ce n'était pas la
        // cause du bug `storeToRefs` réel décrit plus haut (c'était
        // `_fetchMePromise`, pas ce getter).
        csrfToken() {
            const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

            return match ? decodeURIComponent(match[1]) : null;
        },
    },

    actions: {
        setSessionToken(token) {
            this.sessionToken = token;
            if (token) {
                localStorage.setItem(SESSION_TOKEN_STORAGE_KEY, token);
            } else {
                localStorage.removeItem(SESSION_TOKEN_STORAGE_KEY);
            }
        },

        /**
         * Appelée au boot de l'app (app.js) ET, indépendamment, par le garde
         * de route pour les pages protégées (router/index.js) : les deux
         * peuvent se déclencher avant que l'une n'ait résolu. La promesse en
         * cours est mémoïsée dans `fetchMePromise` (fermeture de module, cf.
         * commentaire en haut du fichier — jamais `this.xxx`) pour qu'un seul
         * GET /auth/me parte réellement, les appelants concurrents attendant
         * tous la même promesse.
         *
         * Un 401 ici est un état normal (visiteur non connecté), jamais une
         * erreur à afficher — c'est la différence avec les autres 401
         * rencontrés ailleurs dans l'app.
         */
        fetchMe() {
            if (fetchMePromise) {
                return fetchMePromise;
            }

            this.loading = true;
            fetchMePromise = apiAuth
                .me()
                .then(({ data }) => {
                    this.user = data.user;
                    this.isAuthenticated = true;
                })
                .catch((error) => {
                    this.user = null;
                    this.isAuthenticated = false;
                    if (error.response?.status !== 401) {
                        this.error = toUserMessage(error);
                    }
                })
                .finally(() => {
                    this.loading = false;
                    this.authChecked = true;
                    fetchMePromise = null;
                });

            return fetchMePromise;
        },

        async register(payload) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiAuth.register(payload, this.sessionToken);
                this.user = data.user;
                this.isAuthenticated = true;
                // Compte créé : la session invitée n'a plus de raison d'être
                // suivie séparément, qu'elle ait été migrée ou non (cf.
                // `migrated`/`reason` renvoyés par l'API pour un message
                // contextuel, à exploiter dans l'écran d'inscription).
                this.setSessionToken(null);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async login(payload) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiAuth.login(payload, this.sessionToken);
                this.user = data.user;
                this.isAuthenticated = true;
                this.setSessionToken(null);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            this.loading = true;
            try {
                await apiAuth.logout();
            } finally {
                this.user = null;
                this.isAuthenticated = false;
                this.loading = false;
            }
        },

        async updateAvatar(avatarSeed) {
            this.error = null;
            try {
                const { data } = await apiUsers.updateAvatar(avatarSeed);
                this.user = data.user;

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            }
        },

        loginWithGoogle() {
            // Navigation plein-page volontaire (Socialite), pas un appel
            // axios : voir apiAuth.socialRedirectUrl.
            window.location.href = apiAuth.socialRedirectUrl('google');
        },

        async forgotPassword(payload) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiAuth.forgotPassword(payload);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async resetPassword(payload) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await apiAuth.resetPassword(payload);

                return data;
            } catch (error) {
                this.error = toUserMessage(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
