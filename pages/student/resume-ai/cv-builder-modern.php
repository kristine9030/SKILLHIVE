<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../../../backend/db_connect.php';
}

$userId = isset($userId) ? (int) $userId : (int) ($_SESSION['user_id'] ?? 0);
$student = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'program' => '',
    'department' => '',
    'city' => '',
    'country' => '',
    'job_title' => '',
    'bio' => '',
    'profile_picture' => '',
];

if (isset($pdo) && $pdo instanceof PDO && $userId > 0) {
  try {
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone, program, department, city, country, profile_picture FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
      $student = array_merge($student, $row);
    }
  } catch (Throwable $e) {
    // Backward compatibility for older schemas without extended profile columns.
    try {
      $stmt = $pdo->prepare('SELECT first_name, last_name, email, program, department FROM student WHERE student_id = ? LIMIT 1');
      $stmt->execute([$userId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (is_array($row)) {
        $student = array_merge($student, $row);
      }
    } catch (Throwable $ignored) {
      // Keep default fallback values so the page can still load.
    }
  }
}

$defaultFirstName = (string) ($student['first_name'] ?? '');
$defaultLastName = (string) ($student['last_name'] ?? '');
$defaultEmail = (string) ($student['email'] ?? ($_SESSION['user_email'] ?? ''));
$defaultPhone = (string) ($student['phone'] ?? '');
$defaultCity = (string) ($student['city'] ?? '');
$defaultCountry = (string) ($student['country'] ?? '');
$defaultJobTitle = (string) ($student['program'] ?? 'Service Designer');
$defaultBio = 'Motivated student seeking internship opportunities and focused on practical project impact.';

$defaultSkills = [];
if (isset($pdo) && $pdo instanceof PDO && $userId > 0) {
  try {
    $skillsStmt = $pdo->prepare(
      'SELECT DISTINCT sk.skill_name
       FROM student_skill ss
       INNER JOIN skill sk ON sk.skill_id = ss.skill_id
       WHERE ss.student_id = ?
       ORDER BY ss.verified DESC, sk.skill_name ASC'
    );
    $skillsStmt->execute([$userId]);
    foreach ($skillsStmt->fetchAll(PDO::FETCH_ASSOC) as $skillRow) {
      $skillName = trim((string) ($skillRow['skill_name'] ?? ''));
      if ($skillName !== '') {
        $defaultSkills[] = $skillName;
      }
    }
  } catch (Throwable $e) {
    $defaultSkills = [];
  }
}

$savedCv = [];
if (isset($pdo) && $pdo instanceof PDO && $userId > 0) {
  try {
    $savedCvStmt = $pdo->prepare('SELECT cv_json FROM student_cv_builder WHERE student_id = ? AND source_mode = "form" LIMIT 1');
    $savedCvStmt->execute([$userId]);
    $savedCvRow = $savedCvStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($savedCvRow) && !empty($savedCvRow['cv_json'])) {
      $decodedCv = json_decode((string) $savedCvRow['cv_json'], true);
      if (is_array($decodedCv)) {
        $savedCv = $decodedCv;
      }
    }
  } catch (Throwable $e) {
    $savedCv = [];
  }
}

$initialCv = [
  'firstName' => $defaultFirstName,
  'lastName' => $defaultLastName,
  'email' => $defaultEmail,
  'phone' => $defaultPhone,
  'jobTitle' => $defaultJobTitle,
  'city' => $defaultCity,
  'country' => $defaultCountry,
  'bio' => $defaultBio,
  'program' => (string) ($student['program'] ?? ''),
  'department' => (string) ($student['department'] ?? ''),
  'addressLine' => '',
  'linkedin' => '',
  'twitter' => '',
  'educationDates' => '',
  'skills' => $defaultSkills,
  'experiences' => [],
  'profilePicture' => (string) ($student['profile_picture'] ?? ''),
  'language' => 'en',
];

if (is_array($savedCv) && !empty($savedCv)) {
  foreach (['firstName', 'lastName', 'email', 'phone', 'jobTitle', 'city', 'country', 'bio', 'program', 'department', 'addressLine', 'linkedin', 'twitter', 'educationDates', 'profilePicture', 'language'] as $key) {
    if (array_key_exists($key, $savedCv) && is_string($savedCv[$key])) {
      $initialCv[$key] = $savedCv[$key];
    }
  }

  if (isset($savedCv['skills']) && is_array($savedCv['skills'])) {
    $skills = [];
    foreach ($savedCv['skills'] as $skillValue) {
      $skillText = trim((string) $skillValue);
      if ($skillText !== '' && !in_array(strtolower($skillText), array_map('strtolower', $skills), true)) {
        $skills[] = $skillText;
      }
    }
    $initialCv['skills'] = $skills;
  }

  if (isset($savedCv['experiences']) && is_array($savedCv['experiences'])) {
    $experiences = [];
    foreach ($savedCv['experiences'] as $experience) {
      if (!is_array($experience)) {
        continue;
      }
      $experiences[] = [
        'role' => trim((string) ($experience['role'] ?? '')),
        'company' => trim((string) ($experience['company'] ?? '')),
        'location' => trim((string) ($experience['location'] ?? '')),
        'dates' => trim((string) ($experience['dates'] ?? '')),
        'description' => trim((string) ($experience['description'] ?? '')),
      ];
    }
    $initialCv['experiences'] = $experiences;
  }

  if (!in_array($initialCv['language'], ['en', 'es', 'fr'], true)) {
    $initialCv['language'] = 'en';
  }
}

$resumeAiEndpointUrl = (isset($baseUrl) && is_string($baseUrl) && trim($baseUrl) !== '' ? rtrim($baseUrl, '/') : '/SkillHive') . '/pages/student/resume_ai_endpoint.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">CV Builder</h2>
    <p class="page-subtitle" id="cvPageSubtitle">Create a professional CV with live preview</p>
  </div>
</div>

