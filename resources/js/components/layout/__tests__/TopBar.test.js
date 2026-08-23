import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import TopBar from '@/components/layout/TopBar.vue';
import { useAuthStore } from '@/stores/authStore';
import { apiAuth } from '@/services/apiAuth';

const push = vi.fn();

vi.mock('vue-router', () => ({
    useRouter: () => ({ push }),
}));

vi.mock('@/services/apiAuth', () => ({
    apiAuth: {
        me: vi.fn(),
        register: vi.fn(),
        login: vi.fn(),
        logout: vi.fn(),
        forgotPassword: vi.fn(),
        resetPassword: vi.fn(),
        socialRedirectUrl: vi.fn(),
    },
}));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

/**
 * Après déconnexion, /dashboard ne doit jamais rester affiché : un clic sur
 * "Se déconnecter" qui ne déclenche aucune navigation laisserait le garde
 * `requiresAuth` du routeur sans jamais l'occasion de s'exécuter (aucune
 * transition = aucun appel à router.beforeEach). Ce test vérifie
 * l'intégration réelle du bouton, pas seulement authStore.logout()
 * lui-même (déjà couvert par authStore.test.js).
 */
describe('TopBar : déconnexion', () => {
    it('redirige vers l\'accueil après un clic sur "Se déconnecter"', async () => {
        apiAuth.logout.mockResolvedValue({ data: { message: 'Déconnecté.' } });
        const authStore = useAuthStore();
        authStore.isAuthenticated = true;
        authStore.user = { id: 1, username: 'randy' };

        const wrapper = mount(TopBar);
        await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
        const logoutButton = wrapper.findAll('button').find((b) => b.text() === 'Se déconnecter');
        await logoutButton.trigger('click');
        await flushPromises();

        expect(push).toHaveBeenCalledWith({ name: 'home' });
    });

    /**
     * L'état d'authentification doit être réinitialisé AVANT la redirection
     * (pas de flash intermédiaire, cf. demande explicite) : vérifié ici en
     * confirmant l'ordre d'appel plutôt qu'en devinant un timing.
     */
    it('réinitialise isAuthenticated avant d\'appeler router.push, pas après', async () => {
        const callOrder = [];
        apiAuth.logout.mockImplementation(() => Promise.resolve({ data: {} }));
        push.mockImplementation(() => {
            callOrder.push(`push:isAuthenticated=${authStore.isAuthenticated}`);
        });
        const authStore = useAuthStore();
        authStore.isAuthenticated = true;
        authStore.user = { id: 1, username: 'randy' };

        const wrapper = mount(TopBar);
        await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
        const logoutButton = wrapper.findAll('button').find((b) => b.text() === 'Se déconnecter');
        await logoutButton.trigger('click');
        await flushPromises();

        expect(callOrder).toEqual(['push:isAuthenticated=false']);
    });

    it('affiche "Se connecter" (pas le menu de compte) une fois déconnecté', async () => {
        apiAuth.logout.mockResolvedValue({ data: {} });
        const authStore = useAuthStore();
        authStore.isAuthenticated = true;
        authStore.user = { id: 1, username: 'randy' };

        const wrapper = mount(TopBar);
        await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
        const logoutButton = wrapper.findAll('button').find((b) => b.text() === 'Se déconnecter');
        await logoutButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Se connecter');
        expect(wrapper.text()).not.toContain('Se déconnecter');
    });
});

/**
 * Le menu du compte doit réagir à Échap et au clic en dehors de lui : sans
 * ça, il resterait ouvert indéfiniment, bloquant au passage les clics
 * destinés au reste de la page. Montage avec
 * attachTo : document.body nécessaire ici, contrairement aux autres tests de
 * ce fichier, puisque les écouteurs testés sont posés sur `document` et
 * doivent recevoir de vrais événements DOM qui bullent jusque là.
 */
describe('TopBar : fermeture du menu du compte', () => {
    let wrapper;

    beforeEach(() => {
        const authStore = useAuthStore();
        authStore.isAuthenticated = true;
        authStore.user = { id: 1, username: 'randy' };
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
    });

    it('se ferme et rend le focus au bouton déclencheur à la pression d\'Échap', async () => {
        wrapper = mount(TopBar, { attachTo: document.body });
        const menuButton = wrapper.find('button[aria-haspopup="menu"]');
        await menuButton.trigger('click');
        expect(wrapper.find('[role="menu"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        expect(document.activeElement).toBe(menuButton.element);
    });

    it('se ferme au clic en dehors, sans jamais bloquer le clic destiné à un autre élément', async () => {
        wrapper = mount(TopBar, { attachTo: document.body });
        const outsideButton = document.createElement('button');
        const outsideHandler = vi.fn();
        outsideButton.textContent = 'Autre bouton de la page';
        outsideButton.addEventListener('click', outsideHandler);
        document.body.appendChild(outsideButton);

        await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
        expect(wrapper.find('[role="menu"]').exists()).toBe(true);

        outsideButton.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        expect(outsideHandler).toHaveBeenCalledTimes(1);

        document.body.removeChild(outsideButton);
    });

    it('ne se referme pas sur le clic qui vient précisément de l\'ouvrir', async () => {
        wrapper = mount(TopBar, { attachTo: document.body });
        const menuButton = wrapper.find('button[aria-haspopup="menu"]');

        await menuButton.trigger('click');

        expect(wrapper.find('[role="menu"]').exists()).toBe(true);
    });
});
