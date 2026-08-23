<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Chart as ChartJS, LinearScale, PointElement, Tooltip } from 'chart.js';
import { Scatter } from 'vue-chartjs';

ChartJS.register(LinearScale, PointElement, Tooltip);

const props = defineProps({
    axisX: { type: [Number, String, null], default: null },
    axisY: { type: [Number, String, null], default: null },
    // [{ id, name, abbreviation, color_hex, ideological_x, ideological_y }] —
    // cf. GET /api/parties (PartyRepository::listActive()) ou
    // party_scores[].party (déjà présent dans la réponse de /results et
    // /share, la relation Party étant sérialisée en entier).
    parties: { type: Array, default: () => [] },
});

const x = computed(() => (props.axisX === null || props.axisX === undefined ? null : Number(props.axisX)));
const y = computed(() => (props.axisY === null || props.axisY === undefined ? null : Number(props.axisY)));
const hasBoth = computed(() => x.value !== null && y.value !== null);

/**
 * Un canvas Chart.js n'expose par défaut aucun contenu à un lecteur
 * d'écran (ni les points, ni leur position) : sans
 * texte alternatif, un utilisateur non-voyant perd entièrement
 * l'information portée par ce graphique, contrairement au reste de la page
 * (résultat texte, tableau des scores). Réutilise exactement les fonctions
 * axisXDescriptor/axisYDescriptor déjà utilisées pour les infobulles
 * (définies plus bas, hissées par JS comme toute déclaration de fonction) :
 * même vocabulaire, jamais une deuxième formulation qui pourrait diverger.
 */
const chartAriaLabel = computed(() => (hasBoth.value
    ? `Graphique de positionnement politique. Ta position : ${axisXDescriptor(x.value)}, et ${axisYDescriptor(y.value)}.`
    : 'Graphique de positionnement politique des partis sur les axes économique et sociétal.'));

// party_positions couvre toutes les questions actives par construction
// (contrainte unique en base) : une position idéologique de parti n'est
// donc quasiment jamais null en pratique (contrairement à l'utilisateur, qui
// peut passer toutes les questions d'un axe) — filtré ici uniquement par
// défense, pas parce que c'est un cas attendu.
const partyPoints = computed(() => props.parties
    .filter((p) => p.ideological_x !== null && p.ideological_x !== undefined && p.ideological_y !== null && p.ideological_y !== undefined)
    .map((p) => ({
        name: p.name,
        // Abbréviation utilisée pour l'étiquette sur le graphique (courte,
        // tient même à côté d'un point sur mobile) ; le nom complet reste
        // utilisé dans la légende et l'infobulle. Repli défensif si jamais
        // l'abréviation manquait.
        abbreviation: p.abbreviation ?? p.name.slice(0, 4).toUpperCase(),
        x: Number(p.ideological_x),
        y: Number(p.ideological_y),
        color: p.color_hex ?? '#9ca3af',
    })));

// Les partis peuvent quasiment toujours être positionnés (voir plus haut),
// même quand l'utilisateur n'a pas assez répondu pour ses deux propres axes
// — le graphique reste donc utile (comparer les partis entre eux) même dans
// ce cas, plutôt que de tout masquer derrière la dégradation "un seul axe"
// qui ne concerne, elle, que la position de l'utilisateur.
const showScatter = computed(() => partyPoints.value.length > 0 || hasBoth.value);
const hasNoDataAtAll = computed(() => !showScatter.value && x.value === null && y.value === null);

// Couleur bordeaux (accent secondaire de la direction artistique, jamais
// l'or : l'or est trop proche, en famille de teinte, des couleurs de DéFI
// et Les Engagés pour rester un repère fiable sur ce graphique précis).
// Volontairement neutre et non partisane, vérifiée distincte des 6 couleurs
// de partis en base : "Toi" reste ainsi identifiable sans ambiguïté avec un
// point de parti.
const USER_COLOR = '#62273b';

