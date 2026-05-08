<?php
/**
 * Purpose: Employer profile page - company details, logo upload.
 */

require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

// Define upload directory for company logos
$uploadDir = __DIR__ . '/../../assets/backend/uploads/company';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);
if (!$employerId) {
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

$industries = [
    'Advertising & Marketing',
    'Aerospace & Aviation',
    'Agriculture & Farming',
    'Artificial Intelligence & Machine Learning',
    'Automotive',
    'Banking & Financial Services',
    'Biotechnology & Life Sciences',
    'Blockchain & Cryptocurrency',
    'Broadcasting & Media',
    'Construction & Real Estate',
    'Consulting & Professional Services',
    'Consumer Goods & Retail',
    'Cybersecurity',
    'Education & EdTech',
    'Energy & Utilities',
    'Entertainment & Gaming',
    'Environmental Services & Sustainability',
    'Food & Beverage',
    'Healthcare & Pharmaceutical',
    'Human Resources & Recruitment',
    'Information Technology',
    'Insurance',
    'Legal Services',
    'Logistics & Transportation',
    'Manufacturing',
    'Mining & Metals',
    'Non-Profit & NGO',
    'Oil & Gas',
    'Publishing & Printing',
    'Real Estate & Property Management',
    'Renewable Energy & Green Tech',
    'Research & Development',
    'Robotics & Automation',
    'Software & SaaS',
    'Sports & Fitness',
    'Telecommunications',
    'Tourism & Hospitality',
    'Venture Capital & Private Equity',
];

$errorMessage = '';
$successMessage = '';
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
    'created_at' => '',
];

$companyStats = ['posts' => 0, 'applications' => 0, 'interviews' => 0];

try {
    $stmt = $pdo->prepare(
        'SELECT employer_id, company_name, industry, company_address, email, contact_number, website_url, company_logo, verification_status, company_badge_status, created_at, updated_at
         FROM employer
         WHERE employer_id = :employer_id
         LIMIT 1'
    );
    $stmt->execute([':employer_id' => (int)$employerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row && !empty($_SESSION['user_email'])) {
        $stmt = $pdo->prepare(
            'SELECT employer_id, company_name, industry, company_address, email, contact_number, website_url, company_logo, verification_status, company_badge_status, created_at, updated_at
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
        $form['created_at'] = trim((string)($row['created_at'] ?? ''));

        try {
            $s1 = $pdo->prepare('SELECT COUNT(*) FROM internship WHERE employer_id = ?');
            $s1->execute([$employerId]);
            $companyStats['posts'] = (int)$s1->fetchColumn();
        } catch (Throwable $e) {}

        try {
            $s2 = $pdo->prepare('SELECT COUNT(*) FROM application a JOIN internship i ON a.internship_id = i.internship_id WHERE i.employer_id = ?');
            $s2->execute([$employerId]);
            $companyStats['applications'] = (int)$s2->fetchColumn();
        } catch (Throwable $e) {}

        try {
            $s3 = $pdo->prepare('SELECT COUNT(*) FROM interview iv JOIN application a ON iv.application_id = a.application_id JOIN internship i ON a.internship_id = i.internship_id WHERE i.employer_id = ?');
            $s3->execute([$employerId]);
            $companyStats['interviews'] = (int)$s3->fetchColumn();
        } catch (Throwable $e) {}
    } else {
        $errorMessage = 'Employer profile record not found.';
    }
} catch (Throwable $e) {
    $errorMessage = 'Unable to load employer profile right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $selectedIndustry = trim((string)($_POST['industry'] ?? ''));
    $otherIndustry = trim((string)($_POST['other_industry'] ?? ''));
    $industry = $selectedIndustry === 'Other' ? $otherIndustry : $selectedIndustry;
    $companyAddress = trim((string)($_POST['company_address'] ?? ''));
    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $websiteUrl = trim((string)($_POST['website_url'] ?? ''));
    $companyLogoInput = trim((string)($_POST['company_logo'] ?? ''));

    $uploadedLogo = '';
    if (isset($_FILES['company_logo_file']) && is_array($_FILES['company_logo_file'])) {
        $file = $_FILES['company_logo_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($file['size'] ?? 0) > 0) {
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                $maxSize = 5 * 1024 * 1024;
                if (in_array($ext, $allowedExt, true) && (int)$file['size'] <= $maxSize) {
                    $filename = 'logo_' . $employerId . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . '/' . $filename;
                    if (move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
                        $uploadedLogo = $filename;
                        $companyLogoInput = '';
                    }
                }
            }
        }
    }

    if ($companyLogoInput !== '' && strpos($companyLogoInput, 'data:') === 0) {
        $base64Data = $companyLogoInput;
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $m)) {
            $mimeType = $m[1];
            $data = base64_decode($m[2]);
            $ext = 'png';
            if (strpos($mimeType, 'jpeg') !== false || strpos($mimeType, 'jpg') !== false) $ext = 'jpg';
            elseif (strpos($mimeType, 'webp') !== false) $ext = 'webp';
            
            $filename = 'logo_' . $employerId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . '/' . $filename;
            if (file_put_contents($targetPath, $data) !== false) {
                $uploadedLogo = $filename;
            }
        }
        $companyLogoInput = '';
    }

    if ($uploadedLogo !== '') {
        $existingStmt = $pdo->prepare('SELECT company_logo FROM employer WHERE employer_id = ?');
        $existingStmt->execute([(int)$employerId]);
        $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);
        $oldLogo = $existingRow ? trim((string)$existingRow['company_logo']) : '';
        if ($oldLogo !== '' && $oldLogo !== $uploadedLogo && file_exists($uploadDir . '/' . $oldLogo)) {
            @unlink($uploadDir . '/' . $oldLogo);
        }
    }

    $finalLogo = $uploadedLogo ?: $companyLogoInput;

    $form['company_name'] = $companyName;
    $form['industry'] = $industry;
    $form['company_address'] = $companyAddress;
    $form['contact_number'] = $contactNumber;
    $form['website_url'] = $websiteUrl;

    if ($companyName === '') {
        $errorMessage = 'Company name is required.';
    } elseif ($selectedIndustry === '') {
        $errorMessage = 'Industry is required.';
    } elseif ($selectedIndustry === 'Other' && $otherIndustry === '') {
        $errorMessage = 'Please specify your industry.';
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
                ':company_logo' => $finalLogo,
                ':employer_id' => (int)$employerId,
            ]);

            $_SESSION['user_name'] = $companyName;
            $successMessage = 'Profile updated successfully.';

            $stmt = $pdo->prepare('SELECT company_name, industry, company_address, contact_number, website_url, company_logo FROM employer WHERE employer_id = ? LIMIT 1');
            $stmt->execute([(int)$employerId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $form['company_logo'] = trim((string)($r['company_logo'] ?? ''));
            }
        } catch (Throwable $e) {
            $errorMessage = 'Unable to save profile changes right now.';
        }
    }
}

