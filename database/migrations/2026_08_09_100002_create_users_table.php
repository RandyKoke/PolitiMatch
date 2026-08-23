<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Généré côté application (App\Models\User::booted) plutôt que par
            // défaut SQL, pour rester portable entre SQLite (local) et PostgreSQL.
            $table->uuid('uuid')->unique();
            $table->string('username', 50)->unique();
            $table->string('email')->unique();
            // Nullable : un compte créé uniquement via OAuth social n'a pas
            // de mot de passe local (cf. social_accounts).
            $table->string('password_hash')->nullable();
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->string('avatar_seed', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
