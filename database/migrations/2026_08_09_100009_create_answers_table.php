<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_result_id')->constrained('quiz_results')->cascadeOnDelete();
            // restrictOnDelete : une question déjà répondue ne doit pas être
            // supprimée (la désactiver via is_active est le mécanisme prévu).
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->smallInteger('user_score');
            $table->boolean('was_skipped')->default(false);
            $table->timestamp('answered_at');

            $table->unique(['quiz_result_id', 'question_id']);
            $table->index('quiz_result_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE answers ADD CONSTRAINT answers_user_score_check CHECK (user_score IN (-2, -1, 0, 1, 2))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
