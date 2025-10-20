<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\StepCAService;
use App\Services\CertificateService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CertificateController extends Controller
{
    private $stepCAService;
    private $certificateService;
    private $notificationService;

    public function __construct(
        StepCAService $stepCAService,
        CertificateService $certificateService,
        NotificationService $notificationService
    ) {
        $this->stepCAService = $stepCAService;
        $this->certificateService = $certificateService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Certificate::query();

        // Apply role-based filtering
        if ($user && $user->isRegularUser()) {
            $query->where('user_id', $user->id);
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('common_name')) {
            $query->where('common_name', 'like', '%' . $request->common_name . '%');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $certificates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $certificates->items(),
            'pagination' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
                'from' => $certificates->firstItem(),
                'to' => $certificates->lastItem()
            ]
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $certificate = Certificate::with(['user', 'approver', 'auditLogs'])->findOrFail($id);

        // Check permissions
        if ($user->isRegularUser() && $certificate->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $this->logAuditEvent('certificate_viewed', 'certificate_management', 'Certificate details viewed', [
            'certificate_id' => $certificate->certificate_id,
            'common_name' => $certificate->common_name
        ], request());

        return response()->json([
            'success' => true,
            'data' => $certificate
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'common_name' => 'required|string|max:255',
            'subject_alt_names' => 'array',
            'subject_alt_names.*' => 'string|max:255',
            'validity_days' => 'integer|min:1|max:3650',
            'key_type' => 'in:RSA,ECDSA,ED25519',
            'key_size' => 'integer|min:1024|max:4096',
            'key_usage' => 'array',
            'extended_key_usage' => 'array',
            'notes' => 'string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // Check if user can generate certificates
        if (!$user->canManageCertificates()) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions to generate certificates'
            ], 403);
        }

        try {
            $certificateData = $this->certificateService->generateCertificate([
                'common_name' => $request->common_name,
                'subject_alt_names' => $request->subject_alt_names ?? [],
                'validity_days' => $request->validity_days ?? 365,
                'key_type' => $request->key_type ?? 'RSA',
                'key_size' => $request->key_size ?? 2048,
                'key_usage' => $request->key_usage ?? ['Digital Signature', 'Key Encipherment'],
                'extended_key_usage' => $request->extended_key_usage ?? ['TLS Web Server Authentication'],
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'notes' => $request->notes
            ]);

            $this->logAuditEvent('certificate_generated', 'certificate_management', 'Certificate generated', [
                'certificate_id' => $certificateData['certificate_id'],
                'common_name' => $certificateData['common_name'],
                'user_id' => $user->id
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Certificate generated successfully',
                'data' => $certificateData
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate generation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Certificate generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function revoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'certificate_id' => 'required|string',
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $certificate = Certificate::where('certificate_id', $request->certificate_id)->firstOrFail();

        // Check permissions
        if ($user->isRegularUser() && $certificate->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        if (!$certificate->canBeRevoked()) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate cannot be revoked'
            ], 400);
        }

        try {
            $this->certificateService->revokeCertificate($certificate, $request->reason, $user);

            $this->logAuditEvent('certificate_revoked', 'certificate_management', 'Certificate revoked', [
                'certificate_id' => $certificate->certificate_id,
                'common_name' => $certificate->common_name,
                'reason' => $request->reason,
                'user_id' => $user->id
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Certificate revoked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate revocation failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $request->certificate_id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Certificate revocation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function renew(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'certificate_id' => 'required|string',
            'validity_days' => 'integer|min:1|max:3650'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $certificate = Certificate::where('certificate_id', $request->certificate_id)->firstOrFail();

        // Check permissions
        if ($user->isRegularUser() && $certificate->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        if (!$certificate->canBeRenewed()) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate cannot be renewed'
            ], 400);
        }

        try {
            $newCertificate = $this->certificateService->renewCertificate($certificate, [
                'validity_days' => $request->validity_days ?? 365,
                'user_id' => $user->id,
                'approved_by' => $user->canApproveRequests() ? $user->id : null
            ]);

            $this->logAuditEvent('certificate_renewed', 'certificate_management', 'Certificate renewed', [
                'old_certificate_id' => $certificate->certificate_id,
                'new_certificate_id' => $newCertificate->certificate_id,
                'common_name' => $certificate->common_name,
                'user_id' => $user->id
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Certificate renewed successfully',
                'data' => $newCertificate
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate renewal failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $request->certificate_id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Certificate renewal failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download(string $id, string $format = 'pem'): JsonResponse
    {
        $user = auth()->user();
        $certificate = Certificate::findOrFail($id);

        // Check permissions
        if ($user->isRegularUser() && $certificate->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        if (!$certificate->isIssued()) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not available for download'
            ], 400);
        }

        try {
            $fileContent = $this->certificateService->exportCertificate($certificate, $format);
            $filename = "{$certificate->common_name}_{$certificate->certificate_id}.{$format}";

            $this->logAuditEvent('certificate_downloaded', 'certificate_management', 'Certificate downloaded', [
                'certificate_id' => $certificate->certificate_id,
                'common_name' => $certificate->common_name,
                'format' => $format,
                'user_id' => $user->id
            ], request());

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => base64_encode($fileContent),
                    'filename' => $filename,
                    'mime_type' => $this->getMimeType($format)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate download failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $id,
                'format' => $format,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Certificate download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $query = Certificate::query();

        // Apply role-based filtering
        if ($user->isRegularUser()) {
            $query->where('user_id', $user->id);
        }

        $stats = [
            'total' => $query->count(),
            'issued' => $query->clone()->where('status', 'issued')->count(),
            'pending' => $query->clone()->where('status', 'pending')->count(),
            'revoked' => $query->clone()->where('status', 'revoked')->count(),
            'expired' => $query->clone()->expired()->count(),
            'expiring_soon' => $query->clone()->expiringSoon(30)->count(),
            'by_type' => $query->clone()->selectRaw('type, COUNT(*) as count')->groupBy('type')->get()->pluck('count', 'type'),
            'by_key_type' => $query->clone()->selectRaw('key_type, COUNT(*) as count')->groupBy('key_type')->get()->pluck('count', 'key_type'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function health(): JsonResponse
    {
        $stepCAStatus = $this->stepCAService->checkHealth();
        $databaseStatus = $this->checkDatabaseHealth();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'timestamp' => now()->toISOString(),
                'services' => [
                    'step_ca' => $stepCAStatus,
                    'database' => $databaseStatus
                ],
                'version' => config('app.version', '1.0.0')
            ]
        ]);
    }

    private function checkDatabaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getMimeType(string $format): string
    {
        return match($format) {
            'pem' => 'application/x-pem-file',
            'der' => 'application/x-x509-ca-cert',
            'p12' => 'application/x-pkcs12',
            'pfx' => 'application/x-pkcs12',
            default => 'application/octet-stream'
        };
    }

    private function logAuditEvent(string $eventType, string $category, string $description, array $metadata = [], Request $request = null): void
    {
        AuditLog::create([
            'event_type' => $eventType,
            'event_category' => $category,
            'description' => $description,
            'ip_address' => $request ? $request->ip() : '127.0.0.1',
            'user_agent' => $request ? $request->userAgent() : null,
            'user_id' => auth()->id(),
            'metadata' => $metadata,
            'severity' => $this->getSeverityFromEventType($eventType)
        ]);
    }

    private function getSeverityFromEventType(string $eventType): string
    {
        return match($eventType) {
            'certificates_viewed', 'certificate_viewed', 'certificate_downloaded' => 'low',
            'certificate_generated', 'certificate_renewed' => 'medium',
            'certificate_revoked' => 'high',
            default => 'medium'
        };
    }
}