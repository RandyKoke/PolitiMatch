import { apiClient } from '@/services/apiClient';

export const apiUsers = {
    // Historique des quiz de l'utilisateur connecté (cahier des charges,
    // Module Historique) — nécessite une session Sanctum active.
    results() {
        return apiClient.get('/user/results');
    },

    // Changement d'avatar depuis le tableau de bord — nécessite une session
    // Sanctum active (auth:sanctum côté route).
    updateAvatar(avatarSeed) {
        return apiClient.patch('/user/avatar', { avatar_seed: avatarSeed });
    },
};