$verificationClass = dashboard_status_class((string)$form['verification_status']);
$verificationLabel = dashboard_status_label((string)$form['verification_status']);
$memberSince = $form['created_at'] !== '' ? date('M Y', strtotime($form['created_at'])) : 'N/A';

$uploadDir = __DIR__ . '/../../assets/backend/uploads/company';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function getEmployerLogoUrl($logoPath, $baseUrl) {
    if (empty($logoPath)) return '';
    if (strpos($logoPath, 'http://') === 0 || strpos($logoPath, 'https://') === 0) return $logoPath;
    return $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($logoPath);
}

$logoDisplayUrl = getEmployerLogoUrl($form['company_logo'], $baseUrl);
?>

<style>
  .employer-profile-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    animation: epp-fade-in 0.5s ease-out;
    padding-bottom: 0;
  }

  @keyframes epp-fade-in {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }


/* ── empprofile Banner ─────────────────────────────────── */
.empprofile-banner {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  padding: 32px 36px;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  gap: 20px;
  background: linear-gradient(120deg, #0d5f58 0%, #0f766e 45%, #134e4a 100%);
  box-shadow: 0 8px 32px rgba(15, 118, 110, 0.22), 0 2px 6px rgba(0,0,0,0.08);
  border: 1px solid rgba(255,255,255,0.08);
  transition: all 0.35s cubic-bezier(.4,0,.2,1);
}

.empprofile-banner .bnr-art-left {
  position: absolute;
  pointer-events: none;
  border-radius: 50%;
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, #fff 0%, transparent 70%);
  top: -80px;
  left: -60px;
  opacity: 0.12;
}

.empprofile-banner .bnr-art-right {
  position: absolute;
  pointer-events: none;
  border-radius: 50%;
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, #5eead4 0%, transparent 70%);
  bottom: -90px;
  right: 60px;
  opacity: 0.18;
}

.empprofile-banner .bnr-body {
  position: relative;
  z-index: 1;
  flex: 1;
}

.empprofile-banner .bnr-meta {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.55);
  margin-bottom: 6px;
}

