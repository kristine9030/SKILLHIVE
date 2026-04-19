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

<style>
  .employer-profile-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .employer-profile-cover {
    height: 180px;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 40%, #dc2626 70%, #b91c1c 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    margin-bottom: -60px;
    z-index: 1;
  }

  .employer-profile-cover::before {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    pointer-events: none;
  }

  .employer-profile-cover::after {
    content: '';
    position: absolute;
    bottom: -40px;
    left: -20px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
  }

  .employer-profile-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, .85fr);
    gap: 18px;
    position: relative;
    z-index: 2;
  }

  .employer-profile-panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all .2s ease;
  }

  .employer-profile-panel::before {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(249, 115, 22, 0.06);
    pointer-events: none;
  }

  .employer-profile-panel::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -20px;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(249, 115, 22, 0.04);
    pointer-events: none;
  }

  .employer-profile-panel:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  }

  .employer-profile-title {
    margin: 0 0 18px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    position: relative;
    z-index: 1;
  }

  .employer-profile-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: relative;
    z-index: 1;
  }

  .employer-form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .employer-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .employer-form-label {
    font-size: .82rem;
    font-weight: 600;
    color: #0f172a;
  }

  .employer-form-control {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
    color: #0f172a;
    font-size: .86rem;
    padding: 12px 14px;
    font-family: inherit;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .employer-form-control:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
  }

  .employer-form-control[readonly] {
    background: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
  }

  .employer-form-control:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
  }

  .employer-profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 12px;
    border: none;
    min-height: 42px;
    padding: 0 24px;
    cursor: pointer;
    text-decoration: none;
    font-size: .86rem;
    font-weight: 700;
    background: #111827;
    color: #fff;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.3);
    transition: all .2s ease;
  }

  .employer-profile-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 24, 39, 0.4);
    background: #0f172a;
  }

  .employer-profile-error {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: #dc2626;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: .82rem;
    position: relative;
    z-index: 1;
  }

  .employer-profile-card {
    text-align: center;
  }

  .employer-profile-avatar {
    width: 100px;
    height: 100px;
    margin: 0 auto 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    box-shadow: 0 8px 24px rgba(249, 115, 22, 0.3);
    border: 4px solid #fff;
    position: relative;
    z-index: 1;
  }

  .employer-profile-company-name {
    margin: 0 0 8px;
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
    position: relative;
    z-index: 1;
  }

  .employer-profile-meta {
    margin: 4px 0 0;
    color: #64748b;
    font-size: .86rem;
    position: relative;
    z-index: 1;
  }

  .employer-profile-status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 700;
    margin-top: 12px;
    position: relative;
    z-index: 1;
  }

  .employer-profile-status-badge.verified {
    background: rgba(34, 197, 94, 0.1);
    color: #16a34a;
  }

  .employer-profile-status-badge.pending {
    background: rgba(249, 115, 22, 0.1);
    color: #ea580c;
  }

  .employer-profile-status-badge.rejected {
    background: rgba(220, 38, 38, 0.1);
    color: #dc2626;
  }

  .employer-profile-stats {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-top: 18px;
    position: relative;
    z-index: 1;
  }

  .employer-profile-stat-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 14px;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .employer-profile-stat-item:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .employer-profile-stat-label {
    font-size: .82rem;
    color: #64748b;
    font-weight: 600;
  }

  .employer-profile-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: #f97316;
  }

  @media (max-width: 1000px) {
    .employer-profile-layout {
      grid-template-columns: 1fr;
    }

    .employer-profile-cover {
      height: 150px;
      margin-bottom: -50px;
    }
  }

  @media (max-width: 640px) {
    .employer-form-row {
      grid-template-columns: 1fr;
    }

    .employer-profile-panel {
      padding: 18px;
    }
  }
</style>

<div class="employer-profile-page">
  <div class="employer-profile-cover"></div>

  <div class="employer-profile-layout">
    <section class="employer-profile-panel">
      <h3 class="employer-profile-title">Company Profile Details</h3>

      <?php if ($errorMessage !== ''): ?>
        <div class="employer-profile-error" style="margin-bottom:14px;"><?php echo dashboard_escape($errorMessage); ?></div>
      <?php endif; ?>

      <form class="employer-profile-form" method="post">
        <div class="employer-form-row">
          <div class="employer-form-group">
            <label class="employer-form-label" for="companyName">Company Name</label>
            <input id="companyName" class="employer-form-control" type="text" name="company_name" value="<?php echo dashboard_escape($form['company_name']); ?>" required>
          </div>
          <div class="employer-form-group">
            <label class="employer-form-label" for="industry">Industry</label>
            <input id="industry" class="employer-form-control" type="text" name="industry" value="<?php echo dashboard_escape($form['industry']); ?>" required>
          </div>
        </div>

        <div class="employer-form-group">
          <label class="employer-form-label" for="companyAddress">Company Address</label>
          <textarea id="companyAddress" class="employer-form-control" name="company_address" rows="3" required><?php echo dashboard_escape($form['company_address']); ?></textarea>
        </div>

        <div class="employer-form-row">
          <div class="employer-form-group">
            <label class="employer-form-label" for="email">Email</label>
            <input id="email" class="employer-form-control" type="email" value="<?php echo dashboard_escape($form['email']); ?>" readonly>
          </div>
          <div class="employer-form-group">
            <label class="employer-form-label" for="contactNumber">Contact Number</label>
            <input id="contactNumber" class="employer-form-control" type="text" name="contact_number" maxlength="20" value="<?php echo dashboard_escape($form['contact_number']); ?>" placeholder="e.g. +63 917 123 4567">
          </div>
        </div>

        <div class="employer-form-row">
          <div class="employer-form-group">
            <label class="employer-form-label" for="websiteUrl">Website URL</label>
            <input id="websiteUrl" class="employer-form-control" type="url" name="website_url" value="<?php echo dashboard_escape($form['website_url']); ?>" placeholder="https://your-company.com">
          </div>
          <div class="employer-form-group">
            <label class="employer-form-label" for="companyLogo">Company Logo URL</label>
            <input id="companyLogo" class="employer-form-control" type="url" name="company_logo" value="<?php echo dashboard_escape($form['company_logo']); ?>" placeholder="https://.../logo.png">
          </div>
        </div>

        <div>
          <button type="submit" class="employer-profile-btn"><i class="fas fa-floppy-disk"></i> Save Profile</button>
        </div>
      </form>
    </section>

    <aside class="employer-profile-panel employer-profile-card">
      <div class="employer-profile-avatar"><?php echo dashboard_escape(dashboard_initials($form['company_name'], '')); ?></div>
      <p class="employer-profile-company-name"><?php echo dashboard_escape($form['company_name']); ?></p>
      <p class="employer-profile-meta"><?php echo dashboard_escape($form['industry'] !== '' ? $form['industry'] : 'Industry not specified'); ?></p>
      <span class="employer-profile-status-badge <?php echo strtolower((string)$form['verification_status']); ?>"><?php echo dashboard_escape($verificationLabel); ?></span>

      <div class="employer-profile-stats">
        <div class="employer-profile-stat-item">
          <span class="employer-profile-stat-label">Badge Status</span>
          <span class="employer-profile-stat-value"><?php echo dashboard_escape($form['company_badge_status']); ?></span>
        </div>
      </div>
    </aside>
  </div>

