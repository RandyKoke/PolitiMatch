import { apiClient, ensureCsrfCookie } from '@/services/apiClient';

export const apiQuiz = {
    questions() {
        return apiClient.get('/questions');
    },

    async start() {
        await ensureCsrfCookie();

        return apiClient.post('/quiz/start');
    },

    /**
     * Reprise après rechargement de page : renvoie le statut, les réponses
     * déjà données et le questionnaire en un seul appel.
     */
    state(quizResultUuid, sessionToken) {
        return apiClient.get(`/quiz/${quizResultUuid}/state`, {
            params: { session_token: sessionToken || undefined },
        });
    },

    async submitAnswer(payload) {
        await ensureCsrfCookie();

        return apiClient.post('/answers', payload);
    },

    async complete(payload) {
        await ensureCsrfCookie();

        return apiClient.post('/quiz/complete', payload);
    },

    async retry(quizResultUuid, payload) {
        await ensureCsrfCookie();

        return apiClient.post(`/quiz/${quizResultUuid}/retry`, payload);
    },

    /**
     * Reprise ciblée : réouvre un résultat "too_few" (Completed → Pending)
     * pour permettre de répondre spécifiquement aux questions passées.
     */
    async resumeSkipped(quizResultUuid, payload) {
        await ensureCsrfCookie();

        return apiClient.post(`/quiz/${quizResultUuid}/resume-skipped`, payload);
    },
};
