<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot centrale de l'algorithme de matching (cf. cahier des
     * charges §10) : un parti × une question → une position notée par
     * l'expert politique.
     */
    public function up(): void
    {
        Schema::create('party_positions', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete sur les deux FK : cette table pivot n'a aucun
            // sens sans le parti et la question qu'elle relie.
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->smallInteger('score');
            $table->text('justification');
            $table->string('source_reference', 500);
            $table->boolean('validated_by_expert')->default(false);
            $table->timestamps();

            $table->unique(['party_id', 'question_id']);
            // Index individuels en plus de l'unique composite : le
            // MatchingService interroge aussi par party_id ou question_id seul.
            $table->index('party_id');
            $table->index('question_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE party_positions ADD CONSTRAINT party_positions_score_check CHECK (score IN (-2, -1, 0, 1, 2))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_positions');
    }
};
