<?php
/**
 * Purpose: Employer profile page for updating company account details.
 * Tables/columns used: employer(employer_id, company_name, industry, company_address, email, contact_number, verification_status, company_badge_status, company_logo, website_url, updated_at).
 */

require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);
if (!$employerId) {
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

$errorMessage = '';
$form = [
    'company_name' => '',
    'industry' => '',
    'company_address' => '',
    'email' => '',
    'contact_number' => '',
    'website_url' => '',
    'company_logo' => '',
    'verification_status' => 'Pending',
    'company_badge_status' => 'None',
];

try {
    $stmt = $pdo->prepare(
        'SELECT employer_id, company_name, industry, company_address, email, contact_number, website_url, company_logo, verification_status, company_badge_status
         FROM employer
         WHERE employer_id = :employer_id
         LIMIT 1'
    );
    $stmt->execute([':employer_id' => (int)$employerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row && !empty($_SESSION['user_email'])) {
        $stmt = $pdo->prepare(
            'SELECT employer_id, company_name, industry, company_address, email, contact_number, website_url, company_logo, verification_status, company_badge_status
             FROM employer
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => (string)$_SESSION['user_email']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($row) {
        $employerId = (int)($row['employer_id'] ?? $employerId);
        $_SESSION['employer_id'] = $employerId;

        $form['company_name'] = trim((string)($row['company_name'] ?? ''));
        $form['industry'] = trim((string)($row['industry'] ?? ''));
        $form['company_address'] = trim((string)($row['company_address'] ?? ''));
        $form['email'] = trim((string)($row['email'] ?? ''));
        $form['contact_number'] = trim((string)($row['contact_number'] ?? ''));
        $form['website_url'] = trim((string)($row['website_url'] ?? ''));
        $form['company_logo'] = trim((string)($row['company_logo'] ?? ''));
        $form['verification_status'] = trim((string)($row['verification_status'] ?? 'Pending'));
        $form['company_badge_status'] = trim((string)($row['company_badge_status'] ?? 'None'));
    } else {
        $errorMessage = 'Employer profile record not found.';
    }
} catch (Throwable $e) {
    $errorMessage = 'Unable to load employer profile right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $industry = trim((string)($_POST['industry'] ?? ''));
    $companyAddress = trim((string)($_POST['company_address'] ?? ''));
    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $websiteUrl = trim((string)($_POST['website_url'] ?? ''));
    $companyLogo = trim((string)($_POST['company_logo'] ?? ''));

    $form['company_name'] = $companyName;
    $form['industry'] = $industry;
    $form['company_address'] = $companyAddress;
    $form['contact_number'] = $contactNumber;
    $form['website_url'] = $websiteUrl;
    $form['company_logo'] = $companyLogo;

    if ($companyName === '') {
        $errorMessage = 'Company name is required.';
    } elseif ($industry === '') {
        $errorMessage = 'Industry is required.';
    } elseif ($companyAddress === '') {
        $errorMessage = 'Company address is required.';
    } elseif ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
        $errorMessage = 'Website URL must be a valid URL.';
    } elseif (strlen($contactNumber) > 20) {
        $errorMessage = 'Contact number must be 20 characters or less.';
    }

    if ($errorMessage === '') {
        try {
            $updateStmt = $pdo->prepare(
                'UPDATE employer
                 SET company_name = :company_name,
                     industry = :industry,
                     company_address = :company_address,
                     contact_number = :contact_number,
                     website_url = :website_url,
                     company_logo = :company_logo,
                     updated_at = NOW()
                 WHERE employer_id = :employer_id'
            );
            $updateStmt->execute([
                ':company_name' => $companyName,
                ':industry' => $industry,
                ':company_address' => $companyAddress,
                ':contact_number' => $contactNumber,
                ':website_url' => $websiteUrl,
                ':company_logo' => $companyLogo,
                ':employer_id' => (int)$employerId,
            ]);

            $_SESSION['user_name'] = $companyName;
            $_SESSION['status'] = 'Employer profile updated successfully.';

            header('Location: ' . $baseUrl . '/layout.php?page=employer/profile');
            exit;
        } catch (Throwable $e) {
            $errorMessage = 'Unable to save profile changes right now.';
        }
    }
}

$verificationClass = dashboard_status_class((string)$form['verification_status']);
$verificationLabel = dashboard_status_label((string)$form['verification_status']);
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Company Profile</h2>
    <p class="page-subtitle">Update your employer account details and keep your company information complete.</p>
  </div>
</div>

<?php if ($errorMessage !== ''): ?>
  <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:.82rem;">
    <?php echo dashboard_escape($errorMessage); ?>
  </div>
<?php endif; ?>

<div class="feed-layout">
  <div class="feed-main">
    <div class="panel-card">
      <div class="panel-card-header"><h3>Profile Details</h3></div>
      <form method="post" style="display:flex;flex-direction:column;gap:14px;">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Company Name</label>
            <input class="form-control" type="text" name="company_name" value="<?php echo dashboard_escape($form['company_name']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Industry</label>
            <input class="form-control" type="text" name="industry" value="<?php echo dashboard_escape($form['industry']); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Company Address</label>
          <textarea class="form-control" name="company_address" rows="3" required><?php echo dashboard_escape($form['company_address']); ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" value="<?php echo dashboard_escape($form['email']); ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input class="form-control" type="text" name="contact_number" maxlength="20" value="<?php echo dashboard_escape($form['contact_number']); ?>" placeholder="e.g. +63 917 123 4567">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Website URL</label>
            <input class="form-control" type="url" name="website_url" value="<?php echo dashboard_escape($form['website_url']); ?>" placeholder="https://your-company.com">
          </div>
          <div class="form-group">
            <label class="form-label">Company Logo URL</label>
            <input class="form-control" type="url" name="company_logo" value="<?php echo dashboard_escape($form['company_logo']); ?>" placeholder="https://.../logo.png">
          </div>
        </div>

        <div>
          <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
        </div>
      </form>
    </div>
  </div>

  <div class="feed-side">
    <div class="panel-card" style="text-align:center;">
      <div class="profile-avatar-lg" style="margin:0 auto 12px;"><?php echo dashboard_escape(dashboard_initials($form['company_name'], '')); ?></div>
      <div style="font-weight:700;font-size:1rem;margin-bottom:4px"><?php echo dashboard_escape($form['company_name']); ?></div>
      <div style="font-size:.78rem;color:#999;margin-bottom:10px">Employer Account</div>
      <span class="status-pill <?php echo $verificationClass; ?>"><?php echo dashboard_escape($verificationLabel); ?></span>
      <div style="font-size:.78rem;color:#64748b;margin-top:8px;">Badge: <?php echo dashboard_escape($form['company_badge_status']); ?></div>
    </div>
  </div>
</div>
