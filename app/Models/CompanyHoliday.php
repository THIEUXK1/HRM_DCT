<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyHoliday extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'holiday_date',
        'end_date',
        'is_paid',
    ];

    protected $appends = ['day_count', 'date_range_label'];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_paid' => 'boolean',
        ];
    }

    protected function dayCount(): Attribute
    {
        return Attribute::get(fn () => $this->calendarDayCount());
    }

    protected function dateRangeLabel(): Attribute
    {
        return Attribute::get(function () {
            $start = $this->holiday_date?->format('d/m/Y');
            $end = ($this->end_date ?? $this->holiday_date)?->format('d/m/Y');

            if (! $start) {
                return '—';
            }

            return $start === $end ? $start : "{$start} → {$end}";
        });
    }

    public function rangeEnd(): Carbon
    {
        return Carbon::parse($this->end_date ?? $this->holiday_date);
    }

    public function calendarDayCount(): int
    {
        if (! $this->holiday_date) {
            return 0;
        }

        return (int) $this->holiday_date->diffInDays($this->rangeEnd()) + 1;
    }

    /** @return array<string, string> date => name */
    public function expandToDateMap(): array
    {
        $map = [];
        $cursor = Carbon::parse($this->holiday_date);
        $end = $this->rangeEnd();

        while ($cursor <= $end) {
            $map[$cursor->format('Y-m-d')] = $this->name;
            $cursor->addDay();
        }

        return $map;
    }
}
