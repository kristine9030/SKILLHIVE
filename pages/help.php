<?php
$helpBaseUrl = isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : '/SkillHive';
$helpRole = strtolower((string)($role ?? ($_SESSION['role'] ?? 'student')));
$helpName = trim((string)($userName ?? ($_SESSION['user_name'] ?? 'User')));

if (!function_exists('skillhive_help_escape')) {
    function skillhive_help_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$roleLabels = [
    'student' => 'Student',
    'employer' => 'Employer',
    'adviser' => 'Adviser',
    'admin' => 'Administrator',
];

$messagePages = [
    'student' => 'student/messaging',
    'employer' => 'employer/messaging',
    'adviser' => 'adviser/messaging',
    'admin' => 'admin/messaging',
];

$settingsPages = [
    'student' => 'student/settings',
    'employer' => 'employer/profile',
    'adviser' => 'adviser/settings',
    'admin' => 'admin/settings',
];

$roleGuides = [
    'student' => [
        'title' => 'Student Help',
        'intro' => 'Fast links for profile setup, applications, requirements, and OJT tracking.',
        'topics' => [
            [
                'icon' => 'fa-id-card',
                'title' => 'Complete Your Profile',
                'copy' => 'Keep your skills, resume, and profile details ready before applying.',
                'page' => 'student/profile',
                'label' => 'Open Profile',
            ],
            [
                'icon' => 'fa-store',
                'title' => 'Find Internships',
                'copy' => 'Browse marketplace listings and check which roles match your skills.',
                'page' => 'student/marketplace',
                'label' => 'Open Marketplace',
            ],
            [
                'icon' => 'fa-paper-plane',
                'title' => 'Track Applications',
                'copy' => 'Review submitted applications, status changes, and next steps.',
                'page' => 'student/applications',
                'label' => 'View Applications',
            ],
            [
                'icon' => 'fa-clipboard-check',
                'title' => 'Submit Requirements',
                'copy' => 'Upload required documents and check what still needs attention.',
                'page' => 'student/requirements',
                'label' => 'Open Requirements',
            ],
            [
                'icon' => 'fa-clock',
                'title' => 'Update OJT Logs',
                'copy' => 'Record attendance, daily progress, and journal entries during OJT.',
                'page' => 'student/ojt-log',
                'label' => 'Open OJT Tracker',
            ],
            [
                'icon' => 'fa-file-lines',
                'title' => 'Build Your CV',
                'copy' => 'Use the CV builder to prepare a cleaner internship-ready resume.',
                'page' => 'student/resume-ai',
                'label' => 'Open CV Builder',
            ],
        ],
    ],
    'employer' => [
        'title' => 'Employer Help',
        'intro' => 'Fast links for company profile updates, postings, candidates, and evaluations.',
        'topics' => [
            [
                'icon' => 'fa-building',
                'title' => 'Update Company Profile',
                'copy' => 'Keep your company details, contact information, and website current.',
                'page' => 'employer/profile',
                'label' => 'Open Profile',
            ],
            [
                'icon' => 'fa-briefcase',
                'title' => 'Manage Postings',
                'copy' => 'Create internship roles, edit details, and keep listings accurate.',
                'page' => 'employer/post_internship',
                'label' => 'Open Postings',
            ],
            [
                'icon' => 'fa-users',
                'title' => 'Review Candidates',
                'copy' => 'Scan applicant profiles and move qualified candidates forward.',
                'page' => 'employer/candidates',
                'label' => 'Open Candidates',
            ],
            [
                'icon' => 'fa-clipboard-check',
                'title' => 'Submit Evaluations',
                'copy' => 'Complete student evaluations and keep training records updated.',
                'page' => 'employer/evaluation',
                'label' => 'Open Evaluations',
            ],
        ],
    ],
    'adviser' => [
        'title' => 'Adviser Help',
        'intro' => 'Fast links for student management, endorsements, requirements, and monitoring.',
        'topics' => [
            [
                'icon' => 'fa-user-graduate',
                'title' => 'Manage Students',
                'copy' => 'Review assigned students and keep advising records organized.',
                'page' => 'adviser/students',
                'label' => 'Open Students',
            ],
            [
                'icon' => 'fa-folder-open',
                'title' => 'Check Requirements',
                'copy' => 'Review submitted documents and track missing requirements.',
                'page' => 'adviser/requirements',
                'label' => 'Open Requirements',
            ],
            [
                'icon' => 'fa-stamp',
                'title' => 'Handle Endorsements',
                'copy' => 'Approve or review endorsement requests for internship applications.',
                'page' => 'adviser/endorsement',
                'label' => 'Open Endorsements',
            ],
            [
                'icon' => 'fa-eye',
                'title' => 'Monitor OJT',
                'copy' => 'Track student progress, attendance, and OJT activity.',
                'page' => 'adviser/monitoring',
                'label' => 'Open Monitoring',
            ],
            [
                'icon' => 'fa-book-open',
                'title' => 'Review Journals',
                'copy' => 'Read student journal entries and spot progress patterns.',
                'page' => 'adviser/journal_analytics',
                'label' => 'Open Journals',
            ],
        ],
    ],
    'admin' => [
        'title' => 'Admin Help',
        'intro' => 'Fast links for company verification, user management, reports, and platform settings.',
        'topics' => [
            [
                'icon' => 'fa-building-shield',
                'title' => 'Verify Companies',
                'copy' => 'Review employer submissions and approve verified partner accounts.',
                'page' => 'admin/verify-companies',
                'label' => 'Open Reviews',
            ],
            [
                'icon' => 'fa-users-gear',
                'title' => 'Manage Users',
                'copy' => 'Search accounts, review roles, and handle user access concerns.',
                'page' => 'admin/users',
                'label' => 'Open Users',
            ],
            [
                'icon' => 'fa-chart-bar',
                'title' => 'Check Reports',
                'copy' => 'Review platform activity, application flow, and usage summaries.',
                'page' => 'admin/reports',
                'label' => 'Open Reports',
            ],
            [
                'icon' => 'fa-clock-rotate-left',
                'title' => 'Audit Activity',
                'copy' => 'Inspect recent activity when investigating account or workflow issues.',
                'page' => 'admin/audit-logs',
                'label' => 'Open Audit Logs',
            ],
        ],
    ],
];

$guide = $roleGuides[$helpRole] ?? $roleGuides['student'];
$roleLabel = $roleLabels[$helpRole] ?? 'User';
$messagesPage = $messagePages[$helpRole] ?? 'student/messaging';
$settingsPage = $settingsPages[$helpRole] ?? 'student/settings';

$quickActions = [
    [
        'icon' => 'fa-message',
        'title' => 'Messages',
        'copy' => 'Open your inbox for conversations and updates.',
        'page' => $messagesPage,
        'label' => 'Open Messages',
    ],
    [
        'icon' => 'fa-gear',
        'title' => 'Account Settings',
        'copy' => 'Review account preferences and related settings.',
        'page' => $settingsPage,
        'label' => 'Open Settings',
    ],
    [
        'icon' => 'fa-house',
        'title' => 'Dashboard',
        'copy' => 'Return to your main workspace.',
        'page' => $helpRole . '/dashboard',
        'label' => 'Open Dashboard',
    ],
];

$faqs = [
    [
        'question' => 'Why did a page send me back to the dashboard?',
        'answer' => 'SkillHive protects role-specific pages. If a page is outside your role, unavailable, or your account still needs approval, the system sends you back to a safe page.',
    ],
    [
        'question' => 'Where do I update my account details?',
        'answer' => 'Use My Profile for profile information and Settings for account preferences. Employers manage most account details from their company profile.',
    ],
    [
        'question' => 'How do I know if something needs action?',
        'answer' => 'Check notifications, your dashboard, and the related module page. Items needing review usually show a pending, missing, or unread status.',
    ],
    [
        'question' => 'What should I do if uploads or forms fail?',
        'answer' => 'Refresh the page, confirm required fields are complete, and try again. For file uploads, use common document or image formats and avoid oversized files.',
    ],
];
?>

<style>
    .help-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .help-hero {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 24px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fbff 62%, #eefbf9 100%);
        box-shadow: var(--card-shadow);
    }

    .help-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(260px, .75fr);
        gap: 22px;
        align-items: stretch;
    }

    .help-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: #0f766e;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .help-title {
        margin: 0;
        color: var(--text);
        font-size: clamp(1.45rem, 2vw, 2rem);
        font-weight: 800;
        line-height: 1.2;
    }

    .help-copy {
        margin: 10px 0 0;
        max-width: 740px;
        color: var(--text2);
        font-size: .95rem;
    }

    .help-search-card {
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
        border: 1px solid rgba(18, 179, 172, .25);
        border-radius: 8px;
        padding: 16px;
        background: #fff;
    }

    .help-search-label {
        color: var(--text);
        font-size: .85rem;
        font-weight: 700;
    }

    .help-search {
        position: relative;
    }

    .help-search i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text3);
        font-size: .85rem;
    }

    .help-search input {
        width: 100%;
        min-height: 42px;
        padding: 10px 12px 10px 38px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        outline: none;
    }

    .help-search input:focus {
        border-color: #111;
    }

    .help-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 20px;
        align-items: start;
    }

    .help-main,
    .help-side,
    .help-section {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .help-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
    }

    .help-section-title {
        margin: 0;
        color: var(--text);
        font-size: 1.05rem;
        font-weight: 800;
    }

    .help-section-note {
        color: var(--text3);
        font-size: .8rem;
        text-align: right;
    }

    .help-topic-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .help-topic,
    .help-box,
    .help-faq {
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #fff;
        box-shadow: var(--card-shadow);
    }

    .help-topic {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
    }

    .help-topic-top {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .help-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        color: #0f766e;
        background: rgba(18, 179, 172, .12);
    }

    .help-topic h4,
    .help-action h4 {
        margin: 0;
        color: var(--text);
        font-size: .93rem;
        font-weight: 800;
    }

    .help-topic p,
    .help-action p,
    .help-box p {
        margin: 5px 0 0;
        color: var(--text2);
        font-size: .82rem;
    }

    .help-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: auto;
        color: #0f766e;
        font-size: .82rem;
        font-weight: 800;
        text-decoration: none;
    }

    .help-link:hover {
        color: #111;
    }

    .help-box {
        padding: 16px;
    }

    .help-box-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 12px;
        color: var(--text);
        font-size: .98rem;
        font-weight: 800;
    }

    .help-action-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .help-action {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 10px;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        text-decoration: none;
        background: #fcfcfc;
        transition: border-color .2s ease, transform .2s ease;
    }

    .help-action:hover {
        border-color: rgba(18, 179, 172, .55);
        transform: translateY(-1px);
    }

    .help-action .help-icon {
        width: 34px;
        height: 34px;
    }

    .help-checklist {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .help-checklist li {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        color: var(--text2);
        font-size: .82rem;
    }

    .help-checklist i {
        margin-top: 3px;
        color: #0f766e;
    }

    .help-faq-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .help-faq {
        padding: 0;
        overflow: hidden;
    }

    .help-faq summary {
        cursor: pointer;
        padding: 14px 16px;
        color: var(--text);
        font-size: .9rem;
        font-weight: 800;
        list-style: none;
    }

    .help-faq summary::-webkit-details-marker {
        display: none;
    }

    .help-faq summary::after {
        content: '+';
        float: right;
        color: #0f766e;
        font-weight: 900;
    }

    .help-faq[open] summary::after {
        content: '-';
    }

    .help-faq p {
        margin: 0;
        padding: 0 16px 15px;
        color: var(--text2);
        font-size: .84rem;
    }

    .help-empty {
        display: none;
        border: 1px dashed var(--border);
        border-radius: 8px;
        padding: 16px;
        color: var(--text3);
        text-align: center;
        background: #fff;
    }

    @media (max-width: 1100px) {
        .help-grid,
        .help-hero-grid {
            grid-template-columns: 1fr;
        }

        .help-side {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 720px) {
        .help-hero {
            padding: 18px;
        }

        .help-topic-grid,
        .help-side {
            grid-template-columns: 1fr;
        }

        .help-section-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .help-section-note {
            text-align: left;
        }
    }
</style>

<div class="help-page">
    <section class="help-hero">
        <div class="help-hero-grid">
            <div>
                <div class="help-kicker"><i class="fas fa-circle-question"></i> Help Center</div>
                <h2 class="help-title">Hi <?php echo skillhive_help_escape($helpName !== '' ? $helpName : 'there'); ?>, what do you need?</h2>
                <p class="help-copy"><?php echo skillhive_help_escape($guide['intro']); ?></p>
            </div>
            <div class="help-search-card">
                <label class="help-search-label" for="helpSearchInput">Search help topics</label>
                <div class="help-search">
                    <i class="fas fa-search"></i>
                    <input id="helpSearchInput" type="search" placeholder="Search by keyword">
                </div>
            </div>
        </div>
    </section>

    <div class="help-grid">
        <main class="help-main">
            <section class="help-section">
                <div class="help-section-head">
                    <h3 class="help-section-title"><?php echo skillhive_help_escape($guide['title']); ?></h3>
                    <div class="help-section-note"><?php echo skillhive_help_escape($roleLabel); ?> workspace</div>
                </div>

                <div class="help-topic-grid" id="helpTopicGrid">
                    <?php foreach ($guide['topics'] as $topic): ?>
                        <article class="help-topic" data-help-search="<?php echo skillhive_help_escape(($topic['title'] ?? '') . ' ' . ($topic['copy'] ?? '') . ' ' . ($topic['label'] ?? '')); ?>">
                            <div class="help-topic-top">
                                <span class="help-icon"><i class="fas <?php echo skillhive_help_escape($topic['icon']); ?>"></i></span>
                                <div>
                                    <h4><?php echo skillhive_help_escape($topic['title']); ?></h4>
                                    <p><?php echo skillhive_help_escape($topic['copy']); ?></p>
                                </div>
                            </div>
                            <a class="help-link" href="<?php echo skillhive_help_escape($helpBaseUrl); ?>/layout.php?page=<?php echo skillhive_help_escape($topic['page']); ?>">
                                <?php echo skillhive_help_escape($topic['label']); ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="help-empty" id="helpTopicEmpty">No matching help topics.</div>
            </section>

            <section class="help-section">
                <div class="help-section-head">
                    <h3 class="help-section-title">Common Questions</h3>
                    <div class="help-section-note">Quick answers</div>
                </div>

                <div class="help-faq-list" id="helpFaqList">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <details class="help-faq" <?php echo $index === 0 ? 'open' : ''; ?> data-help-search="<?php echo skillhive_help_escape(($faq['question'] ?? '') . ' ' . ($faq['answer'] ?? '')); ?>">
                            <summary><?php echo skillhive_help_escape($faq['question']); ?></summary>
                            <p><?php echo skillhive_help_escape($faq['answer']); ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
                <div class="help-empty" id="helpFaqEmpty">No matching questions.</div>
            </section>
        </main>

        <aside class="help-side">
            <section class="help-box">
                <h3 class="help-box-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="help-action-list">
                    <?php foreach ($quickActions as $action): ?>
                        <a class="help-action" href="<?php echo skillhive_help_escape($helpBaseUrl); ?>/layout.php?page=<?php echo skillhive_help_escape($action['page']); ?>">
                            <span class="help-icon"><i class="fas <?php echo skillhive_help_escape($action['icon']); ?>"></i></span>
                            <div>
                                <h4><?php echo skillhive_help_escape($action['title']); ?></h4>
                                <p><?php echo skillhive_help_escape($action['copy']); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="help-box">
                <h3 class="help-box-title"><i class="fas fa-life-ring"></i> Before Reporting</h3>
                <ul class="help-checklist">
                    <li><i class="fas fa-check-circle"></i><span>Refresh the page and try the action one more time.</span></li>
                    <li><i class="fas fa-check-circle"></i><span>Check that required fields are complete.</span></li>
                    <li><i class="fas fa-check-circle"></i><span>Note the page name and the action you attempted.</span></li>
                    <li><i class="fas fa-check-circle"></i><span>Use Messages if another user or admin needs to review it.</span></li>
                </ul>
            </section>
        </aside>
    </div>
</div>

<script>
(function initHelpSearch() {
    var input = document.getElementById('helpSearchInput');
    var topics = Array.prototype.slice.call(document.querySelectorAll('.help-topic'));
    var faqs = Array.prototype.slice.call(document.querySelectorAll('.help-faq'));
    var topicEmpty = document.getElementById('helpTopicEmpty');
    var faqEmpty = document.getElementById('helpFaqEmpty');

    if (!input) {
        return;
    }

    function filterItems(items, emptyEl, query) {
        var shown = 0;
        items.forEach(function (item) {
            var haystack = String(item.getAttribute('data-help-search') || '').toLowerCase();
            var visible = query === '' || haystack.indexOf(query) !== -1;
            item.style.display = visible ? '' : 'none';
            if (visible) {
                shown += 1;
            }
        });

        if (emptyEl) {
            emptyEl.style.display = shown === 0 ? 'block' : 'none';
        }
    }

    input.addEventListener('input', function () {
        var query = String(input.value || '').trim().toLowerCase();
        filterItems(topics, topicEmpty, query);
        filterItems(faqs, faqEmpty, query);
    });
})();
</script>