.empprofile-banner .bnr-heading {
  font-size: 26px;
  font-weight: 800;
  color: #ffffff;
  margin-bottom: 6px;
  line-height: 1.2;
}

.empprofile-banner .bnr-sub {
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  line-height: 1.5;
  max-width: 520px;
  margin-bottom: 18px;
}

.empprofile-banner .bnr-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.empprofile-banner .bnr-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #ffffff;
  backdrop-filter: blur(4px);
}

.empprofile-banner .bnr-collapse-btn {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 2;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.75);
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  backdrop-filter: blur(4px);
}

.empprofile-banner .bnr-collapse-btn:hover {
  background: rgba(255,255,255,0.22);
  color: #fff;
}

.empprofile-banner.bnr-collapsed {
  display: none;
}

.empprofile-restore-bar {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  margin: 0 0 16px 0;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  user-select: none;
}

.empprofile-restore-bar:hover { background: #f9fafb; }

.empprofile-restore-bar .bnr-restore-date {
  font-size: 12px;
  font-weight: 400;
  color: #9ca3af;
  margin-left: auto;
}

.empprofile-restore-bar.bnr-visible { display: flex; }

@media (max-width: 768px) {
  .empprofile-banner { padding: 24px 20px 20px; }
  .empprofile-banner .bnr-heading { font-size: 20px; }
  .empprofile-banner .bnr-sub { display: none; }
}

  .employer-profile-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    position: relative;
    z-index: 2;
    align-items: start;
    margin-top: 8px;
  }

  .employer-profile-panel {
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    padding: 28px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .employer-profile-panel:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }

  .employer-profile-title {
    margin: 0 0 24px;
    font-size: 1.25rem;
    font-weight: 700;
    color: #050505;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .employer-profile-title i {
    color: #12b3ac;
    font-size: 1.1rem;
  }

  .employer-profile-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .employer-form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .employer-form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .employer-form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .employer-form-label i {
    font-size: 0.75rem;
    color: #12b3ac;
  }

  .employer-form-control {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    background: #fafbfc;
    color: #0f172a;
    font-size: 0.9rem;
    padding: 13px 16px;
    font-family: inherit;
    outline: none;
    transition: all 0.2s ease;
  }

  .employer-form-control:hover { border-color: #d1d5db; }

  .employer-form-control:focus {
    border-color: #12b3ac;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(18, 179, 172, 0.1);
  }

  .employer-form-control[readonly] {
    background: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
    border-style: dashed;
  }

  .employer-profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-radius: 14px;
    border: none;
    min-height: 48px;
    padding: 0 28px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    background: linear-gradient(135deg, #050505 0%, #1a1a1a 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(5, 5, 5, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }

  .employer-profile-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #12b3ac 0%, #10B981 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .employer-profile-btn:hover::before { opacity: 1; }

  .employer-profile-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(18, 179, 172, 0.3);
  }

  .employer-profile-btn span,
  .employer-profile-btn i { position: relative; z-index: 1; }
  .employer-profile-btn:active { transform: translateY(0); }

  .employer-profile-error,
  .employer-profile-success {
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .employer-profile-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(239, 68, 68, 0.04) 100%);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: #dc2626;
  }

  .employer-profile-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, rgba(34, 197, 94, 0.04) 100%);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: #16a34a;
  }

  .employer-profile-card {
    text-align: center;
    background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
  }

  .employer-profile-avatar-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 20px;
  }

  .employer-profile-avatar {
    width: 110px;
    height: 110px;
    border-radius: 24px;
    background: linear-gradient(135deg, #12b3ac 0%, #10B981 100%);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    box-shadow: 0 8px 24px rgba(18, 179, 172, 0.3);
    border: 4px solid #fff;
    position: relative;
    z-index: 1;
    transition: transform 0.3s ease;
    overflow: hidden;
  }

  .employer-profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .employer-profile-avatar-wrapper:hover .employer-profile-avatar {
    transform: scale(1.05) rotate(2deg);
  }

  .employer-profile-avatar-ring {
    position: absolute;
    inset: -6px;
    border-radius: 28px;
    border: 2px dashed rgba(18, 179, 172, 0.3);
    animation: epp-spin 20s linear infinite;
  }

  @keyframes epp-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  .employer-profile-company-name {
    margin: 0 0 6px;
    font-size: 1.3rem;
    font-weight: 700;
    color: #050505;
    letter-spacing: -0.02em;
  }

  .employer-profile-meta {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }

  .employer-profile-meta i {
    font-size: 0.8rem;
    color: #12b3ac;
  }

  .employer-profile-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 16px;
    transition: all 0.2s ease;
  }

  .employer-profile-status-badge:hover { transform: scale(1.05); }
  .employer-profile-status-badge i { font-size: 0.7rem; }

  .employer-profile-status-badge.verified,
  .employer-profile-status-badge.approved {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.12) 0%, rgba(34, 197, 94, 0.06) 100%);
    color: #16a34a;
    border: 1px solid rgba(34, 197, 94, 0.2);
  }

  .employer-profile-status-badge.pending {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.12) 0%, rgba(249, 115, 22, 0.06) 100%);
    color: #ea580c;
    border: 1px solid rgba(249, 115, 22, 0.2);
  }

  .employer-profile-status-badge.rejected {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.12) 0%, rgba(220, 38, 38, 0.06) 100%);
    color: #dc2626;
    border: 1px solid rgba(220, 38, 38, 0.2);
  }

  .employer-profile-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
    margin: 20px 0;
  }

  .employer-profile-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
  }

  .employer-profile-stat-item {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px 16px;
    background: #fff;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
  }

  .employer-profile-stat-item:hover {
    border-color: #12b3ac;
    box-shadow: 0 4px 12px rgba(18, 179, 172, 0.1);
    background: linear-gradient(135deg, #fff 0%, rgba(18, 179, 172, 0.03) 100%);
  }

  .employer-profile-stat-label {
    font-size: 0.85rem;
    color: #6b7280;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .employer-profile-stat-label i {
    color: #12b3ac;
    font-size: 0.85rem;
  }

  .employer-profile-stat-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #050505;
    background: linear-gradient(135deg, #12b3ac, #10B981);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .employer-profile-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
  }

  .epp-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
  }

  .epp-info-row:last-child { border-bottom: none; }

  .epp-info-label {
    font-size: 0.82rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .epp-info-label i {
    color: #12b3ac;
    font-size: 0.8rem;
    width: 16px;
    text-align: center;
  }

  .epp-info-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #050505;
  }

  .logo-upload-area {
    border: 2px dashed #e5e7eb;
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 16px;
    background: #fafbfc;
  }

  .logo-upload-area:hover {
    border-color: #12b3ac;
    background: rgba(18, 179, 172, 0.05);
  }

  .logo-upload-area i {
    font-size: 2.5rem;
    color: #12b3ac;
    margin-bottom: 12px;
    display: block;
  }

  .logo-upload-text {
    font-size: 0.9rem;
    color: #6b7280;
    line-height: 1.5;
  }

  .logo-upload-text strong {
    color: #12b3ac;
  }

  .logo-preview {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    object-fit: cover;
    margin: 0 auto 12px;
    display: none;
    border: 3px solid #e5e7eb;
  }

  .logo-preview.show { display: block; }

  .logo-preview-hint {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 6px;
  }

  #logoFileInput { display: none; }

  @media (max-width: 1000px) {
    .employer-profile-layout {
      grid-template-columns: 1fr;
    }
    .employer-profile-cover {
      height: 160px;
      margin-bottom: -55px;
    }
  }

  @media (max-width: 640px) {
    .employer-form-row { grid-template-columns: 1fr; }
    .employer-profile-panel { padding: 20px; }
    .employer-profile-cover { height: 140px; }
  }

  .other-industry-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
  }

  .other-industry-modal.show {
    display: flex;
  }

  .other-industry-modal-content {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  }

  .other-industry-modal-content h3 {
    margin: 0 0 16px;
    font-size: 1.1rem;
    font-weight: 600;
    color: #111;
  }

  .other-industry-modal-content input {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    font-size: 0.9rem;
    margin-bottom: 16px;
    outline: none;
    transition: all 0.2s ease;
  }

  .other-industry-modal-content input:focus {
    border-color: #12b3ac;
    box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.1);
  }

  .other-industry-modal-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }

  .other-industry-modal-actions button {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .other-industry-modal-actions .btn-cancel {
    background: #f3f4f6;
    border: none;
    color: #374151;
  }

  .other-industry-modal-actions .btn-cancel:hover {
    background: #e5e7eb;
  }

  .other-industry-modal-actions .btn-save {
    background: #12b3ac;
    border: none;
    color: #fff;
  }

  .other-industry-modal-actions .btn-save:hover {
    background: #10a8a0;
  }
</style>

<script>
function toggleEmpprofileBanner() {
  const banner = document.getElementById('empprofileBanner');
  const bar    = document.getElementById('empprofileRestoreBar');
  const isCollapsed = banner.classList.toggle('bnr-collapsed');
  bar.classList.toggle('bnr-visible', isCollapsed);
}

document.addEventListener('DOMContentLoaded', function() {
  var industrySelect = document.getElementById('industry');
  var industryHidden = document.getElementById('industryHidden');
  var modal = document.getElementById('otherIndustryModal');
  var modalInput = document.getElementById('otherIndustryInput');

  if (industrySelect) {
    industrySelect.addEventListener('change', function() {
      if (this.value === 'Other') {
        modal.classList.add('show');
        setTimeout(function() { modalInput.focus(); }, 100);
      }
    });
  }

  window.closeOtherIndustryModal = function() {
    modal.classList.remove('show');
    industrySelect.value = industryHidden ? industryHidden.value : '';
  };

  window.saveOtherIndustry = function() {
    var value = modalInput.value.trim();
    if (value) {
      industryHidden.value = value;
      closeOtherIndustryModal();
    } else {
      modalInput.style.borderColor = '#ef4444';
      setTimeout(function() {
        modalInput.style.borderColor = '#e5e7eb';
      }, 1000);
    }
  };

  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      closeOtherIndustryModal();
    }
  });
});
</script>

