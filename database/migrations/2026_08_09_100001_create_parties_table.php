<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('abbreviation', 20);
            $table->string('logo_url')->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->text('description')->nullable();
            $table->enum('language_community', ['FR', 'NL', 'DE', 'FED']);
            // Désactivation plutôt que suppression : un parti peut avoir des
            // positions et des scores historiques à préserver (cf. result_party_scores).
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
