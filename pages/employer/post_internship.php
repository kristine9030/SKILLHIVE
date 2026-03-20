<?php
/**
 * Purpose: Employer internship posting page that validates the form, loads selectable skills, and creates internship plus internship_skill records.
 * Tables/columns used: Indirectly uses skill(skill_id, skill_name), internship(internship_id, employer_id, title, description, duration_weeks, allowance, work_setup, location, slots_available, status, posted_at, created_at), internship_skill(internship_id, skill_id, required_level, is_mandatory), application(application_id, internship_id), student(student_id), interview(application_id).
 */

// Database connection (layout.php doesn't require it globally)
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/internship_data.php';

// ── 1. Auth check ──────────────────────────────
// The login flow stores the employer PK as `user_id` with role `employer`.
$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);

if (!$employerId) {
    // Not logged in as employer — bounce to login
    header('Location: /Skillhive/pages/auth/login.php');
    exit;
}

// ── 2. Constants & master data ─────────────────
$errors          = [];
$old             = [];
$allowedWorkSetup = ['Remote', 'On-site', 'Hybrid'];
$allowedStatus    = ['Draft', 'Open', 'Closed'];
$allowedLevels    = ['Beginner', 'Intermediate', 'Advanced'];

// Load every skill from the `skill` table
$skills = [];
try {
  $skills = getSkillMasterList($pdo);
} catch (Throwable $e) {
    $errors[] = 'Failed to load skills list.';
}
// Build a fast look‑up of valid IDs
$validSkillIds = array_map(static fn($s) => (int)$s['skill_id'], $skills);

$postingsPerPage = 4;
$postingsPage = max(1, (int)($_GET['postings_page'] ?? 1));
$postingsTotal = 0;
$postingsTotalPages = 1;
$myPostings = [];

// ── 3. Handle form submission ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['delete_posting_id'])) {
    $deletePostingId = (int)($_POST['delete_posting_id'] ?? 0);
    $deletePage = max(1, (int)($_POST['postings_page'] ?? $postingsPage));

    try {
      $deleteResult = deleteEmployerInternshipPosting($pdo, (int)$employerId, $deletePostingId);
      if (!empty($deleteResult['success'])) {
        $_SESSION['status'] = 'Posting deleted successfully.';
      } else {
        $errors[] = (string)($deleteResult['error'] ?? 'Unable to delete posting.');
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = 'Unable to delete posting right now. Please try again.';
    }

    if (empty($errors)) {
      header('Location: /Skillhive/layout.php?page=employer/post_internship&postings_page=' . $deletePage . '#my-postings');
      exit;
    }
  } else {
    $validated = validatePostInternshipPayload($_POST, $validSkillIds);
    $errors = $validated['errors'];
    $old = $validated['old'];
    $allowedWorkSetup = $validated['allowed_work_setup'];
    $allowedStatus = $validated['allowed_status'];
    $allowedLevels = $validated['allowed_levels'];

      /* ── 4. INSERT using a transaction (prepared stmts = no SQL‑injection) ── */
      if (empty($errors)) {
          try {
        createInternshipPosting($pdo, (int)$employerId, $validated);

              // 5. Flash success & stay on the postings page
              $_SESSION['status'] = 'Internship posted successfully!';
              header('Location: /Skillhive/layout.php?page=employer/post_internship&postings_page=1#my-postings');
              exit;

          } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $errors[] = 'Database error — please try again.';
          }
      }
  }
}

try {
  $postingsTotal = getEmployerInternshipPostingsTotal($pdo, (int)$employerId);
  $postingsTotalPages = max(1, (int)ceil($postingsTotal / $postingsPerPage));
  $postingsPage = min($postingsPage, $postingsTotalPages);
  $postingsOffset = ($postingsPage - 1) * $postingsPerPage;
  $myPostings = getEmployerInternshipPostings($pdo, (int)$employerId, $postingsPerPage, $postingsOffset);
} catch (Throwable $e) {
  $myPostings = [];
  $postingsTotal = 0;
  $postingsTotalPages = 1;
  $postingsPage = 1;
}

$focusPostingId = max(0, (int)($_GET['focus_posting'] ?? 0));
$selectedPosting = null;
if (!empty($myPostings)) {
  $selectedPosting = $myPostings[0];
  if ($focusPostingId > 0) {
    foreach ($myPostings as $postingRow) {
      if ((int)($postingRow['internship_id'] ?? 0) === $focusPostingId) {
        $selectedPosting = $postingRow;
        break;
      }
    }
  }
}