const chartData = computed(() => ({
    datasets: [
        ...partyPoints.value.map((party) => ({
            label: party.name,
            labelShort: party.abbreviation,
            isUser: false,
            data: [{ x: party.x, y: party.y }],
            backgroundColor: party.color,
            borderColor: '#ffffff',
            borderWidth: 1,
            pointStyle: 'circle',
            pointRadius: 6,
            pointHoverRadius: 8,
            // Un parti (ou l'utilisateur, ci-dessous) qui répond au maximum
            // sur toutes les questions d'un axe se retrouve exactement au
            // bord de l'échelle (-1 ou 1) : sans ceci, Chart.js rogne la
            // moitié du marqueur au ras de la zone de tracé. Combiné à la
            // marge ajoutée dans layout.padding, le marqueur reste entier.
            clip: false,
        })),
        // Le point de l'utilisateur, avec une forme (triangle) et une taille
        // différentes des partis (cercles) — pas seulement une couleur
        // différente — pour rester identifiable même pour un daltonien ou en
        // cas de chevauchement avec un parti. Note : le pointStyle 'star' de
        // Chart.js 4.4 ne se dessine pas du tout ici (marqueur invisible y
        // compris pour des positions non extrêmes) ; 'triangle' reste une
        // forme tout aussi distincte et fonctionne correctement.
        ...(hasBoth.value ? [{
            label: 'Toi',
            labelShort: 'Toi',
            isUser: true,
            data: [{ x: x.value, y: y.value }],
            backgroundColor: USER_COLOR,
            borderColor: '#ffffff',
            borderWidth: 2,
            pointStyle: 'triangle',
            // Nettement plus grand que les partis (rayon 6) : "Toi" doit
            // sauter aux yeux en premier, pas se fondre dans le nuage —
            // combiné au halo pulsant (userHaloPlugin ci-dessous).
            pointRadius: 14,
            pointHoverRadius: 16,
            clip: false,
        }] : []),
    ],
}));

// Formulations en langage courant pour l'infobulle (plutôt que d'obliger
// l'utilisateur à interpréter lui-même une coordonnée -1..1). Même seuil
// (0.15) et même vocabulaire que axisQualifier() dans PartyDetailView.vue,
// pour qu'un utilisateur comparant sa position à celle d'un parti lise le
// même registre des deux côtés de l'app.
function axisXDescriptor(value) {
    const numeric = Number(value);
    if (numeric < -0.15) return 'plutôt libéral sur le plan économique';
    if (numeric > 0.15) return 'plutôt interventionniste sur le plan économique';
    return 'modéré sur le plan économique';
}

function axisYDescriptor(value) {
    const numeric = Number(value);
    if (numeric < -0.15) return 'plutôt conservateur sur le plan sociétal';
    if (numeric > 0.15) return 'plutôt progressiste sur le plan sociétal';
    return 'modéré sur le plan sociétal';
}

