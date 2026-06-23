<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAwardDiscipline extends Model
{
    use BelongsToCompany;

    protected $table = 'employee_awards_disciplines';

    protected $fillable = [
        'company_id', 'employee_id', 'type', 'decision_number',
        'decision_date', 'reason', 'amount', 'signed_by', 'note'
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
