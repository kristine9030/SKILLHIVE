<?php
/**
 * Purpose: Handles employer candidate pipeline actions (status updates and interview scheduling).
 * Tables/columns used: application(application_id, student_id, internship_id, status, updated_at), internship(internship_id, employer_id, duration_weeks), interview(application_id, interview_date, interview_mode, interview_status, meeting_link, venue, notes), ojt_record(student_id, internship_id, hours_required, hours_completed, completion_status, start_date, end_date, created_at, updated_at).
 */

if (!function_exists('candidates_normalize_status')) {
    function candidates_normalize_status(?string $status): string
    {
        $raw = strtolower(trim((string)($status ?? '')));
        $raw = str_replace(['_', '-'], ' ', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw ?? '') ?: '';

        $map = [
            'pending' => 'Pending',
            'shortlisted' => 'Shortlisted',
            'interview scheduled' => 'Interview Scheduled',
            'interview' => 'Interview Scheduled',
            'accepted' => 'Accepted',
            'hired' => 'Accepted',
            'rejected' => 'Rejected',
            'declined' => 'Rejected',
        ];

        return $map[$raw] ?? 'Pending';
    }
}

if (!function_exists('candidates_can_transition')) {
    function candidates_can_transition(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }

        $allowed = [
            'Pending' => ['Shortlisted', 'Rejected'],
            'Shortlisted' => ['Interview Scheduled', 'Rejected'],
            'Interview Scheduled' => ['Accepted', 'Rejected'],
            'Accepted' => [],
            'Rejected' => [],
        ];

        return in_array($next, $allowed[$current] ?? [], true);
    }
}

if (!function_exists('candidates_get_owned_application_status')) {
    function candidates_get_owned_application_status(PDO $pdo, int $employerId, int $applicationId): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT a.status
             FROM application a
             INNER JOIN internship i ON i.internship_id = a.internship_id
             WHERE a.application_id = :application_id
               AND i.employer_id = :employer_id
             LIMIT 1'
        );
        $stmt->execute([
            ':application_id' => $applicationId,
            ':employer_id' => $employerId,
        ]);

        $status = $stmt->fetchColumn();
        if ($status === false) {
            return null;
        }

        return candidates_normalize_status((string)$status);
    }
}

if (!function_exists('candidates_update_application_status')) {
    function candidates_update_application_status(PDO $pdo, int $employerId, int $applicationId, string $nextStatus): array
    {
        if ($applicationId <= 0) {
            return ['success' => false, 'error' => 'Invalid application selected.'];
        }

        $normalizedNext = candidates_normalize_status($nextStatus);
        $allowedStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
        if (!in_array($normalizedNext, $allowedStatuses, true)) {
            return ['success' => false, 'error' => 'Invalid candidate status selected.'];
        }

        $currentStatus = candidates_get_owned_application_status($pdo, $employerId, $applicationId);
        if ($currentStatus === null) {
            return ['success' => false, 'error' => 'Application not found for your account.'];
        }

        if (!candidates_can_transition($currentStatus, $normalizedNext)) {
            return ['success' => false, 'error' => 'Invalid status transition: ' . $currentStatus . ' → ' . $normalizedNext . '.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE application
             SET status = :status,
                 updated_at = NOW()
             WHERE application_id = :application_id'
        );
        $stmt->execute([
            ':status' => $normalizedNext,
            ':application_id' => $applicationId,
        ]);

        if ($normalizedNext === 'Accepted') {
            $ojtResult = candidates_ensure_ojt_record($pdo, $employerId, $applicationId);
            if (empty($ojtResult['success'])) {
                return $ojtResult;
            }
        }

        return ['success' => true, 'error' => null];
    }
}

if (!function_exists('candidates_ensure_ojt_record')) {
    function candidates_ensure_ojt_record(PDO $pdo, int $employerId, int $applicationId): array
    {
        $detailsStmt = $pdo->prepare(
            'SELECT a.student_id, a.internship_id, COALESCE(i.duration_weeks, 0) AS duration_weeks
             FROM application a
             INNER JOIN internship i ON i.internship_id = a.internship_id
             WHERE a.application_id = :application_id
               AND i.employer_id = :employer_id
             LIMIT 1'
        );
        $detailsStmt->execute([
            ':application_id' => $applicationId,
            ':employer_id' => $employerId,
        ]);

        $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);
        if (!$details) {
            return ['success' => false, 'error' => 'Unable to prepare OJT record for this candidate.'];
        }

        $studentId = (int)($details['student_id'] ?? 0);
        $internshipId = (int)($details['internship_id'] ?? 0);
        $durationWeeks = max(0, (int)($details['duration_weeks'] ?? 0));
        $hoursRequired = $durationWeeks > 0 ? (float)($durationWeeks * 40) : 0.0;

        $existsStmt = $pdo->prepare(
            'SELECT record_id
             FROM ojt_record
             WHERE student_id = :student_id
               AND internship_id = :internship_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':student_id' => $studentId,
            ':internship_id' => $internshipId,
        ]);

        $recordId = (int)$existsStmt->fetchColumn();
        if ($recordId > 0) {
            $touchStmt = $pdo->prepare(
                'UPDATE ojt_record
                 SET updated_at = NOW()
                 WHERE record_id = :record_id'
            );
            $touchStmt->execute([':record_id' => $recordId]);
            return ['success' => true, 'error' => null];
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO ojt_record
                (student_id, internship_id, hours_required, hours_completed, start_date, end_date, completion_status, created_at, updated_at)
             VALUES
                (:student_id, :internship_id, :hours_required, :hours_completed, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :duration_weeks WEEK), :completion_status, NOW(), NOW())'
        );
        $insertStmt->execute([
            ':student_id' => $studentId,
            ':internship_id' => $internshipId,
            ':hours_required' => $hoursRequired,
            ':hours_completed' => 0.00,
            ':duration_weeks' => $durationWeeks,
            ':completion_status' => 'Ongoing',
        ]);

        return ['success' => true, 'error' => null];
    }
}