// Fond de cadran très légèrement teinté : permet de reconnaître sa "famille"
// politique en un coup d'œil, sans lire les axes. Dessiné en beforeDraw
// (donc sous la grille, sous
// les étiquettes de cadran, sous les points) via un damier diagonal
// noir/bordeaux plutôt que 4 teintes différentes : chaque couleur de marque
// apparaît une fois côté "libéral" et une fois côté "interventionniste", une
// fois côté "progressiste" et une fois côté "conservateur" — aucune des deux
// teintes n'est donc associée à une direction politique précise (neutralité
// vérifiée, pas seulement esthétique). Opacité 6% choisie après vérification
// : le texte de cadran (stone-600 plein) garde 6.2-6.9:1 de contraste sur ces
// fonds teintés (vs 7.25:1 sur le fond uni) — largement au-dessus du seuil
// AA (4.5:1), la différence reste donc purement visuelle, jamais fonctionnelle.
const quadrantFillPlugin = {
    id: 'quadrantFill',
    beforeDraw(chart) {
        const { ctx, chartArea, scales } = chart;
        if (!chartArea || !scales.x || !scales.y) return;

        const zeroX = scales.x.getPixelForValue(0);
        const zeroY = scales.y.getPixelForValue(0);
        const ink = 'rgba(26, 26, 26, 0.06)';
        const bordeaux = 'rgba(98, 39, 59, 0.06)';

        ctx.save();
        ctx.fillStyle = ink; // Libéral + Progressiste (haut-gauche)
        ctx.fillRect(chartArea.left, chartArea.top, zeroX - chartArea.left, zeroY - chartArea.top);
        ctx.fillStyle = bordeaux; // Interventionniste + Progressiste (haut-droit)
        ctx.fillRect(zeroX, chartArea.top, chartArea.right - zeroX, zeroY - chartArea.top);
        ctx.fillStyle = bordeaux; // Libéral + Conservateur (bas-gauche)
        ctx.fillRect(chartArea.left, zeroY, zeroX - chartArea.left, chartArea.bottom - zeroY);
        ctx.fillStyle = ink; // Interventionniste + Conservateur (bas-droit)
        ctx.fillRect(zeroX, zeroY, chartArea.right - zeroX, chartArea.bottom - zeroY);
        ctx.restore();
    },
};

// Étiquettes de cadran dans les 4 coins : évite à l'utilisateur de devoir
// déduire lui-même le sens du croisement des deux axes. Dessinées avant les
// points (beforeDatasetsDraw) pour ne jamais passer par-dessus un marqueur.
// Enregistré localement via la prop `:plugins` du composant (et non
// ChartJS.register global) pour ne pas affecter d'autres graphiques Chart.js
// qui seraient ajoutés ailleurs dans l'app à l'avenir.
const quadrantLabelsPlugin = {
    id: 'quadrantLabels',
    beforeDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        const pad = 8;
        // Sur mobile, le graphique est étroit : on ne dessine une étiquette
        // que si elle tient dans la moitié de la largeur, plutôt que de la
        // laisser déborder ou chevaucher le cadran voisin.
        const maxWidth = (chartArea.right - chartArea.left) / 2 - pad * 2;

        ctx.save();
        ctx.font = '10px system-ui, -apple-system, sans-serif';
        // Gris chaud plein (stone-600), pas semi-transparent : une version
        // semi-transparente (rgba(...,0.55)) ne ferait que 2.1:1 de contraste
        // sur le fond clair, sous le seuil AA (4.5:1). Cette couleur pleine
        // atteint 7.25:1.
        ctx.fillStyle = '#57534e';
        ctx.textBaseline = 'alphabetic';

        const draw = (text, align, drawX, drawY) => {
            if (ctx.measureText(text).width > maxWidth) return;
            ctx.textAlign = align;
            ctx.fillText(text, drawX, drawY);
        };

        draw('Libéral + Progressiste', 'left', chartArea.left + pad, chartArea.top + pad + 8);
        draw('Interventionniste + Progressiste', 'right', chartArea.right - pad, chartArea.top + pad + 8);
        draw('Libéral + Conservateur', 'left', chartArea.left + pad, chartArea.bottom - pad);
        draw('Interventionniste + Conservateur', 'right', chartArea.right - pad, chartArea.bottom - pad);

        ctx.restore();
    },
};

