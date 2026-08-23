import { defineStore } from 'pinia';

// État transverse purement UI (toasts, spinner global, modales) : ne
// contient jamais de donnée métier (ça, c'est authStore/quizStore/
// resultsStore) — évite qu'un store devienne un fourre-tout.
export const useUiStore = defineStore('ui', {
    state: () => ({
        globalLoading: false,
        toasts: [], // { id, message, variant, timeoutId }
        modals: {}, // { [nom]: boolean } — ouverture/fermeture par nom logique
    }),

    actions: {
        showToast(message, variant = 'info', durationMs = 4000) {
            const id = crypto.randomUUID();
            const timeoutId = setTimeout(() => this.hideToast(id), durationMs);
            this.toasts.push({ id, message, variant, timeoutId });

            return id;
        },

        hideToast(id) {
            const index = this.toasts.findIndex((toast) => toast.id === id);
            if (index === -1) {
                return;
            }
            clearTimeout(this.toasts[index].timeoutId);
            this.toasts.splice(index, 1);
        },

        openModal(name) {
            this.modals[name] = true;
        },

        closeModal(name) {
            this.modals[name] = false;
        },
    },
});
