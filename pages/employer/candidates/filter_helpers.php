<?php
/**
 * Purpose: Normalizes incoming candidate filter values and maps sort keys to safe SQL ORDER BY fragments.
 * Tables/columns used: No database access. Consumes filter inputs search, position, status, and sort.
 */

if (!function_exists('candidates_parse_filters')) {
    function candidates_parse_filters(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $positionRaw = trim((string)($filters['position'] ?? ''));
        $position = ctype_digit($positionRaw) ? $positionRaw : '';
        $status = trim((string)($filters['status'] ?? ''));
        $sort = trim((string)($filters['sort'] ?? 'match'));

        $allowedSort = [
            'match' => 'a.compatibility_score DESC, a.application_date DESC',
            'date' => 'a.application_date DESC, a.application_id DESC',
            'name' => 's.last_name ASC, s.first_name ASC',
        ];

        if (!array_key_exists($sort, $allowedSort)) {
            $sort = 'match';
        }

        return [
            'search' => $search,
            'position' => $position,
            'status' => $status,
            'sort' => $sort,
            'order_by' => $allowedSort[$sort],
        ];
    }
}