<div class="other-industry-modal" id="otherIndustryModal">
  <div class="other-industry-modal-content">
    <h3>Specify Your Industry</h3>
    <input type="text" id="otherIndustryInput" placeholder="Enter your industry">
    <div class="other-industry-modal-actions">
      <button type="button" class="btn-cancel" onclick="closeOtherIndustryModal()">Cancel</button>
      <button type="button" class="btn-save" onclick="saveOtherIndustry()">Save</button>
    </div>
  </div>
</div>

<div class="employer-profile-page">
  <div class="empprofile-banner" id="empprofileBanner">
    <div class="bnr-art-left"></div>
    <div class="bnr-art-right"></div>
    <div class="bnr-body">
      <div class="bnr-meta"><?php echo date('l, j F Y'); ?></div>
      <div class="bnr-heading"><?php echo 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) . ', ' . dashboard_escape($form['company_name'] ?: 'Your Company') . '!'; ?></div>
      <div class="bnr-sub">Update your company details, logo, and contact information below.</div>
      <div class="bnr-pills">
      <span class="bnr-pill"><i class="fas fa-id-card"></i> Company Profile</span>
      </div>
    </div>
    <button type="button" class="bnr-collapse-btn" onclick="toggleEmpprofileBanner()" title="Collapse banner">
      <i class="fas fa-chevron-up"></i>
    </button>
  </div>
  <div class="empprofile-restore-bar" id="empprofileRestoreBar" onclick="toggleEmpprofileBanner()">
    <span><i class="fas fa-building" style="margin-right:6px;color:#0f766e;"></i>Company Profile</span>
    <span class="bnr-restore-date"><?php echo date('l, j F'); ?></span>
    <i class="fas fa-chevron-down" style="color:#9ca3af;font-size:12px;"></i>
  </div>


    <section class="employer-profile-panel">
      <?php if ($errorMessage !== ''): ?>
        <div class="employer-profile-error" style="margin-bottom:18px;"><i class="fas fa-circle-exclamation"></i> <?php echo dashboard_escape($errorMessage); ?></div>
      <?php endif; ?>
      <?php if ($successMessage !== ''): ?>
        <div class="employer-profile-success" style="margin-bottom:18px;"><i class="fas fa-circle-check"></i> <?php echo dashboard_escape($successMessage); ?></div>
        <?php if ($logoDisplayUrl !== ''): ?>
        <div id="pendingAvatarUpdate" data-url="<?php echo htmlspecialchars($logoDisplayUrl); ?>" style="display:none;"></div>
        <?php endif; ?>
      <?php endif; ?>

      <h3 class="employer-profile-title"><i class="fas fa-pen-to-square"></i> Edit Company Profile</h3>

      <form class="employer-profile-form" method="post" enctype="multipart/form-data">
        <label class="logo-upload-area" for="logoFileInput">
          <img class="logo-preview <?php echo $form['company_logo'] !== '' ? 'show' : ''; ?>" 
               src="<?php echo $logoDisplayUrl; ?>" 
               alt="Company Logo" id="logoPreview">
          <i class="fas fa-cloud-arrow-up"></i>
          <div class="logo-upload-text">
            <strong>Click to upload company logo</strong><br>
            JPG, PNG, WEBP (max 5MB)
          </div>
          <div class="logo-preview-hint" id="logoHint"></div>
          <input type="file" id="logoFileInput" name="company_logo_file" accept=".jpg,.jpeg,.png,.webp">
        </label>

        <div class="employer-form-row">
          <div class="employer-form-group">
            <label class="employer-form-label" for="companyName"><i class="fas fa-building"></i> Company Name</label>
            <input id="companyName" class="employer-form-control" type="text" name="company_name" value="<?php echo dashboard_escape($form['company_name']); ?>" required>
          </div>
          <div class="employer-form-group">
            <label class="employer-form-label" for="industry"><i class="fas fa-briefcase"></i> Industry</label>
            <select id="industry" class="employer-form-control" name="industry" required>
              <option value="" disabled <?php echo $form['industry'] === '' ? 'selected' : ''; ?>>Select your industry</option>
              <?php foreach ($industries as $ind): ?>
                <option value="<?php echo dashboard_escape($ind); ?>" <?php echo $form['industry'] === $ind ? 'selected' : ''; ?>><?php echo dashboard_escape($ind); ?></option>
              <?php endforeach; ?>
              <option value="Other" <?php echo !in_array($form['industry'], $industries, true) && $form['industry'] !== '' ? 'selected' : ''; ?>>Other</option>
            </select>
            <input type="hidden" id="industryHidden" name="other_industry" value="<?php echo !in_array($form['industry'], $industries, true) ? dashboard_escape($form['industry']) : ''; ?>">
          </div>
        </div>

        <div class="employer-form-group">
          <label class="employer-form-label" for="companyAddress"><i class="fas fa-location-dot"></i> Company Address</label>
          <textarea id="companyAddress" class="employer-form-control" name="company_address" rows="3" required><?php echo dashboard_escape($form['company_address']); ?></textarea>
        </div>

        <div class="employer-form-row">
          <div class="employer-form-group">
            <label class="employer-form-label" for="email"><i class="fas fa-envelope"></i> Email</label>
            <input id="email" class="employer-form-control" type="email" value="<?php echo dashboard_escape($form['email']); ?>" readonly>
          </div>
          <div class="employer-form-group">
            <label class="employer-form-label" for="contactNumber"><i class="fas fa-phone"></i> Contact Number</label>
            <input id="contactNumber" class="employer-form-control" type="text" name="contact_number" maxlength="20" value="<?php echo dashboard_escape($form['contact_number']); ?>" placeholder="e.g. +63 917 123 4567">
          </div>
        </div>

        <div class="employer-form-group">
          <label class="employer-form-label" for="websiteUrl"><i class="fas fa-globe"></i> Website URL</label>
          <input id="websiteUrl" class="employer-form-control" type="url" name="website_url" value="<?php echo dashboard_escape($form['website_url']); ?>" placeholder="https://your-company.com">
        </div>

        <input type="hidden" name="company_logo" id="companyLogoHidden" value="<?php echo dashboard_escape($form['company_logo']); ?>">

        <div>
          <button type="submit" class="employer-profile-btn"><i class="fas fa-floppy-disk"></i> <span>Save Changes</span></button>
        </div>
      </form>
    </section>

    <aside class="employer-profile-panel employer-profile-card">
      <div class="employer-profile-avatar-wrapper">
        <div class="employer-profile-avatar" id="sidebarAvatar">
          <?php if ($form['company_logo'] !== ''): ?>
            <img src="<?php echo $logoDisplayUrl; ?>" alt="Company logo" onerror="this.style.display='none';this.parentElement.textContent='<?php echo dashboard_escape(dashboard_initials($form['company_name'], '')); ?>';">
          <?php else: ?>
            <?php echo dashboard_escape(dashboard_initials($form['company_name'], '')); ?>
          <?php endif; ?>
        </div>
        <div class="employer-profile-avatar-ring"></div>
      </div>
      <p class="employer-profile-company-name"><?php echo dashboard_escape($form['company_name']); ?></p>
      <p class="employer-profile-meta"><i class="fas fa-briefcase"></i> <?php echo dashboard_escape($form['industry'] !== '' ? $form['industry'] : 'Industry not specified'); ?></p>
      <span class="employer-profile-status-badge <?php echo strtolower((string)$form['verification_status']); ?>">
        <?php if (strtolower((string)$form['verification_status']) === 'verified' || strtolower((string)$form['verification_status']) === 'approved'): ?>
          <i class="fas fa-circle-check"></i>
        <?php elseif (strtolower((string)$form['verification_status']) === 'pending'): ?>
          <i class="fas fa-clock"></i>
        <?php else: ?>
          <i class="fas fa-circle-xmark"></i>
        <?php endif; ?>
        <?php echo dashboard_escape($verificationLabel); ?>
      </span>

      <div class="employer-profile-divider"></div>

      <div class="employer-profile-info">
        <div class="epp-info-row">
          <span class="epp-info-label"><i class="fas fa-certificate"></i> Badge</span>
          <span class="epp-info-value"><?php echo dashboard_escape($form['company_badge_status']); ?></span>
        </div>
        <div class="epp-info-row">
          <span class="epp-info-label"><i class="fas fa-calendar-plus"></i> Member Since</span>
          <span class="epp-info-value"><?php echo dashboard_escape($memberSince); ?></span>
        </div>
        <div class="epp-info-row">
          <span class="epp-info-label"><i class="fas fa-envelope"></i> Email</span>
          <span class="epp-info-value" style="font-size:0.75rem;"><?php echo dashboard_escape($form['email']); ?></span>
        </div>
      </div>

      <div class="employer-profile-divider"></div>

      <div class="employer-profile-stats">
        <div class="employer-profile-stat-item">
          <span class="employer-profile-stat-label"><i class="fas fa-file-lines"></i> Active Posts</span>
          <span class="employer-profile-stat-value"><?php echo (int)$companyStats['posts']; ?></span>
        </div>
        <div class="employer-profile-stat-item">
          <span class="employer-profile-stat-label"><i class="fas fa-users"></i> Applications</span>
          <span class="employer-profile-stat-value"><?php echo (int)$companyStats['applications']; ?></span>
        </div>
        <div class="employer-profile-stat-item">
          <span class="employer-profile-stat-label"><i class="fas fa-video"></i> Interviews</span>
          <span class="employer-profile-stat-value"><?php echo (int)$companyStats['interviews']; ?></span>
        </div>
      </div>
    </aside>
  </div>
