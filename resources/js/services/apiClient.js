import axios from 'axios';

/**
 * Client HTTP unique pour toute l'application. baseURL relative ('/api') :
 * le déploiement mono-service Railway sert frontend et API sur la même
 * origine (cahier des charges §8.1), donc pas besoin d'URL absolue ni de
 * variable d'environnement VITE_API_BASE_URL.
 *
 * withCredentials + withXSRFToken : Laravel Sanctum en mode SPA/cookie
 * (config/sanctum.php, config/cors.php avec supports_credentials=true)
 * authentifie via cookie de session, pas de token Bearer. axios ≥1.7 lit
 * automatiquement le cookie XSRF-TOKEN et pose l'en-tête X-XSRF-TOKEN sur
 * les requêtes qui modifient l'état — condition nécessaire pour que
 * POST/PUT/DELETE passent la protection CSRF de Laravel.
 */
export const apiClient = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

/**
 * À appeler avant toute action qui modifie l'état côté serveur (login,
 * register, submitAnswer...) si le cookie XSRF-TOKEN n'est pas encore posé
 * (ex. premier chargement de l'app). Hors du préfixe /api — c'est une route
 * Sanctum native, pas un endpoint métier — d'où l'appel axios direct plutôt
 * que via apiClient (qui préfixe /api).
 */
export function ensureCsrfCookie() {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

/**
 * Réconciliation minimale des erreurs réseau/serveur, pour que chaque store
 * n'ait pas à répéter la même logique de lecture d'erreur Laravel :
 * - 422 (Form Request) : message de validation lisible + détail par champ.
 * - 401 : l'appelant décide (session expirée vs simple visiteur non connecté
 *   selon l'endpoint) — voir authStore.
 * - 500 / erreur réseau : message générique, jamais l'erreur technique brute
 *   affichée à l'utilisateur (cahier des charges §7 : zéro jargon).
 */
export function toUserMessage(error) {
    if (!error.response) {
        return 'Connexion impossible. Vérifiez votre réseau et réessayez.';
    }

    const { status, data } = error.response;

    if (status === 422 && data?.message) {
        return data.message;
    }

    if (status === 409 && data?.message) {
        return data.message;
    }

    if (status >= 500) {
        return 'Une erreur est survenue de notre côté. Réessayez dans un instant.';
    }

    return data?.message ?? "Une erreur inattendue s'est produite.";
}

apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status >= 500 || !error.response) {
            // eslint-disable-next-line no-console
            console.error('[apiClient]', error.response?.status ?? 'network', error.config?.url, error);
        }

        return Promise.reject(error);
    },
);
