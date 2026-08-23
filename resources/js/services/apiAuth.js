import { apiClient, ensureCsrfCookie } from '@/services/apiClient';

// Le session_token du Guest Flow voyage en en-tête X-Session-Token sur les
// routes d'auth (register/login), pas dans le corps — décision backend
// documentée dans AuthController. Les routes du quiz, elles, l'attendent
// dans le corps de la requête (cf. apiQuiz.js) : transport volontairement
// différent selon la route, pas une incohérence à corriger côté front.
function sessionTokenHeader(sessionToken) {
    return sessionToken ? { headers: { 'X-Session-Token': sessionToken } } : {};
}

export const apiAuth = {
    async register(payload, sessionToken) {
        await ensureCsrfCookie();

        return apiClient.post('/auth/register', payload, sessionTokenHeader(sessionToken));
    },

    async login(payload, sessionToken) {
        await ensureCsrfCookie();

        return apiClient.post('/auth/login', payload, sessionTokenHeader(sessionToken));
    },

    logout() {
        return apiClient.post('/auth/logout');
    },

    me() {
        return apiClient.get('/auth/me');
    },

    async forgotPassword(payload) {
        await ensureCsrfCookie();

        return apiClient.post('/auth/forgot-password', payload);
    },

    async resetPassword(payload) {
        await ensureCsrfCookie();

        return apiClient.post('/auth/reset-password', payload);
    },

    // Navigation plein-page (Socialite), pas un appel JSON : la route vit
    // dans web.php, hors du préfixe /api (cf. routes/web.php).
    socialRedirectUrl(provider) {
        return `/auth/${provider}/redirect`;
    },
};
