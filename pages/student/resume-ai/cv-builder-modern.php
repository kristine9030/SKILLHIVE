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
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone, program, department, city, country, profile_picture FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $student = array_merge($student, $row);
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
?>

<div class="page-header">
  <div>
    <h2 class="page-title">CV Builder</h2>
    <p class="page-subtitle">Create a professional CV with live preview</p>
  </div>
</div>

<div class="cv-builder-container">
  <div class="cv-form-section">
    <!-- Profile Section -->
    <div class="cv-form-card">
      <div class="cv-form-header">
        <h3>Your Details</h3>
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
            <img id="profilePicturePreview" src="<?php echo htmlspecialchars($student['profile_picture'] ?? '', ENT_QUOTES); ?>" alt="Profile" onerror="this.style.display='none'">
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
          <input type="text" id="firstName" class="cv-form-input" placeholder="First Name" value="<?php echo htmlspecialchars($defaultFirstName); ?>">
        </div>
        <div class="cv-form-group">
          <label for="lastName">Last Name</label>
          <input type="text" id="lastName" class="cv-form-input" placeholder="Last Name" value="<?php echo htmlspecialchars($defaultLastName); ?>">
        </div>
      </div>

      <!-- Email -->
      <div class="cv-form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" class="cv-form-input" placeholder="email@example.com" value="<?php echo htmlspecialchars($defaultEmail); ?>">
      </div>

      <!-- Phone -->
      <div class="cv-form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" class="cv-form-input" placeholder="(123) 456-7890" value="<?php echo htmlspecialchars($defaultPhone); ?>">
      </div>

      <!-- Location -->
      <div class="cv-form-row">
        <div class="cv-form-group">
          <label for="city">City</label>
          <input type="text" id="city" class="cv-form-input" placeholder="City" value="<?php echo htmlspecialchars($defaultCity); ?>">
        </div>
        <div class="cv-form-group">
          <label for="country">Country</label>
          <input type="text" id="country" class="cv-form-input" placeholder="Country" value="<?php echo htmlspecialchars($defaultCountry); ?>">
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
            <input type="text" id="program" class="cv-form-input" placeholder="Program" value="<?php echo htmlspecialchars($student['program'] ?? ''); ?>">
          </div>
          <div class="cv-form-group">
            <label for="department">Department</label>
            <input type="text" id="department" class="cv-form-input" placeholder="Department" value="<?php echo htmlspecialchars($student['department'] ?? ''); ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Short Bio Section -->
    <div class="cv-form-card">
      <h3>Short Bio</h3>
      <p class="cv-form-hint">Be concise - The harsh reality is that hiring managers only spent an average of 6 seconds on each resume. <a href="#" class="cv-help-link">See examples</a> for help.</p>
      <div class="cv-form-group">
        <textarea id="bio" class="cv-form-textarea" placeholder="Write a brief professional summary..." rows="4"><?php echo htmlspecialchars($defaultBio); ?></textarea>
        <div class="cv-textarea-counter"><span id="bioCount">0</span>/200</div>
      </div>
    </div>

    <!-- Skills Section -->
    <div class="cv-form-card">
      <h3>Skills</h3>
      <div class="cv-skills-list" id="skillsList"></div>
      <button type="button" class="cv-btn-add-skill" id="addSkillBtn">+ Add skill</button>
      <div class="cv-form-group" style="margin-top: 10px;">
        <input type="text" id="newSkillInput" class="cv-form-input" placeholder="Enter skill name" style="display: none;">
      </div>
    </div>

    <!-- Experience Section -->
    <div class="cv-form-card">
      <h3>Experience</h3>
      <button type="button" class="cv-btn-add-section" id="addExperienceBtn">+ Add experience</button>
      <div class="cv-experience-list" id="experienceList"></div>
    </div>

    <!-- Actions -->
    <div class="cv-form-actions">
      <button type="button" class="cv-btn cv-btn-primary" id="saveCvBtn">Save CV</button>
      <button type="button" class="cv-btn cv-btn-secondary" id="downloadPdfBtn">Download PDF</button>
    </div>
  </div>

  <!-- Preview Section -->
  <div class="cv-preview-section">
    <div class="cv-preview-header">
      <span>Page 1 of 2</span>
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
          <div class="cv-preview-contact">
            <p id="previewAddress">123 Your street<br>Your city, ST 123456<br>Your Country</p>
            <p id="previewPhone">(123) 456-7890</p>
            <p id="previewEmail">matthew@smith007.com</p>
          </div>
        </div>

        <!-- Bio Section -->
        <div class="cv-preview-section" id="bioPreviewSection" style="display: none;">
          <p id="previewBio"></p>
        </div>

        <!-- Skills Section -->
        <div class="cv-preview-section" id="skillsPreviewSection" style="display: none;">
          <h2>Skills</h2>
          <div class="cv-preview-skills" id="previewSkills"></div>
        </div>

        <!-- Experience Section -->
        <div class="cv-preview-section" id="experiencePreviewSection" style="display: none;">
          <h2>Experience</h2>
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

