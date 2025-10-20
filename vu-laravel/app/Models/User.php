<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'first_name',
        'last_name',
        'phone',
        'department',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'is_active',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'array',
        'is_active' => 'boolean',
    ];

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function certificateRequests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function approvedCertificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'approved_by');
    }

    public function approvedRequests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class, 'approved_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCertificateManager(): bool
    {
        return $this->role === 'certificate_manager';
    }

    public function isRegularUser(): bool
    {
        return $this->role === 'regular_user';
    }

    public function canManageCertificates(): bool
    {
        return $this->isAdmin() || $this->isCertificateManager();
    }

    public function canApproveRequests(): bool
    {
        return $this->isAdmin() || $this->isCertificateManager();
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

}