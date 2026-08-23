<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete : une thématique en cours d'usage ne doit pas
            // pouvoir être supprimée par erreur tant qu'elle a des questions.
            $table->foreignId('theme_id')->constrained('themes')->restrictOnDelete();
            $table->text('label');
            $table->text('explanation')->nullable();
            $table->smallInteger('weight');
            $table->boolean('is_active')->default(true);
            $table->integer('position_order');
            $table->timestamps();
        });

        // CHECK non portable simplement vers SQLite (ALTER TABLE limité) :
        // appliqué uniquement sur PostgreSQL, la cible de production réelle.
        // La validation est de toute façon dupliquée côté application (Form Request).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_weight_check CHECK (weight IN (1, 2, 3))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
