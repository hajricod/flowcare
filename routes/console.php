<?php

use App\Services\SlotRetentionCleanupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('slots:cleanup-expired', function (SlotRetentionCleanupService $cleanupService) {
    $deletedCount = $cleanupService->cleanup();

    $this->info("Hard-deleted {$deletedCount} expired soft-deleted slots.");
})->purpose('Hard-delete soft-deleted slots after the retention period');

Schedule::command('slots:cleanup-expired')->dailyAt('01:00');
