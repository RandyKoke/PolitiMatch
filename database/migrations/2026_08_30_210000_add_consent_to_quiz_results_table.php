<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * consent_given_at / consent_version : preuve du consentement explicite
     * exigé par l'article 9 du RGPD pour le traitement d'une donnée relevant
     * d'une catégorie particulière (ici, une opinion politique). Avant cette
     * migration, la case à cocher de StartAccessView.vue n'était vérifiée
     * que côté interface : rien n'en gardait trace côté serveur, donc rien
     * ne permettait de démontrer ce consentement après coup. Nullable pour
     * ne pas invalider les résultats déjà en production avant ce correctif
     * (leur absence de valeur documente honnêtement qu'ils précèdent la
     * mise en conformité, plutôt que de leur attribuer un faux consentement
     * rétroactif).
     */
    public function up(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->timestamp('consent_given_at')->nullable()->after('session_token');
            $table->string('consent_version', 50)->nullable()->after('consent_given_at');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn(['consent_given_at', 'consent_version']);
        });
    }
};
