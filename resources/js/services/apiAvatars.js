import { apiClient } from '@/services/apiClient';

export const apiAvatars = {
    // Public, pas de session_token ni de cookie nécessaire : utilisable
    // avant même la création du compte (étape de choix dans RegisterView).
    suggestions() {
        return apiClient.get('/avatars/suggestions');
    },
};
