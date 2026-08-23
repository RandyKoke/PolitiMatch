import { apiClient } from '@/services/apiClient';

export const apiCompare = {
    /**
     * @param {{quizResultUuid: string, sessionToken?: string|null, themeId?: number|null, axeIdeologique?: string|null}} params
     */
    index({ quizResultUuid, sessionToken, themeId, axeIdeologique }) {
        return apiClient.get('/compare', {
            params: {
                quiz_result_uuid: quizResultUuid,
                session_token: sessionToken || undefined,
                theme_id: themeId || undefined,
                axe_ideologique: axeIdeologique || undefined,
            },
        });
    },
};