/* Preview Styles */
.cv-preview-section {
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
  padding: 40px;
  border-radius: 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  aspect-ratio: 8.5/11;
  max-width: 600px;
  margin: 0 auto;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #1f2937;
  line-height: 1.5;
}

.cv-preview-header-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e5e7eb;
}

.cv-preview-profile {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.cv-preview-pic {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
}

.cv-preview-info h1 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
}

.cv-preview-info p {
  margin: 4px 0 0 0;
  font-size: 0.9rem;
  color: #9ca3af;
}

.cv-preview-contact {
  text-align: right;
  font-size: 0.85rem;
  color: #9ca3af;
}

.cv-preview-contact p {
  margin: 0;
  line-height: 1.4;
}

.cv-preview-section {
  margin-bottom: 15px;
}

.cv-preview-section h2 {
  margin: 0 0 8px 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #111827;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 4px;
}

.cv-preview-section p {
  margin: 0;
  font-size: 0.85rem;
  line-height: 1.5;
  color: #475569;
}

.cv-preview-skills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 0.8rem;
}

.cv-preview-skill-tag {
  background: #f0f9ff;
  color: #0369a1;
  padding: 4px 8px;
  border-radius: 4px;
  border: 1px solid #bae6fd;
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
</style>

<script>
(function() {
  const form = {
    firstName: document.getElementById('firstName'),
    lastName: document.getElementById('lastName'),
    email: document.getElementById('email'),
    phone: document.getElementById('phone'),
    jobTitle: document.getElementById('jobTitle'),
    city: document.getElementById('city'),
    country: document.getElementById('country'),
    program: document.getElementById('program'),
    department: document.getElementById('department'),
    bio: document.getElementById('bio'),
    profilePicture: document.getElementById('profilePicture'),
    profilePicturePreview: document.getElementById('profilePicturePreview'),
  };

  const preview = {
    name: document.getElementById('previewName'),
    jobTitle: document.getElementById('previewJobTitle'),
    address: document.getElementById('previewAddress'),
    phone: document.getElementById('previewPhone'),
    email: document.getElementById('previewEmail'),
    bio: document.getElementById('previewBio'),
    profilePic: document.getElementById('previewProfilePic'),
  };

  // Initialize CV Builder
  function updatePreview() {
    const firstName = form.firstName.value.trim();
    const lastName = form.lastName.value.trim();
    const fullName = firstName + (lastName ? ' ' + lastName : '') || 'Matthew Smith';
    
    preview.name.textContent = fullName;
    preview.jobTitle.textContent = form.jobTitle.value || 'Service Designer';
    
    const city = form.city.value.trim();
    const country = form.country.value.trim();
    const address = `123 Your street\n${city || 'Your city'}, ST 123456\n${country || 'Your Country'}`;
    preview.address.innerHTML = address.replace(/\n/g, '<br>');
    
    preview.phone.textContent = form.phone.value || '(123) 456-7890';
    preview.email.textContent = form.email.value || 'matthew@smith007.com';
    
    if (form.bio.value.trim()) {
      preview.bio.textContent = form.bio.value.trim();
      document.getElementById('bioPreviewSection').style.display = 'block';
    } else {
      document.getElementById('bioPreviewSection').style.display = 'none';
    }
    
    // Update character count
    document.getElementById('bioCount').textContent = form.bio.value.length;
  }

  // Event listeners
  form.firstName.addEventListener('input', updatePreview);
  form.lastName.addEventListener('input', updatePreview);
  form.email.addEventListener('input', updatePreview);
  form.phone.addEventListener('input', updatePreview);
  form.jobTitle.addEventListener('change', updatePreview);
  form.city.addEventListener('input', updatePreview);
  form.country.addEventListener('input', updatePreview);
  form.bio.addEventListener('input', updatePreview);

  // Profile picture upload
  form.profilePicture.addEventListener('change', function(e) {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(event) {
        const src = event.target?.result;
        form.profilePicturePreview.src = src;
        form.profilePicturePreview.style.display = 'block';
        preview.profilePic.src = src;
        preview.profilePic.style.display = 'block';
        document.getElementById('profilePicturePlaceholder').style.display = 'none';
      };
      reader.readAsDataURL(file);
    }
  });

  // Collapsible sections
  document.querySelectorAll('.cv-form-toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
      const expanded = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', !expanded);
      const content = this.nextElementSibling;
      if (content) {
        content.hidden = expanded;
      }
    });
  });

  // Initial preview update
  updatePreview();

  // Save CV
  document.getElementById('saveCvBtn').addEventListener('click', function() {
    alert('CV saved! (Feature coming soon)');
  });

  // Download PDF
  document.getElementById('downloadPdfBtn').addEventListener('click', function() {
    alert('PDF download coming soon!');
  });
})();
</script>
