import { apiClient, ensureCsrfCookie } from '@/services/apiClient';

export const apiQuiz = {
    questions() {
        return apiClient.get('/questions');
    },

    // consentVersion : identifiant de la version du texte de consentement
    // RGPD affiché (cf. QuizController::CURRENT_CONSENT_VERSION côté
    // backend, seule source de vérité) — jamais accepté si différent de la
    // version actuellement attendue par le serveur.
    async start(consentVersion) {
        await ensureCsrfCookie();

        return apiClient.post('/quiz/start', {
            consent: true,
            consent_version: consentVersion,
        });
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
