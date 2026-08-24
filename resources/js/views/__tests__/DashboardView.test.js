import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import DashboardView from '@/views/DashboardView.vue';
import { useAuthStore } from '@/stores/authStore';
import { useQuizStore } from '@/stores/quizStore';
import { apiUsers } from '@/services/apiUsers';
import { apiQuiz } from '@/services/apiQuiz';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock }),
}));

vi.mock('@/services/apiUsers', () => ({ apiUsers: { results: vi.fn(), deleteResult: vi.fn() } }));
vi.mock('@/services/apiQuiz', () => ({ apiQuiz: { start: vi.fn() } }));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    localStorage.clear();
    const authStore = useAuthStore();
    authStore.user = { id: 1, username: 'randy', email: 'randy@example.com', avatar_seed: 'seed' };
});

/**
 * pending/computing restent en badge neutre ("patiente encore"), failed
 * distingué en rouge sobre (variant="disagree" de PmTag, déjà dans la
 * palette du comparateur) puisque c'est le seul statut réellement
 * actionnable par l'utilisateur.
 */
describe('DashboardView : distinction visuelle des statuts de l\'historique', () => {
    it('affiche le statut "Échec du calcul" avec le variant rouge (disagree), distinct du neutre', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'failed-uuid', status: 'failed', profile_label: null, completed_at: null, created_at: '2026-01-01T00:00:00Z' },
                    { uuid: 'pending-uuid', status: 'pending', profile_label: null, completed_at: null, created_at: '2026-01-02T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView);
        await flushPromises();

        const tags = wrapper.findAll('.rounded-full.px-2\\.5');
        const failedTag = tags.find((t) => t.text() === 'Échec du calcul');
        const pendingTag = tags.find((t) => t.text() === 'En cours');

        expect(failedTag).toBeDefined();
        expect(pendingTag).toBeDefined();
        expect(failedTag.classes()).toContain('text-vote-disagree');
        expect(pendingTag.classes()).toContain('text-vote-neutral');
        expect(failedTag.classes()).not.toContain('text-vote-neutral');
    });

    it('n\'affiche aucun badge pour un quiz complété (le lien "Voir le résultat" suffit)', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'done-uuid', status: 'completed', profile_label: 'Progressiste équilibré', completed_at: '2026-01-01T00:00:00Z', created_at: '2026-01-01T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView);
        await flushPromises();

        expect(wrapper.text()).toContain('Progressiste équilibré');
        expect(wrapper.findAll('.rounded-full.px-2\\.5')).toHaveLength(0);
    });
});

describe('DashboardView : libellé du bouton de démarrage selon l\'historique', () => {
    it('affiche "Commencer le quiz" pour un compte sans aucun historique', async () => {
        apiUsers.results.mockResolvedValue({ data: { quiz_results: [] } });

        const wrapper = mount(DashboardView);
        await flushPromises();

        expect(wrapper.text()).toContain('Commencer le quiz');
        expect(wrapper.text()).not.toContain('Refaire le quiz');
    });

    it('affiche "Refaire le quiz" dès qu\'un historique existe', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'done-uuid', status: 'completed', profile_label: 'Progressiste équilibré', completed_at: '2026-01-01T00:00:00Z', created_at: '2026-01-01T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView);
        await flushPromises();

        expect(wrapper.text()).toContain('Refaire le quiz');
        expect(wrapper.text()).not.toContain('Commencer le quiz');
    });
});

describe('DashboardView : reprise d\'un quiz déjà en cours', () => {
    it('propose "Reprendre le quiz" et navigue vers /quiz sans créer un nouveau QuizResult', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'pending-uuid', status: 'pending', profile_label: null, completed_at: null, created_at: '2026-01-02T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView);
        await flushPromises();

        expect(wrapper.text()).toContain('Reprendre le quiz');

        await wrapper.find('button.mt-4.w-full').trigger('click');
        await flushPromises();

        expect(apiQuiz.start).not.toHaveBeenCalled();
        expect(pushMock).toHaveBeenCalledWith('/quiz');
        expect(useQuizStore().currentQuizUuid).toBe('pending-uuid');
    });

    it('rend actif le quiz correspondant en cliquant sur une entrée d\'historique non terminée', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'computing-uuid', status: 'computing', profile_label: null, completed_at: null, created_at: '2026-01-03T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView);
        await flushPromises();

        const entryButtons = wrapper.findAll('li button');
        expect(entryButtons).toHaveLength(2); // le libellé de l'entrée + le bouton de suppression

        await entryButtons[0].trigger('click');

        expect(pushMock).toHaveBeenCalledWith('/results/computing-uuid');
        expect(useQuizStore().currentQuizUuid).toBe('computing-uuid');
    });

    it('ne touche pas currentQuizUuid en cliquant sur un résultat déjà terminé', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'done-uuid', status: 'completed', profile_label: 'Progressiste équilibré', completed_at: '2026-01-01T00:00:00Z', created_at: '2026-01-01T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView);
        await flushPromises();

        await wrapper.find('li button').trigger('click');

        expect(pushMock).toHaveBeenCalledWith('/results/done-uuid');
        expect(useQuizStore().currentQuizUuid).toBeNull();
    });
});