// Étiquettes courtes (abréviation du parti, ou "Toi") directement à côté de
// chaque point, en plus de la légende complète sous le graphique — les deux
// approches sont complémentaires (identification rapide sur le graphique +
// nom complet et couleur dans la légende). L'évitement de chevauchement
// reste volontairement simple (5 positions candidates, on prend la première
// qui ne recouvre aucune étiquette déjà placée) : avec au plus 7 points
// (6 partis + l'utilisateur), une heuristique légère suffit très largement.
const pointLabelsPlugin = {
    id: 'pointLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const placed = [];
        // Un point à valeur extrême (-1 ou 1 pile, ex. un utilisateur qui
        // répond au maximum sur tout un axe) se retrouve au ras du canvas :
        // les candidats de position sont aussi filtrés par ces bornes pour
        // qu'une étiquette ne soit jamais tronquée hors du visible.
        const canvasBounds = { left: 2, right: chart.width - 2, top: 2, bottom: chart.height - 2 };
        ctx.save();

        chart.data.datasets.forEach((dataset, i) => {
            const meta = chart.getDatasetMeta(i);
            if (meta.hidden) return;
            const point = meta.data[0];
            const text = dataset.labelShort;
            if (!point || !text) return;

            ctx.font = dataset.isUser ? 'bold 12px system-ui, -apple-system, sans-serif' : '11px system-ui, -apple-system, sans-serif';
            const textWidth = ctx.measureText(text).width;
            const textHeight = 12;
            // Rayon réel du marqueur (+ contour) : le décalage doit s'appuyer
            // dessus plutôt qu'une valeur fixe, sinon l'étiquette du point
            // "Toi" (rayon 11 + contour 2 = 13) chevauche le marqueur
            // lui-même et son halo blanc le recouvre entièrement, en
            // particulier pour un profil aux positions extrêmes (±1 pile sur
            // les deux axes).
            const gap = 4;
            const clearance = (dataset.pointRadius ?? 6) + (dataset.borderWidth ?? 0) + gap;
            const candidates = [
                { dx: clearance, dy: -clearance * 0.6 },
                { dx: clearance, dy: clearance + textHeight },
                { dx: -clearance - textWidth, dy: -clearance * 0.6 },
                { dx: -clearance - textWidth, dy: clearance + textHeight },
                { dx: clearance, dy: -clearance - textHeight - gap },
            ];

            const boxFor = (candidate) => ({
                left: point.x + candidate.dx - 2,
                right: point.x + candidate.dx + textWidth + 2,
                top: point.y + candidate.dy - textHeight,
                bottom: point.y + candidate.dy + 2,
            });
            const isInBounds = (box) => box.left >= canvasBounds.left && box.right <= canvasBounds.right
                && box.top >= canvasBounds.top && box.bottom <= canvasBounds.bottom;
            const collides = (box) => placed.some((p) => !(box.right < p.left || box.left > p.right || box.bottom < p.top || box.top > p.bottom));

            let chosen = candidates.find((c) => isInBounds(boxFor(c)) && !collides(boxFor(c)))
                ?? candidates.find((c) => isInBounds(boxFor(c)))
                ?? candidates[0];
            let finalBox = boxFor(chosen);

            // Dernier recours (bord de canvas) : translater la position
            // choisie pour qu'elle rentre entièrement dans le canvas plutôt
            // que de laisser le texte se faire tronquer.
            let drawDx = chosen.dx;
            let drawDy = chosen.dy;
            if (!isInBounds(finalBox)) {
                const shiftX = Math.max(0, canvasBounds.left - finalBox.left) - Math.max(0, finalBox.right - canvasBounds.right);
                const shiftY = Math.max(0, canvasBounds.top - finalBox.top) - Math.max(0, finalBox.bottom - canvasBounds.bottom);
                drawDx += shiftX;
                drawDy += shiftY;
                finalBox = {
                    left: finalBox.left + shiftX,
                    right: finalBox.right + shiftX,
                    top: finalBox.top + shiftY,
                    bottom: finalBox.bottom + shiftY,
                };
            }
            placed.push(finalBox);

            // Halo blanc derrière le texte : reste lisible même dessiné
            // par-dessus une ligne de grille ou un autre point.
            ctx.lineWidth = 3;
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.strokeText(text, point.x + drawDx, point.y + drawDy);
            ctx.fillStyle = dataset.isUser ? '#62273b' : '#374151';
            ctx.fillText(text, point.x + drawDx, point.y + drawDy);
        });

        ctx.restore();
    },
};