// ── Helpers ────────────────────────────────────
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function oldVal(array $old, string $key, string $default = ''): string {
    return e($old[$key] ?? $default);
}
?>

<!-- ═══════════════════════════════════════════
     HTML  —  uses skillhive.css classes
     ═══════════════════════════════════════════ -->

<!-- Page Header -->
<div class="page-header">
  <h1 class="page-title"><i class="fa-solid fa-plus-circle" style="color:var(--red);"></i> Post New Internship</h1>
  <p class="page-sub">Fill in the details below to create a new internship listing for students.</p>
</div>

<!-- Errors -->
<?php if (!empty($errors)): ?>
<div style="background:rgba(178,34,34,.06);border:1px solid rgba(178,34,34,.18);color:#8b0000;padding:14px 18px;border-radius:8px;margin-bottom:18px;font-size:13px;">
  <strong><i class="fa-solid fa-triangle-exclamation"></i> Please fix the following:</strong>
  <ul style="margin:8px 0 0 18px;line-height:1.8;">
    <?php foreach ($errors as $err): ?>
      <li><?php echo e($err); ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<!-- My Postings Card -->
<div class="card" id="my-postings">
  <h3 class="card-title"><i class="fa-solid fa-list" style="color:var(--dark,#1a1a1a);margin-right:6px;"></i> My Postings</h3>
  <p style="font-size:12px;color:var(--grey,#888);margin-bottom:14px;">Select a posting on the left to view full details on the right.</p>

  <?php if (!empty($myPostings)): ?>
    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;align-items:start;">
      <div style="display:flex;flex-direction:column;gap:10px;max-height:520px;overflow:auto;padding-right:2px;">
        <?php foreach ($myPostings as $posting): ?>
          <?php
          $postingId = (int)($posting['internship_id'] ?? 0);
          $isActivePosting = $selectedPosting !== null && (int)($selectedPosting['internship_id'] ?? 0) === $postingId;
          $cardBorder = $isActivePosting ? '2px solid var(--red,#8b0000)' : '1px solid var(--border,#e8e0e0)';
          $cardBg = $isActivePosting ? 'rgba(139,0,0,.04)' : '#fff';
          ?>
          <div style="border:<?php echo $cardBorder; ?>;background:<?php echo $cardBg; ?>;border-radius:10px;padding:10px;">
            <button
              type="button"
              data-posting-card="1"
              data-active="<?php echo $isActivePosting ? '1' : '0'; ?>"
              data-id="<?php echo $postingId; ?>"
              data-title="<?php echo e((string)($posting['title'] ?? 'Untitled Internship')); ?>"
              data-description="<?php echo e((string)($posting['description'] ?? '')); ?>"
              data-status="<?php echo e((string)($posting['status'] ?? 'pending')); ?>"
              data-posted="<?php echo e((string)($posting['posted_at'] ?? '')); ?>"
              data-location="<?php echo e((string)($posting['location'] ?? 'N/A')); ?>"
              data-duration="<?php echo (int)($posting['duration_weeks'] ?? 0); ?>"
              data-applicants="<?php echo (int)($posting['applicants_count'] ?? 0); ?>"
              data-work-setup="<?php echo e((string)($posting['work_setup'] ?? 'N/A')); ?>"
              data-allowance="<?php echo number_format((float)($posting['allowance'] ?? 0), 2, '.', ''); ?>"
              data-slots="<?php echo (int)($posting['slots_available'] ?? 0); ?>"
              onclick="selectPostingCard(this)"
              style="text-align:left;border:none;background:transparent;padding:0;cursor:pointer;width:100%;">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <div style="font-weight:700;font-size:.9rem;"><?php echo e((string)($posting['title'] ?? 'Untitled Internship')); ?></div>
                <span class="status-pill <?php echo dashboard_status_class((string)($posting['status'] ?? 'pending')); ?>"><?php echo e(dashboard_status_label((string)($posting['status'] ?? 'pending'))); ?></span>
              </div>
              <div style="font-size:.76rem;color:#777;margin-top:4px;"><?php echo e(dashboard_time_ago((string)($posting['posted_at'] ?? ''))); ?></div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;font-size:.76rem;color:#555;">
                <span><i class="fas fa-users"></i> <?php echo (int)($posting['applicants_count'] ?? 0); ?></span>
                <span><i class="fas fa-map-marker-alt"></i> <?php echo e((string)($posting['location'] ?? 'N/A')); ?></span>
              </div>
            </button>
            <div style="display:flex;justify-content:flex-end;margin-top:8px;">
              <form method="post" action="/Skillhive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsPage; ?>#my-postings" onsubmit="return confirm('Delete this posting?');" style="margin:0;">
                <input type="hidden" name="delete_posting_id" value="<?php echo $postingId; ?>">
                <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
                <button type="submit" class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444;"><i class="fas fa-trash"></i> Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="postingDetailPanel" style="border:1px solid var(--border,#e8e0e0);border-radius:10px;padding:12px;background:#fff;height:498px;display:flex;flex-direction:column;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
          <h4 id="detailTitle" style="margin:0;font-size:1rem;"><?php echo e((string)($selectedPosting['title'] ?? 'Untitled Internship')); ?></h4>
          <span id="detailStatus" class="status-pill <?php echo dashboard_status_class((string)($selectedPosting['status'] ?? 'pending')); ?>"><?php echo e(dashboard_status_label((string)($selectedPosting['status'] ?? 'pending'))); ?></span>
        </div>
        <div id="detailPosted" style="font-size:.78rem;color:#777;margin-top:4px;"><?php echo e(dashboard_time_ago((string)($selectedPosting['posted_at'] ?? ''))); ?></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;font-size:.8rem;">
          <div><strong>Location:</strong> <span id="detailLocation"><?php echo e((string)($selectedPosting['location'] ?? 'N/A')); ?></span></div>
          <div><strong>Duration:</strong> <span id="detailDuration"><?php echo e(dashboard_duration_label((int)($selectedPosting['duration_weeks'] ?? 0))); ?></span></div>
          <div><strong>Work Setup:</strong> <span id="detailWorkSetup"><?php echo e((string)($selectedPosting['work_setup'] ?? 'N/A')); ?></span></div>
          <div><strong>Applicants:</strong> <span id="detailApplicants"><?php echo (int)($selectedPosting['applicants_count'] ?? 0); ?></span></div>
          <div><strong>Allowance:</strong> <span id="detailAllowance">₱<?php echo number_format((float)($selectedPosting['allowance'] ?? 0), 2); ?></span></div>
          <div><strong>Slots:</strong> <span id="detailSlots"><?php echo (int)($selectedPosting['slots_available'] ?? 0); ?></span></div>
        </div>

        <div style="margin-top:12px;flex:1;display:flex;flex-direction:column;min-height:0;">
          <div style="font-weight:700;font-size:.8rem;margin-bottom:4px;">Description</div>
          <div id="detailDescription" style="font-size:.82rem;color:#444;line-height:1.5;height:300px;overflow:auto;border:1px solid var(--border,#e8e0e0);border-radius:8px;padding:10px;background:#fafafa;"><?php echo e((string)($selectedPosting['description'] ?? 'No description provided.')); ?></div>
        </div>

        <div class="job-card-actions" style="margin-top:12px;">
          <a id="detailApplicantsLink" href="/Skillhive/layout.php?page=employer/candidates&position=<?php echo rawurlencode((string)($selectedPosting['title'] ?? '')); ?>" class="btn btn-ghost btn-sm">View Applicants</a>
          <form method="post" action="/Skillhive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsPage; ?>#my-postings" onsubmit="return confirm('Delete this posting?');" style="margin:0;display:inline-block;">
            <input type="hidden" name="delete_posting_id" id="detailDeletePostingId" value="<?php echo (int)($selectedPosting['internship_id'] ?? 0); ?>">
            <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
            <button type="submit" class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444;"><i class="fas fa-trash"></i> Delete</button>
          </form>
        </div>
      </div>
    </div>

    <?php if ($postingsTotalPages > 1): ?>
      <?php
      $startPage = max(1, $postingsPage - 2);
      $endPage = min($postingsTotalPages, $postingsPage + 2);
      if (($endPage - $startPage) < 4) {
        if ($startPage === 1) {
          $endPage = min($postingsTotalPages, $startPage + 4);
        } elseif ($endPage === $postingsTotalPages) {
          $startPage = max(1, $endPage - 4);
        }
      }
      ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">
        <?php if ($postingsPage > 1): ?>
          <a class="btn btn-ghost btn-sm" href="/Skillhive/layout.php?page=employer/post_internship&postings_page=<?php echo ($postingsPage - 1); ?>">Previous</a>
        <?php endif; ?>

        <?php if ($startPage > 1): ?>
          <a class="btn btn-ghost btn-sm" href="/Skillhive/layout.php?page=employer/post_internship&postings_page=1">1</a>
          <?php if ($startPage > 2): ?>
            <span style="padding:6px 4px;color:#999;">...</span>
          <?php endif; ?>
        <?php endif; ?>

        <?php for ($pageNum = $startPage; $pageNum <= $endPage; $pageNum++): ?>
          <?php if ($pageNum === $postingsPage): ?>
            <span class="btn btn-red btn-sm" style="pointer-events:none;"><?php echo $pageNum; ?></span>
          <?php else: ?>
            <a class="btn btn-ghost btn-sm" href="/Skillhive/layout.php?page=employer/post_internship&postings_page=<?php echo $pageNum; ?>"><?php echo $pageNum; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endPage < $postingsTotalPages): ?>
          <?php if ($endPage < ($postingsTotalPages - 1)): ?>
            <span style="padding:6px 4px;color:#999;">...</span>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="/Skillhive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsTotalPages; ?>"><?php echo $postingsTotalPages; ?></a>
        <?php endif; ?>

        <?php if ($postingsPage < $postingsTotalPages): ?>
          <a class="btn btn-ghost btn-sm" href="/Skillhive/layout.php?page=employer/post_internship&postings_page=<?php echo ($postingsPage + 1); ?>">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="job-card" style="margin-bottom:0;">
      <div class="job-card-header">
        <div class="job-card-info">
          <div class="job-card-title">No postings yet</div>
          <div class="job-card-company">Create your first internship posting using the form above.</div>
        </div>
        <span class="status-pill status-pending">Pending</span>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="page-header" style="margin-top:18px;">
  <h2 class="page-title"><i class="fa-solid fa-pen-to-square" style="color:var(--red);"></i> Create New Posting</h2>
  <p class="page-sub">Use the form below to add a new internship.</p>
