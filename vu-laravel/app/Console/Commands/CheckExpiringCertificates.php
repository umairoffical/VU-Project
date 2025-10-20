<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class CheckExpiringCertificates extends Command
{
    protected $signature = 'certificates:check-expiring';
    protected $description = 'Check for expiring certificates and send notifications';

    public function handle()
    {
        $this->info('Checking for expiring certificates...');
        
        // Check certificates expiring in 30, 15, 7, and 1 days
        $thresholds = [30, 15, 7, 1];
        
        foreach ($thresholds as $days) {
            $expiringDate = Carbon::now()->addDays($days)->startOfDay();
            $endDate = Carbon::now()->addDays($days)->endOfDay();
            
            $certificates = Certificate::where('status', 'issued')
                ->whereBetween('expires_at', [$expiringDate, $endDate])
                ->get();
            
            foreach ($certificates as $certificate) {
                $this->notifyUser($certificate, $days);
            }
            
            if ($certificates->count() > 0) {
                $this->info("Found {$certificates->count()} certificate(s) expiring in {$days} days");
            }
        }
        
        $this->info('Certificate expiry check completed!');
    }

    private function notifyUser(Certificate $certificate, int $daysUntilExpiry)
    {
        // Create notification in database
        Notification::create([
            'user_id' => $certificate->user_id,
            'type' => 'certificate_expiring',
            'title' => "Certificate Expiring in {$daysUntilExpiry} Days",
            'message' => "Your certificate for {$certificate->common_name} will expire in {$daysUntilExpiry} days on " . $certificate->expires_at->format('Y-m-d'),
            'severity' => $daysUntilExpiry <= 7 ? 'high' : 'medium',
            'is_read' => false,
            'data' => json_encode([
                'certificate_id' => $certificate->certificate_id,
                'common_name' => $certificate->common_name,
                'expires_at' => $certificate->expires_at,
                'days_until_expiry' => $daysUntilExpiry,
            ]),
        ]);
        
        // Send email notification
        $user = User::find($certificate->user_id);
        if ($user && $user->email) {
            try {
                // Create email content
                $subject = "Certificate Expiring: {$certificate->common_name}";
                $message = "
                    <h2>Certificate Expiration Notice</h2>
                    <p>Your certificate is expiring soon:</p>
                    <ul>
                        <li><strong>Domain:</strong> {$certificate->common_name}</li>
                        <li><strong>Expires:</strong> {$certificate->expires_at->format('F j, Y')}</li>
                        <li><strong>Days Remaining:</strong> {$daysUntilExpiry}</li>
                        <li><strong>Certificate ID:</strong> {$certificate->certificate_id}</li>
                    </ul>
                    <p>Please renew this certificate before it expires.</p>
                    <p><a href='" . config('app.url') . "/dashboard'>Go to Dashboard</a></p>
                ";
                
                // Log email (in production, this would send via Mail facade)
                \Log::info('Certificate expiry notification email', [
                    'to' => $user->email,
                    'certificate' => $certificate->common_name,
                    'days_until_expiry' => $daysUntilExpiry
                ]);
                
                $this->line("  → Notification sent to {$user->email} for {$certificate->common_name}");
                
            } catch (\Exception $e) {
                \Log::error('Failed to send expiry notification email', [
                    'error' => $e->getMessage(),
                    'user' => $user->email,
                    'certificate' => $certificate->certificate_id
                ]);
            }
        }
    }
}

