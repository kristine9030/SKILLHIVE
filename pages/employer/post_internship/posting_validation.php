<?php
/**
 * Purpose: Validates internship posting form payloads and prepares normalized rows for internship_skill inserts.
 * Tables/columns used: No direct database reads. Validates data destined for internship(title, description, duration_weeks, allowance, work_setup, location, slots_available, status) and internship_skill(skill_id, required_level, is_mandatory).
 */

if (!function_exists('validatePostInternshipPayload')) {
    function validatePostInternshipPayload(array $post, array $validSkillIds): array
    {
        $allowedWorkSetup = ['Remote', 'On-site', 'Hybrid'];
        $allowedStatus = ['Draft', 'Open', 'Closed'];
        $allowedLevels = ['Beginner', 'Intermediate', 'Advanced'];

        $old = [];
        $errors = [];

        $old['title'] = trim((string)($post['title'] ?? ''));
        $old['description'] = trim((string)($post['description'] ?? ''));
        $old['duration_hours'] = trim((string)($post['duration_hours'] ?? ''));
        $old['duration_weeks'] = trim((string)($post['duration_weeks'] ?? ''));
        $old['allowance'] = trim((string)($post['allowance'] ?? ''));
        $old['work_setup'] = trim((string)($post['work_setup'] ?? ''));
        $old['region_id'] = trim((string)($post['region_id'] ?? ''));
        $old['region_name'] = trim((string)($post['region_name'] ?? ''));
        $old['province_id'] = trim((string)($post['province_id'] ?? ''));
        $old['province_name'] = trim((string)($post['province_name'] ?? ''));
        $old['city_id'] = trim((string)($post['city_id'] ?? ''));
        $old['city_name'] = trim((string)($post['city_name'] ?? ''));
        $old['location'] = trim((string)($post['location'] ?? ''));
        $old['slots_available'] = trim((string)($post['slots_available'] ?? ''));
        $old['status'] = trim((string)($post['status'] ?? 'Open'));

        if ($old['duration_hours'] === '') {
            $legacyWeeks = filter_var($old['duration_weeks'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($legacyWeeks !== false) {
                $old['duration_hours'] = (string)((int)$legacyWeeks * 40);
            }
        }

        if ($old['location'] === '') {
            $locationParts = array_values(array_filter([
                $old['city_name'],
                $old['province_name'],
                $old['region_name'],
            ], static function ($value): bool {
                return trim((string)$value) !== '';
            }));

            if (!empty($locationParts)) {
                $old['location'] = implode(', ', $locationParts);
            }
        }

        if ($old['title'] === '') $errors[] = 'Title is required.';
        if ($old['description'] === '') $errors[] = 'Description is required.';
        if ($old['location'] === '') $errors[] = 'Location is required.';

        $durationHours = filter_var($old['duration_hours'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 40]]);
        if ($durationHours === false) {
            $errors[] = 'Duration hours must be a whole number ≥ 40.';
        } elseif (((int)$durationHours % 40) !== 0) {
            $errors[] = 'Duration hours must be divisible by 40.';
        }

        $durationWeeks = $durationHours === false ? false : (int)((int)$durationHours / 40);

        $allowance = filter_var($old['allowance'], FILTER_VALIDATE_FLOAT);
        if ($allowance === false || $allowance < 0) $errors[] = 'Allowance must be 0 or higher.';

        $slotsAvailable = filter_var($old['slots_available'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($slotsAvailable === false) $errors[] = 'Slots must be a whole number ≥ 1.';

        if (!in_array($old['work_setup'], $allowedWorkSetup, true)) $errors[] = 'Invalid Work Setup.';
        if (!in_array($old['status'], $allowedStatus, true)) $errors[] = 'Invalid Status.';

        $selectedSkills = $post['skills'] ?? [];
        $skillLevels = $post['skill_level'] ?? [];
        $skillMandatory = $post['skill_mandatory'] ?? [];

        if (!is_array($selectedSkills) || count($selectedSkills) === 0) {
            $errors[] = 'Select at least one required skill.';
        }

        $rowsToInsert = [];
        if (empty($errors)) {
            foreach ($selectedSkills as $sidRaw) {
                $sid = (int)$sidRaw;
                if (!in_array($sid, $validSkillIds, true)) {
                    $errors[] = 'Invalid skill (ID ' . $sid . ').';
                    continue;
                }

                $lvl = (string)($skillLevels[$sid] ?? '');
                if (!in_array($lvl, $allowedLevels, true)) {
                    $errors[] = 'Invalid level for skill ID ' . $sid . '.';
                    continue;
                }

                $isMandatory = isset($skillMandatory[$sid]) ? 1 : 0;
                $rowsToInsert[] = [$sid, $lvl, $isMandatory];
            }
        }

        return [
            'errors' => $errors,
            'old' => $old,
            'duration_hours' => $durationHours === false ? null : (int)$durationHours,
            'duration_weeks' => $durationWeeks === false ? null : (int)$durationWeeks,
            'allowance' => $allowance === false ? null : (float)$allowance,
            'slots_available' => $slotsAvailable === false ? null : (int)$slotsAvailable,
            'rows_to_insert' => $rowsToInsert,
            'allowed_work_setup' => $allowedWorkSetup,
            'allowed_status' => $allowedStatus,
            'allowed_levels' => $allowedLevels,
        ];
    }
}
