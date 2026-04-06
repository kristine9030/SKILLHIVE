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
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .journal-section h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text1);
        }

        .journal-form-group {
            margin-bottom: 20px;
        }

        .journal-form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text2);
        }

        .journal-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 120px;
        }

        .journal-form-group textarea:focus {
            outline: none;
            border-color: #06B6D4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #06B6D4, #0891B2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(6, 182, 212, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: var(--text1);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .journal-entry-preview {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-top: 16px;
        }

        .entry-section {
            margin-bottom: 20px;
        }

        .entry-section-title {
            font-weight: 700;
            color: #0891B2;
            font-size: 0.95rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .entry-section-content {
            color: var(--text2);
            line-height: 1.6;
        }

        .entry-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }

        .entry-item-bullet {
            color: #06B6D4;
            font-weight: 700;
            flex-shrink: 0;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(6, 182, 212, 0.3);
            border-top-color: #06B6D4;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
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
        }

        .journal-entry-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .journal-entry-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-color: #06B6D4;
        }

        .entry-date {
            font-weight: 700;
            color: #0891B2;
            font-size: 0.95rem;
        }

        .entry-date-human {
            font-size: 0.85rem;
            color: var(--text3);
            margin-top: 4px;
        }

        .entry-preview-text {
            margin-top: 12px;
            color: var(--text2);
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .entry-preview-text strong {
            color: var(--text1);
        }

        .tabs {
            display: flex;
            gap: 12px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 24px;
        }

        .tab-btn {
            padding: 12px 16px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text3);
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #06B6D4;
            border-bottom-color: #06B6D4;
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
            border-bottom: 1px solid var(--border);
        }

        .report-section:last-child {
            border-bottom: none;
        }

        .report-section h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0891B2;
            margin-bottom: 12px;
        }

        .report-section p {
            color: var(--text2);
            line-height: 1.8;
            text-align: justify;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #06B6D4;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text3);
            margin-top: 4px;
        }

        .quality-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 12px;
        }

        .quality-excellent {
            background: #dcfce7;
            color: #166534;
        }

        .quality-good {
            background: #dbeafe;
            color: #1e40af;
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
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
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

        .export-menu {
            position: relative;
            display: inline-block;
        }

        .export-dropdown {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 180px;
            top: 100%;
            right: 0;
            margin-top: 4px;
        }

        .export-dropdown.active {
            display: block;
        }

        .export-option {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .export-option:last-child {
            border-bottom: none;
        }

        .export-option:hover {
            background: #f9fafb;
            color: #06B6D4;
        }

        .metric-box {
            display: inline-block;
            background: #f0f9fc;
            border: 1px solid #cffafe;
            border-radius: 6px;
            padding: 8px 12px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #0891B2;
        }
    </style>
</head>
<body>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h2 class="page-title"><i class="fas fa-book"></i> Internship Journal Assistant</h2>
                <p class="page-subtitle">Transform your daily notes into professional, structured journal entries</p>
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
                <button class="tab-btn active" onclick="switchTab('journal')"><i class="fas fa-feather-alt"></i> New Entry</button>
                <button class="tab-btn" onclick="switchTab('entries')"><i class="fas fa-list"></i> My Entries</button>
                <button class="tab-btn" onclick="switchTab('report')"><i class="fas fa-file-pdf"></i> Final Report</button>
            </div>

            <!-- TAB 1: NEW ENTRY -->
            <div id="journal-tab" class="tab-content active">
                <div class="journal-container">
                    <!-- Input Section -->
                    <div class="journal-section">
                        <h3><i class="fas fa-pen-fancy"></i> Input Raw Notes</h3>
                        <p style="color: var(--text3); font-size: 0.9rem; margin-bottom: 16px;">
                            Write down your daily internship notes as naturally as you would. Our AI assistant will automatically 
                            structure them into a professional journal entry.
                        </p>
                        
                        <form id="journalForm">
                            <div class="journal-form-group">
                                <label for="rawNotes">What did you accomplish today?</label>
                                <textarea id="rawNotes" name="raw_notes" placeholder="Example:&#10;- Worked on the login module, fixed the JWT token validation bug&#10;- Learned about implementing OAuth2 authentication&#10;- Found it challenging to debug the async/await issues at first&#10;- Collaborator helped me understand the problem better&#10;- Realized the importance of proper error handling&#10;- Great day, feeling more confident!" required></textarea>
                            </div>

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
                            <p>Enter your notes above and click "Generate Entry" to see the preview.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: MY ENTRIES -->
            <div id="entries-tab" class="tab-content">
                <div class="journal-section">
                    <h3><i class="fas fa-history"></i> Your Journal Entries</h3>
                    <p style="color: var(--text3); font-size: 0.9rem; margin-bottom: 16px;">
                        All your professionally structured journal entries are listed below.
                    </p>

                    <?php if (empty($journal_entries)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text3);">
                            <i class="fas fa-book-open" style="font-size: 2rem; margin-bottom: 12px;"></i>
                            <p>No journal entries yet. Generate your first entry above!</p>
                        </div>
                    <?php else: ?>
                        <div class="journal-entries-list" id="entriesList">
                            <?php foreach ($journal_entries as $entry): ?>
                                <div class="journal-entry-card" onclick="expandEntry(this)">
                                    <div class="entry-date">
                                        <?php echo date('l, F j, Y', strtotime($entry['entry_date'])); ?>
                                    </div>
                                    <div class="entry-preview-text">
                                        <?php if (!empty($entry['tasks_accomplished'])): ?>
                                            <strong>Tasks:</strong> <?php echo implode(', ', array_slice($entry['tasks_accomplished'], 0, 2)); ?>...
                                        <?php else: ?>
                                            <em>No tasks recorded</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: FINAL REPORT -->
            <div id="report-tab" class="tab-content">
                <div class="journal-section" style="grid-column: 1 / -1;">
                    <h3><i class="fas fa-file-pdf"></i> Internship Final Report</h3>
                    <p style="color: var(--text3); font-size: 0.9rem; margin-bottom: 16px;">
                        Generate a comprehensive summary of your entire internship experience based on all your journal entries.
                    </p>

                    <button class="btn btn-success" onclick="generateReport()">
                        <i class="fas fa-file-export"></i> Generate Final Report
                    </button>

                    <div id="reportContainer" style="margin-top: 24px; display: none;">
                        <!-- Report will be loaded here -->
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Please ensure you have an active OJT record to use the journal assistant.
            </div>
        <?php endif; ?>
    </div>

    <script>
        const baseUrl = '<?php echo $baseUrl; ?>';
        
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            const tabEl = document.getElementById(tabName + '-tab');
            if (tabEl) {
                tabEl.classList.add('active');
            }
            
            // Update button state
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                if (btn.textContent.includes(tabName === 'journal' ? 'New Entry' : tabName === 'entries' ? 'My Entries' : 'Final Report')) {
                    btn.classList.add('active');
                }
            });
            
            // Load data for specific tabs
            if (tabName === 'entries') {
                loadMyEntries();
            } else if (tabName === 'report') {
                loadReport();
            }
        }

        function loadMyEntries() {
            const container = document.getElementById('entries-tab');
            if (!container) return;
            
            const btn = document.createElement('button');
            btn.className = 'btn btn-primary';
            btn.innerHTML = '<span class="loading-spinner"></span> Loading entries...';
            container.innerHTML = '';
            container.appendChild(btn);
            
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
                    let html = '<div class="entries-list">';
                    data.entries.forEach(entry => {
                        const date = new Date(entry.entry_date).toLocaleDateString('en-US', { 
                            year: 'numeric', month: 'short', day: 'numeric' 
                        });
                        const qualityClass = entry.quality_score >= 80 ? 'excellent' : 
                                            entry.quality_score >= 60 ? 'good' :
                                            entry.quality_score >= 40 ? 'fair' : 'basic';
                        html += `<div class="entry-card">
                            <div class="entry-header">
                                <span class="entry-date">${date}</span>
                                <span class="quality-badge quality-${qualityClass}">Quality: ${entry.quality_score}%</span>
                            </div>`;
                        
                        if (entry.tasks_accomplished) {
                            const tasks = JSON.parse(entry.tasks_accomplished || '[]');
                            if (tasks.length > 0) {
                                html += `<div class="entry-preview"><strong>Tasks:</strong> ${tasks.slice(0, 2).join(', ')}</div>`;
                            }
                        }
                        
                        html += '</div>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p style="text-align: center; color: var(--text3); padding: 40px;">No entries yet. Create your first entry above!</p>';
                }
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<p style="color: red;">Error loading entries: ' + err.message + '</p>';
            });
        }

        function loadReport() {
            const container = document.getElementById('report-tab');
            if (!container) return;
            
            const btn = document.createElement('button');
            btn.className = 'btn btn-primary';
            btn.innerHTML = '<span class="loading-spinner"></span> Loading report...';
            container.innerHTML = '';
            container.appendChild(btn);
            
            const formData = new FormData();
            formData.append('action', 'load_report');

            fetch(baseUrl + '/pages/student/ojt-log/journal_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.report) {
                    displayReport(data.report);
                } else {
                    container.innerHTML = '<button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-cog"></i> Generate Final Report</button>';
                }
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<p style="color: red;">Error loading report: ' + err.message + '</p>';
            });
        }

        function generateEntry() {
            const rawNotes = document.getElementById('rawNotes').value.trim();
            
            if (!rawNotes) {
                alert('Please enter your daily notes first.');
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
            
            let html = '';
            
            if (entry.company_department) {
                html += `<div class="entry-section">
                    <div class="entry-section-title">Company/Department</div>
                    <div class="entry-section-content">${entry.company_department}</div>
                </div>`;
            }

            html += `<div class="entry-section">
                <div class="entry-section-title">Tasks Accomplished</div>
                <div class="entry-section-content">`;
            if (Array.isArray(entry.tasks_accomplished) && entry.tasks_accomplished.length > 0) {
                entry.tasks_accomplished.forEach(task => {
                    html += `<div class="entry-item"><span class="entry-item-bullet">▸</span><span>${task}</span></div>`;
                });
            } else {
                html += '<em>No specific tasks identified</em>';
            }
            html += `</div></div>`;

            html += `<div class="entry-section">
                <div class="entry-section-title">Skills Applied or Learned</div>
                <div class="entry-section-content">`;
            if (Array.isArray(entry.skills_applied_learned) && entry.skills_applied_learned.length > 0) {
                entry.skills_applied_learned.forEach(skill => {
                    html += `<div class="entry-item"><span class="entry-item-bullet">▸</span><span>${skill}</span></div>`;
                });
            } else {
                html += '<em>No specific skills identified</em>';
            }
            html += `</div></div>`;

            if (Array.isArray(entry.challenges_encountered) && entry.challenges_encountered.length > 0) {
                html += `<div class="entry-section">
                    <div class="entry-section-title">Challenges Encountered</div>
                    <div class="entry-section-content">`;
                entry.challenges_encountered.forEach(challenge => {
                    html += `<div class="entry-item"><span class="entry-item-bullet">▸</span><span>${challenge}</span></div>`;
                });
                html += `</div></div>`;
            }

            if (Array.isArray(entry.solutions_actions_taken) && entry.solutions_actions_taken.length > 0) {
                html += `<div class="entry-section">
                    <div class="entry-section-title">Solutions/Actions Taken</div>
                    <div class="entry-section-content">`;
                entry.solutions_actions_taken.forEach(solution => {
                    html += `<div class="entry-item"><span class="entry-item-bullet">▸</span><span>${solution}</span></div>`;
                });
                html += `</div></div>`;
            }

            html += `<div class="entry-section">
                <div class="entry-section-title">Key Learnings/Insights</div>
                <div class="entry-section-content">`;
            if (Array.isArray(entry.key_learnings_insights) && entry.key_learnings_insights.length > 0) {
                entry.key_learnings_insights.forEach(insight => {
                    html += `<div class="entry-item"><span class="entry-item-bullet">▸</span><span>${insight}</span></div>`;
                });
            } else {
                html += '<em>No specific insights identified</em>';
            }
            html += `</div></div>`;

            if (entry.reflection) {
                html += `<div class="entry-section">
                    <div class="entry-section-title">Reflection</div>
                    <div class="entry-section-content">${entry.reflection}</div>
                </div>`;
            }

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
            
            // Add quality and sentiment indicators
            let indicatorsHtml = '<div style="margin-top: 20px; margin-bottom: 20px;">';
            
            // Quality indicator
            indicatorsHtml += `<div class="quality-indicator quality-${quality.level.toLowerCase()}">
                <i class="fas fa-star"></i>
                <span>${quality.level} Quality (${quality.overall}%)</span>
            </div>`;
            
            // Sentiment indicator
            indicatorsHtml += `<div class="sentiment-indicator sentiment-${sentiment.label}">
                <i class="fas fa-face-smile"></i>
                <span>${sentiment.label.replace('_', ' ').toUpperCase()}</span>
            </div>`;
            
            indicatorsHtml += '</div>';
            
            // Add buttons container
            let buttonsHtml = '<div class="button-group" style="margin-top: 20px; flex-wrap: wrap;">';
            
            // Save button - use data attribute instead of onclick parameter
            buttonsHtml += '<button type="button" class="btn btn-success save-entry-btn" data-entry="' + JSON.stringify(entry).replace(/"/g, '&quot;') + '">';
            buttonsHtml += '<i class="fas fa-save"></i> Save This Entry';
            buttonsHtml += '</button>';
            
            // Export menu
            buttonsHtml += '<div class="export-menu">';
            buttonsHtml += '<button type="button" class="btn btn-secondary" onclick="toggleExportMenu()">';
            buttonsHtml += '<i class="fas fa-envelope"></i> Export <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 4px;"></i>';
            buttonsHtml += '</button>';
            buttonsHtml += '<div id="exportDropdown" class="export-dropdown">';
            buttonsHtml += '<div class="export-option" onclick="exportEntryEmail()"><i class="fas fa-envelope"></i> Email Entry</div>';
            buttonsHtml += '</div>';
            buttonsHtml += '</div>';
            
            buttonsHtml += '</div>';
            
            container.innerHTML += indicatorsHtml + buttonsHtml;
            
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

        function toggleExportMenu() {
            const dropdown = document.getElementById('exportDropdown');
            dropdown.classList.toggle('active');
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

        function exportEntryEmail() {
            // Get the most recently saved entry from display or use journal ID
            const email = prompt('Enter recipient email address:');
            if (!email) return;
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // For now, show note about future implementation
            alert('Note: You can export entries after viewing them in "My Entries" tab.');
        }

        function exportReportEmail() {
            const email = prompt('Enter recipient email address:');
            if (!email) return;
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            const btn = document.querySelector('.btn-primary');
            if (btn) btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'export_report_email');
            formData.append('recipient_email', email);

            fetch(baseUrl + '/pages/student/ojt-log/journal_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    alert('Report sent successfully to ' + email);
                } else {
                    alert('Error: ' + (data.message || 'Failed to send email'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred: ' + err.message);
            })
            .finally(() => {
                if (btn) btn.disabled = false;
            });
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
                    alert('Entry saved successfully! Journal ID: ' + data.journal_id);
                    document.getElementById('journalForm').reset();
                    document.getElementById('previewContainer').innerHTML = 
                        '<div style="text-align: center; padding: 40px 20px; color: var(--text3);"><i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981; margin-bottom: 12px;"></i><p>Entry saved! Generate another entry or view your journal.</p></div>';
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

        function generateReport() {
            const container = document.getElementById('report-tab');
            if (!container) return;
            
            const btn = document.createElement('button');
            btn.className = 'btn btn-primary';
            btn.innerHTML = '<span class="loading-spinner"></span> Generating Report...';
            container.innerHTML = '';
            container.appendChild(btn);

            const formData = new FormData();
            formData.append('action', 'generate_report');

            fetch(baseUrl + '/pages/student/ojt-log/journal_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    displayReport(data.report, container);
                } else {
                    alert('Error generating report: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred: ' + err.message);
            });
        }

        function displayReport(report, containerEl) {
            const container = containerEl || document.getElementById('report-tab') || document.getElementById('reportContainer');
            if (!container) return;
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">${report.duration_days || 0}</div>
                        <div class="stat-label">Days</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${report.total_journal_entries || 0}</div>
                        <div class="stat-label">Entries</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${(report.hours_completed || 0).toFixed(1)}</div>
                        <div class="stat-label">Hours</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${Math.round(((report.hours_completed || 0) / (report.hours_required || 1)) * 100)}%</div>
                        <div class="stat-label">Complete</div>
                    </div>
                </div>
            `;

            if (report.internship_overview) {
                html += `<div class="report-section">
                    <h4>Internship Overview</h4>
                    <p>${report.internship_overview}</p>
                </div>`;
            }

            if (report.key_responsibilities) {
                html += `<div class="report-section">
                    <h4>Key Responsibilities</h4>
                    <p>${report.key_responsibilities.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            if (report.skills_developed) {
                html += `<div class="report-section">
                    <h4>Skills Developed</h4>
                    <p>${report.skills_developed.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            if (report.challenges_resolutions) {
                html += `<div class="report-section">
                    <h4>Challenges and Resolutions</h4>
                    <p>${report.challenges_resolutions.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            if (report.contributions_achievements) {
                html += `<div class="report-section">
                    <h4>Major Contributions and Achievements</h4>
                    <p>${report.contributions_achievements.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            if (report.personal_professional_growth) {
                html += `<div class="report-section">
                    <h4>Personal and Professional Growth</h4>
                    <p>${report.personal_professional_growth}</p>
                </div>`;
            }

            if (report.conclusion_reflection) {
                html += `<div class="report-section">
                    <h4>Conclusion and Overall Reflection</h4>
                    <p>${report.conclusion_reflection}</p>
                </div>`;
            }

            // Add email and print buttons
            html += `<div style="margin-top: 24px; display: flex; gap: 12px;">
                <button class="btn btn-primary" onclick="exportReportEmail()"><i class="fas fa-envelope"></i> Email Report</button>
                <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>`;

            container.innerHTML = html;
        }

        function expandEntry(element) {
            // For now, just highlight. Can be enhanced with modal view
            element.style.background = '#f0fdf4';
            setTimeout(() => {
                element.style.background = '';
            }, 300);
        }
    </script>
</body>
</html>