<div class="cv-builder-container">
  <div class="cv-form-section">
    <!-- Profile Section -->
    <div class="cv-form-card">
      <div class="cv-form-header">
        <h3 id="detailsCardTitle">Your Details</h3>
        <select id="cvLanguage" class="cv-language-select">
          <option value="en" selected>🇬🇧 English</option>
          <option value="es">🇪🇸 Español</option>
          <option value="fr">🇫🇷 Français</option>
        </select>
      </div>

      <!-- Profile Picture -->
      <div class="cv-form-group">
        <label for="profilePicture">Profile Picture</label>
        <div class="profile-picture-upload">
          <input type="file" id="profilePicture" accept="image/*" class="profile-picture-input">
          <div class="profile-picture-preview">
            <img id="profilePicturePreview" src="<?php echo htmlspecialchars((string) ($initialCv['profilePicture'] ?? ''), ENT_QUOTES); ?>" alt="Profile" onerror="this.style.display='none'">
            <span id="profilePicturePlaceholder">+</span>
          </div>
        </div>
      </div>

      <!-- Job Title -->
      <div class="cv-form-group">
        <label for="jobTitle">Job Title <i class="fas fa-info-circle"></i></label>
        <select id="jobTitle" class="cv-form-input">
          <option value="">Select job title</option>
          <option value="Service Designer">Service Designer</option>
          <option value="Product Manager">Product Manager</option>
          <option value="UX Designer">UX Designer</option>
          <option value="Developer">Developer</option>
          <option value="Data Analyst">Data Analyst</option>
        </select>
      </div>

      <!-- Name Fields -->
      <div class="cv-form-row">
        <div class="cv-form-group">
          <label for="firstName">First Name</label>
          <input type="text" id="firstName" class="cv-form-input" placeholder="First Name" value="<?php echo htmlspecialchars((string) ($initialCv['firstName'] ?? $defaultFirstName), ENT_QUOTES); ?>">
        </div>
        <div class="cv-form-group">
          <label for="lastName">Last Name</label>
          <input type="text" id="lastName" class="cv-form-input" placeholder="Last Name" value="<?php echo htmlspecialchars((string) ($initialCv['lastName'] ?? $defaultLastName), ENT_QUOTES); ?>">
        </div>
      </div>

      <!-- Email -->
      <div class="cv-form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" class="cv-form-input" placeholder="email@example.com" value="<?php echo htmlspecialchars((string) ($initialCv['email'] ?? $defaultEmail), ENT_QUOTES); ?>">
      </div>

      <!-- Phone -->
      <div class="cv-form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" class="cv-form-input" placeholder="(123) 456-7890" value="<?php echo htmlspecialchars((string) ($initialCv['phone'] ?? ''), ENT_QUOTES); ?>">
      </div>

      <!-- Location -->
      <div class="cv-form-row">
        <div class="cv-form-group">
          <label for="city">City</label>
          <input type="text" id="city" class="cv-form-input" placeholder="City" value="<?php echo htmlspecialchars((string) ($initialCv['city'] ?? ''), ENT_QUOTES); ?>">
        </div>
        <div class="cv-form-group">
          <label for="country">Country</label>
          <input type="text" id="country" class="cv-form-input" placeholder="Country" value="<?php echo htmlspecialchars((string) ($initialCv['country'] ?? ''), ENT_QUOTES); ?>">
        </div>
      </div>

      <!-- Edit Additional Info -->
      <div class="cv-form-collapsible">
        <button type="button" class="cv-form-toggle" aria-expanded="false">
          <span>Edit additional info</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="cv-form-collapsible-content" hidden>
          <div class="cv-form-group">
            <label for="program">Program</label>
            <input type="text" id="program" class="cv-form-input" placeholder="Program" value="<?php echo htmlspecialchars((string) ($initialCv['program'] ?? ''), ENT_QUOTES); ?>">
          </div>
          <div class="cv-form-group">
            <label for="department">Department</label>
            <input type="text" id="department" class="cv-form-input" placeholder="Department" value="<?php echo htmlspecialchars((string) ($initialCv['department'] ?? ''), ENT_QUOTES); ?>">
          </div>
          <div class="cv-form-group">
            <label for="addressLine">Street Address</label>
            <input type="text" id="addressLine" class="cv-form-input" placeholder="713 N 4th St, Philadelphia, PA 19123, USA" value="<?php echo htmlspecialchars((string) ($initialCv['addressLine'] ?? ''), ENT_QUOTES); ?>">
          </div>
          <div class="cv-form-row">
            <div class="cv-form-group">
              <label for="linkedin">LinkedIn</label>
              <input type="text" id="linkedin" class="cv-form-input" placeholder="linkedin.com/in/yourname" value="<?php echo htmlspecialchars((string) ($initialCv['linkedin'] ?? ''), ENT_QUOTES); ?>">
            </div>
            <div class="cv-form-group">
              <label for="twitter">Twitter / X</label>
              <input type="text" id="twitter" class="cv-form-input" placeholder="twitter.com/yourname" value="<?php echo htmlspecialchars((string) ($initialCv['twitter'] ?? ''), ENT_QUOTES); ?>">
            </div>
          </div>
          <div class="cv-form-group">
            <label for="educationDates">Education Date Range</label>
            <input type="text" id="educationDates" class="cv-form-input" placeholder="2000-08 - 2008-05" value="<?php echo htmlspecialchars((string) ($initialCv['educationDates'] ?? ''), ENT_QUOTES); ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Short Bio Section -->
    <div class="cv-form-card">
      <h3 id="bioCardTitle">Short Bio</h3>
      <p class="cv-form-hint">Be concise - The harsh reality is that hiring managers only spent an average of 6 seconds on each resume. <a href="#" class="cv-help-link">See examples</a> for help.</p>
      <div class="cv-form-group">
        <textarea id="bio" class="cv-form-textarea" placeholder="Write a brief professional summary..." rows="4"><?php echo htmlspecialchars((string) ($initialCv['bio'] ?? $defaultBio), ENT_QUOTES); ?></textarea>
        <div class="cv-textarea-counter"><span id="bioCount">0</span>/200</div>
      </div>
    </div>

    <!-- Skills Section -->
    <div class="cv-form-card">
      <h3 id="skillsCardTitle">Skills</h3>
      <div class="cv-skills-list" id="skillsList"></div>
      <button type="button" class="cv-btn-add-skill" id="addSkillBtn">+ Add skill</button>
      <div class="cv-form-group" style="margin-top: 10px;">
        <input type="text" id="newSkillInput" class="cv-form-input" placeholder="Enter skill name" style="display: none;">
      </div>
    </div>

    <!-- Experience Section -->
    <div class="cv-form-card">
      <h3 id="experienceCardTitle">Experience</h3>
      <button type="button" class="cv-btn-add-section" id="addExperienceBtn">+ Add experience</button>
      <div class="cv-experience-list" id="experienceList"></div>
    </div>

    <!-- Actions -->
    <div class="cv-form-actions">
      <button type="button" class="cv-btn cv-btn-primary" id="saveCvBtn">Save CV</button>
      <button type="button" class="cv-btn cv-btn-secondary" id="downloadPdfBtn">Download PDF</button>
    </div>
    <div class="cv-save-status" id="cvSaveStatus" aria-live="polite"></div>
  </div>

  <!-- Preview Section -->
  <div class="cv-preview-pane">
    <div class="cv-preview-header">
      <span id="previewPageLabel">Page 1 of 1</span>
      <button type="button" class="cv-preview-zoom" id="zoomBtn">100%</button>
    </div>
    <div class="cv-preview-container">
      <div class="cv-preview-paper" id="cvPreview">
        <!-- Header -->
        <div class="cv-preview-header-section">
          <div class="cv-preview-profile">
            <img id="previewProfilePic" class="cv-preview-pic" src="" alt="Profile" style="display: none;">
            <div class="cv-preview-info">
              <h1 id="previewName">Matthew Smith</h1>
              <p id="previewJobTitle">Service Designer</p>
            </div>
          </div>
          <div class="cv-preview-contact-grid">
            <div class="cv-preview-contact-col">
              <p class="cv-preview-contact-label">Address</p>
              <p class="cv-preview-contact-strong" id="previewInstitution">University / Institution</p>
              <p id="previewAddress">City, Country</p>
              <p id="previewAddressLine">Street Address</p>
              <p class="cv-preview-inline"><strong>Phone</strong> <span id="previewPhone">(123) 456-7890</span></p>
              <p class="cv-preview-inline"><strong>E-mail</strong> <span id="previewEmail">matthew@smith007.com</span></p>
            </div>
            <div class="cv-preview-contact-col cv-preview-contact-col-right">
              <p class="cv-preview-inline"><strong>LinkedIn</strong> <span id="previewLinkedIn">linkedin.com/in/yourname</span></p>
              <p class="cv-preview-inline"><strong>Twitter</strong> <span id="previewTwitter">twitter.com/yourname</span></p>
            </div>
          </div>
        </div>

        <!-- Bio Section -->
        <div class="cv-preview-section cv-preview-summary-section" id="bioPreviewSection" style="display: none;">
          <h2 id="previewSummaryHeading">Summary</h2>
          <p id="previewBio"></p>
        </div>

        <!-- Education Section -->
        <div class="cv-preview-section" id="educationPreviewSection" style="display: none;">
          <h2 id="previewEducationHeading">Education</h2>
          <div id="previewEducation"></div>
        </div>

        <!-- Skills Section -->
        <div class="cv-preview-section" id="skillsPreviewSection" style="display: none;">
          <h2 id="previewSkillsHeading">Skills</h2>
          <div class="cv-preview-skills" id="previewSkills"></div>
        </div>

        <!-- Experience Section -->
        <div class="cv-preview-section" id="experiencePreviewSection" style="display: none;">
          <h2 id="previewExperienceHeading">Experience</h2>
          <div class="cv-preview-experience" id="previewExperience"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.cv-builder-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  padding: 20px;
  max-width: 1600px;
  margin: 0 auto;
}

