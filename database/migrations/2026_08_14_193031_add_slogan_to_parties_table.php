<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slogan officiel de campagne (cahier des charges, Module Fiches Partis) :
     * absent du schéma initial. Nullable comme les autres champs éditoriaux
     * de cette table (logo_url, description) : rien n'impose qu'un parti en
     * ait un renseigné pour rester affichable. 255 caractères suffisent
     * largement au plus long des 6 slogans fournis par l'expert politique
     * (43 caractères), avec une marge confortable pour des slogans futurs.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('slogan', 255)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('slogan');
        });
    }
};