</div>

<!-- Form Card -->
<div class="card">
  <h3 class="card-title"><i class="fa-solid fa-briefcase" style="color:var(--red);margin-right:6px;"></i> Internship Details</h3>

  <form method="post" action="/Skillhive/layout.php?page=employer/post_internship">

    <!-- Row: Title + Work Setup -->
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Title <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="text" name="title" placeholder="e.g. Web Developer Intern" value="<?php echo oldVal($old, 'title'); ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Work Setup <span style="color:var(--red);">*</span></label>
        <select class="form-control" name="work_setup" required>
          <option value="">— Select —</option>
          <?php foreach ($allowedWorkSetup as $w): ?>
            <option value="<?php echo e($w); ?>" <?php echo (oldVal($old,'work_setup') === $w ? 'selected' : ''); ?>>
              <?php echo e($w); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Description -->
    <div class="form-group">
      <label class="form-label">Description <span style="color:var(--red);">*</span></label>
      <textarea class="form-control" name="description" rows="5" placeholder="Describe the role, responsibilities, and what the intern will learn…" required><?php echo oldVal($old, 'description'); ?></textarea>
    </div>

    <!-- Row: Duration + Allowance -->
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Duration (weeks) <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="number" min="1" name="duration_weeks" placeholder="e.g. 12" value="<?php echo oldVal($old, 'duration_weeks'); ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Allowance (₱) <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="number" min="0" step="0.01" name="allowance" placeholder="e.g. 5000" value="<?php echo oldVal($old, 'allowance'); ?>" required>
      </div>
    </div>

    <!-- Row: Location + Slots -->
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Location <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="text" name="location" placeholder="e.g. Batangas City" value="<?php echo oldVal($old, 'location'); ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Slots Available <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="number" min="1" name="slots_available" placeholder="e.g. 5" value="<?php echo oldVal($old, 'slots_available'); ?>" required>
      </div>
    </div>

    <!-- Status -->
    <div class="form-group" style="max-width:320px;">
      <label class="form-label">Status <span style="color:var(--red);">*</span></label>
      <select class="form-control" name="status" required>
        <?php foreach ($allowedStatus as $s): ?>
          <option value="<?php echo e($s); ?>" <?php echo (oldVal($old,'status','Open') === $s ? 'selected' : ''); ?>>
            <?php echo e($s); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

  </form>
