<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // FK vers la colonne unique guest_sessions.session_token (et non son id).
            $table->uuid('session_token')->nullable();
            $table->timestamp('completed_at');
            $table->uuid('share_token')->unique()->nullable();
            $table->boolean('is_shared')->default(false);
            $table->decimal('political_axis_x', 5, 2)->nullable();
            $table->decimal('political_axis_y', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('session_token');

            // cascadeOnDelete : le job planifié de purge des sessions anonymes
            // non converties (cf. cahier des charges §5.2) entraîne la
            // suppression complète des résultats jamais rattachés à un compte.
            $table->foreign('session_token')
                ->references('session_token')->on('guest_sessions')
                ->cascadeOnDelete();
        });

        // Règle métier : user_id OU session_token doit être renseigné (jamais
        // les deux null). Appliquée en base sur PostgreSQL ; validée aussi
        // côté application, qui reste la source de vérité portable.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE quiz_results ADD CONSTRAINT quiz_results_owner_check CHECK (user_id IS NOT NULL OR session_token IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
    }
};
