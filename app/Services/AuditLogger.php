<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Explicit audit logger for sensitive HR actions.
 *
 * Used for business events that cannot be captured automatically by the
 * Eloquent Observer (approve, reject, export, finalize, role-assign, login…).
 */
class AuditLogger
{
    /**
     * Fields that must never be stored in plain text in audit logs.
     * Their values are replaced with a masked placeholder.
     */
    private const MASKED_FIELDS = [
        'basic_salary', 'net_salary', 'gross_salary', 'tax_amount',
        'bhxh_employee', 'bhxh_employer', 'pit_amount', 'insurance_salary',
        'cccd', 'passport_number', 'tax_code', 'bank_account',
        'password', 'remember_token',
    ];

    // ── Generic log ──────────────────────────────────────────────────────────

    public static function log(
        string $action,
        Model|string $entity,
        ?int $entityId = null,
        string $category = 'general',
        ?string $description = null,
        mixed $oldValue = null,
        mixed $newValue = null,
    ): void {
        if ($entity instanceof Model) {
            $entityType = $entity::class;
            $entityId   = $entity->getKey();
        } else {
            $entityType = $entity;
        }

        AuditLog::create([
            'actor_id'        => Auth::id(),
            'actor_name'      => Auth::user()?->name,
            'company_id'      => self::currentCompanyId(),
            'tenant_id'       => self::currentTenantId(),
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'action'          => $action,
            'action_category' => $category,
            'description'     => $description,
            'old_value'       => $oldValue !== null ? json_encode(self::mask($oldValue), JSON_UNESCAPED_UNICODE) : null,
            'new_value'       => $newValue !== null ? json_encode(self::mask($newValue), JSON_UNESCAPED_UNICODE) : null,
            'ip_address'      => Request::ip(),
        ]);
    }

    // ── Convenience methods ──────────────────────────────────────────────────

    public static function approved(Model $entity, ?string $description = null): void
    {
        self::log('approved', $entity, null, 'workflow', $description ?? class_basename($entity).' approved');
    }

    public static function rejected(Model $entity, ?string $description = null): void
    {
        self::log('rejected', $entity, null, 'workflow', $description ?? class_basename($entity).' rejected');
    }

    public static function exported(string $entityType, int $entityId, string $description): void
    {
        self::log('exported', $entityType, $entityId, 'export', $description);
    }

    public static function finalized(Model $entity, ?string $description = null): void
    {
        self::log('finalized', $entity, null, 'payroll', $description);
    }

    public static function roleAssigned(Model $user, array $roles): void
    {
        self::log('role_assigned', $user, null, 'security', 'Roles synced: '.implode(', ', $roles));
    }

    public static function login(Model $user): void
    {
        self::log('login', $user, null, 'security', 'User logged in');
    }

    public static function loginFailed(string $email): void
    {
        AuditLog::create([
            'actor_id'        => null,
            'actor_name'      => $email,
            'company_id'      => null,
            'entity_type'     => 'App\\Models\\User',
            'entity_id'       => 0,
            'action'          => 'login_failed',
            'action_category' => 'security',
            'description'     => "Failed login attempt for: {$email}",
            'ip_address'      => Request::ip(),
        ]);
    }

    public static function companyAccessChanged(Model $user, array $companyIds): void
    {
        self::log('company_access_changed', $user, null, 'security',
            'Company access synced: '.implode(', ', $companyIds));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function mask(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach (self::MASKED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '***';
            }
        }

        return $data;
    }

    private static function currentCompanyId(): ?int
    {
        return app()->bound('current_company_id')
            ? app('current_company_id')
            : (Request::hasHeader('X-Company-Id') ? (int) Request::header('X-Company-Id') : null);
    }

    private static function currentTenantId(): ?int
    {
        return app()->bound('current_tenant_id')
            ? app('current_tenant_id')
            : null;
    }
}