if (!function_exists('candidates_schedule_interview')) {
    function candidates_schedule_interview(PDO $pdo, int $employerId, int $applicationId, array $payload): array
    {
        if ($applicationId <= 0) {
            return ['success' => false, 'error' => 'Invalid application selected for interview scheduling.'];
        }

        $currentStatus = candidates_get_owned_application_status($pdo, $employerId, $applicationId);
        if ($currentStatus === null) {
            return ['success' => false, 'error' => 'Application not found for your account.'];
        }

        if (!in_array($currentStatus, ['Pending', 'Shortlisted', 'Interview Scheduled'], true)) {
            return ['success' => false, 'error' => 'Only pending or shortlisted candidates can be scheduled for interview.'];
        }

        $interviewDateRaw = trim((string)($payload['interview_date'] ?? ''));
        $interviewMode = trim((string)($payload['interview_mode'] ?? 'Online'));
        $meetingTarget = trim((string)($payload['meeting_link'] ?? ''));

        $allowedModes = ['Online', 'In-Person'];
        if (!in_array($interviewMode, $allowedModes, true)) {
            $interviewMode = 'Online';
        }

        if ($meetingTarget === '') {
            return ['success' => false, 'error' => $interviewMode === 'Online' ? 'Please provide a meeting link.' : 'Please provide a venue.'];
        }

        $timestamp = strtotime($interviewDateRaw);
        if ($timestamp === false) {
            return ['success' => false, 'error' => 'Please provide a valid interview date and time.'];
        }

            $interviewDate = date('Y-m-d', $timestamp);
            $interviewTime = date('H:i:s', $timestamp);
        $meetingLink = $interviewMode === 'Online' ? $meetingTarget : null;
        $venue = $interviewMode === 'In-Person' ? $meetingTarget : null;

        try {
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare(
                'UPDATE interview
                 SET interview_date = :interview_date,
                        interview_time = :interview_time,
                     interview_mode = :interview_mode,
                     meeting_link = :meeting_link,
                     venue = :venue,
                     notes = NULL,
                     interview_status = :interview_status
                 WHERE application_id = :application_id'
            );
            $updateStmt->execute([
                ':interview_date' => $interviewDate,
                   ':interview_time' => $interviewTime,
                ':interview_mode' => $interviewMode,
                ':meeting_link' => $meetingLink,
                ':venue' => $venue,
                ':interview_status' => 'scheduled',
                ':application_id' => $applicationId,
            ]);

            if ($updateStmt->rowCount() === 0) {
                $insertStmt = $pdo->prepare(
                        'INSERT INTO interview (application_id, interview_date, interview_time, interview_mode, meeting_link, venue, notes, interview_status, created_at)
                         VALUES (:application_id, :interview_date, :interview_time, :interview_mode, :meeting_link, :venue, NULL, :interview_status, NOW())'
                );
                $insertStmt->execute([
                    ':application_id' => $applicationId,
                    ':interview_date' => $interviewDate,
                       ':interview_time' => $interviewTime,
                    ':interview_mode' => $interviewMode,
                    ':meeting_link' => $meetingLink,
                    ':venue' => $venue,
                    ':interview_status' => 'scheduled',
                ]);
            }

            $statusResult = candidates_update_application_status($pdo, $employerId, $applicationId, 'Interview Scheduled');
            if (empty($statusResult['success'])) {
                $pdo->rollBack();
                return $statusResult;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Unable to schedule interview right now. Please try again.'];
        }

        return ['success' => true, 'error' => null];
    }
}