</div>

<script>
(function() {
  var logoInput = document.getElementById('logoFileInput');
  var logoPreview = document.getElementById('logoPreview');
  var logoHint = document.getElementById('logoHint');
  var logoHidden = document.getElementById('companyLogoHidden');
  var sidebarAvatar = document.getElementById('sidebarAvatar');
  var baseUrl = '<?php echo $baseUrl; ?>';

  var industryHidden = document.getElementById('industryHidden');
  var industrySelect = document.getElementById('industry');

  function getLogoUrl(path) {
    if (!path) return '';
    if (path.indexOf('http://') === 0 || path.indexOf('https://') === 0) return path;
    if (path.indexOf('data:') === 0) return path;
    return baseUrl + '/assets/backend/uploads/company/' + path;
  }

  if (logoHidden && logoHidden.value) {
    var url = getLogoUrl(logoHidden.value);
    if (logoPreview) logoPreview.src = url;
    if (sidebarAvatar) {
      var sidebarImg = sidebarAvatar.querySelector('img');
      if (sidebarImg) sidebarImg.src = url;
    }
  }

  if (logoInput) {
    logoInput.addEventListener('change', function(e) {
      var file = e.target.files[0];
      if (!file) return;

      var allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (allowed.indexOf(file.type) === -1) {
        if (logoHint) logoHint.textContent = 'Only JPG, PNG, WEBP allowed.';
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        if (logoHint) logoHint.textContent = 'File must be under 5MB.';
        return;
      }

      var reader = new FileReader();
      reader.onload = function(evt) {
        var result = evt.target.result;
        if (logoPreview) {
          logoPreview.src = result;
          logoPreview.classList.add('show');
        }
        if (logoHint) logoHint.textContent = file.name;
        logoHidden.value = result;
      };
      reader.readAsDataURL(file);
    });
  }
})();
</script>