.cv-form-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-height: 90vh;
  overflow-y: auto;
  padding-right: 10px;
}

.cv-form-section::-webkit-scrollbar {
  width: 6px;
}

.cv-form-section::-webkit-scrollbar-track {
  background: transparent;
}

.cv-form-section::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.cv-form-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 20px;
  gap: 15px;
  display: flex;
  flex-direction: column;
}

.cv-form-card h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
}

.cv-form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

.cv-form-header h3 {
  margin: 0;
}

.cv-language-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  background: white;
  cursor: pointer;
}

.cv-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cv-form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 4px;
}

.cv-form-group label i {
  font-size: 0.75rem;
  color: #9ca3af;
  cursor: help;
}

.cv-form-input,
.cv-form-textarea {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.875rem;
  font-family: inherit;
  color: #111827;
  background: white;
}

.cv-form-input:focus,
.cv-form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.cv-form-textarea {
  resize: vertical;
  min-height: 80px;
}

.cv-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.cv-form-hint {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: -10px 0 10px 0;
  line-height: 1.5;
}

.cv-help-link {
  color: #f97316;
  text-decoration: none;
  font-weight: 600;
}

.cv-help-link:hover {
  text-decoration: underline;
}

.cv-textarea-counter {
  font-size: 0.75rem;
  color: #9ca3af;
  text-align: right;
}

.profile-picture-upload {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-picture-input {
  display: none;
}

.profile-picture-preview {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  font-size: 2rem;
  color: #9ca3af;
  font-weight: bold;
}

.profile-picture-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cv-form-collapsible {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.cv-form-toggle {
  background: none;
  border: none;
  padding: 8px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  color: #f97316;
  font-weight: 600;
  font-size: 0.875rem;
  transition: color 0.2s;
}

.cv-form-toggle:hover {
  color: #ea580c;
}

.cv-form-toggle i {
  transition: transform 0.3s;
}

.cv-form-toggle[aria-expanded="true"] i {
  transform: rotate(180deg);
}

.cv-form-collapsible-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    max-height: 0;
  }
  to {
    opacity: 1;
    max-height: 500px;
  }
}

.cv-btn-add-skill,
.cv-btn-add-section {
  background: none;
  border: 1px dashed #d1d5db;
  border-radius: 6px;
  padding: 10px;
  color: #3b82f6;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.cv-btn-add-skill:hover,
.cv-btn-add-section:hover {
  border-color: #3b82f6;
  background: #eff6ff;
}

.cv-skills-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 30px;
}

.cv-skill-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 0.8rem;
  font-weight: 600;
}

.cv-skill-remove {
  background: transparent;
  border: none;
  color: #1e40af;
  cursor: pointer;
  font-size: 0.85rem;
  line-height: 1;
  padding: 0;
}

.cv-skill-remove:hover {
  color: #1e3a8a;
}

.cv-experience-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cv-experience-item {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cv-experience-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.cv-experience-title {
  font-size: 0.82rem;
  font-weight: 700;
  color: #1f2937;
}

.cv-btn-remove {
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  border-radius: 6px;
  padding: 4px 8px;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 700;
}

.cv-btn-remove:hover {
  background: #fecaca;
}

.cv-experience-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.cv-empty-note {
  color: #9ca3af;
  font-size: 0.8rem;
  font-style: italic;
}

.cv-form-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.cv-btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.2s;
  flex: 1;
}

.cv-btn-primary {
  background: #3b82f6;
  color: white;
}

.cv-btn-primary:hover {
  background: #2563eb;
}

.cv-btn-secondary {
  background: white;
  color: #3b82f6;
  border: 1px solid #3b82f6;
}

.cv-btn-secondary:hover {
  background: #eff6ff;
}

.cv-save-status {
  min-height: 20px;
  font-size: 0.78rem;
  color: #6b7280;
}

.cv-save-status.is-saving {
  color: #2563eb;
}

.cv-save-status.is-success {
  color: #047857;
}

.cv-save-status.is-error {
  color: #b91c1c;
}

/* Preview Styles */
.cv-preview-pane {
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cv-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background: #f3f4f6;
  border-radius: 6px;
  font-size: 0.875rem;
  color: #6b7280;
}

.cv-preview-zoom {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  padding: 6px 12px;
  font-size: 0.875rem;
  cursor: pointer;
  color: #6b7280;
}

.cv-preview-container {
  flex: 1;
  overflow-y: auto;
  background: #efefef;
  border-radius: 6px;
  padding: 10px;
  max-height: 90vh;
}

.cv-preview-container::-webkit-scrollbar {
  width: 6px;
}

.cv-preview-container::-webkit-scrollbar-track {
  background: transparent;
}

.cv-preview-container::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.cv-preview-paper {
  width: 100%;
  background: white;
  padding: 34px 36px;
  border-radius: 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  aspect-ratio: 8.5/11;
  max-width: 600px;
  margin: 0 auto;
  font-family: 'Times New Roman', Times, serif;
  color: #1f2937;
  line-height: 1.42;
  transform-origin: top center;
}

.cv-preview-header-section {
  margin-bottom: 16px;
  padding-bottom: 0;
  border-bottom: none;
}

.cv-preview-profile {
  display: flex;
  gap: 14px;
  align-items: center;
  margin-bottom: 10px;
}

.cv-preview-pic {
  width: 72px;
  height: 72px;
  border-radius: 6px;
  object-fit: cover;
}

.cv-preview-info h1 {
  margin: 0;
  font-size: 2.25rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.05;
}

.cv-preview-info p {
  margin: 4px 0 0;
  font-size: 0.73rem;
  color: #1f2937;
  line-height: 1.35;
}

.cv-preview-contact-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
  gap: 22px;
  font-size: 0.73rem;
}

.cv-preview-contact-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cv-preview-contact-col-right {
  padding-top: 3px;
}

.cv-preview-contact-label {
  margin: 0;
  font-weight: 700;
}

.cv-preview-contact-strong {
  margin: 0;
  font-weight: 700;
}

.cv-preview-inline {
  margin: 0;
  line-height: 1.36;
}

