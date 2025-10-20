<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'event_category',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'session_id',
        'user_id',
        'certificate_id',
        'resource_type',
        'resource_id',
        'severity',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'error',
            'critical' => 'error',
            default => 'default',
        };
    }

    public function getSeverityIconAttribute(): string
    {
        return match($this->severity) {
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'error',
            'critical' => 'error',
            default => 'help',
        };
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCertificate($query, int $certificateId)
    {
        return $query->where('certificate_id', $certificateId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
