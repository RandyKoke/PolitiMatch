// Doit rester cohérent avec AvatarController::DICEBEAR_STYLE (backend) : la
// grille de suggestions (/api/avatars/suggestions) renvoie déjà une URL
// complète construite avec ce même style, ce helper ne sert qu'à afficher un
// avatar dont on ne connaît que le seed (déjà choisi, stocké en base).
const DICEBEAR_STYLE = 'avataaars';

export function avatarUrl(seed) {
    return `https://api.dicebear.com/9.x/${DICEBEAR_STYLE}/svg?seed=${encodeURIComponent(seed ?? 'politimatch')}`;
}
