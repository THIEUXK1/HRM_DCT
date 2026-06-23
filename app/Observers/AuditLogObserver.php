<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Automatically records create/update/delete events on observed models.
 *
 * Sensitive fields (salary, CCCD, bank account, etc.) are masked before
 * being stored so audit logs never contain PII or financial data in plain text.
 */
class AuditLogObserver
{
    /** Fields to replace with '***' in audit storage */
    private const MASKED_FIELDS = [
        'basic_salary', 'net_salary', 'gross_salary', 'tax_amount',
        'bhxh_employee', 'bhxh_employer', 'pit_amount', 'insurance_salary',
        'cccd', 'passport_number', 'tax_code', 'bank_account',
        'password', 'remember_token', 'api_token',
    ];

    public function created(Model $model): void
    {
        $this->recordAudit($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if (empty($changes)) {
            return;
        }

        $original = collect($model->getOriginal())
            ->only(array_keys($changes))
            ->all();

        $this->recordAudit($model, 'updated', $original, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->recordAudit($model, 'deleted', $model->getOriginal(), null);
    }

    protected function recordAudit(Model $model, string $action, mixed $old, mixed $new): void
    {
        // Prevent recursion
        if ($model instanceof AuditLog) {
            return;
        }

        $companyId = $this->currentCompanyId($model);

        AuditLog::create([
            'actor_id'        => Auth::id(),
            'actor_name'      => Auth::user()?->name,
            'company_id'      => $companyId,
            'entity_type'     => $model::class,
            'entity_id'       => $model->getKey(),
            'action'          => $action,
            'action_category' => 'data',
            'old_value'       => $old !== null ? json_encode($this->mask($old), JSON_UNESCAPED_UNICODE) : null,
            'new_value'       => $new !== null ? json_encode($this->mask($new), JSON_UNESCAPED_UNICODE) : null,
            'ip_address'      => Request::ip(),
        ]);
    }

    private function mask(array $data): array
    {
        foreach (self::MASKED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '***';
            }
        }

        return $data;
    }

    private function currentCompanyId(Model $model): ?int
    {
        // Try model's own company_id field first
        if (isset($model->company_id)) {
            return $model->company_id;
        }

        // Fall back to request header
        return Request::hasHeader('X-Company-Id')
            ? (int) Request::header('X-Company-Id')
            : null;
    }
}