</div>

<!-- Skills Card -->
<div class="card mt">
  <h3 class="card-title"><i class="fa-solid fa-list-check" style="color:var(--gold,#c9a84c);margin-right:6px;"></i> Required Skills</h3>
  <p style="font-size:12px;color:var(--grey,#888);margin-bottom:14px;">Check the skills needed, set the proficiency level, and mark mandatory ones.</p>

  <!-- Search filter for skills -->
  <div class="form-group" style="max-width:360px;margin-bottom:14px;">
    <input class="form-control" type="text" id="skillSearch" placeholder="🔍  Search skills…" oninput="filterSkills()">
  </div>

  <div style="border:1px solid var(--border,#e8e0e0);border-radius:8px;max-height:360px;overflow-y:auto;">
    <!-- Header row -->
    <div style="display:grid;grid-template-columns:32px 1fr 170px 100px;gap:10px;align-items:center;padding:10px 14px;background:var(--dark,#1a1a1a);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;border-radius:8px 8px 0 0;position:sticky;top:0;z-index:2;">
      <span></span>
      <span>Skill</span>
      <span>Level</span>
      <span>Mandatory</span>
    </div>

    <?php if (empty($skills)): ?>
      <div style="padding:24px;text-align:center;color:var(--grey,#888);font-size:13px;">
        <i class="fa-solid fa-circle-exclamation"></i> No skills found in the database. Please seed the <code>skill</code> table first.
      </div>
    <?php endif; ?>

    <?php foreach ($skills as $idx => $skill):
      $sid     = (int)$skill['skill_id'];
      $checked = isset($_POST['skills']) && in_array((string)$sid, array_map('strval', $_POST['skills'] ?? []), true);
      $bgRow   = ($idx % 2 === 0) ? 'background:rgba(0,0,0,.015);' : '';
    ?>
    <div class="skill-entry" style="display:grid;grid-template-columns:32px 1fr 170px 100px;gap:10px;align-items:center;padding:9px 14px;border-bottom:1px solid var(--border,#e8e0e0);<?php echo $bgRow; ?>" data-name="<?php echo e(strtolower($skill['skill_name'])); ?>">
      <input type="checkbox" form="internshipForm" name="skills[]" value="<?php echo $sid; ?>"
             style="width:16px;height:16px;accent-color:var(--red,#8b0000);cursor:pointer;"
             <?php echo $checked ? 'checked' : ''; ?>>
      <span style="font-size:13px;font-weight:500;"><?php echo e($skill['skill_name']); ?></span>
      <select form="internshipForm" name="skill_level[<?php echo $sid; ?>]" class="form-control" style="padding:6px 8px;font-size:12px;">
        <?php foreach ($allowedLevels as $lvl): ?>
          <?php $sel = (($_POST['skill_level'][$sid] ?? 'Beginner') === $lvl) ? 'selected' : ''; ?>
          <option value="<?php echo e($lvl); ?>" <?php echo $sel; ?>><?php echo e($lvl); ?></option>
        <?php endforeach; ?>
      </select>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;font-weight:500;color:var(--grey,#888);">
        <input type="checkbox" form="internshipForm" name="skill_mandatory[<?php echo $sid; ?>]" value="1"
               style="accent-color:var(--gold,#c9a84c);"
               <?php echo isset($_POST['skill_mandatory'][$sid]) ? 'checked' : ''; ?>>
        Yes
      </label>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Actions (inside skills card but submitting the form above) -->
  <div style="display:flex;gap:10px;margin-top:18px;">
    <button type="submit" form="internshipForm" class="btn btn-red btn-lg">
      <i class="fa-solid fa-rocket"></i> &nbsp;Create Internship
    </button>
    <a href="/Skillhive/layout.php?page=employer/dashboard" class="btn btn-dark btn-lg">
      <i class="fa-solid fa-xmark"></i> &nbsp;Cancel
    </a>
  </div>
</div>

<!-- Hidden form tag that wraps everything via "form" attribute -->
<form id="internshipForm" method="post" action="/Skillhive/layout.php?page=employer/post_internship" style="display:none;"></form>

<!-- Re-associate all inputs in the first card to internshipForm -->
<script>
  // Attach all inputs/selects/textareas inside .card forms to the single internshipForm
  document.querySelectorAll('.card input, .card select, .card textarea').forEach(el => {
    const parentForm = el.closest('form');
    const isDeleteFormField = parentForm && parentForm.querySelector('input[name="delete_posting_id"]');

    if (isDeleteFormField) {
      return;
    }

    if (!el.hasAttribute('form') && el.name) {
      el.setAttribute('form', 'internshipForm');
    }
  });

  // Skill search filter
  function filterSkills() {
    const q = document.getElementById('skillSearch').value.toLowerCase();
    document.querySelectorAll('.skill-entry').forEach(row => {
      const name = row.getAttribute('data-name') || '';
      row.style.display = name.includes(q) ? '' : 'none';
    });
  }

  function setPostingCardActiveState(button) {
    document.querySelectorAll('[data-posting-card="1"]').forEach(card => {
      card.dataset.active = '0';
      const wrap = card.closest('div[style*="border-radius:10px"]');
      if (wrap) {
        wrap.style.border = '1px solid var(--border,#e8e0e0)';
        wrap.style.background = '#fff';
      }
    });

    button.dataset.active = '1';
    const currentWrap = button.closest('div[style*="border-radius:10px"]');
    if (currentWrap) {
      currentWrap.style.border = '2px solid var(--red,#8b0000)';
      currentWrap.style.background = 'rgba(139,0,0,.04)';
    }
  }

  function selectPostingCard(button) {
    if (!button) return;
    setPostingCardActiveState(button);

    const title = button.getAttribute('data-title') || 'Untitled Internship';
    const description = button.getAttribute('data-description') || 'No description provided.';
    const status = button.getAttribute('data-status') || 'pending';
    const posted = button.getAttribute('data-posted') || '';
    const location = button.getAttribute('data-location') || 'N/A';
    const duration = parseInt(button.getAttribute('data-duration') || '0', 10);
    const applicants = button.getAttribute('data-applicants') || '0';
    const workSetup = button.getAttribute('data-work-setup') || 'N/A';
    const allowanceRaw = parseFloat(button.getAttribute('data-allowance') || '0');
    const slots = button.getAttribute('data-slots') || '0';

    const durationText = duration > 0 ? (duration % 4 === 0 ? (duration / 4) + ' month' + ((duration / 4) === 1 ? '' : 's') : duration + ' week' + (duration === 1 ? '' : 's')) : 'N/A';

    const statusLabel = status.replace(/[_-]+/g, ' ').trim();
    const prettyStatus = statusLabel ? statusLabel.replace(/\b\w/g, c => c.toUpperCase()) : 'N/A';

    const postedDate = new Date(posted);
    const postedText = isNaN(postedDate.getTime()) ? 'Posted recently' : 'Posted ' + postedDate.toLocaleString();

    document.getElementById('detailTitle').textContent = title;
    document.getElementById('detailStatus').textContent = prettyStatus;
    document.getElementById('detailStatus').className = 'status-pill ' + (
      ['accepted','hired','open','verified','approved','scheduled'].includes(status.toLowerCase()) ? 'status-accepted' :
      ['rejected','declined','closed','cancelled','canceled'].includes(status.toLowerCase()) ? 'status-rejected' :
      ['interview','interviewing','for interview'].includes(status.toLowerCase()) ? 'status-interview' :
      ['shortlisted','reviewed'].includes(status.toLowerCase()) ? 'status-shortlisted' :
      'status-pending'
    );
    document.getElementById('detailPosted').textContent = postedText;
    document.getElementById('detailLocation').textContent = location;
    document.getElementById('detailDuration').textContent = durationText;
    document.getElementById('detailWorkSetup').textContent = workSetup;
    document.getElementById('detailApplicants').textContent = applicants;
    document.getElementById('detailAllowance').textContent = '₱' + allowanceRaw.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('detailSlots').textContent = slots;
    document.getElementById('detailDescription').textContent = description;
    document.getElementById('detailApplicantsLink').setAttribute('href', '/Skillhive/layout.php?page=employer/candidates&position=' + encodeURIComponent(title));
    const detailDeletePostingId = document.getElementById('detailDeletePostingId');
    if (detailDeletePostingId) {
      detailDeletePostingId.value = button.getAttribute('data-id') || '0';
    }
  }

  const initiallyActiveCard = document.querySelector('[data-posting-card="1"][data-active="1"]') || document.querySelector('[data-posting-card="1"]');
  if (initiallyActiveCard) {
    selectPostingCard(initiallyActiveCard);
  }
</script>