<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * answers.question_id et result_party_scores.party_id n'avaient jusqu'ici
 * d'index que comme second membre de leur contrainte d'unicité composite
 * respective ([quiz_result_id, question_id] et [quiz_result_id, party_id]),
 * ce qui ne permet pas à PostgreSQL d'utiliser efficacement un index pour
 * une recherche filtrée sur la seule colonne question_id/party_id (à la
 * différence de quiz_result_id, en tête de ces mêmes contraintes composites,
 * qui bénéficie déjà de son propre index standalone explicite dans les
 * migrations d'origine). Laravel `foreignId()->constrained()` ne crée pas
 * d'index automatiquement sous PostgreSQL, contrairement à MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->index('question_id');
        });

        Schema::table('result_party_scores', function (Blueprint $table) {
            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex(['question_id']);
        });

        Schema::table('result_party_scores', function (Blueprint $table) {
            $table->dropIndex(['party_id']);
        });
    }
};
