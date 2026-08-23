<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useResultsStore } from '@/stores/resultsStore';
import { useUiStore } from '@/stores/uiStore';
import PmCard from '@/components/ui/PmCard.vue';
import PmLoader from '@/components/ui/PmLoader.vue';
import PmButton from '@/components/ui/PmButton.vue';
import QuizReliabilityNotice from '@/components/results/QuizReliabilityNotice.vue';

const route = useRoute();
const router = useRouter();
const resultsStore = useResultsStore();
const uiStore = useUiStore();

const loading = ref(true);
const notFound = ref(false);

async function load() {
    loading.value = true;
    notFound.value = false;
    try {
        await resultsStore.loadShareData(route.params.token);
    } catch {
        notFound.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

const topParties = computed(() => resultsStore.compatibilityScores.slice(0, 3));

// Un lien de partage déjà distribué, pointant vers un résultat désormais
// qualifié de bloquant, doit afficher ce message explicatif plutôt que les
// anciens boutons de partage actifs (copier le lien, réseaux sociaux),
// jamais de fuite du classement de partis ni du profil pour un résultat
// jugé non fiable.
const isBlocked = computed(() => resultsStore.reliability?.state === 'empty' || resultsStore.reliability?.state === 'too_few');

const shareUrl = computed(() => window.location.href);
const shareText = computed(() => `Mon profil politique sur PolitiMatch : ${resultsStore.profileLabel}. Découvre le tien !`);

async function copyLink() {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        uiStore.showToast('Lien copié !', 'success');
    } catch {
        uiStore.showToast('Impossible de copier le lien automatiquement. Copie-le manuellement.', 'error');
    }
}

const shareLinks = computed(() => ({
    twitter: `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText.value)}&url=${encodeURIComponent(shareUrl.value)}`,
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`${shareText.value} ${shareUrl.value}`)}`,
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl.value)}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl.value)}`,
}));
</script>

<template>
    <div v-if="loading" class="flex justify-center py-16">
        <PmLoader label="Chargement du résultat partagé" />
    </div>

    <PmCard v-else-if="notFound">
        <h1 class="text-lg font-semibold text-gray-900">Lien introuvable</h1>
        <p class="mt-2 text-sm text-gray-500">Ce lien de partage n'existe pas ou n'est plus actif.</p>
        <PmButton class="mt-4" @click="router.push('/')">Faire le test</PmButton>
    </PmCard>

    <PmCard v-else-if="isBlocked">
        <QuizReliabilityNotice :state="resultsStore.reliability.state" />
        <PmButton class="mt-4" @click="router.push('/')">Fais le test toi-même</PmButton>
    </PmCard>

    <div v-else class="flex flex-col gap-4">
        <PmCard>
            <p class="text-xs font-medium text-gray-400">Résultat partagé · anonyme, sans donnée personnelle</p>
            <h1 class="mt-2 text-xl font-semibold text-gray-900">{{ resultsStore.profileLabel }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ resultsStore.profileDescription }}</p>

            <ul v-if="topParties.length > 0" class="mt-4 flex flex-col gap-2">
                <li v-for="entry in topParties" :key="entry.party_id" class="flex items-center gap-3">
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                        :style="{ backgroundColor: entry.party?.color_hex ?? '#9ca3af' }"
                        aria-hidden="true"
                    >
                        {{ (entry.party?.abbreviation ?? '?').slice(0, 3) }}
                    </span>
                    <span class="flex-1 text-sm text-gray-700">{{ entry.party?.name ?? 'Parti' }}</span>
                    <span v-if="entry.compatibility_score !== null" class="text-sm font-semibold text-bordeaux-700">
                        {{ Math.round(Number(entry.compatibility_score)) }}%
                    </span>
                </li>
            </ul>
        </PmCard>

        <PmCard>
            <PmButton class="w-full" variant="secondary" @click="copyLink">Copier le lien</PmButton>

            <!-- Vrais logos officiels (tracés SVG Simple Icons, licence libre
                 explicitement prévue pour cet usage), fond aux couleurs de
                 marque officielles de chaque plateforme : seule exception
                 assumée à la palette de l'app, ce sont des logos tiers
                 reconnaissables, pas des éléments du design system
                 PolitiMatch. Icône blanche : contraste maximal sur chacun de
                 ces quatre fonds saturés, vérifié. -->
            <div class="mt-3 grid grid-cols-4 gap-2">
                <a
                    :href="shareLinks.twitter"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center justify-center rounded-xl py-2.5 transition-transform duration-150 hover:scale-105"
                    style="background-color: #000000;"
                    aria-label="Partager sur X"
                >
                    <svg viewBox="0 0 24 24" class="h-4.5 w-4.5 fill-white" aria-hidden="true">
                        <path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z" />
                    </svg>
                </a>
                <a
                    :href="shareLinks.whatsapp"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center justify-center rounded-xl py-2.5 transition-transform duration-150 hover:scale-105"
                    style="background-color: #25D366;"
                    aria-label="Partager sur WhatsApp"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-white" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                    </svg>
                </a>
                <a
                    :href="shareLinks.facebook"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center justify-center rounded-xl py-2.5 transition-transform duration-150 hover:scale-105"
                    style="background-color: #1877F2;"
                    aria-label="Partager sur Facebook"
                >
                    <svg viewBox="0 0 24 24" class="h-4.5 w-4.5 fill-white" aria-hidden="true">
                        <path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z" />
                    </svg>
                </a>
                <a
                    :href="shareLinks.linkedin"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center justify-center rounded-xl py-2.5 transition-transform duration-150 hover:scale-105"
                    style="background-color: #0A66C2;"
                    aria-label="Partager sur LinkedIn"
                >
                    <svg viewBox="0 0 24 24" class="h-4.5 w-4.5 fill-white" aria-hidden="true">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                    </svg>
                </a>
            </div>
        </PmCard>

        <!-- Même route/mécanisme que sur ResultsView.vue (téléchargement
             géré entièrement par le navigateur, Content-Disposition:
             attachment) — quizUuid vient ici du payload de partage
             (ShareController::show, toResultPayload inclut quiz_uuid). -->
        <a
            :href="`/api/results/${resultsStore.quizUuid}/download-image`"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-50 px-6 py-3 text-lg font-medium text-ink transition-[color,background-color,box-shadow,transform] duration-200 ease-out hover:bg-brand-100 active:bg-brand-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Télécharger cette carte
        </a>

        <PmButton size="lg" @click="router.push('/')">Fais le test toi aussi !</PmButton>
    </div>
</template>
