import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/App.vue';
import { router, resolveNavigation } from '@/router';
import { useAuthStore } from '@/stores/authStore';

const app = createApp(App);

app.use(createPinia());
app.use(router);

// Réconcilie l'état Pinia avec le cookie de session Sanctum réel avant le
// premier rendu : évite un flash "non connecté" pour un utilisateur qui
// l'est déjà. fetchMe() est mémoïsé (cf. authStore) : si le garde de route déclenche
// aussi cet appel pendant la résolution de la navigation initiale, un seul
// GET /auth/me part réellement.
useAuthStore().fetchMe();

router.isReady().then(() => app.mount('#app'));

/**
 * Cas distinct d'une navigation SPA classique (retour arrière via
 * popstate, déjà géré par router.beforeEach) : la restauration bfcache du
 * navigateur (`event.persisted === true`) restitue la page exactement comme
 * elle était figée en mémoire, SANS ré-exécuter ce fichier ni redéclencher
 * la moindre garde de route — le scénario concret que ce garde-fou couvre
 * est un utilisateur qui se déconnecte, quitte l'onglet ou navigue en
 * dehors du SPA, puis revient en arrière : le navigateur peut restituer la
 * page /dashboard depuis son cache local avant que quoi que ce soit ne
 * réagisse côté JS. `pageshow` se déclenche aussi sur un chargement normal
 * (persisted=false dans ce cas, ignoré ici) — seul le cas bfcache force une
 * revérification. Réutilise `resolveNavigation` telle quelle (pas une copie
 * de sa logique) : la route actuelle est réévaluée exactement comme une
 * navigation fraîche le ferait, cohérent avec le garde `requiresAuth`.
 */
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    const authStore = useAuthStore();
    authStore.authChecked = false;

    resolveNavigation(router.currentRoute.value).then((result) => {
        if (result !== true) {
            router.push(result);
        }
    });
});
