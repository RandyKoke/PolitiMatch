import { apiClient } from '@/services/apiClient';

export const apiResults = {
    show(uuid) {
        return apiClient.get(`/results/${uuid}`);
    },
};
