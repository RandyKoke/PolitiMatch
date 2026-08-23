import { apiClient } from '@/services/apiClient';

export const apiParties = {
    index() {
        return apiClient.get('/parties');
    },

    // quizUuid optionnel : preuve d'un lien historique réel (ResultPartyScore)
    // permettant de consulter la fiche d'un parti désactivé depuis un ancien
    // résultat qui le référence encore (cf. PartyController::show).
    show(id, quizUuid = null) {
        return apiClient.get(`/parties/${id}`, quizUuid ? { params: { quiz: quizUuid } } : {});
    },
};
