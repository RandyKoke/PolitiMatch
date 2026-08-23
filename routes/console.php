<?php

use App\Console\Commands\CleanupStaleComputingQuizzes;
use App\Console\Commands\PurgeExpiredGuestSessions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CleanupStaleComputingQuizzes::class)->everyTenMinutes();

// Fréquence quotidienne, sans chevauchement avec CleanupStaleComputingQuizzes
// ci-dessus (portée distincte : calculs bloqués, pas sessions anonymes). Les
// deux tâches n'agissent jamais sur les mêmes lignes (QuizResult.status vs
// GuestSession.expires_at), aucune interférence possible entre elles.
Schedule::command(PurgeExpiredGuestSessions::class)->daily();