// PmModal se rend via <Teleport to="body"> : son contenu vit hors de l'arbre
// que wrapper.find()/wrapper.text() explorent, il faut interroger le vrai
// document pour l'atteindre (cf. StartAccessView.test.js pour la même
// contrainte sur ce composant).
function findByText(selector, text) {
    return Array.from(document.querySelectorAll(selector)).find((el) => el.textContent.trim() === text);
}

describe('DashboardView : suppression d\'un résultat', () => {
    it('demande confirmation avant de supprimer, et n\'appelle pas l\'API en cas d\'annulation', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'done-uuid', status: 'completed', profile_label: 'Progressiste équilibré', completed_at: '2026-01-01T00:00:00Z', created_at: '2026-01-01T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView, { attachTo: document.body });
        await flushPromises();

        await wrapper.find('li button[aria-label="Supprimer ce résultat"]').trigger('click');
        await flushPromises();

        expect(document.body.textContent).toContain('Supprimer ce résultat ?');
        expect(document.body.textContent).toContain('lien de partage');

        await findByText('button', 'Annuler').click();
        await flushPromises();

        expect(apiUsers.deleteResult).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Progressiste équilibré');

        wrapper.unmount();
    });

    it('avertit de la perte des réponses pour un quiz non terminé, distinct du message pour un résultat complet', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'pending-uuid', status: 'pending', profile_label: null, completed_at: null, created_at: '2026-01-02T00:00:00Z' },
                ],
            },
        });

        const wrapper = mount(DashboardView, { attachTo: document.body });
        await flushPromises();

        await wrapper.find('li button[aria-label="Supprimer ce résultat"]').trigger('click');
        await flushPromises();

        expect(document.body.textContent).toContain('réponses déjà données');
        expect(document.body.textContent).not.toContain('lien de partage');

        wrapper.unmount();
    });

    it('supprime réellement le résultat après confirmation et le retire de la liste affichée', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'done-uuid', status: 'completed', profile_label: 'Progressiste équilibré', completed_at: '2026-01-01T00:00:00Z', created_at: '2026-01-01T00:00:00Z' },
                ],
            },
        });
        apiUsers.deleteResult.mockResolvedValue({});

        const wrapper = mount(DashboardView, { attachTo: document.body });
        await flushPromises();

        await wrapper.find('li button[aria-label="Supprimer ce résultat"]').trigger('click');
        await flushPromises();

        await findByText('button', 'Supprimer').click();
        await flushPromises();

        expect(apiUsers.deleteResult).toHaveBeenCalledWith('done-uuid');
        expect(wrapper.text()).not.toContain('Progressiste équilibré');
        expect(wrapper.text()).toContain('Tu n\'as pas encore fait de quiz');

        wrapper.unmount();
    });

    it('vide currentQuizUuid si le quiz supprimé était le quiz actif', async () => {
        apiUsers.results.mockResolvedValue({
            data: {
                quiz_results: [
                    { uuid: 'pending-uuid', status: 'pending', profile_label: null, completed_at: null, created_at: '2026-01-02T00:00:00Z' },
                ],
            },
        });
        apiUsers.deleteResult.mockResolvedValue({});

        const wrapper = mount(DashboardView, { attachTo: document.body });
        await flushPromises();
        useQuizStore().setQuizUuid('pending-uuid');

        await wrapper.find('li button[aria-label="Supprimer ce résultat"]').trigger('click');
        await flushPromises();
        await findByText('button', 'Supprimer').click();
        await flushPromises();

        expect(useQuizStore().currentQuizUuid).toBeNull();

        wrapper.unmount();
    });
});
