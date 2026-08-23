import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Garde-fou de non-régression pour le token --color-gray-400 (texte
 * secondaire : dates, sources, icône de fermeture de modale), dont la
 * valeur par défaut de Tailwind (#9ca3af) ne donnait que 2.60:1 de
 * contraste sur fond blanc. Recalcule le ratio réel (formule de luminance
 * relative WCAG 2.1) à partir de la valeur effectivement déclarée dans
 * app.css, pas d'une valeur copiée ici en dur : si quelqu'un modifie ce
 * token sans recalculer le contraste, ce test l'attrape.
 */
function srgbToLinear(channel) {
    const c = channel / 255;
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

function relativeLuminance(hex) {
    const value = hex.replace('#', '');
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return 0.2126 * srgbToLinear(r) + 0.7152 * srgbToLinear(g) + 0.0722 * srgbToLinear(b);
}

function contrastRatio(hexA, hexB) {
    const lumA = relativeLuminance(hexA);
    const lumB = relativeLuminance(hexB);
    const lighter = Math.max(lumA, lumB);
    const darker = Math.min(lumA, lumB);
    return (lighter + 0.05) / (darker + 0.05);
}

function readGray400() {
    const cssPath = resolve(process.cwd(), 'resources/css/app.css');
    const css = readFileSync(cssPath, 'utf-8');
    const match = css.match(/--color-gray-400:\s*(#[0-9a-fA-F]{6})/);
    if (!match) {
        throw new Error('--color-gray-400 introuvable dans app.css');
    }
    return match[1];
}

describe('Contraste WCAG AA : --color-gray-400 (texte secondaire)', () => {
    it('atteint au moins 4.5:1 sur fond blanc (#ffffff)', () => {
        const gray400 = readGray400();
        expect(contrastRatio(gray400, '#ffffff')).toBeGreaterThanOrEqual(4.5);
    });

    it('atteint au moins 4.5:1 sur le fond crème de l\'app (--color-cream, #faf9f6)', () => {
        const gray400 = readGray400();
        expect(contrastRatio(gray400, '#faf9f6')).toBeGreaterThanOrEqual(4.5);
    });
});
