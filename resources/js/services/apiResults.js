import { apiClient } from '@/services/apiClient';

export const apiResults = {
    // session_token : requis côté serveur pour un visiteur anonyme depuis
    // que ResultController::show vérifie la propriété du résultat
    // (QuizAccessService) ; ignoré pour un utilisateur connecté, dont la
    // propriété est déjà vérifiée via le cookie Sanctum.
    show(uuid, sessionToken) {
        return apiClient.get(`/results/${uuid}`, {
            params: { session_token: sessionToken || undefined },
        });
    },
};
