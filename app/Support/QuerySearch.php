<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Bộ lọc tìm kiếm text thống nhất cho các API danh sách.
 */
class QuerySearch
{
    public static function employee(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        $lower = strtolower($search);
        return $query->where(function ($q) use ($lower) {
            $q->whereRaw('LOWER(full_name) LIKE ?', ["%{$lower}%"])
                ->orWhereRaw('LOWER(employee_code) LIKE ?', ["%{$lower}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lower}%"]);
        });
    }

    public static function employeeRelation(Builder $query, ?string $search, string $relation = 'employee'): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $q) => self::employee($q, $search));
    }

    public static function user(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('employee', fn (Builder $e) => self::employee($e, $search));
        });
    }

    public static function candidate(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
