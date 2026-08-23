<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_party_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_result_id')->constrained('quiz_results')->cascadeOnDelete();
            // restrictOnDelete : un parti avec des scores historiques ne doit
            // pas être supprimé (la désactiver via is_active est le mécanisme prévu).
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->decimal('compatibility_score', 5, 2);
            $table->smallInteger('rank');
            $table->timestamp('calculated_at');

            $table->unique(['quiz_result_id', 'party_id']);
            $table->index('quiz_result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_party_scores');
    }
};
