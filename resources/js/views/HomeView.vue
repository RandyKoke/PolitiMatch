<script setup>
import PmButton from '@/components/ui/PmButton.vue';
</script>

<template>
    <section class="relative flex flex-col items-center gap-6 overflow-hidden py-10 text-center">
        <!-- Ce tracé est celui de l'icône "Atomium" d'Anna Weiss (The Noun
             Project, via Wikimedia Commons, licence CC BY 3.0 US, attribution
             requise), une silhouette stylisée (9 sphères + tubes + tripode)
             fidèle aux proportions réelles du monument, pas un dessin
             approximatif. Positionné en débord haut-droite (jamais pile
             derrière le texte central), teinte bordeaux unique de la
             palette, opacité ~17% : contraste texte/fond vérifié par calcul
             (luminance relative WCAG). Toujours placé avant le contenu dans
             le DOM pour rester au niveau d'empilement le plus bas de la
             composition. -->
        <svg
            viewBox="0 0 100 100"
            class="pointer-events-none absolute -top-[45px] -right-[15px] z-0 h-[150px] w-[150px] text-bordeaux-600 opacity-[0.17] sm:-top-[60px] sm:-right-[20px] sm:h-[180px] sm:w-[180px]"
            fill="currentColor"
            aria-hidden="true"
        >
            <path d="m 86.627,63.609 c 0,-3.199 -1.912,-5.945 -4.654,-7.176 l 2.438,-15.589 c 4.08,-1.028 7.102,-4.723 7.102,-9.123 0,-5.196 -4.213,-9.409 -9.41,-9.409 -1.939,0 -3.744,0.589 -5.24,1.596 L 58.48,12.344 c 0.309,-0.906 0.486,-1.875 0.486,-2.886 0,-4.918 -3.986,-8.905 -8.906,-8.905 -4.919,0 -8.905,3.987 -8.905,8.905 0,1.013 0.178,1.981 0.488,2.889 L 23.201,23.951 c -1.51,-1.033 -3.336,-1.638 -5.304,-1.638 -5.196,0 -9.409,4.213 -9.409,9.409 0,4.485 3.14,8.235 7.34,9.178 l 2.394,15.315 c -3.034,1.09 -5.208,3.984 -5.208,7.395 0,2.977 1.655,5.566 4.094,6.902 l -0.051,-0.008 -7.865,28.908 7.433,0.035 4.405,-27.982 c 2.142,-0.041 4.072,-0.934 5.466,-2.359 L 42.38,81.814 c -0.773,1.318 -1.225,2.85 -1.225,4.49 0,2.682 1.191,5.082 3.068,6.715 l -7.09,2.549 v 3.807 H 62.987 V 95.568 L 55.85,93.06 c 1.906,-1.635 3.115,-4.051 3.115,-6.756 0,-1.447 -0.352,-2.807 -0.965,-4.014 L 73.766,69.68 c 1.256,1.037 2.844,1.68 4.576,1.771 l 4.408,27.996 7.434,-0.035 -7.838,-28.809 c 2.541,-1.302 4.281,-3.943 4.281,-6.994 z m -7.863,-7.865 c -2.445,0 -4.633,1.117 -6.076,2.871 L 57.979,49.152 C 57.817,48.958 57.647,48.77 57.469,48.59 L 74.176,36.787 c 1.549,2.419 4.172,4.082 7.197,4.313 L 79.434,55.777 C 79.213,55.76 78.99,55.744 78.764,55.744 z M 74.781,25.816 c -1.307,1.615 -2.088,3.668 -2.088,5.906 0,0.967 0.146,1.899 0.416,2.777 l -0.152,0.107 -15.174,8.154 c -0.564,-3.014 -2.848,-5.414 -5.795,-6.159 l 0.484,-18.578 c 1.922,-0.54 3.572,-1.705 4.732,-3.265 L 74.781,25.816 z M 48.042,18.124 48.644,36.488 C 45.497,37.062 43,39.504 42.359,42.627 L 26.933,34.34 c 0.242,-0.832 0.373,-1.709 0.373,-2.619 0,-2.2 -0.758,-4.222 -2.023,-5.824 L 42.939,14.788 c 1.236,1.65 3.029,2.855 5.103,3.336 z M 18.853,41.083 c 2.868,-0.29 5.354,-1.866 6.874,-4.144 L 42.313,48.658 26.91,58.566 c -1.442,-1.725 -3.608,-2.822 -6.032,-2.822 -0.028,0 -0.056,0.004 -0.085,0.004 l -1.94,-14.665 z m 9.695,20.806 11.431,-6.043 c 0.062,4.621 3.195,8.5 7.454,9.684 l -1.689,12.988 c -0.597,0.332 -1.146,0.732 -1.648,1.189 L 28.035,66.855 c 0.45,-0.992 0.707,-2.088 0.707,-3.246 0,-0.591 -0.071,-1.166 -0.194,-1.72 z M 54.611,78.658 52.904,65.533 c 4.131,-1.146 7.209,-4.828 7.449,-9.268 l 10.729,5.672 c -0.119,0.539 -0.182,1.098 -0.182,1.672 0,1.461 0.406,2.826 1.102,4 l -15.58,12.467 c -0.539,-0.549 -1.149,-1.021 -1.811,-1.418 z" />
        </svg>

        <span class="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl bg-ink font-display text-3xl font-semibold text-brand-500">
            P
            <span class="absolute inset-x-0 bottom-0 h-1" style="background: var(--gradient-tricolore);" />
        </span>

        <div class="relative">
            <h1 class="font-display text-3xl font-semibold text-ink">PolitiMatch</h1>
            <!-- Accroche : Fraunces italique, jamais une 3e police, juste un
                 traitement différent de la même police de titres, avec un
                 interlignage plus aéré que le paragraphe d'explication qui
                 suit. -->
            <p class="mt-3 text-balance font-display text-xl leading-relaxed text-ink italic">
                Tes idées politiques ont une place. On t'aide à la trouver.
            </p>
            <p class="mt-3 text-stone-600">
                Réponds honnêtement, découvre où tu te situes, et trouve les partis qui partagent tes idées. En
                toute neutralité.
            </p>
        </div>

        <div class="relative flex w-full flex-col gap-3">
            <PmButton size="lg" class="pm-shimmer-cta" @click="$router.push('/start')">Commencer</PmButton>
            <router-link to="/intro" class="text-sm text-bordeaux-600 hover:text-bordeaux-700">En savoir plus</router-link>
        </div>

        <!--
            Accès direct compte/connexion depuis l'accueil, sans passer par
            /start : demande explicite de l'utilisateur (l'un ou l'autre doit
            être atteignable "sur cette page"). Le gros bouton "Commencer"
            reste le seul point d'entrée vers le choix invité/compte de
            /start — ces deux liens ne le dupliquent pas, ils offrent un
            raccourci pour qui sait déjà qu'il veut créer un compte ou se
            connecter plutôt que de passer par l'écran intermédiaire.
        -->
        <p class="relative text-sm text-gray-500">
            <router-link to="/auth/register" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">Créer un compte</router-link>
            ou
            <router-link to="/auth/login" class="font-medium text-bordeaux-600 hover:text-bordeaux-700">se connecter</router-link>
        </p>
    </section>
</template>
