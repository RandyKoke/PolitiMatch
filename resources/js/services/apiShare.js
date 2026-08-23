import { apiClient, ensureCsrfCookie } from '@/services/apiClient';

export const apiShare = {
    async create(quizResultUuid, sessionToken) {
        await ensureCsrfCookie();

        return apiClient.post(`/results/${quizResultUuid}/share`, {
            session_token: sessionToken || undefined,
        });
    },

    // Entièrement public : jamais de session_token ni de cookie d'auth
    // requis pour lire un résultat partagé (cf. ShareController::show).
    show(token) {
        return apiClient.get(`/share/${token}`);
    },
};
