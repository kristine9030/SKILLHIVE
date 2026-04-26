<?php
/**
 * OJT Journal Assistant - Complete UI
 * Allows students to input raw notes, generates structured entries, and creates final reports
 */
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/journal_helper.php';
require_once __DIR__ . '/ojt_log_helpers.php';

// Verify logged-in student
$role = (string) ($_SESSION['role'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($role !== 'student' || $userId <= 0) {
    header('Location: /SkillHive/pages/auth/login.php');
    exit;
}

// Load (or auto-create) OJT record
$ojt_basic = ojt_get_or_create_record($pdo, $userId);
$ojt_record = null;
if ($ojt_basic) {
    $stmt = $pdo->prepare('
        SELECT o.*, e.company_name, i.title as internship_title, s.first_name, s.last_name
        FROM ojt_record o
        LEFT JOIN internship i ON i.internship_id = o.internship_id
        LEFT JOIN employer e ON e.employer_id = i.employer_id
        INNER JOIN student s ON s.student_id = o.student_id
        WHERE o.record_id = ?
        LIMIT 1
    ');
    $stmt->execute([(int) $ojt_basic['record_id']]);
    $ojt_record = $stmt->fetch(PDO::FETCH_ASSOC) ?: $ojt_basic;
}

$errorMsg = '';
$successMsg = '';

if (!$ojt_record) {
    $errorMsg = 'No active OJT record found. Please contact your adviser.';
}

// Load journal entries
$journal_entries = [];
if ($ojt_record) {
    $journal_entries = journal_load_entries($pdo, (int) $ojt_record['record_id'], 50);
}

// Set base URL for includes
if (!isset($baseUrl)) {
    $baseUrl = '/SkillHive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OJT Journal Assistant - SkillHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/skillhive.css">
    <style>
        .journal-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        @media (max-width: 1200px) {
            .journal-container {
                grid-template-columns: 1fr;
            }
        }

        .journal-section {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            transition: all .2s ease;
        }

        .journal-section::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(0, 128, 255, 0.06);
            pointer-events: none;
        }

        .journal-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -20px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(0, 128, 255, 0.04);
            pointer-events: none;
        }

        .journal-section:hover {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
        }

        .journal-section h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 18px;
            color: #0f172a;
            position: relative;
            z-index: 1;
        }

        .journal-form-group {
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }

        .journal-form-group label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .journal-form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-family: inherit;
            font-size: .9rem;
            resize: vertical;
            min-height: 120px;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .journal-form-group textarea:focus {
            border-color: #0080FF;
            box-shadow: 0 0 0 4px rgba(0, 128, 255, 0.1);
        }

        .section-builder {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 14px;
            position: relative;
            z-index: 1;
        }

        .section-builder-heading {
            font-size: .9rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .section-builder-text {
            color: #475569;
            font-size: .82rem;
            margin: 0 0 10px 0;
        }

        .section-builder-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            align-items: stretch;
        }

        .section-builder-field {
            margin-bottom: 0;
        }

        .section-builder-note textarea {
            min-height: 90px;
        }

        .section-builder-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
        }

        .draft-preview {
            margin-top: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            min-height: 64px;
        }

        .draft-empty {
            margin: 0;
            color: #64748b;
            font-size: .82rem;
        }

        .draft-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .draft-item:last-child {
            border-bottom: none;
        }

        .draft-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0c4a6e;
            font-size: .73rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .draft-text {
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.45;
            flex: 1;
            word-break: break-word;
        }

        .raw-notes-hidden {
            display: none;
        }

        .journal-form-group select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-family: inherit;
            font-size: .88rem;
            background: #fff;
            color: #0f172a;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .journal-form-group select:focus {
            border-color: #0080FF;
            box-shadow: 0 0 0 4px rgba(0, 128, 255, 0.1);
        }

        @media (max-width: 900px) {
            .section-builder-grid {
                grid-template-columns: 1fr;
            }
        }

        .btn-small {
            padding: 8px 12px;
            font-size: .78rem;
            border-radius: 10px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .btn {
            padding: 12px 18px;
            border: none;
            border-radius: 12px;
            font-size: .86rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #050505;
            color: white;
            box-shadow: 0 4px 12px rgba(17, 24, 39, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 24, 39, 0.4);
            background: #0f172a;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #0f172a;
            border: 1px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-success {
            background: linear-gradient(135deg, #12b3ac, #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .journal-entry-preview {
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            padding: 18px;
            margin-top: 16px;
            position: relative;
            z-index: 1;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.7);
            text-align: left;
        }

        .entry-context-row {
            margin-bottom: 12px;
        }

        .entry-context-pill {
            display: inline;
        }

        .entry-context-label {
            font-size: .87rem;
            font-weight: 700;
            color: #0f172a;
        }

        .entry-context-value {
            color: #334155;
            font-size: .87rem;
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .entry-section {
            margin-bottom: 10px;
        }

        .entry-section:last-child {
            margin-bottom: 0;
        }

        .entry-section-title {
            font-weight: 700;
            color: #0f172a;
            font-size: .9rem;
            margin-bottom: 7px;
            letter-spacing: .1px;
        }

        .entry-section-content {
            color: #334155;
            line-height: 1.55;
        }

        .entry-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
        }

        .entry-item-bullet {
            color: #0284c7;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .entry-item-text {
            flex: 1;
            word-break: break-word;
        }

        .entry-empty {
            margin: 0;
            color: #64748b;
            font-size: .82rem;
            font-style: italic;
        }

        .visibility-helper-text {
            margin-top: 6px;
            color: #64748b;
            font-size: .8rem;
        }

        .entry-meta-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .visibility-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 700;
        }

        .visibility-shared {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .visibility-private {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .preview-actions {
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .preview-visibility-note {
            margin-top: 8px;
            color: #475569;
            font-size: 0.83rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 128, 255, 0.3);
            border-top-color: #0080FF;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: .9rem;
            position: relative;
            z-index: 1;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #cffafe;
            color: #164e63;
            border: 1px solid #a5f3fc;
        }

        .journal-entries-list {
            margin-top: 24px;
            position: relative;
            z-index: 1;
        }

        .journal-entry-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 12px;
            transition: all .2s ease;
            cursor: pointer;
        }

        .journal-entry-card:hover {
            box-shadow: 0 6px 20px rgba(0, 128, 255, 0.15);
            border-color: #0080FF;
            transform: translateY(-2px);
        }

        .journal-entry-card:focus-visible {
            outline: 2px solid #0080FF;
            outline-offset: 2px;
        }

        .entry-date {
            font-weight: 700;
            color: #0080FF;
            font-size: .95rem;
        }

        .entry-date-human {
            font-size: .85rem;
            color: #94a3b8;
            margin-top: 4px;
        }

        .entry-preview-text {
            margin-top: 12px;
            color: #475569;
            line-height: 1.6;
            font-size: .9rem;
        }

        .entry-preview-text strong {
            color: #0f172a;
        }

        .tabs {
            display: flex;
            gap: 12px;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }

        .tab-btn {
            padding: 12px 16px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: .95rem;
            color: #94a3b8;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all .2s ease;
        }

        .tab-btn.active {
            color: #0080FF;
            border-bottom-color: #0080FF;
        }

        .tab-btn:hover:not(.active) {
            color: #64748b;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .report-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
            z-index: 1;
        }

        .report-section:last-child {
            border-bottom: none;
        }

        .report-section h4 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0080FF;
            margin-bottom: 12px;
        }

        .report-section p {
            color: #475569;
            line-height: 1.8;
            text-align: justify;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .stat-box {
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all .2s ease;
        }

        .stat-box:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0080FF;
        }

        .stat-label {
            font-size: .85rem;
            color: #94a3b8;
            margin-top: 6px;
            font-weight: 600;
        }

        .quality-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 700;
            margin-top: 12px;
        }

        .quality-excellent {
            background: #dcfce7;
            color: #166534;
        }

        .quality-good {
            background: #dbeafe;
            color: #12b3ac;
        }

        .quality-fair {
            background: #fef3c7;
            color: #92400e;
        }

        .quality-basic {
            background: #fecdd3;
            color: #9f1239;
        }

        .sentiment-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 700;
            margin: 8px 0;
        }

        .sentiment-very-positive {
            background: #dcfce7;
            color: #166534;
        }

        .sentiment-positive {
            background: #d1fae5;
            color: #065f46;
        }

        .sentiment-neutral {
            background: #e5e7eb;
            color: #374151;
        }

        .sentiment-negative {
            background: #fee2e2;
            color: #991b1b;
        }

        .sentiment-very-negative {
            background: #fecaca;
            color: #7f1d1d;
        }

        .metric-box {
            display: inline-block;
            background: linear-gradient(135deg, #e0f2fe 0%, #cffafe 100%);
            border: 1px solid #7dd3fc;
            border-radius: 10px;
            padding: 10px 14px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: .9rem;
            color: #0080FF;
            font-weight: 600;
            transition: all .2s ease;
        }

        .metric-box:hover {
            background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%);
            box-shadow: 0 4px 12px rgba(0, 128, 255, 0.2);
        }

        .entry-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 2100;
        }

        .entry-modal.active {
            display: flex;
        }

        .entry-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.64);
            backdrop-filter: blur(2px);
        }

        .entry-modal-dialog {
            position: relative;
            z-index: 1;
            width: min(760px, 100%);
            max-height: min(88vh, 920px);
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 28px 54px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .entry-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .entry-modal-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .entry-modal-close {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            border-radius: 10px;
            font-size: .82rem;
            font-weight: 700;
            padding: 8px 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .entry-modal-close:hover {
            background: #f1f5f9;
        }

        .entry-modal-body {
            padding: 16px;
            overflow-y: auto;
        }

        .entry-modal-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .entry-modal-date {
            color: #0369a1;
            font-weight: 700;
            font-size: .92rem;
        }

        .entry-modal-context {
            margin-bottom: 12px;
            color: #334155;
            font-size: .88rem;
        }

        .entry-modal-context strong {
            color: #0f172a;
        }

        @media (max-width: 640px) {
            .entry-modal {
                padding: 10px;
            }

            .entry-modal-header {
                padding: 12px;
            }

            .entry-modal-title {
                font-size: .92rem;
            }

            .entry-modal-close {
                padding: 7px 10px;
                font-size: .76rem;
            }

            .entry-modal-body {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h2 class="page-title"><i class="fas fa-book"></i> Internship Journal Assistant</h2>
                <p class="page-subtitle">Capture your daily internship experiences, emotions, and personal reflections.</p>
            </div>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <?php if ($ojt_record): ?>
            <!-- Tabs -->
            <div class="tabs" style="margin-top: 24px;">
                <button class="tab-btn active" data-tab="journal" onclick="switchTab('journal')"><i class="fas fa-feather-alt"></i> New Entry</button>
                <button class="tab-btn" data-tab="entries" onclick="switchTab('entries')"><i class="fas fa-list"></i> My Entries</button>
            </div>

            <!-- TAB 1: NEW ENTRY -->
            <div id="journal-tab" class="tab-content active">
                <div class="journal-container">
                    <!-- Input Section -->
                    <div class="journal-section">
                        <h3><i class="fas fa-pen-fancy"></i> Personal Journal Entry</h3>
                        <p style="color: var(--text3); font-size: 0.9rem; margin-bottom: 16px;">
                            This space is for your personal thoughts. Write what happened, how you felt, and what you learned about yourself today.
                            The assistant will structure your reflection into a clear journal entry.
                        </p>
                        
                        <form id="journalForm">

                            <div class="section-builder">
                                <div class="section-builder-heading">
                                    <i class="fas fa-sliders-h"></i> Structured Entry Helper
                                </div>
                                <p class="section-builder-text">
                                    Choose a section first, write your answer, then add it to your draft.
                                </p>

                                <div class="section-builder-grid">
                                    <div class="journal-form-group section-builder-field">
                                        <label for="entrySectionSelect">Section</label>
                                        <select id="entrySectionSelect" aria-label="Journal section selector">
                                            <option value="Tasks">What Happened Today</option>
                                            <option value="Skills">What I Learned</option>
                                            <option value="Challenges">Challenges and Emotions</option>
                                            <option value="Solutions">How I Handled It</option>
                                            <option value="Insights">Personal Insights</option>
                                        </select>
                                    </div>

                                    <div class="journal-form-group section-builder-field section-builder-note">
                                        <label for="sectionNoteInput">Your answer</label>
                                        <textarea id="sectionNoteInput" placeholder="Type your answer for the selected section..."></textarea>
                                    </div>
                                </div>

                                <div class="section-builder-actions">
                                    <button type="button" class="btn btn-secondary btn-small" onclick="appendSelectedSection()">
                                        <i class="fas fa-plus"></i> Add to Draft
                                    </button>
                                </div>

                                <div id="draftPreview" class="draft-preview">
                                    <p class="draft-empty">No sections added yet.</p>
                                </div>
                            </div>

                            <div class="journal-form-group">
                                <label for="entryVisibilitySelect">Adviser Visibility</label>
                                <select id="entryVisibilitySelect" name="entry_visibility" aria-label="Adviser visibility">
                                    <option value="1" selected>Visible to adviser/professor</option>
                                    <option value="0">Private journal (hidden from adviser/professor)</option>
                                </select>
                                <div class="visibility-helper-text">
                                    You can choose if this entry is viewable by your assigned adviser.
                                </div>
                            </div>

                            <textarea id="rawNotes" name="raw_notes" class="raw-notes-hidden" required></textarea>

                            <div class="button-group">
                                <button type="button" class="btn btn-primary" onclick="generateEntry()" id="generateBtn">
                                    <i class="fas fa-magic"></i> Generate Entry
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Preview Section -->
                    <div class="journal-section">
                        <h3><i class="fas fa-eye"></i> Preview</h3>
                        <div id="previewContainer" style="color: var(--text3); text-align: center; padding: 40px 20px;">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 12px;"></i>
                            <p>Build your draft using the helper on the left, then click "Generate Entry" to see the preview.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: MY ENTRIES -->
            <div id="entries-tab" class="tab-content">
                <div class="journal-section">
                    <h3><i class="fas fa-history"></i> Your Journal Entries</h3>
                    <p style="color: var(--text3); font-size: 0.9rem; margin-bottom: 16px;">
                        Your personal reflections and structured journal entries are listed below.
                    </p>

                    <?php if (empty($journal_entries)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text3);">
                            <i class="fas fa-book-open" style="font-size: 2rem; margin-bottom: 12px;"></i>
                            <p>No personal entries yet. Start with today's reflection above.</p>
                        </div>
                    <?php else: ?>
                        <div class="journal-entries-list" id="entriesList">
                            <?php foreach ($journal_entries as $entry): ?>
                                <?php
                                    $isVisibleToAdviser = ((int) ($entry['is_visible_to_adviser'] ?? 1) === 0) ? 0 : 1;
                                    $normalizeEntryItems = static function (array $items): array {
                                        $cleanItems = [];
                                        foreach ($items as $item) {
                                            $text = trim((string) $item);
                                            if ($text !== '') {
                                                $cleanItems[] = $text;
                                            }
                                        }
                                        return $cleanItems;
                                    };

                                    $entryPayload = [
                                        'journal_id' => (int) ($entry['journal_id'] ?? 0),
                                        'entry_date' => (string) ($entry['entry_date'] ?? ''),
                                        'company_department' => (string) ($entry['company_department'] ?? ''),
                                        'tasks_accomplished' => $normalizeEntryItems((array) ($entry['tasks_accomplished'] ?? [])),
                                        'skills_applied_learned' => $normalizeEntryItems((array) ($entry['skills_applied_learned'] ?? [])),
                                        'challenges_encountered' => $normalizeEntryItems((array) ($entry['challenges_encountered'] ?? [])),
                                        'solutions_actions_taken' => $normalizeEntryItems((array) ($entry['solutions_actions_taken'] ?? [])),
                                        'key_learnings_insights' => $normalizeEntryItems((array) ($entry['key_learnings_insights'] ?? [])),
                                        'reflection' => trim((string) ($entry['reflection'] ?? '')),
                                        'is_visible_to_adviser' => $isVisibleToAdviser,
                                    ];

                                    $summaryLabel = '';
                                    $summaryText = '';

                                    if ($entryPayload['reflection'] !== '') {
                                        $summaryLabel = 'Reflection';
                                        $summaryText = strlen($entryPayload['reflection']) > 160
                                            ? substr($entryPayload['reflection'], 0, 160) . '...'
                                            : $entryPayload['reflection'];
                                    } else {
                                        $summarySources = [
                                            ['label' => 'Insights', 'items' => $entryPayload['key_learnings_insights']],
                                            ['label' => 'Highlights', 'items' => $entryPayload['tasks_accomplished']],
                                            ['label' => 'Learnings', 'items' => $entryPayload['skills_applied_learned']],
                                            ['label' => 'Challenges', 'items' => $entryPayload['challenges_encountered']],
                                            ['label' => 'How I Handled It', 'items' => $entryPayload['solutions_actions_taken']],
                                        ];

                                        foreach ($summarySources as $summarySource) {
                                            if (!empty($summarySource['items'])) {
                                                $summaryLabel = (string) $summarySource['label'];
                                                $summaryText = implode(', ', array_slice((array) $summarySource['items'], 0, 2));
                                                if (count((array) $summarySource['items']) > 2) {
                                                    $summaryText .= '...';
                                                }
                                                break;
                                            }
                                        }
                                    }

                                    $entryPayloadJson = htmlspecialchars(
                                        rawurlencode((string) json_encode($entryPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $entryTimestamp = strtotime($entryPayload['entry_date']);
                                    $entryDateLabel = $entryTimestamp
                                        ? date('l, F j, Y', $entryTimestamp)
                                        : $entryPayload['entry_date'];
                                ?>
                                <div class="journal-entry-card" role="button" tabindex="0" data-entry="<?php echo $entryPayloadJson; ?>" onclick="expandEntry(this)" onkeydown="handleEntryCardKeydown(event, this)">
                                    <div class="entry-meta-row">
                                        <div class="entry-date">
                                            <?php echo htmlspecialchars($entryDateLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <span class="visibility-badge <?php echo $isVisibleToAdviser ? 'visibility-shared' : 'visibility-private'; ?>">
                                            <i class="fas <?php echo $isVisibleToAdviser ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                            <?php echo $isVisibleToAdviser ? 'Visible to Adviser' : 'Private'; ?>
                                        </span>
                                    </div>
                                    <div class="entry-preview-text">
                                        <?php if ($summaryLabel !== '' && $summaryText !== ''): ?>
                                            <strong><?php echo htmlspecialchars($summaryLabel, ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php else: ?>
                                            <em>Tap this entry to view full details.</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Please ensure you have an active OJT record to use the journal assistant.
            </div>
        <?php endif; ?>
    </div>

    <div id="entryDetailModal" class="entry-modal" aria-hidden="true">
        <div class="entry-modal-backdrop" onclick="closeEntryModal()"></div>
        <div class="entry-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="entryModalTitle">
            <div class="entry-modal-header">
                <h3 id="entryModalTitle" class="entry-modal-title"><i class="fas fa-book-open"></i> Journal Entry Details</h3>
                <button type="button" class="entry-modal-close" onclick="closeEntryModal()" aria-label="Exit entry details">
                    <i class="fas fa-times"></i> Exit
                </button>
            </div>
            <div id="entryModalBody" class="entry-modal-body"></div>
        </div>
    </div>

    <div id="entrySaveSuccessModal" class="entry-modal" aria-hidden="true">
        <div class="entry-modal-backdrop" onclick="closeSaveSuccessModal()"></div>
        <div class="entry-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="entrySaveSuccessTitle">
            <div class="entry-modal-header">
                <h3 id="entrySaveSuccessTitle" class="entry-modal-title"><i class="fas fa-check-circle"></i> Journal Entry Saved</h3>
                <button type="button" class="entry-modal-close" onclick="closeSaveSuccessModal()" aria-label="Close saved entry modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div id="entrySaveSuccessBody" class="entry-modal-body"></div>
        </div>
    </div>

    <script>
        const baseUrl = '<?php echo $baseUrl; ?>';
        let latestSavedJournalId = null;
        let sectionDraftItems = [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[char] || char);
        }

        function normalizeArrayField(value) {
            if (Array.isArray(value)) {
                return value.map(item => String(item ?? '').trim()).filter(Boolean);
            }

            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (!trimmed) {
                    return [];
                }

                try {
                    const parsed = JSON.parse(trimmed);
                    if (Array.isArray(parsed)) {
                        return parsed.map(item => String(item ?? '').trim()).filter(Boolean);
                    }
                } catch (_error) {
                    return [trimmed];
                }

                return [trimmed];
            }

            return [];
        }

        function formatEntryDate(entryDate, withWeekday = false) {
            const rawValue = String(entryDate ?? '').trim();
            if (!rawValue) {
                return '';
            }

            const parsedDate = new Date(rawValue);
            if (Number.isNaN(parsedDate.getTime())) {
                return rawValue;
            }

            const formatOptions = withWeekday
                ? { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
                : { year: 'numeric', month: 'short', day: 'numeric' };

            return parsedDate.toLocaleDateString('en-US', formatOptions);
        }

        function buildEntrySummary(entry) {
            const reflection = String(entry.reflection || '').trim();
            if (reflection) {
                return {
                    label: 'Reflection',
                    text: reflection.length > 160 ? reflection.slice(0, 160) + '...' : reflection
                };
            }

            const summarySources = [
                { label: 'Insights', items: normalizeArrayField(entry.key_learnings_insights) },
                { label: 'Highlights', items: normalizeArrayField(entry.tasks_accomplished) },
                { label: 'Learnings', items: normalizeArrayField(entry.skills_applied_learned) },
                { label: 'Challenges', items: normalizeArrayField(entry.challenges_encountered) },
                { label: 'How I Handled It', items: normalizeArrayField(entry.solutions_actions_taken) }
            ];

            for (const source of summarySources) {
                if (source.items.length > 0) {
                    const text = source.items.slice(0, 2).join(', ') + (source.items.length > 2 ? '...' : '');
                    return { label: source.label, text };
                }
            }

            return null;
        }

        function getEntrySummaryMarkup(entry) {
            const summary = buildEntrySummary(entry);
            if (!summary) {
                return '<em>Tap this entry to view full details.</em>';
            }

            return `<strong>${escapeHtml(summary.label)}:</strong> ${escapeHtml(summary.text)}`;
        }

        function encodeEntryPayload(entry) {
            try {
                return encodeURIComponent(JSON.stringify(entry));
            } catch (_error) {
                return '';
            }
        }

        function decodeEntryPayload(encodedPayload) {
            try {
                const decoded = decodeURIComponent(String(encodedPayload || ''));
                const parsed = JSON.parse(decoded);
                return (parsed && typeof parsed === 'object') ? parsed : null;
            } catch (_error) {
                return null;
            }
        }

        function getAdviserVisibilitySetting() {
            const visibilitySelect = document.getElementById('entryVisibilitySelect');
            if (!visibilitySelect) {
                return 1;
            }

            return String(visibilitySelect.value) === '0' ? 0 : 1;
        }

        function getVisibilityBadgeMarkup(isVisibleToAdviser) {
            if (isVisibleToAdviser) {
                return '<span class="visibility-badge visibility-shared"><i class="fas fa-eye"></i> Visible to Adviser</span>';
            }

            return '<span class="visibility-badge visibility-private"><i class="fas fa-eye-slash"></i> Private</span>';
        }

        function syncRawNotesFromDraft() {
            const rawNotes = document.getElementById('rawNotes');
            if (!rawNotes) {
                return;
            }

            rawNotes.value = sectionDraftItems
                .map((item) => `${item.key}: ${item.text}`)
                .join('\n');
        }

        function renderDraftPreview() {
            const draftPreview = document.getElementById('draftPreview');
            if (!draftPreview) {
                return;
            }

            if (sectionDraftItems.length === 0) {
                draftPreview.innerHTML = '<p class="draft-empty">No sections added yet.</p>';
                return;
            }

            draftPreview.innerHTML = sectionDraftItems.map((item) => `
                <div class="draft-item">
                    <span class="draft-badge">${escapeHtml(item.label)}</span>
                    <span class="draft-text">${escapeHtml(item.text)}</span>
                </div>
            `).join('');
        }

        function appendSelectedSection() {
            const sectionSelect = document.getElementById('entrySectionSelect');
            const sectionNoteInput = document.getElementById('sectionNoteInput');

            if (!sectionSelect || !sectionNoteInput) {
                return;
            }

            const selectedSection = String(sectionSelect.value || '').trim();
            const selectedLabel = String(sectionSelect.options[sectionSelect.selectedIndex]?.text || selectedSection).trim();
            const sectionNote = sectionNoteInput.value.trim();

            if (!sectionNote) {
                alert('Please write your answer before adding it to the draft.');
                sectionNoteInput.focus();
                return;
            }

            const normalizedNote = sectionNote.replace(/\s+/g, ' ').trim();
            sectionDraftItems.push({
                key: selectedSection,
                label: selectedLabel,
                text: normalizedNote
            });

            syncRawNotesFromDraft();
            renderDraftPreview();

            sectionNoteInput.value = '';
            sectionNoteInput.focus();
        }

        function initializeSectionBuilder() {
            const sectionNoteInput = document.getElementById('sectionNoteInput');
            if (sectionNoteInput) {
                sectionNoteInput.addEventListener('keydown', (event) => {
                    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                        event.preventDefault();
                        appendSelectedSection();
                    }
                });
            }

            const journalForm = document.getElementById('journalForm');
            if (journalForm) {
                journalForm.addEventListener('reset', () => {
                    window.setTimeout(() => {
                        const sectionSelect = document.getElementById('entrySectionSelect');
                        const sectionInput = document.getElementById('sectionNoteInput');

                        if (sectionSelect) {
                            sectionSelect.selectedIndex = 0;
                        }

                        if (sectionInput) {
                            sectionInput.value = '';
                        }

                        const visibilitySelect = document.getElementById('entryVisibilitySelect');
                        if (visibilitySelect) {
                            visibilitySelect.value = '1';
                        }

                        sectionDraftItems = [];
                        syncRawNotesFromDraft();
                        renderDraftPreview();
                    }, 0);
                });
            }

            syncRawNotesFromDraft();
            renderDraftPreview();
        }

        initializeSectionBuilder();

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.toggle('active', el.dataset.tab === tabName);
            });
            
            // Show selected tab
            const tabEl = document.getElementById(tabName + '-tab');
            if (tabEl) {
                tabEl.classList.add('active');
            }
            
            // Load data for specific tabs
            if (tabName === 'entries') {
                loadMyEntries();
            }
        }

        function loadMyEntries() {
            const entriesList = document.getElementById('entriesList');
            if (!entriesList) return;

            entriesList.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text3);"><span class="loading-spinner"></span> Loading entries...</div>';
            
            const formData = new FormData();
            formData.append('action', 'load_entries');
            formData.append('limit', 50);

            fetch(baseUrl + '/pages/student/ojt-log/journal_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.entries && data.entries.length > 0) {
                    let html = '';
                    data.entries.forEach(entry => {
                        const isVisibleToAdviser = Number(entry.is_visible_to_adviser ?? 1) !== 0;
                        const normalizedEntry = {
                            journal_id: Number(entry.journal_id || 0),
                            entry_date: String(entry.entry_date || ''),
                            company_department: String(entry.company_department || ''),
                            tasks_accomplished: normalizeArrayField(entry.tasks_accomplished),
                            skills_applied_learned: normalizeArrayField(entry.skills_applied_learned),
                            challenges_encountered: normalizeArrayField(entry.challenges_encountered),
                            solutions_actions_taken: normalizeArrayField(entry.solutions_actions_taken),
                            key_learnings_insights: normalizeArrayField(entry.key_learnings_insights),
                            reflection: String(entry.reflection || '').trim(),
                            is_visible_to_adviser: isVisibleToAdviser ? 1 : 0
                        };

                        const dateLabel = formatEntryDate(normalizedEntry.entry_date, true) || String(normalizedEntry.entry_date || '');
                        const entrySummaryMarkup = getEntrySummaryMarkup(normalizedEntry);
                        const encodedEntryPayload = encodeEntryPayload(normalizedEntry);
                        const visibilityBadge = getVisibilityBadgeMarkup(isVisibleToAdviser);

                        html += `<div class="journal-entry-card" role="button" tabindex="0" data-entry="${encodedEntryPayload}" onclick="expandEntry(this)" onkeydown="handleEntryCardKeydown(event, this)">
                            <div class="entry-meta-row">
                                <div class="entry-date">${escapeHtml(dateLabel)}</div>
                                ${visibilityBadge}
                            </div>
                            <div class="entry-preview-text">${entrySummaryMarkup}</div>
                        </div>`;
                    });
                    entriesList.innerHTML = html;
                } else {
                    entriesList.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text3);"><i class="fas fa-book-open" style="font-size:2rem;margin-bottom:12px;"></i><p>No personal entries yet. Start with your reflection above.</p></div>';
                }
            })
            .catch(err => {
                console.error(err);
                entriesList.innerHTML = '<p style="color:red;">Error loading entries: ' + escapeHtml(err.message) + '</p>';
            });
        }

        function generateEntry() {
            const rawNotes = document.getElementById('rawNotes').value.trim();
            
            if (!rawNotes) {
                alert('Please add at least one section to your draft first.');
                return;
            }

            const btn = document.getElementById('generateBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading-spinner"></span> Generating...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'generate_entry');
            formData.append('raw_notes', rawNotes);

            fetch(baseUrl + '/pages/student/ojt-log/journal_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    displayEntryPreview(data.entry);
                    addSaveButton(data.entry);
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate entry'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred: ' + err.message);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function displayEntryPreview(entry) {
            const preview = document.getElementById('previewContainer');

            const renderItems = (items) => {
                const normalizedItems = normalizeArrayField(items);
                return normalizedItems.map((item) => `
                    <div class="entry-item">
                        <span class="entry-item-bullet">▸</span>
                        <span class="entry-item-text">${escapeHtml(item)}</span>
                    </div>
                `).join('');
            };

            const sectionBlock = (title, body) => `
                <div class="entry-section">
                    <div class="entry-section-title">${escapeHtml(title)}</div>
                    <div class="entry-section-content">${body}</div>
                </div>
            `;

            const sectionItems = [
                { title: 'What Happened Today', items: normalizeArrayField(entry.tasks_accomplished) },
                { title: 'What I Learned', items: normalizeArrayField(entry.skills_applied_learned) },
                { title: 'Challenges and Emotions', items: normalizeArrayField(entry.challenges_encountered) },
                { title: 'How I Handled It', items: normalizeArrayField(entry.solutions_actions_taken) },
                { title: 'Personal Insights', items: normalizeArrayField(entry.key_learnings_insights) }
            ];

            let html = '<div class="journal-entry-preview">';

            if (entry.company_department) {
                html += `<div class="entry-context-row"><div class="entry-context-pill"><span class="entry-context-label">Context:</span> <span class="entry-context-value">${escapeHtml(entry.company_department)}</span></div></div>`;
            }

            const visibleSections = sectionItems.filter((section) => section.items.length > 0);
            if (visibleSections.length === 0) {
                html += '<p class="entry-empty">No section content generated yet.</p>';
            } else {
                visibleSections.forEach((section) => {
                    html += sectionBlock(section.title, renderItems(section.items));
                });
            }

            html += '</div>';

            preview.innerHTML = html;
        }

        function addSaveButton(entry) {
            const container = document.getElementById('previewContainer');
            
            // Calculate quality score
            const quality = calculateEntryQuality(entry);
            const sentiment = analyzeSentiment(Object.values(entry).join(' '));
            
            // Store quality and sentiment on entry object for persistence
            entry.quality_score = quality.overall;
            entry.sentiment_analysis = sentiment.label;
            entry.productivity_score = Math.round(50 + (quality.overall / 2)); // Scale to 50-100
            entry.is_visible_to_adviser = getAdviserVisibilitySetting();
            const isVisibleToAdviser = Number(entry.is_visible_to_adviser) !== 0;
            
            // Add buttons container
            let buttonsHtml = '<div class="button-group preview-actions">';
            
            // Save button - use data attribute instead of onclick parameter
            buttonsHtml += '<button type="button" class="btn btn-success save-entry-btn" data-entry="' + JSON.stringify(entry).replace(/"/g, '&quot;') + '">';
            buttonsHtml += '<i class="fas fa-save"></i> Save This Entry';
            buttonsHtml += '</button>';
            
            buttonsHtml += '</div>';
            buttonsHtml += '<p class="preview-visibility-note">';
            if (isVisibleToAdviser) {
                buttonsHtml += '<i class="fas fa-user-check"></i> This journal will be visible to your assigned adviser/professor.';
            } else {
                buttonsHtml += '<i class="fas fa-user-lock"></i> This journal is private and will stay hidden from your adviser/professor.';
            }
            buttonsHtml += '</p>';
            
            container.innerHTML += buttonsHtml;
            
            // Add event listener to save button
            const saveBtn = container.querySelector('.save-entry-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    const entryJson = this.getAttribute('data-entry');
                    const entryData = JSON.parse(entryJson.replace(/&quot;/g, '"'));
                    saveEntry(entryData);
                });
            }
        }

        function calculateEntryQuality(entry) {
            let score = 0;
            let breakdown = {};
            
            // Completeness
            let completeness = 0;
            if (entry.tasks_accomplished && entry.tasks_accomplished.length > 0) completeness += 25;
            if (entry.skills_applied_learned && entry.skills_applied_learned.length > 0) completeness += 25;
            if (entry.challenges_encountered && entry.challenges_encountered.length > 0) completeness += 25;
            if (entry.reflection && entry.reflection.length > 50) completeness += 25;
            breakdown.completeness = completeness;
            
            // Detail level
            const totalWords = Object.values(entry).join(' ').split(/\s+/).length;
            const detailLevel = Math.min(100, (totalWords / 2));
            breakdown.detailLevel = detailLevel;
            
            // Reflection quality
            let reflectionQuality = 0;
            if (entry.reflection) {
                const insightWords = ['learned', 'realized', 'important', 'valuable', 'growth', 'develop', 'professional', 'skill', 'confidence'];
                const reflectionLower = entry.reflection.toLowerCase();
                const insightCount = insightWords.filter(w => reflectionLower.includes(w)).length;
                reflectionQuality = Math.min(100, (insightCount / 5) * 100);
            }
            breakdown.reflection = reflectionQuality;
            
            score = (breakdown.completeness + breakdown.detailLevel + breakdown.reflection) / 3;
            
            let level = 'Basic';
            if (score >= 80) level = 'Excellent';
            else if (score >= 60) level = 'Good';
            else if (score >= 40) level = 'Fair';
            
            return { overall: Math.round(score), level: level, breakdown: breakdown };
        }

        function analyzeSentiment(text) {
            const textLower = text.toLowerCase();
            const positiveWords = ['excellent', 'great', 'fantastic', 'amazing', 'successfully', 'achieved', 'learned', 'mastered', 'improved', 'confident'];
            const negativeWords = ['failed', 'difficult', 'frustrated', 'confused', 'stuck', 'problem', 'issue', 'error'];
            
            const positiveCount = positiveWords.filter(w => textLower.includes(w)).length;
            const negativeCount = negativeWords.filter(w => textLower.includes(w)).length;
            
            const netSentiment = positiveCount - negativeCount;
            
            let label = 'neutral';
            if (netSentiment > 3) label = 'very_positive';
            else if (netSentiment > 1) label = 'positive';
            else if (netSentiment < -3) label = 'very_negative';
            else if (netSentiment < -1) label = 'negative';
            
            return { label: label, score: netSentiment };
        }

        function saveEntryAfterPreview(entry) {
            // Deprecated: Use event listener instead
            saveEntry(entry);
        }

        function saveEntry(entry) {
            // Create a temporary button element to track loading state
            const btn = document.querySelector('.save-entry-btn');
            if (!btn) return;
            
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading-spinner"></span> Saving...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'save_entry');
            formData.append('entry_data', JSON.stringify(entry));

            fetch(baseUrl + '/pages/student/ojt-log/journal_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    latestSavedJournalId = Number(data.journal_id || 0) || null;
                    openSaveSuccessModal();
                    document.getElementById('journalForm').reset();
                    document.getElementById('previewContainer').innerHTML = 
                        '<div style="text-align: center; padding: 40px 20px; color: var(--text3);"><i class="fas fa-check-circle" style="font-size: 2rem; color: #12b3ac; margin-bottom: 12px;"></i><p>Entry saved! Generate another entry or view your journal.</p></div>';
                    // Refresh entries list
                    setTimeout(() => loadMyEntries(), 500);
                } else {
                    alert('Error saving entry: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred: ' + err.message);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function buildEntrySectionsMarkup(entry) {
            const sections = [
                { title: 'What Happened Today', items: normalizeArrayField(entry.tasks_accomplished) },
                { title: 'What I Learned', items: normalizeArrayField(entry.skills_applied_learned) },
                { title: 'Challenges and Emotions', items: normalizeArrayField(entry.challenges_encountered) },
                { title: 'How I Handled It', items: normalizeArrayField(entry.solutions_actions_taken) },
                { title: 'Personal Insights', items: normalizeArrayField(entry.key_learnings_insights) }
            ];

            const reflection = String(entry.reflection || '').trim();
            let html = '';
            let hasAnyContent = false;

            sections.forEach((section) => {
                if (section.items.length === 0) {
                    return;
                }

                hasAnyContent = true;
                html += '<div class="entry-section">';
                html += `<div class="entry-section-title">${escapeHtml(section.title)}</div>`;
                html += '<div class="entry-section-content">';
                section.items.forEach((item) => {
                    html += `
                        <div class="entry-item">
                            <span class="entry-item-bullet">▸</span>
                            <span class="entry-item-text">${escapeHtml(item)}</span>
                        </div>
                    `;
                });
                html += '</div>';
                html += '</div>';
            });

            if (reflection) {
                hasAnyContent = true;
                html += `
                    <div class="entry-section">
                        <div class="entry-section-title">Reflection</div>
                        <div class="entry-section-content">${escapeHtml(reflection)}</div>
                    </div>
                `;
            }

            if (!hasAnyContent) {
                html += '<p class="entry-empty">No section details were recorded for this entry.</p>';
            }

            return html;
        }

        function openSaveSuccessModal() {
            const modal = document.getElementById('entrySaveSuccessModal');
            const modalBody = document.getElementById('entrySaveSuccessBody');
            if (!modal || !modalBody) {
                return;
            }

            modalBody.innerHTML =
                '<div style="text-align:center;padding:22px 12px;">'
                + '<i class="fas fa-circle-check" style="font-size:2.15rem;color:#12b3ac;margin-bottom:10px;"></i>'
                + '<p style="margin:0;color:#0f172a;font-weight:700;font-size:1rem;">Entry saved successfully.</p>'
                + '<p style="margin:10px 0 0;color:#64748b;font-size:.88rem;">You can create another entry or view your saved journal entries.</p>'
                + '</div>';

            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeSaveSuccessModal() {
            const modal = document.getElementById('entrySaveSuccessModal');
            if (!modal) {
                return;
            }

            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function openEntryModal(entry) {
            const modal = document.getElementById('entryDetailModal');
            const modalBody = document.getElementById('entryModalBody');
            if (!modal || !modalBody) {
                return;
            }

            const isVisibleToAdviser = Number(entry.is_visible_to_adviser ?? 1) !== 0;
            const dateLabel = formatEntryDate(entry.entry_date, true) || String(entry.entry_date || '');

            let modalHtml = '<div class="entry-modal-meta">';
            modalHtml += `<div class="entry-modal-date">${escapeHtml(dateLabel)}</div>`;
            modalHtml += getVisibilityBadgeMarkup(isVisibleToAdviser);
            modalHtml += '</div>';

            if (String(entry.company_department || '').trim() !== '') {
                modalHtml += `<div class="entry-modal-context"><strong>Context:</strong> ${escapeHtml(String(entry.company_department || '').trim())}</div>`;
            }

            modalHtml += '<div class="journal-entry-preview">';
            modalHtml += buildEntrySectionsMarkup(entry);
            modalHtml += '</div>';

            modalBody.innerHTML = modalHtml;
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeEntryModal() {
            const modal = document.getElementById('entryDetailModal');
            if (!modal) {
                return;
            }

            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function handleEntryCardKeydown(event, element) {
            if (!event || !element) {
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                expandEntry(element);
            }
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            const saveSuccessModal = document.getElementById('entrySaveSuccessModal');
            if (saveSuccessModal && saveSuccessModal.classList.contains('active')) {
                closeSaveSuccessModal();
                return;
            }

            const modal = document.getElementById('entryDetailModal');
            if (modal && modal.classList.contains('active')) {
                closeEntryModal();
            }
        });

        function expandEntry(element) {
            if (!element) {
                return;
            }

            const payload = decodeEntryPayload(element.getAttribute('data-entry'));
            if (!payload) {
                return;
            }

            openEntryModal(payload);
        }
    </script>
</body>
</html>
