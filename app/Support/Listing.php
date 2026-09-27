<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Listing
{
    /** Apply an OR-LIKE search across $columns when $term is non-empty. */
    public static function applySearch(Builder $query, ?string $term, array $columns): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        return $query->where(function (Builder $q) use ($term, $columns) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', '%'.$term.'%');
            }
        });
    }

    /** Apply an ORDER BY only when $sortBy is in the whitelist; else fall back. */
    public static function applySort(Builder $query, ?string $sortBy, string $direction, array $allowed, string $default): Builder
    {
        $column = in_array($sortBy, $allowed, true) ? $sortBy : $default;
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        return $query->orderBy($column, $dir);
    }
}
