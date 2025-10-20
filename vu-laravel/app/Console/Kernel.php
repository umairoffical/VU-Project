<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check for expiring certificates daily at 2 AM
        $schedule->command('certificates:check-expiring')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer();

        // Create daily database backup at 3 AM
        $schedule->command('db:backup --type=full')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->after(function () {
                \Log::info('Daily database backup completed');
            });

        // Clean up old backups weekly
        $schedule->call(function () {
            $backupService = app(\App\Services\DatabaseBackupService::class);
            $stats = $backupService->getStats();
            \Log::info('Backup cleanup completed', $stats);
        })->weekly()->sundays()->at('04:00');

        // Process scheduled notifications every 5 minutes
        $schedule->call(function () {
            $notificationService = app(\App\Services\NotificationService::class);
            $processed = $notificationService->processScheduledNotifications();
            \Log::info('Processed scheduled notifications', ['count' => $processed]);
        })->everyFiveMinutes();

        // Clean up expired audit logs monthly
        $schedule->call(function () {
            \App\Models\AuditLog::where('created_at', '<', now()->subMonths(6))->delete();
            \Log::info('Old audit logs cleaned up');
        })->monthly();

        // Update certificate statuses hourly
        $schedule->call(function () {
            $expired = \App\Models\Certificate::where('status', 'issued')
                ->where('expires_at', '<', now())
                ->update(['status' => 'expired']);
            
            \Log::info('Certificate statuses updated', ['expired_count' => $expired]);
        })->hourly();

        // Cache warm-up every hour
        $schedule->call(function () {
            $cacheService = app(\App\Services\CacheService::class);
            $result = $cacheService->warmUp();
            \Log::info('Cache warmed up', $result);
        })->hourly();

        // Health check every 15 minutes
        $schedule->call(function () {
            $stepCA = app(\App\Services\StepCAService::class);
            $vault = app(\App\Services\VaultService::class);
            
            $health = [
                'step_ca' => $stepCA->isAvailable(),
                'vault' => $vault->isAvailable(),
                'database' => \DB::connection()->getDatabaseName() !== null
            ];
            
            // Send alert if any service is down
            if (in_array(false, $health, true)) {
                $notificationService = app(\App\Services\NotificationService::class);
                $notificationService->sendSystemAlert(
                    'System Health Alert',
                    'One or more services are unavailable: ' . json_encode($health),
                    'high',
                    $health
                );
            }
        })->everyFifteenMinutes();

        // Weekly certificate renewal reminders
        $schedule->call(function () {
            $expiringCertificates = \App\Models\Certificate::expiringSoon(30)->get();
            
            foreach ($expiringCertificates as $cert) {
                $notificationService = app(\App\Services\NotificationService::class);
                $notificationService->sendCertificateExpiryNotification($cert);
            }
        })->weekly()->mondays()->at('09:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

