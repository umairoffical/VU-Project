<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'common_name',
        'subject_alt_names',
        'csr',
        'certificate',
        'private_key',
        'status',
        'type',
        'serial_number',
        'fingerprint',
        'issuer',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revocation_reason',
        'validity_days',
        'key_usage',
        'extended_key_usage',
        'signature_algorithm',
        'key_size',
        'key_type',
        'user_id',
        'approved_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'subject_alt_names' => 'array',
        'key_usage' => 'array',
        'extended_key_usage' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at && $this->expires_at->isBefore(Carbon::now()->addDays($days));
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }
        
        return max(0, Carbon::now()->diffInDays($this->expires_at, false));
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'issued' => 'success',
            'pending' => 'warning',
            'revoked' => 'error',
            'expired' => 'error',
            'renewed' => 'info',
            default => 'default',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'issued' => 'check_circle',
            'pending' => 'schedule',
            'revoked' => 'block',
            'expired' => 'error',
            'renewed' => 'refresh',
            default => 'help',
        };
    }

    public function canBeRenewed(): bool
    {
        return $this->isIssued() && 
               !$this->isRevoked() && 
               $this->isExpiringSoon(90); // Can renew 90 days before expiry
    }

    public function canBeRevoked(): bool
    {
        return $this->isIssued() && !$this->isRevoked();
    }

    public function getFormattedExpiryAttribute(): string
    {
        if (!$this->expires_at) {
            return 'N/A';
        }
        
        return $this->expires_at->format('Y-m-d H:i:s');
    }

    public function getFormattedIssuedAttribute(): string
    {
        if (!$this->issued_at) {
            return 'N/A';
        }
        
        return $this->issued_at->format('Y-m-d H:i:s');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expires_at', '<=', Carbon::now()->addDays($days))
                    ->where('status', 'issued');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', Carbon::now())
                    ->where('status', 'issued');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCommonName($query, string $commonName)
    {
        return $query->where('common_name', 'like', "%{$commonName}%");
    }
}