.cv-preview-section {
  margin-bottom: 16px;
}

.cv-preview-section h2 {
  margin: 0 0 8px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #1f2937;
  border-bottom: 1px solid #cfd5db;
  padding-bottom: 3px;
}

.cv-preview-section p {
  margin: 0;
  font-size: 0.72rem;
  line-height: 1.5;
  color: #1f2937;
}

.cv-preview-summary-section h2 {
  display: none;
}

.cv-preview-summary-section p {
  margin-top: 2px;
}

.cv-preview-skills {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cv-preview-skill-tag {
  border: none;
  background: transparent;
  color: #1f2937;
  padding: 0;
  border-radius: 0;
  font-size: 0.72rem;
}

.cv-preview-education {
  font-size: 0.73rem;
  color: #1f2937;
  line-height: 1.45;
}

.cv-preview-education strong {
  color: #1f2937;
}

.cv-preview-experience {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cv-preview-entry-row {
  display: grid;
  grid-template-columns: 108px minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}

.cv-preview-entry-date {
  font-size: 0.72rem;
  color: #1f2937;
  font-weight: 700;
  line-height: 1.38;
}

.cv-preview-entry-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cv-preview-entry-title {
  margin: 0;
  font-size: 0.76rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.35;
}

.cv-preview-entry-subtitle {
  margin: 0;
  font-size: 0.72rem;
  font-style: italic;
  color: #1f2937;
  line-height: 1.4;
}

.cv-preview-entry-meta {
  margin: 0;
  font-size: 0.72rem;
  color: #1f2937;
  line-height: 1.45;
  white-space: pre-wrap;
}

@media (max-width: 1200px) {
  .cv-builder-container {
    grid-template-columns: 1fr;
  }

  .cv-form-section {
    max-height: none;
  }

  .cv-preview-container {
    max-height: 500px;
  }
}

@media (max-width: 680px) {
  .cv-form-row,
  .cv-experience-grid,
  .cv-preview-contact-grid,
  .cv-preview-entry-row {
    grid-template-columns: 1fr;
  }

  .cv-preview-entry-date {
    margin-bottom: -2px;
  }

  .cv-form-actions {
    flex-direction: column;
  }
}
</style>

<script>
(function() {
  const endpointUrl = <?php echo json_encode($resumeAiEndpointUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  const initialPayload = <?php echo json_encode($initialCv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  const AUTO_SAVE_DELAY_MS = 1200;
  const MAX_PROFILE_PICTURE_BYTES = 2 * 1024 * 1024;

  const form = {
    cvLanguage: document.getElementById('cvLanguage'),
    firstName: document.getElementById('firstName'),
    lastName: document.getElementById('lastName'),
    email: document.getElementById('email'),
    phone: document.getElementById('phone'),
    jobTitle: document.getElementById('jobTitle'),
    city: document.getElementById('city'),
    country: document.getElementById('country'),
    program: document.getElementById('program'),
    department: document.getElementById('department'),
    addressLine: document.getElementById('addressLine'),
    linkedin: document.getElementById('linkedin'),
    twitter: document.getElementById('twitter'),
    educationDates: document.getElementById('educationDates'),
    bio: document.getElementById('bio'),
    profilePicture: document.getElementById('profilePicture'),
    profilePicturePreview: document.getElementById('profilePicturePreview'),
  };

  const ui = {
    bioCount: document.getElementById('bioCount'),
    profilePicturePlaceholder: document.getElementById('profilePicturePlaceholder'),
    profilePictureContainer: document.querySelector('.profile-picture-preview'),
    addSkillBtn: document.getElementById('addSkillBtn'),
    newSkillInput: document.getElementById('newSkillInput'),
    skillsList: document.getElementById('skillsList'),
    addExperienceBtn: document.getElementById('addExperienceBtn'),
    experienceList: document.getElementById('experienceList'),
    saveCvBtn: document.getElementById('saveCvBtn'),
    downloadPdfBtn: document.getElementById('downloadPdfBtn'),
    saveStatus: document.getElementById('cvSaveStatus'),
    zoomBtn: document.getElementById('zoomBtn'),
    cvPreview: document.getElementById('cvPreview'),
    detailsCardTitle: document.getElementById('detailsCardTitle'),
    bioCardTitle: document.getElementById('bioCardTitle'),
    skillsCardTitle: document.getElementById('skillsCardTitle'),
    experienceCardTitle: document.getElementById('experienceCardTitle'),
    pageSubtitle: document.getElementById('cvPageSubtitle'),
    previewPageLabel: document.getElementById('previewPageLabel'),
  };

  const preview = {
    name: document.getElementById('previewName'),
    jobTitle: document.getElementById('previewJobTitle'),
    institution: document.getElementById('previewInstitution'),
    address: document.getElementById('previewAddress'),
    addressLine: document.getElementById('previewAddressLine'),
    linkedin: document.getElementById('previewLinkedIn'),
    twitter: document.getElementById('previewTwitter'),
    phone: document.getElementById('previewPhone'),
    email: document.getElementById('previewEmail'),
    bio: document.getElementById('previewBio'),
    bioSection: document.getElementById('bioPreviewSection'),
    educationSection: document.getElementById('educationPreviewSection'),
    education: document.getElementById('previewEducation'),
    skillsSection: document.getElementById('skillsPreviewSection'),
    skills: document.getElementById('previewSkills'),
    experienceSection: document.getElementById('experiencePreviewSection'),
    experience: document.getElementById('previewExperience'),
    profilePic: document.getElementById('previewProfilePic'),
    summaryHeading: document.getElementById('previewSummaryHeading'),
    educationHeading: document.getElementById('previewEducationHeading'),
    skillsHeading: document.getElementById('previewSkillsHeading'),
    experienceHeading: document.getElementById('previewExperienceHeading'),
  };

  if (!form.firstName || !preview.name || !ui.cvPreview) {
    return;
  }

  const languagePacks = {
    en: {
      subtitle: 'Create a professional CV with live preview',
      detailsTitle: 'Your Details',
      bioTitle: 'Short Bio',
      skillsTitle: 'Skills',
      experienceTitle: 'Experience',
      addSkill: '+ Add skill',
      addExperience: '+ Add experience',
      saveCv: 'Save CV',
      downloadPdf: 'Download PDF',
      pageLabel: 'Page 1 of 1',
      summaryHeading: 'Summary',
      educationHeading: 'Education',
      skillsHeading: 'Books',
      experienceHeading: 'Professional Appointments'
    },
    es: {
      subtitle: 'Crea un CV profesional con vista previa en vivo',
      detailsTitle: 'Tus datos',
      bioTitle: 'Resumen corto',
      skillsTitle: 'Habilidades',
      experienceTitle: 'Experiencia',
      addSkill: '+ Agregar habilidad',
      addExperience: '+ Agregar experiencia',
      saveCv: 'Guardar CV',
      downloadPdf: 'Descargar PDF',
      pageLabel: 'Pagina 1 de 1',
      summaryHeading: 'Resumen',
      educationHeading: 'Educacion',
      skillsHeading: 'Publicaciones',
      experienceHeading: 'Trayectoria profesional'
    },
    fr: {
      subtitle: 'Creez un CV professionnel avec apercu en direct',
      detailsTitle: 'Vos informations',
      bioTitle: 'Courte biographie',
      skillsTitle: 'Competences',
      experienceTitle: 'Experience',
      addSkill: '+ Ajouter une competence',
      addExperience: '+ Ajouter une experience',
      saveCv: 'Enregistrer le CV',
      downloadPdf: 'Telecharger le PDF',
      pageLabel: 'Page 1 sur 1',
      summaryHeading: 'Resume',
      educationHeading: 'Formation',
      skillsHeading: 'Publications',
      experienceHeading: 'Parcours professionnel'
    }
  };

  function normalizeString(value) {
    return String(value == null ? '' : value).trim();
  }

  function normalizeSkills(skills) {
    if (!Array.isArray(skills)) {
      return [];
    }
    const seen = new Set();
    const out = [];
    for (const item of skills) {
      const text = normalizeString(item);
      if (!text) {
        continue;
      }
      const lower = text.toLowerCase();
      if (seen.has(lower)) {
        continue;
      }
      seen.add(lower);
      out.push(text);
    }
    return out;
  }

  function normalizeExperiences(experiences) {
    if (!Array.isArray(experiences)) {
      return [];
    }
    return experiences
      .filter(function(item) {
        return item && typeof item === 'object';
      })
      .map(function(item) {
        return {
          role: normalizeString(item.role),
          company: normalizeString(item.company),
          location: normalizeString(item.location),
          dates: normalizeString(item.dates),
          description: normalizeString(item.description),
        };
      });
  }

  function normalizePayload(payload) {
    const source = payload && typeof payload === 'object' ? payload : {};
    let language = normalizeString(source.language || 'en').toLowerCase();
    if (!Object.prototype.hasOwnProperty.call(languagePacks, language)) {
      language = 'en';
    }
    return {
      firstName: normalizeString(source.firstName),
      lastName: normalizeString(source.lastName),
      email: normalizeString(source.email),
      phone: normalizeString(source.phone),
      jobTitle: normalizeString(source.jobTitle),
      city: normalizeString(source.city),
      country: normalizeString(source.country),
      bio: normalizeString(source.bio),
      program: normalizeString(source.program),
      department: normalizeString(source.department),
      addressLine: normalizeString(source.addressLine),
      linkedin: normalizeString(source.linkedin),
      twitter: normalizeString(source.twitter),
      educationDates: normalizeString(source.educationDates),
      profilePicture: normalizeString(source.profilePicture),
      language: language,
      skills: normalizeSkills(source.skills),
      experiences: normalizeExperiences(source.experiences),
    };
  }

  function createBlankExperience() {
    return {
      role: '',
      company: '',
      location: '',
      dates: '',
      description: '',
    };
  }

  let state = normalizePayload(initialPayload);
  let autosaveTimer = null;
  let saveInFlight = false;
  let queuedSave = false;
  let zoomLevel = 100;

  function setSaveStatus(message, tone) {
    if (!ui.saveStatus) {
      return;
    }
    ui.saveStatus.textContent = message || '';
    ui.saveStatus.classList.remove('is-saving', 'is-success', 'is-error');
    if (tone === 'saving') {
      ui.saveStatus.classList.add('is-saving');
    } else if (tone === 'success') {
      ui.saveStatus.classList.add('is-success');
    } else if (tone === 'error') {
      ui.saveStatus.classList.add('is-error');
    }
  }

  function ensureJobTitleOption(value) {
    const nextValue = normalizeString(value);
    const dynamicOptions = form.jobTitle.querySelectorAll('option[data-dynamic="1"]');
    dynamicOptions.forEach(function(opt) {
      opt.remove();
    });

    if (!nextValue) {
      form.jobTitle.value = '';
      return;
    }

    let exists = false;
    for (let i = 0; i < form.jobTitle.options.length; i += 1) {
      if (form.jobTitle.options[i].value === nextValue) {
        exists = true;
        break;
      }
    }

    if (!exists) {
      const customOption = document.createElement('option');
      customOption.value = nextValue;
      customOption.textContent = nextValue;
      customOption.setAttribute('data-dynamic', '1');
      form.jobTitle.appendChild(customOption);
    }

    form.jobTitle.value = nextValue;
  }

  function syncStateFromForm() {
    state.firstName = normalizeString(form.firstName.value);
    state.lastName = normalizeString(form.lastName.value);
    state.email = normalizeString(form.email.value);
    state.phone = normalizeString(form.phone.value);
    state.jobTitle = normalizeString(form.jobTitle.value);
    state.city = normalizeString(form.city.value);
    state.country = normalizeString(form.country.value);
    state.program = normalizeString(form.program.value);
    state.department = normalizeString(form.department.value);
    state.addressLine = normalizeString(form.addressLine.value);
    state.linkedin = normalizeString(form.linkedin.value);
    state.twitter = normalizeString(form.twitter.value);
    state.educationDates = normalizeString(form.educationDates.value);
    state.language = normalizeString(form.cvLanguage.value || 'en').toLowerCase();

    const bioText = String(form.bio.value == null ? '' : form.bio.value).trim();
    state.bio = bioText.length > 200 ? bioText.slice(0, 200) : bioText;
    if (form.bio.value !== state.bio) {
      form.bio.value = state.bio;
    }

    if (!Object.prototype.hasOwnProperty.call(languagePacks, state.language)) {
      state.language = 'en';
      form.cvLanguage.value = 'en';
    }
  }

  function applyStateToForm() {
    form.firstName.value = state.firstName;
    form.lastName.value = state.lastName;
    form.email.value = state.email;
    form.phone.value = state.phone;
    form.city.value = state.city;
    form.country.value = state.country;
    form.program.value = state.program;
    form.department.value = state.department;
    form.addressLine.value = state.addressLine;
    form.linkedin.value = state.linkedin;
    form.twitter.value = state.twitter;
    form.educationDates.value = state.educationDates;
    form.bio.value = state.bio;
    form.cvLanguage.value = state.language;
    ensureJobTitleOption(state.jobTitle);
    updateProfilePictureDisplays(state.profilePicture);
  }

  function updateProfilePictureDisplays(imageSrc) {
    const src = normalizeString(imageSrc);
    state.profilePicture = src;

    if (src) {
      form.profilePicturePreview.src = src;
      form.profilePicturePreview.style.display = 'block';
      preview.profilePic.src = src;
      preview.profilePic.style.display = 'block';
      if (ui.profilePicturePlaceholder) {
        ui.profilePicturePlaceholder.style.display = 'none';
      }
      return;
    }

    form.profilePicturePreview.removeAttribute('src');
    form.profilePicturePreview.style.display = 'none';
    preview.profilePic.removeAttribute('src');
    preview.profilePic.style.display = 'none';
    if (ui.profilePicturePlaceholder) {
      ui.profilePicturePlaceholder.style.display = 'block';
    }
  }

  function applyLanguage() {
    const pack = languagePacks[state.language] || languagePacks.en;

    if (ui.pageSubtitle) {
      ui.pageSubtitle.textContent = pack.subtitle;
    }
    if (ui.detailsCardTitle) {
      ui.detailsCardTitle.textContent = pack.detailsTitle;
    }
    if (ui.bioCardTitle) {
      ui.bioCardTitle.textContent = pack.bioTitle;
    }
    if (ui.skillsCardTitle) {
      ui.skillsCardTitle.textContent = pack.skillsTitle;
    }
    if (ui.experienceCardTitle) {
      ui.experienceCardTitle.textContent = pack.experienceTitle;
    }
    if (ui.addSkillBtn) {
      ui.addSkillBtn.textContent = pack.addSkill;
    }
    if (ui.addExperienceBtn) {
      ui.addExperienceBtn.textContent = pack.addExperience;
    }
    if (ui.saveCvBtn) {
      ui.saveCvBtn.textContent = pack.saveCv;
    }
    if (ui.downloadPdfBtn) {
      ui.downloadPdfBtn.textContent = pack.downloadPdf;
    }
    if (ui.previewPageLabel) {
      ui.previewPageLabel.textContent = pack.pageLabel;
    }
    if (preview.summaryHeading) {
      preview.summaryHeading.textContent = pack.summaryHeading;
    }
    if (preview.educationHeading) {
      preview.educationHeading.textContent = pack.educationHeading;
    }
    if (preview.skillsHeading) {
      preview.skillsHeading.textContent = pack.skillsHeading;
    }
    if (preview.experienceHeading) {
      preview.experienceHeading.textContent = pack.experienceHeading;
    }
  }

  function renderSkillsList() {
    ui.skillsList.innerHTML = '';

    if (!state.skills.length) {
      const empty = document.createElement('p');
      empty.className = 'cv-empty-note';
      empty.textContent = 'No skills added yet.';
      ui.skillsList.appendChild(empty);
      return;
    }

    state.skills.forEach(function(skill, index) {
      const pill = document.createElement('div');
      pill.className = 'cv-skill-pill';

      const text = document.createElement('span');
      text.textContent = skill;

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'cv-skill-remove';
      removeBtn.textContent = 'x';
      removeBtn.setAttribute('aria-label', 'Remove skill');
      removeBtn.addEventListener('click', function() {
        state.skills.splice(index, 1);
        renderSkillsList();
        updatePreview();
        scheduleAutoSave();
      });

      pill.appendChild(text);
      pill.appendChild(removeBtn);
      ui.skillsList.appendChild(pill);
    });
  }

  function renderExperienceList() {
    ui.experienceList.innerHTML = '';

    if (!state.experiences.length) {
      const empty = document.createElement('p');
      empty.className = 'cv-empty-note';
      empty.textContent = 'No experience entries yet.';
      ui.experienceList.appendChild(empty);
      return;
    }

    state.experiences.forEach(function(exp, index) {
      const card = document.createElement('div');
      card.className = 'cv-experience-item';
      card.innerHTML =
        '<div class="cv-experience-head">'
          + '<span class="cv-experience-title">Entry ' + (index + 1) + '</span>'
          + '<button type="button" class="cv-btn-remove" data-remove-index="' + index + '">Remove</button>'
        + '</div>'
        + '<div class="cv-experience-grid">'
          + '<div class="cv-form-group">'
            + '<label>Role / Position</label>'
            + '<input type="text" class="cv-form-input" data-exp-index="' + index + '" data-exp-key="role" value="' + escapeHtml(exp.role) + '" placeholder="Frontend Intern">'
          + '</div>'
          + '<div class="cv-form-group">'
            + '<label>Company</label>'
            + '<input type="text" class="cv-form-input" data-exp-index="' + index + '" data-exp-key="company" value="' + escapeHtml(exp.company) + '" placeholder="SkillHive Tech">'
          + '</div>'
          + '<div class="cv-form-group">'
            + '<label>Location</label>'
            + '<input type="text" class="cv-form-input" data-exp-index="' + index + '" data-exp-key="location" value="' + escapeHtml(exp.location) + '" placeholder="Cebu City">'
          + '</div>'
          + '<div class="cv-form-group">'
            + '<label>Dates</label>'
            + '<input type="text" class="cv-form-input" data-exp-index="' + index + '" data-exp-key="dates" value="' + escapeHtml(exp.dates) + '" placeholder="Jun 2024 - Aug 2024">'
          + '</div>'
        + '</div>'
        + '<div class="cv-form-group">'
          + '<label>Description</label>'
          + '<textarea class="cv-form-textarea" rows="3" data-exp-index="' + index + '" data-exp-key="description" placeholder="What you worked on...">' + escapeHtml(exp.description) + '</textarea>'
        + '</div>';

      ui.experienceList.appendChild(card);
    });

    ui.experienceList.querySelectorAll('[data-remove-index]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const removeIndex = Number(this.getAttribute('data-remove-index'));
        if (Number.isNaN(removeIndex)) {
          return;
        }
        state.experiences.splice(removeIndex, 1);
        renderExperienceList();
        updatePreview();
        scheduleAutoSave();
      });
    });

    ui.experienceList.querySelectorAll('[data-exp-index][data-exp-key]').forEach(function(field) {
      field.addEventListener('input', function() {
        const idx = Number(this.getAttribute('data-exp-index'));
        const key = this.getAttribute('data-exp-key');
        if (Number.isNaN(idx) || !state.experiences[idx] || !key) {
          return;
        }
        state.experiences[idx][key] = this.value;
        updatePreview();
        scheduleAutoSave();
      });
    });
  }

  function createPreviewEntryRow(dateValue, titleValue, subtitleValue, metaValue) {
    const row = document.createElement('div');
    row.className = 'cv-preview-entry-row';

    const date = document.createElement('div');
    date.className = 'cv-preview-entry-date';
    date.textContent = normalizeString(dateValue) || '';

    const body = document.createElement('div');
    body.className = 'cv-preview-entry-body';

    const titleText = normalizeString(titleValue);
    if (titleText) {
      const title = document.createElement('p');
      title.className = 'cv-preview-entry-title';
      title.textContent = titleText;
      body.appendChild(title);
    }

    const subtitleText = normalizeString(subtitleValue);
    if (subtitleText) {
      const subtitle = document.createElement('p');
      subtitle.className = 'cv-preview-entry-subtitle';
      subtitle.textContent = subtitleText;
      body.appendChild(subtitle);
    }

    const metaText = normalizeString(metaValue);
    if (metaText) {
      const meta = document.createElement('p');
      meta.className = 'cv-preview-entry-meta';
      meta.textContent = metaText;
      body.appendChild(meta);
    }

    row.appendChild(date);
    row.appendChild(body);
    return row;
  }

  function parsePublicationLine(line) {
    const text = normalizeString(line);
    if (!text) {
      return { date: '', title: '', meta: '' };
    }

    if (text.includes('|')) {
      const parts = text.split('|').map(function(part) {
        return normalizeString(part);
      });
      return {
        date: parts[0] || '',
        title: parts[1] || '',
        meta: parts.slice(2).join(' | '),
      };
    }

    const dateMatch = text.match(/^([0-9]{4}(?:-[0-9]{2})?(?:\s*-\s*[0-9]{4}(?:-[0-9]{2})?)?)\s+(.*)$/);
    if (dateMatch) {
      return {
        date: normalizeString(dateMatch[1]),
        title: normalizeString(dateMatch[2]),
        meta: '',
      };
    }

    return { date: '', title: text, meta: '' };
  }

  function renderPreviewEducation() {
    const hasProgram = !!state.program;
    const hasDepartment = !!state.department;

    if (!hasProgram && !hasDepartment) {
      preview.educationSection.style.display = 'none';
      preview.education.innerHTML = '';
      return;
    }

    preview.education.innerHTML = '';
    const location = [state.city, state.country].filter(Boolean).join(', ');
    const row = createPreviewEntryRow(
      state.educationDates,
      state.program || 'Program / Degree',
      state.department || '',
      location
    );

    preview.education.appendChild(row);
    preview.educationSection.style.display = 'block';
  }

  function renderPreviewSkills() {
    preview.skills.innerHTML = '';

    if (!state.skills.length) {
      preview.skillsSection.style.display = 'none';
      return;
    }

    state.skills.forEach(function(skillLine) {
      const publication = parsePublicationLine(skillLine);
      if (!publication.title) {
        return;
      }

      const row = createPreviewEntryRow(publication.date, publication.title, '', publication.meta);
      row.querySelectorAll('.cv-preview-entry-meta').forEach(function(metaNode) {
        metaNode.classList.add('cv-preview-skill-tag');
      });
      preview.skills.appendChild(row);
    });

    preview.skillsSection.style.display = preview.skills.children.length ? 'block' : 'none';
  }

  function hasExperienceContent(exp) {
    return !!(normalizeString(exp.role)
      || normalizeString(exp.company)
      || normalizeString(exp.location)
      || normalizeString(exp.dates)
      || normalizeString(exp.description));
  }

  function renderPreviewExperiences() {
    preview.experience.innerHTML = '';
    const visibleExperiences = state.experiences.filter(hasExperienceContent);

    if (!visibleExperiences.length) {
      preview.experienceSection.style.display = 'none';
      return;
    }

    visibleExperiences.forEach(function(exp) {
      const titleParts = [];
      if (exp.role) {
        titleParts.push(exp.role);
      }
      if (exp.company) {
        titleParts.push(exp.company);
      }
      const title = titleParts.join(', ') || 'Experience';
      const subtitle = exp.location || '';
      const row = createPreviewEntryRow(exp.dates, title, subtitle, exp.description);
      preview.experience.appendChild(row);
    });

    preview.experienceSection.style.display = 'block';
  }

  function updatePreview() {
    syncStateFromForm();

    const fullName = (state.firstName + ' ' + state.lastName).trim() || 'Your Name';
    preview.name.textContent = fullName;
    preview.jobTitle.textContent = state.jobTitle || 'Professional Title';

    preview.institution.textContent = state.program || 'University / Institution';

    const locationParts = [];
    if (state.city) {
      locationParts.push(state.city);
    }
    if (state.country) {
      locationParts.push(state.country);
    }
    preview.address.textContent = locationParts.length ? locationParts.join(', ') : 'City, Country';
    preview.addressLine.textContent = state.addressLine || 'Street Address';
    preview.linkedin.textContent = state.linkedin || 'linkedin.com/in/yourname';
    preview.twitter.textContent = state.twitter || 'twitter.com/yourname';
    preview.phone.textContent = state.phone || '(123) 456-7890';
    preview.email.textContent = state.email || 'email@example.com';

    if (state.bio) {
      preview.bio.textContent = state.bio;
      preview.bioSection.style.display = 'block';
    } else {
      preview.bio.textContent = '';
      preview.bioSection.style.display = 'none';
    }

    if (ui.bioCount) {
      ui.bioCount.textContent = String(state.bio.length);
    }

    renderPreviewEducation();
    renderPreviewSkills();
    renderPreviewExperiences();
    updateProfilePictureDisplays(state.profilePicture);
  }

  function getPayloadForSave() {
    syncStateFromForm();
    return {
      firstName: state.firstName,
      lastName: state.lastName,
      email: state.email,
      phone: state.phone,
      jobTitle: state.jobTitle,
      city: state.city,
      country: state.country,
      bio: state.bio,
      program: state.program,
      department: state.department,
      addressLine: state.addressLine,
      linkedin: state.linkedin,
      twitter: state.twitter,
      educationDates: state.educationDates,
      skills: state.skills.slice(),
      experiences: state.experiences.map(function(exp) {
        return {
          role: normalizeString(exp.role),
          company: normalizeString(exp.company),
          location: normalizeString(exp.location),
          dates: normalizeString(exp.dates),
          description: normalizeString(exp.description),
        };
      }),
      profilePicture: state.profilePicture,
      language: state.language,
    };
  }

  async function saveCv(showSuccessMessage) {
    if (saveInFlight) {
      queuedSave = true;
      return;
    }

    saveInFlight = true;
    setSaveStatus(showSuccessMessage ? 'Saving CV...' : 'Auto-saving...', 'saving');

    try {
      const payload = getPayloadForSave();
      const params = new URLSearchParams();
      params.set('action', 'save_form_cv');
      params.set('cv_data', JSON.stringify(payload));

      const response = await fetch(endpointUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: params.toString(),
        credentials: 'same-origin'
      });

      let data;
      try {
        data = await response.json();
      } catch (jsonError) {
        throw new Error('Unexpected server response.');
      }

      if (!response.ok || !data || data.ok !== true) {
        throw new Error(data && data.message ? data.message : 'Unable to save CV.');
      }

      const timestamp = new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
      });

      const statusText = (showSuccessMessage ? 'CV saved' : 'Auto-saved') + ' at ' + timestamp + '.';
      setSaveStatus(statusText, 'success');
    } catch (error) {
      setSaveStatus('Save failed: ' + (error && error.message ? error.message : 'Unknown error.'), 'error');
    } finally {
      saveInFlight = false;
      if (queuedSave) {
        queuedSave = false;
        saveCv(false);
      }
    }
  }

  async function loadCv() {
    setSaveStatus('Loading saved CV...', 'saving');

    try {
      const params = new URLSearchParams();
      params.set('action', 'load_form_cv');

      const response = await fetch(endpointUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: params.toString(),
        credentials: 'same-origin'
      });

      let data;
      try {
        data = await response.json();
      } catch (jsonError) {
        throw new Error('Unexpected server response.');
      }

      if (!response.ok || !data || data.ok !== true) {
        throw new Error(data && data.message ? data.message : 'Unable to load saved CV.');
      }

      if (data.data && typeof data.data === 'object') {
        state = normalizePayload(Object.assign({}, state, data.data));
        applyStateToForm();
        applyLanguage();
        renderSkillsList();
        renderExperienceList();
        updatePreview();
        setSaveStatus('Loaded saved CV data.', 'success');
      } else {
        setSaveStatus('No saved CV found yet.', '');
      }
    } catch (error) {
      setSaveStatus('Load failed: ' + (error && error.message ? error.message : 'Unknown error.'), 'error');
    }
  }

  function scheduleAutoSave() {
    setSaveStatus('Unsaved changes...', '');
    if (autosaveTimer) {
      window.clearTimeout(autosaveTimer);
    }
    autosaveTimer = window.setTimeout(function() {
      saveCv(false);
    }, AUTO_SAVE_DELAY_MS);
  }

  function toggleNewSkillInput(show) {
    if (!ui.newSkillInput) {
      return;
    }
    ui.newSkillInput.style.display = show ? 'block' : 'none';
    if (show) {
      ui.newSkillInput.focus();
      ui.newSkillInput.select();
    } else {
      ui.newSkillInput.value = '';
    }
  }

  function addSkillFromInput() {
    const newSkill = normalizeString(ui.newSkillInput.value);
    if (!newSkill) {
      toggleNewSkillInput(false);
      return;
    }

    const duplicate = state.skills.some(function(skill) {
      return skill.toLowerCase() === newSkill.toLowerCase();
    });

    if (!duplicate) {
      state.skills.push(newSkill);
      renderSkillsList();
      updatePreview();
      scheduleAutoSave();
    }

    toggleNewSkillInput(false);
  }

  function addExperience() {
    state.experiences.push(createBlankExperience());
    renderExperienceList();
    updatePreview();
    scheduleAutoSave();
  }

  function handleZoomToggle() {
    zoomLevel = zoomLevel >= 120 ? 90 : zoomLevel + 10;
    const scale = zoomLevel / 100;
    ui.cvPreview.style.transform = 'scale(' + scale + ')';
    ui.zoomBtn.textContent = zoomLevel + '%';
  }

  function handleDownloadPdf() {
    saveCv(false);

    const printWindow = window.open('', '_blank', 'noopener,noreferrer');
    if (!printWindow) {
      setSaveStatus('Enable pop-ups to allow PDF download.', 'error');
      return;
    }

    const previewHtml = ui.cvPreview.outerHTML;
    const printStyles = [
      'body { margin: 0; padding: 20px; background: #fff; font-family: "Times New Roman", Times, serif; }',
      '.cv-preview-paper { margin: 0 auto; max-width: 794px; box-shadow: none; border: 1px solid #d1d5db; padding: 34px 36px; color: #1f2937; line-height: 1.42; }',
      '.cv-preview-header-section { margin-bottom: 16px; }',
      '.cv-preview-profile { display: flex; gap: 14px; align-items: center; margin-bottom: 10px; }',
      '.cv-preview-pic { width: 72px; height: 72px; border-radius: 6px; object-fit: cover; }',
      '.cv-preview-info h1 { margin: 0; font-size: 2.25rem; line-height: 1.05; }',
      '.cv-preview-info p { margin: 4px 0 0; font-size: 0.73rem; }',
      '.cv-preview-contact-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr); gap: 22px; font-size: 0.73rem; }',
      '.cv-preview-contact-col { display: flex; flex-direction: column; gap: 3px; }',
      '.cv-preview-contact-col-right { padding-top: 3px; }',
      '.cv-preview-contact-label { margin: 0; font-weight: 700; }',
      '.cv-preview-contact-strong { margin: 0; font-weight: 700; }',
      '.cv-preview-inline { margin: 0; line-height: 1.36; }',
      '.cv-preview-section { margin-bottom: 16px; }',
      '.cv-preview-section h2 { margin: 0 0 8px; font-size: 0.85rem; font-weight: 700; border-bottom: 1px solid #cfd5db; padding-bottom: 3px; }',
      '.cv-preview-summary-section h2 { display: none; }',
      '.cv-preview-entry-row { display: grid; grid-template-columns: 108px minmax(0, 1fr); gap: 12px; align-items: start; }',
      '.cv-preview-entry-date { font-size: 0.72rem; font-weight: 700; line-height: 1.38; }',
      '.cv-preview-entry-body { display: flex; flex-direction: column; gap: 3px; }',
      '.cv-preview-entry-title { margin: 0; font-size: 0.76rem; font-weight: 700; line-height: 1.35; }',
      '.cv-preview-entry-subtitle { margin: 0; font-size: 0.72rem; font-style: italic; line-height: 1.4; }',
      '.cv-preview-entry-meta, .cv-preview-skill-tag { margin: 0; font-size: 0.72rem; line-height: 1.45; white-space: pre-wrap; }',
      '@media (max-width: 680px) { .cv-preview-contact-grid, .cv-preview-entry-row { grid-template-columns: 1fr; } }'
    ].join('');

    printWindow.document.open();
    printWindow.document.write(
      '<!doctype html>'
      + '<html><head><meta charset="utf-8"><title>CV</title><style>' + printStyles + '</style></head>'
      + '<body>' + previewHtml + '</body></html>'
    );
    printWindow.document.close();

    printWindow.focus();
    printWindow.onafterprint = function() {
      printWindow.close();
    };

    window.setTimeout(function() {
      printWindow.print();
    }, 250);
  }

  function bindBaseFormEvents() {
    const fields = [
      form.firstName,
      form.lastName,
      form.email,
      form.phone,
      form.jobTitle,
      form.city,
      form.country,
      form.program,
      form.department,
      form.addressLine,
      form.linkedin,
      form.twitter,
      form.educationDates,
      form.bio,
    ];

    fields.forEach(function(field) {
      if (!field) {
        return;
      }
      field.addEventListener('input', function() {
        updatePreview();
        scheduleAutoSave();
      });
      field.addEventListener('change', function() {
        updatePreview();
        scheduleAutoSave();
      });
    });
  }

  function bindUiEvents() {
    bindBaseFormEvents();

    if (form.cvLanguage) {
      form.cvLanguage.addEventListener('change', function() {
        state.language = normalizeString(form.cvLanguage.value || 'en').toLowerCase();
        if (!Object.prototype.hasOwnProperty.call(languagePacks, state.language)) {
          state.language = 'en';
        }
        applyLanguage();
        updatePreview();
        scheduleAutoSave();
      });
    }

    if (ui.profilePictureContainer && form.profilePicture) {
      ui.profilePictureContainer.addEventListener('click', function() {
        form.profilePicture.click();
      });
    }

    if (form.profilePicture) {
      form.profilePicture.addEventListener('change', function(event) {
        const files = event.target.files;
        const file = files && files.length ? files[0] : null;
        if (!file) {
          return;
        }

        if (file.size > MAX_PROFILE_PICTURE_BYTES) {
          setSaveStatus('Profile picture must be 2 MB or less.', 'error');
          form.profilePicture.value = '';
          return;
        }

        const reader = new FileReader();
        reader.onload = function(loadEvent) {
          const result = loadEvent && loadEvent.target ? loadEvent.target.result : '';
          const src = typeof result === 'string' ? result : '';
          updateProfilePictureDisplays(src);
          updatePreview();
          scheduleAutoSave();
        };
        reader.readAsDataURL(file);
      });
    }

    document.querySelectorAll('.cv-form-toggle').forEach(function(toggle) {
      toggle.addEventListener('click', function() {
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        const content = this.nextElementSibling;
        if (content) {
          content.hidden = expanded;
        }
      });
    });

    if (ui.addSkillBtn) {
      ui.addSkillBtn.addEventListener('click', function() {
        toggleNewSkillInput(true);
      });
    }

    if (ui.newSkillInput) {
      ui.newSkillInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          addSkillFromInput();
        }
      });
      ui.newSkillInput.addEventListener('blur', function() {
        if (!normalizeString(ui.newSkillInput.value)) {
          toggleNewSkillInput(false);
        }
      });
    }

    if (ui.addExperienceBtn) {
      ui.addExperienceBtn.addEventListener('click', addExperience);
    }

    if (ui.saveCvBtn) {
      ui.saveCvBtn.addEventListener('click', function() {
        saveCv(true);
      });
    }

    if (ui.downloadPdfBtn) {
      ui.downloadPdfBtn.addEventListener('click', handleDownloadPdf);
    }

    if (ui.zoomBtn) {
      ui.zoomBtn.addEventListener('click', handleZoomToggle);
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function init() {
    applyStateToForm();
    applyLanguage();
    renderSkillsList();
    renderExperienceList();
    updatePreview();
    bindUiEvents();
    await loadCv();
  }

  init();
})();
</script>
