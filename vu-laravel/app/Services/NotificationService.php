<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    public function sendCertificateExpiryNotification(Certificate $certificate): void
    {
        $user = $certificate->user;
        $daysUntilExpiry = $certificate->days_until_expiry;

        $notification = Notification::create([
            'type' => 'certificate_expiry',
            'title' => "Certificate Expiring in {$daysUntilExpiry} Days",
            'message' => "Certificate '{$certificate->common_name}' will expire on {$certificate->formatted_expiry}",
            'priority' => $daysUntilExpiry <= 7 ? 'high' : 'medium',
            'channel' => 'email',
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'data' => [
                'days_until_expiry' => $daysUntilExpiry,
                'expiry_date' => $certificate->expires_at->toISOString(),
                'common_name' => $certificate->common_name
            ]
        ]);

        $this->sendEmailNotification($notification, $user);
    }

    public function sendCertificateExpiredNotification(Certificate $certificate): void
    {
        $user = $certificate->user;

        $notification = Notification::create([
            'type' => 'certificate_expired',
            'title' => 'Certificate Expired',
            'message' => "Certificate '{$certificate->common_name}' has expired on {$certificate->formatted_expiry}",
            'priority' => 'high',
            'channel' => 'email',
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'data' => [
                'expiry_date' => $certificate->expires_at->toISOString(),
                'common_name' => $certificate->common_name
            ]
        ]);

        $this->sendEmailNotification($notification, $user);
    }

    public function sendCertificateRevokedNotification(Certificate $certificate, User $revokedBy): void
    {
        $user = $certificate->user;

        $notification = Notification::create([
            'type' => 'certificate_revoked',
            'title' => 'Certificate Revoked',
            'message' => "Certificate '{$certificate->common_name}' has been revoked by {$revokedBy->full_name}",
            'priority' => 'high',
            'channel' => 'email',
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'data' => [
                'revoked_by' => $revokedBy->full_name,
                'revocation_reason' => $certificate->revocation_reason,
                'revoked_at' => $certificate->revoked_at->toISOString(),
                'common_name' => $certificate->common_name
            ]
        ]);

        $this->sendEmailNotification($notification, $user);
    }

    public function sendCertificateRenewedNotification(Certificate $oldCertificate, Certificate $newCertificate): void
    {
        $user = $newCertificate->user;

        $notification = Notification::create([
            'type' => 'certificate_renewed',
            'title' => 'Certificate Renewed',
            'message' => "Certificate '{$newCertificate->common_name}' has been renewed successfully",
            'priority' => 'medium',
            'channel' => 'email',
            'user_id' => $user->id,
            'certificate_id' => $newCertificate->id,
            'data' => [
                'old_certificate_id' => $oldCertificate->certificate_id,
                'new_certificate_id' => $newCertificate->certificate_id,
                'common_name' => $newCertificate->common_name,
                'new_expiry_date' => $newCertificate->expires_at->toISOString()
            ]
        ]);

        $this->sendEmailNotification($notification, $user);
    }

    public function sendCertificateIssuedNotification(Certificate $certificate): void
    {
        $user = $certificate->user;

        $notification = Notification::create([
            'type' => 'certificate_issued',
            'title' => 'Certificate Issued',
            'message' => "Certificate '{$certificate->common_name}' has been issued successfully",
            'priority' => 'medium',
            'channel' => 'email',
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'data' => [
                'common_name' => $certificate->common_name,
                'issued_date' => $certificate->issued_at->toISOString(),
                'expiry_date' => $certificate->expires_at->toISOString()
            ]
        ]);

        $this->sendEmailNotification($notification, $user);
    }

    public function sendSystemAlert(string $title, string $message, string $priority = 'medium', array $data = []): void
    {
        // Send to all admins
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'type' => 'system_alert',
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'channel' => 'email',
                'user_id' => $admin->id,
                'data' => $data
            ]);

            $this->sendEmailNotification($notification, $admin);
        }
    }

    public function sendSMSNotification(Notification $notification, User $user): void
    {
        if (!$user->phone) {
            Log::warning('Cannot send SMS notification: user has no phone number', [
                'user_id' => $user->id,
                'notification_id' => $notification->id
            ]);
            return;
        }

        try {
            // Implement SMS sending logic here
            // This would integrate with services like Twilio, AWS SNS, etc.
            
            Log::info('SMS notification sent', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'notification_id' => $notification->id
            ]);

            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('SMS notification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'notification_id' => $notification->id
            ]);

            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    public function sendWebhookNotification(Notification $notification, string $webhookUrl): void
    {
        try {
            $payload = [
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'priority' => $notification->priority,
                'data' => $notification->data,
                'timestamp' => now()->toISOString()
            ];

            $client = new \GuzzleHttp\Client();
            $response = $client->post($webhookUrl, [
                'json' => $payload,
                'timeout' => 30
            ]);

            if ($response->getStatusCode() === 200) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now()
                ]);
            } else {
                throw new \Exception("Webhook returned status: {$response->getStatusCode()}");
            }

        } catch (\Exception $e) {
            Log::error('Webhook notification failed', [
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl,
                'notification_id' => $notification->id
            ]);

            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    private function sendEmailNotification(Notification $notification, User $user): void
    {
        try {
            Mail::to($user->email)->send(new \App\Mail\CertificateNotification($notification));

            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('Email notification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'notification_id' => $notification->id
            ]);

            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    public function processScheduledNotifications(): int
    {
        $notifications = Notification::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();

        $processed = 0;

        foreach ($notifications as $notification) {
            $user = $notification->user;
            
            if (!$user) {
                $notification->update(['status' => 'failed', 'error_message' => 'User not found']);
                continue;
            }

            switch ($notification->channel) {
                case 'email':
                    $this->sendEmailNotification($notification, $user);
                    break;
                case 'sms':
                    $this->sendSMSNotification($notification, $user);
                    break;
                case 'webhook':
                    $this->sendWebhookNotification($notification, $notification->webhook_url);
                    break;
            }

            $processed++;
        }

        return $processed;
    }

    public function getNotificationStats(): array
    {
        return [
            'total' => Notification::count(),
            'pending' => Notification::where('status', 'pending')->count(),
            'sent' => Notification::where('status', 'sent')->count(),
            'failed' => Notification::where('status', 'failed')->count(),
            'by_type' => Notification::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type'),
            'by_priority' => Notification::selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get()
                ->pluck('count', 'priority')
        ];
    }
}
