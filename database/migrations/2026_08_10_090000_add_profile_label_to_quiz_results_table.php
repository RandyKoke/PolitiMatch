<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Libellé de profil politique (cahier des charges §10.3) : absent du
     * schéma initial, calculé et persisté par ProfileLabelService dans la
     * même transaction que les scores/axes (Phase 5 du matching). Toujours
     * nullable : un QuizResult existe avant tout calcul (status='pending').
     */
    public function up(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->string('profile_label')->nullable();
            $table->text('profile_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn(['profile_label', 'profile_description']);
        });
    }
};
