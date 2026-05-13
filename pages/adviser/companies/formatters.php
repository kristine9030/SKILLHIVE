<?php
/**
 * Purpose: Shared formatting helpers for adviser companies page.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_companies_escape')) {
    function adviser_companies_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_companies_initial')) {
    function adviser_companies_initial(?string $companyName): string
    {
        $name = trim((string)$companyName);
        return $name !== '' ? strtoupper(substr($name, 0, 1)) : 'C';
    }
}

if (!function_exists('adviser_companies_verification_label')) {
    function adviser_companies_verification_label(?string $verificationStatus): string
    {
        $status = strtolower(trim((string)($verificationStatus ?? 'pending')));
        if (in_array($status, ['approved', 'verified'], true)) {
            return 'Verified';
        }
        return 'Unverified';
    }
}

if (!function_exists('adviser_companies_verification_badge_class')) {
    function adviser_companies_verification_badge_class(?string $verificationStatus): string
    {
        $status = strtolower(trim((string)($verificationStatus ?? 'pending')));
        if (in_array($status, ['approved', 'verified'], true)) {
            return 'badge-verified';
        }
        return 'badge-unverified';
    }
}

if (!function_exists('adviser_companies_gradient')) {
    function adviser_companies_gradient(int $index): string
    {
        $palette = [
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
            'linear-gradient(135deg,#12b3ac,#EC4899)',
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
        ];

        return $palette[$index % count($palette)];
    }
}

if (!function_exists('adviser_companies_rating_text')) {
    function adviser_companies_rating_text($avgRating): string
    {
        if ($avgRating === null || $avgRating === '' || !is_numeric($avgRating)) {
            return 'N/A';
        }

        return number_format((float)$avgRating, 1);
    }
}

if (!function_exists('adviser_companies_contact_person_label')) {
    function adviser_companies_contact_person_label(array $row): string
    {
        $contactPerson = trim((string)($row['contact_person_name'] ?? ''));
        return $contactPerson !== '' ? $contactPerson : 'Not recorded';
    }
}

if (!function_exists('adviser_companies_accepting_status_meta')) {
    function adviser_companies_accepting_status_meta(array $row): array
    {
        $verification = strtolower(trim((string)($row['verification_status'] ?? 'pending')));
        $openPostings = (int)($row['open_postings'] ?? 0);
        $openSlots = (int)($row['open_slots'] ?? 0);

        if (in_array($verification, ['rejected', 'flagged'], true)) {
            return [
                'label' => 'Unverified',
                'detail' => 'Company is not verified for BSU interns.',
                'class' => 'is-danger',
            ];
        }

        if ($openPostings > 0 && in_array($verification, ['approved', 'verified'], true)) {
            $postingText = $openPostings === 1 ? '1 open posting' : $openPostings . ' open postings';
            $slotText = $openSlots === 1 ? '1 listed slot' : $openSlots . ' listed slots';
            return [
                'label' => 'Verified',
                'detail' => 'Verified company with ' . $postingText . ' and ' . $slotText . '.',
                'class' => 'is-success',
            ];
        }

        if ($openPostings > 0) {
            return [
                'label' => 'Unverified',
                'detail' => 'Has open postings but still needs verification.',
                'class' => 'is-warning',
            ];
        }

        if (in_array($verification, ['approved', 'verified'], true)) {
            return [
                'label' => 'Verified',
                'detail' => 'Verified company with no open internship postings right now.',
                'class' => 'is-success',
            ];
        }

        return [
            'label' => 'Unverified',
            'detail' => 'Awaiting company verification.',
            'class' => 'is-warning',
        ];
    }
}

if (!function_exists('adviser_companies_student_hours_text')) {
    function adviser_companies_student_hours_text(array $student): string
    {
        $hoursRequired = (float)($student['hours_required'] ?? 0);
        if ($hoursRequired <= 0) {
            return 'No OJT hours yet';
        }

        $hoursCompleted = (float)($student['hours_completed'] ?? 0);
        return (int)round($hoursCompleted) . '/' . (int)round($hoursRequired) . ' hrs';
    }
}

if (!function_exists('adviser_companies_student_export_text')) {
    function adviser_companies_student_export_text(array $students): string
    {
        if (empty($students)) {
            return 'None';
        }

        $parts = [];
        foreach ($students as $student) {
            $studentName = trim((string)($student['student_name'] ?? 'Student'));
            $internshipTitle = trim((string)($student['internship_title'] ?? 'Internship'));
            $status = trim((string)($student['completion_status'] ?? ''));
            $label = $studentName;
            if ($internshipTitle !== '') {
                $label .= ' - ' . $internshipTitle;
            }
            if ($status !== '') {
                $label .= ' (' . $status . ')';
            }
            $parts[] = $label;
        }

        return implode('; ', $parts);
    }
}

if (!function_exists('adviser_companies_format_date')) {
    function adviser_companies_format_date(?string $dateValue): string
    {
        if (!$dateValue) {
            return 'N/A';
        }

        $timestamp = strtotime($dateValue);
        if ($timestamp === false) {
            return 'N/A';
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('adviser_companies_documents_meta')) {
    function adviser_companies_documents_meta(array $row): array
    {
        $filled = 0;
        $fields = [
            trim((string)($row['website_url'] ?? '')),
            trim((string)($row['email'] ?? '')),
            trim((string)($row['contact_number'] ?? '')),
            trim((string)($row['company_address'] ?? '')),
        ];

        foreach ($fields as $field) {
            if ($field !== '') {
                $filled++;
            }
        }

        if ($filled >= 4) {
            return ['label' => 'Complete', 'class' => 'is-success'];
        }

        if ($filled >= 2) {
            return ['label' => 'Partial', 'class' => 'is-warning'];
        }

        return ['label' => 'Incomplete', 'class' => 'is-danger'];
    }
}

if (!function_exists('adviser_companies_risk_meta')) {
    function adviser_companies_risk_meta(array $row): array
    {
        $status = strtolower(trim((string)($row['verification_status'] ?? 'pending')));
        $documents = adviser_companies_documents_meta($row);

        if ($status === 'rejected' || $status === 'flagged' || $documents['label'] === 'Incomplete') {
            return ['label' => 'High', 'class' => 'is-danger'];
        }

        if ($status === 'approved' || $documents['label'] === 'Complete') {
            return ['label' => 'Low', 'class' => 'is-success'];
        }

        return ['label' => 'Medium', 'class' => 'is-warning'];
    }
}

if (!function_exists('adviser_companies_action_meta')) {
    function adviser_companies_action_meta(array $row): array
    {
        $documents = adviser_companies_documents_meta($row);
        $risk = adviser_companies_risk_meta($row);
        $email = trim((string)($row['email'] ?? ''));

        if ($documents['label'] === 'Complete' && $risk['label'] === 'Low') {
            return ['label' => 'Approve', 'class' => 'primary', 'action' => 'approve'];
        }

        if ($documents['label'] === 'Incomplete' || $risk['label'] === 'High') {
            return ['label' => 'Reject', 'class' => 'danger', 'action' => 'reject'];
        }

        if ($email !== '') {
            return ['label' => 'Request Docs', 'class' => 'secondary', 'action' => 'mailto'];
        }

        return ['label' => 'Review', 'class' => 'secondary', 'action' => 'modal'];
    }
}