// Halo pulsant discret derrière "Toi" (jamais derrière un parti) : le rend
// impossible à manquer au premier coup d'œil, en plus de sa taille et sa
// forme déjà distinctes. Dessiné en beforeDatasetsDraw (donc sous tous les
// points, y compris le triangle lui-même) à partir de la position en pixels
// réelle du point, recalculée à chaque frame — jamais une position figée au
// montage, qui se désynchroniserait au redimensionnement de la fenêtre.
// Respecte prefers-reduced-motion : la boucle d'animation n'est simplement
// jamais démarrée (cf. startPulse ci-dessous), le halo reste alors statique
// (un seul rendu, sans re-déclenchement), jamais totalement absent.
const userHaloPlugin = {
    id: 'userHalo',
    beforeDatasetsDraw(chart) {
        if (!hasBoth.value) return;
        const { scales, ctx } = chart;
        if (!scales.x || !scales.y) return;

        const px = scales.x.getPixelForValue(x.value);
        const py = scales.y.getPixelForValue(y.value);
        const period = 1900;
        const now = typeof performance !== 'undefined' ? performance.now() : Date.now();
        const t = (now % period) / period;
        const radius = 16 + t * 16;
        const opacity = 0.32 * (1 - t);

        ctx.save();
        ctx.beginPath();
        ctx.arc(px, py, radius, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(98, 39, 59, ${opacity.toFixed(3)})`;
        ctx.fill();
        ctx.restore();
    },
};

const chartPlugins = [quadrantFillPlugin, quadrantLabelsPlugin, userHaloPlugin, pointLabelsPlugin];

const scatterRef = ref(null);
let pulseRAF = null;

// Boucle de redessin manuelle : Chart.js ne se redessine pas spontanément en
// continu (seulement sur changement de données/options), donc rien ne
// redéclencherait userHaloPlugin sans ceci. Nettoyée à la destruction du
// composant pour ne jamais laisser une boucle tourner dans le vide.
function startPulse() {
    const hasReducedMotionPreference = typeof window !== 'undefined' && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (hasReducedMotionPreference) return;

    const tick = () => {
        const exposed = scatterRef.value?.chart;
        const chartInstance = exposed && 'value' in exposed ? exposed.value : exposed;
        chartInstance?.draw();
        pulseRAF = requestAnimationFrame(tick);
    };
    pulseRAF = requestAnimationFrame(tick);
}

onMounted(() => {
    if (hasBoth.value) startPulse();
});

onBeforeUnmount(() => {
    if (pulseRAF) cancelAnimationFrame(pulseRAF);
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    // Marge symétrique suffisante pour qu'un marqueur au bord exact de
    // l'échelle (-1 ou 1, cf. `clip: false` ci-dessus) reste entièrement
    // visible sur les quatre côtés, quel que soit le coin concerné.
    layout: { padding: { top: 16, right: 16, bottom: 16, left: 16 } },
    scales: {
        x: {
            min: -1,
            max: 1,
            // Libéral ↔ interventionniste, et non l'inverse : correspond aux
            // données réelles de l'expert (MR, le plus libéral/pro-marché des
            // six partis, obtient un axis_x négatif ; le PTB, le plus
            // interventionniste, un axis_x positif).
            title: {
                display: true,
                text: 'Libéral ← → Interventionniste',
                font: { size: 14, weight: 'bold' },
                padding: { top: 6 },
                color: '#62273b',
            },
            grid: { color: '#e7e5e4' },
            // Les graduations numériques (-1, -0.5, 0…) n'ont pas de sens
            // intuitif pour le public visé (16-25 ans) ; les étiquettes de
            // cadran + les titres d'axe suffisent à situer un point sans
            // recourir à une valeur brute.
            ticks: { display: false },
        },
        y: {
            min: -1,
            max: 1,
            title: {
                display: true,
                text: 'Conservateur ← → Progressiste',
                font: { size: 14, weight: 'bold' },
                padding: { top: 6 },
                color: '#62273b',
            },
            grid: { color: '#e7e5e4' },
            ticks: { display: false },
        },
    },
    plugins: {
        legend: { display: false }, // légende HTML custom sous le graphique (cf. template) : inclut "Toi", ce que la légende native de Chart.js ne permettrait pas facilement.
        tooltip: {
            // Formulation qualitative seule, jamais les coordonnées brutes
            // (-1..1) qui les ont produites : nulle part ailleurs dans l'app
            // (description de profil, fiche parti) un score numérique brut
            // n'est montré à l'utilisateur, une coordonnée décimale sur une
            // échelle abstraite n'étant lisible que par qui connaît le calcul
            // qui la produit.
            callbacks: {
                title: (items) => items[0]?.dataset.label ?? '',
                label: (ctx) => [axisXDescriptor(ctx.parsed.x), axisYDescriptor(ctx.parsed.y)],
            },
        },
    },
};

// Dégradation pour le cas résiduel (rare) où même les partis sont absents du
// graphique et où l'utilisateur n'a qu'un seul axe calculable.
function gaugePercent(value) {
    return ((value + 1) / 2) * 100;
}
</script>

<template>
    <div>
        <div v-if="showScatter" class="flex flex-col gap-3">
            <!-- Alternative textuelle au canvas (invisible à l'écran, lue par
                 les lecteurs d'écran) + role="img"/aria-label sur le conteneur
                 en renfort : le canvas lui-même reste aria-hidden, aucune
                 information n'existe donc qu'à un seul endroit accessible. -->
            <p class="sr-only">{{ chartAriaLabel }}</p>
            <div style="height: 400px;" role="img" :aria-label="chartAriaLabel">
                <Scatter ref="scatterRef" :data="chartData" :options="chartOptions" :plugins="chartPlugins" aria-hidden="true" />
            </div>

            <!-- Légende : nom complet + couleur pour chaque parti, et une entrée
                 "Toi" (triangle bordeaux, même forme que sur le graphique) quand la
                 position de l'utilisateur est affichée. Complète les étiquettes
                 courtes sur le graphique lui-même. -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-stone-200 pt-3 text-xs text-gray-600">
                <div v-if="hasBoth" class="flex items-center gap-1.5 font-semibold text-bordeaux-700">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-bordeaux-600" aria-hidden="true">
                        <path d="M12 3l9 18H3z" />
                    </svg>
                    Toi
                </div>
                <div v-for="party in partyPoints" :key="party.name" class="flex items-center gap-1.5">
                    <span
                        class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: party.color }"
                        aria-hidden="true"
                    />
                    {{ party.name }}
                </div>
            </div>
        </div>

        <div v-else-if="hasNoDataAtAll" class="rounded-xl bg-stone-50 py-8 text-center text-sm text-gray-500">
            Pas assez de réponses pour situer ta position sur les axes économique et sociétal
            (toutes les questions concernées ont été passées).
        </div>

        <div v-else class="flex flex-col gap-4">
            <p class="text-sm text-gray-500">
                Un seul axe a pu être calculé : l'autre nécessite d'avoir répondu à au moins une question de sa
                thématique.
            </p>
            <div v-if="x !== null">
                <div class="mb-1 flex justify-between text-xs text-gray-500">
                    <span>Libéral</span><span>Interventionniste</span>
                </div>
                <div class="relative h-2 rounded-full bg-stone-100">
                    <div
                        class="absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full bg-bordeaux-600"
                        :style="{ left: `calc(${gaugePercent(x)}% - 6px)` }"
                    />
                </div>
            </div>
            <div v-if="y !== null">
                <div class="mb-1 flex justify-between text-xs text-gray-500">
                    <span>Conservateur</span><span>Progressiste</span>
                </div>
                <div class="relative h-2 rounded-full bg-stone-100">
                    <div
                        class="absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full bg-bordeaux-600"
                        :style="{ left: `calc(${gaugePercent(y)}% - 6px)` }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
