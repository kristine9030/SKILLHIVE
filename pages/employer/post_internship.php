<?php

/**
 * ──────────────────────────────────────────────
 *  POST INTERNSHIP  –  Employer Page
 * ──────────────────────────────────────────────
 *  This page is loaded INSIDE layout.php via include,
 *  so session is already started, $pdo is NOT yet available,
 *  and skillhive.css + Font Awesome are already linked.
 *
 *  Flow:
 *  1. Verify employer is logged in.
 *  2. Load the master "skill" table for the multi‑select.
 *  3. On POST ➜ validate ➜ INSERT internship + internship_skill rows.
 *  4. Redirect to dashboard on success.
 * ──────────────────────────────────────────────
 */

// Database connection (layout.php doesn't require it globally)
require_once __DIR__ . '/../../backend/db_connect.php';

// ── 1. Auth check ──────────────────────────────
// The login flow stores the employer PK as `user_id` with role `employer`.
$employerId = $_SESSION['employer_id'] ?? null;
if (!$employerId && (($_SESSION['user_role'] ?? '') === 'employer')) {
    $employerId = $_SESSION['user_id'] ?? null;
}

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
    $stmt  = $pdo->query("SELECT skill_id, skill_name FROM skill ORDER BY skill_name ASC");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errors[] = 'Failed to load skills list.';
}
// Build a fast look‑up of valid IDs
$validSkillIds = array_map(static fn($s) => (int)$s['skill_id'], $skills);

// ── 3. Handle form submission ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── Collect & remember inputs ── */
    $old['title']           = trim($_POST['title'] ?? '');
    $old['description']     = trim($_POST['description'] ?? '');
    $old['duration_weeks']  = trim($_POST['duration_weeks'] ?? '');
    $old['allowance']       = trim($_POST['allowance'] ?? '');
    $old['work_setup']      = trim($_POST['work_setup'] ?? '');
    $old['location']        = trim($_POST['location'] ?? '');
    $old['slots_available'] = trim($_POST['slots_available'] ?? '');
    $old['status']          = trim($_POST['status'] ?? 'Draft');

    $selectedSkills  = $_POST['skills'] ?? [];
    $skillLevels     = $_POST['skill_level'] ?? [];
    $skillMandatory  = $_POST['skill_mandatory'] ?? [];

    /* ── Validate required text fields ── */
    if ($old['title'] === '')       $errors[] = 'Title is required.';
    if ($old['description'] === '') $errors[] = 'Description is required.';
    if ($old['location'] === '')    $errors[] = 'Location is required.';

    /* ── Validate numeric fields ── */
    $durationWeeks = filter_var($old['duration_weeks'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($durationWeeks === false) $errors[] = 'Duration must be a whole number ≥ 1.';

    $allowance = filter_var($old['allowance'], FILTER_VALIDATE_FLOAT);
    if ($allowance === false || $allowance < 0) $errors[] = 'Allowance must be 0 or higher.';

    $slotsAvailable = filter_var($old['slots_available'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($slotsAvailable === false) $errors[] = 'Slots must be a whole number ≥ 1.';

    /* ── Validate enum fields ── */
    if (!in_array($old['work_setup'], $allowedWorkSetup, true)) $errors[] = 'Invalid Work Setup.';
    if (!in_array($old['status'], $allowedStatus, true))         $errors[] = 'Invalid Status.';

    /* ── Validate skills selection ── */
    if (!is_array($selectedSkills) || count($selectedSkills) === 0) {
        $errors[] = 'Select at least one required skill.';
    }

    /* ── Build validated skill rows ── */
    $rowsToInsert = [];
    if (empty($errors)) {
        foreach ($selectedSkills as $sidRaw) {
            $sid = (int)$sidRaw;
            if (!in_array($sid, $validSkillIds, true)) {
                $errors[] = "Invalid skill (ID {$sid}).";
                continue;
            }
            $lvl = $skillLevels[$sid] ?? '';
            if (!in_array($lvl, $allowedLevels, true)) {
                $errors[] = "Invalid level for skill ID {$sid}.";
                continue;
            }
            $isMandatory    = isset($skillMandatory[$sid]) ? 1 : 0;
            $rowsToInsert[] = [$sid, $lvl, $isMandatory];
        }
    }

    /* ── 4. INSERT using a transaction (prepared stmts = no SQL‑injection) ── */
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert the internship record
            $sqlInternship = "
                INSERT INTO internship
                    (employer_id, title, description, duration_weeks, allowance,
                     work_setup, location, slots_available, status, posted_at)
                VALUES
                    (:employer_id, :title, :description, :duration_weeks, :allowance,
                     :work_setup, :location, :slots_available, :status, NOW())
            ";
            $stmtI = $pdo->prepare($sqlInternship);
            $stmtI->execute([
                ':employer_id'     => (int)$employerId,
                ':title'           => $old['title'],
                ':description'     => $old['description'],
                ':duration_weeks'  => (int)$durationWeeks,
                ':allowance'       => (float)$allowance,
                ':work_setup'      => $old['work_setup'],
                ':location'        => $old['location'],
                ':slots_available' => (int)$slotsAvailable,
                ':status'          => $old['status'],
            ]);
            $internshipId = (int)$pdo->lastInsertId();

            // Insert each required skill
            $sqlSkill = "
                INSERT INTO internship_skill
                    (internship_id, skill_id, required_level, is_mandatory)
                VALUES
                    (:internship_id, :skill_id, :required_level, :is_mandatory)
            ";
            $stmtS = $pdo->prepare($sqlSkill);
            foreach ($rowsToInsert as [$skillId, $requiredLevel, $mandatory]) {
                $stmtS->execute([
                    ':internship_id'  => $internshipId,
                    ':skill_id'       => (int)$skillId,
                    ':required_level' => $requiredLevel,
                    ':is_mandatory'   => (int)$mandatory,
                ]);
            }

            $pdo->commit();

            // 5. Flash success & redirect to employer dashboard
            $_SESSION['status'] = 'Internship posted successfully!';
            header('Location: /Skillhive/layout.php?page=employer/dashboard');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Database error — please try again.';
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
          <option value="<?php echo e($s); ?>" <?php echo (oldVal($old,'status','Draft') === $s ? 'selected' : ''); ?>>
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
</script>