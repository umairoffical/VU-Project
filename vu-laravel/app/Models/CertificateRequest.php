<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CertificateRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'common_name',
        'subject_alt_names',
        'organization',
        'organizational_unit',
        'country',
        'state',
        'city',
        'email',
        'csr',
        'status',
        'request_type',
        'validity_days',
        'key_usage',
        'extended_key_usage',
        'signature_algorithm',
        'key_size',
        'key_type',
        'justification',
        'rejection_reason',
        'user_id',
        'approved_by',
        'approved_at',
        'rejected_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subject_alt_names' => 'array',
        'key_usage' => 'array',
        'extended_key_usage' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who approved/rejected this request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope a query to only include pending requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include rejected requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Check if the request is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the request is approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the request is rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the request is issued.
     *
     * @return bool
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * Approve the certificate request.
     *
     * @param  int  $approverId
     * @return bool
     */
    public function approve(int $approverId): bool
    {
        return $this->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject the certificate request.
     *
     * @param  int  $approverId
     * @param  string  $reason
     * @return bool
     */
    public function reject(int $approverId, string $reason): bool
    {
        return $this->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark the request as issued.
     *
     * @return bool
     */
    public function markAsIssued(): bool
    {
        return $this->update([
            'status' => 'issued',
        ]);
    }

    /**
     * Get formatted creation date.
     *
     * @return string
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    /**
     * Get formatted approval date.
     *
     * @return string|null
     */
    public function getFormattedApprovedAtAttribute(): ?string
    {
        return $this->approved_at ? $this->approved_at->format('Y-m-d H:i:s') : null;
    }

    /**
     * Get days since creation.
     *
     * @return int
     */
    public function getDaysSinceCreationAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get the status badge color.
     *
     * @return string
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'error',
            'issued' => 'info',
            default => 'default',
        };
    }
}

