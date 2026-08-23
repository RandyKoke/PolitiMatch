import { describe, expect, it } from 'vitest';
import { CALIBRATED_TOTAL_QUESTIONS, COMPLETION_MESSAGE, PROGRESS_MESSAGE_TIERS, resolveProgressMessage } from '@/config/progressMessages';

describe('grille de paliers PROGRESS_MESSAGE_TIERS', () => {
    it('ne laisse aucun trou ni chevauchement entre les paliers (Q1 à Q30 inclus)', () => {
        for (let questionNumber = 1; questionNumber <= CALIBRATED_TOTAL_QUESTIONS; questionNumber++) {
            const matchingTiers = PROGRESS_MESSAGE_TIERS.filter((t) => questionNumber >= t.from && questionNumber <= t.to);

            // Exactement un palier doit matcher — zéro (trou) ou plusieurs
            // (chevauchement) seraient tous les deux une grille ambiguë.
            expect(matchingTiers).toHaveLength(1);
        }
    });

    it('commence à la question 1 et se termine sans borne supérieure (jamais de trou après la dernière question)', () => {
        const sorted = [...PROGRESS_MESSAGE_TIERS].sort((a, b) => a.from - b.from);

        expect(sorted[0].from).toBe(1);
        expect(sorted.at(-1).to).toBe(Infinity);
    });

    it('les paliers sont contigus (le "to" de chacun précède exactement le "from" du suivant)', () => {
        const sorted = [...PROGRESS_MESSAGE_TIERS].sort((a, b) => a.from - b.from);

        for (let i = 0; i < sorted.length - 1; i++) {
            expect(sorted[i + 1].from).toBe(sorted[i].to + 1);
        }
    });
});

describe('resolveProgressMessage', () => {
    it('palier "Démarrage" (Q1-Q5) : aucun message', () => {
        expect(resolveProgressMessage(1, 30)).toBeNull();
        expect(resolveProgressMessage(5, 30)).toBeNull();
    });

    it('palier "Lancé" (Q6-Q14) : "Tu avances bien, continue !"', () => {
        expect(resolveProgressMessage(6, 30)).toBe('Tu avances bien, continue !');
        expect(resolveProgressMessage(14, 30)).toBe('Tu avances bien, continue !');
    });

    // Bornes critiques explicitement demandées, une par une plutôt qu'une
    // boucle générique : chacune documente précisément le point de bascule
    // attendu, lisible individuellement en cas d'échec.
    it('Q14 : pas encore "mi-chemin" (dernière question du palier "Lancé")', () => {
        expect(resolveProgressMessage(14, 30)).toBe('Tu avances bien, continue !');
    });

    it('Q15 : le message "mi-chemin" apparaît', () => {
        expect(resolveProgressMessage(15, 30)).toBe('Tu es à mi-chemin, continue comme ça !');
    });

    it('Q20 : dernier "mi-chemin" (encore le même message)', () => {
        expect(resolveProgressMessage(20, 30)).toBe('Tu es à mi-chemin, continue comme ça !');
    });

    it('Q21 : le message change ("Plus que quelques questions...")', () => {
        expect(resolveProgressMessage(21, 30)).toBe('Plus que quelques questions...');
    });

    it('Q23 : dernier palier intermédiaire (encore "Plus que quelques questions...")', () => {
        expect(resolveProgressMessage(23, 30)).toBe('Plus que quelques questions...');
    });

    it('Q24 : "Presque fini, encore un petit effort !" apparaît', () => {
        expect(resolveProgressMessage(24, 30)).toBe('Presque fini, encore un petit effort !');
    });

    it('Q30 affichée mais pas encore validée (isComplete=false, valeur par défaut) : encore "Presque fini, encore un petit effort !"', () => {
        expect(resolveProgressMessage(30, 30)).toBe('Presque fini, encore un petit effort !');
        expect(resolveProgressMessage(30, 30, false)).toBe('Presque fini, encore un petit effort !');
    });

    it('ne plante pas et ne renvoie pas un message erroné pour un numéro hors plage (0 ou négatif)', () => {
        expect(resolveProgressMessage(0, 30)).toBeNull();
        expect(resolveProgressMessage(-1, 30)).toBeNull();
    });
});

// Palier de complétion : distinct des paliers numériques
// ci-dessus, piloté par un booléen explicite plutôt que par une coïncidence
// de numéro de question — cf. commentaire dans progressMessages.js pour le
// pourquoi (Q30 affichée-mais-pas-répondue et Q30 effectivement terminée
// sont numériquement indiscernables : même questionNumber=totalQuestions=30
// dans les deux cas).
describe('resolveProgressMessage — palier de complétion (isComplete)', () => {
    it('quiz effectivement terminé (isComplete=true) : message de complétion, plus "Presque fini..."', () => {
        expect(resolveProgressMessage(30, 30, true)).toBe(COMPLETION_MESSAGE);
        expect(resolveProgressMessage(30, 30, true)).not.toBe('Presque fini, encore un petit effort !');
    });

    it('même numéro de question (30/30), résultat différent selon isComplete — la distinction ne peut donc pas venir du numéro seul', () => {
        const stillAnswering = resolveProgressMessage(30, 30, false);
        const justCompleted = resolveProgressMessage(30, 30, true);

        expect(stillAnswering).not.toBe(justCompleted);
    });

    it('isComplete=true prend le pas même sur les paliers de tout début de parcours (garde-fou, cas normalement impossible en pratique)', () => {
        expect(resolveProgressMessage(1, 30, true)).toBe(COMPLETION_MESSAGE);
    });

    it('COMPLETION_MESSAGE est un message à part entière, distinct de tous les paliers de la grille', () => {
        const tierMessages = PROGRESS_MESSAGE_TIERS.map((t) => t.message);

        expect(tierMessages).not.toContain(COMPLETION_MESSAGE);
    });
});
