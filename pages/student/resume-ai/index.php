<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../../../backend/db_connect.php';
}

$userId = isset($userId) ? (int) $userId : (int) ($_SESSION['user_id'] ?? 0);
$student = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'program' => '',
    'department' => '',
];

if (isset($pdo) && $pdo instanceof PDO && $userId > 0) {
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, program, department FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $student = array_merge($student, $row);
    }
}

$defaultFirstName = (string) ($student['first_name'] ?? '');
$defaultLastName = (string) ($student['last_name'] ?? '');
$defaultEmail = (string) ($student['email'] ?? ($_SESSION['user_email'] ?? ''));
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
            <img id="profilePicturePreview" src="" alt="Profile" style="display: none;">
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
        <input type="tel" id="phone" class="cv-form-input" placeholder="(123) 456-7890" value="">
      </div>

      <!-- Location -->
      <div class="cv-form-row">
        <div class="cv-form-group">
          <label for="city">City</label>
          <input type="text" id="city" class="cv-form-input" placeholder="City" value="">
        </div>
        <div class="cv-form-group">
          <label for="country">Country</label>
          <input type="text" id="country" class="cv-form-input" placeholder="Country" value="">
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

        <!-- Education Section -->
        <div class="cv-preview-section" id="educationPreviewSection" style="display: none;">
          <h2>Education</h2>
          <div id="previewEducation"></div>
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

/* Save status indicator */
.cv-btn.saved {
  background: #10b981;
  color: white;
}

.cv-btn.saved:hover {
  background: #059669;
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

  function updatePreview() {
    const firstName = form.firstName.value.trim();
    const lastName = form.lastName.value.trim();
    const fullName = firstName + (lastName ? ' ' + lastName : '') || 'Matthew Smith';
    
    preview.name.textContent = fullName;
    preview.jobTitle.textContent = form.jobTitle.value || 'Service Designer';
    
    const city = form.city.value.trim();
    const country = form.country.value.trim();
    const phone = form.phone.value.trim();
    const email = form.email.value.trim();
    
    // Build address from city and country
    let address = '123 Your street';
    if (city || country) {
      address += '\n' + (city ? city : 'Your city') + ', ' + (country ? country : 'Your Country');
    } else {
      address += '\nYour city, Your Country';
    }
    preview.address.innerHTML = address.replace(/\n/g, '<br>');
    
    preview.phone.textContent = phone || '(123) 456-7890';
    preview.email.textContent = email || 'matthew@smith007.com';
    
    if (form.bio.value.trim()) {
      preview.bio.textContent = form.bio.value.trim();
      document.getElementById('bioPreviewSection').style.display = 'block';
    } else {
      document.getElementById('bioPreviewSection').style.display = 'none';
    }
    
    // Update education section with program and department
    const program = form.program.value.trim();
    const department = form.department.value.trim();
    if (program || department) {
      const eduHTML = `
        <div style="margin-bottom: 10px;">
          ${program ? '<strong>' + program + '</strong>' : ''}
          ${department ? (program ? '<br>' : '') + 'Department: ' + department : ''}
        </div>
      `;
      document.getElementById('previewEducation').innerHTML = eduHTML;
      document.getElementById('educationPreviewSection').style.display = 'block';
    } else {
      document.getElementById('educationPreviewSection').style.display = 'none';
    }
    
    document.getElementById('bioCount').textContent = form.bio.value.length;
  }

  // Collect CV data from form
  function getFormData() {
    return {
      firstName: form.firstName.value.trim(),
      lastName: form.lastName.value.trim(),
      email: form.email.value.trim(),
      phone: form.phone.value.trim(),
      jobTitle: form.jobTitle.value.trim(),
      city: form.city.value.trim(),
      country: form.country.value.trim(),
      bio: form.bio.value.trim(),
      program: form.program.value.trim(),
      department: form.department.value.trim(),
    };
  }

  // Save CV to database (AJAX)
  function saveCvToDatabase() {
    const cvData = JSON.stringify(getFormData());
    const formData = new FormData();
    formData.append('action', 'save_form_cv');
    formData.append('cv_data', cvData);

    fetch('../resume_ai_endpoint.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.ok) {
        const saveBtn = document.getElementById('saveCvBtn');
        saveBtn.textContent = '✓ Saved';
        saveBtn.classList.add('saved');
        setTimeout(() => {
          saveBtn.textContent = 'Save CV';
          saveBtn.classList.remove('saved');
        }, 2000);
      } else {
        console.error('Save failed:', data.message);
      }
    })
    .catch(error => console.error('Error saving CV:', error));
  }

  // Auto-save with debounce
  let autoSaveTimeout;
  function debounceAutoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(saveCvToDatabase, 2000); // Save 2s after last change
  }

  // Load saved CV from database
  function loadSavedCv() {
    const formData = new FormData();
    formData.append('action', 'load_form_cv');

    fetch('../resume_ai_endpoint.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.ok && data.data) {
        // Populate form fields with saved data
        form.firstName.value = data.data.firstName || '';
        form.lastName.value = data.data.lastName || '';
        form.email.value = data.data.email || '';
        form.phone.value = data.data.phone || '';
        form.jobTitle.value = data.data.jobTitle || '';
        form.city.value = data.data.city || '';
        form.country.value = data.data.country || '';
        form.bio.value = data.data.bio || '';
        form.program.value = data.data.program || '';
        form.department.value = data.data.department || '';
        
        // Update preview after loading
        updatePreview();
      }
    })
    .catch(error => console.error('Error loading CV:', error));
  }

  // Event listeners with auto-save
  form.firstName.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.lastName.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.email.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.phone.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.jobTitle.addEventListener('change', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.city.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.country.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.bio.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.program.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });
  form.department.addEventListener('input', () => {
    updatePreview();
    debounceAutoSave();
  });

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

  // Initialize
  loadSavedCv();
  updatePreview();

  // Save button - explicit save
  document.getElementById('saveCvBtn').addEventListener('click', saveCvToDatabase);

  document.getElementById('downloadPdfBtn').addEventListener('click', function() {
    alert('PDF download coming soon!');
  });
});

  function defaultState() {
    return {
      profile: {
        name: <?php echo json_encode($defaultName, JSON_UNESCAPED_UNICODE); ?>,
        contact: <?php echo json_encode($defaultContact, JSON_UNESCAPED_UNICODE); ?>,
        summary: <?php echo json_encode($defaultSummary, JSON_UNESCAPED_UNICODE); ?>,
        headline: <?php echo json_encode($defaultHeadline, JSON_UNESCAPED_UNICODE); ?>
      },
      sections: [
        {
          id: 'experience',
          title: 'Experience',
          type: 'experience',
          items: [
            {
              role: 'Intern / Project Contributor',
              company: 'Academic and Personal Projects',
              location: 'Philippines',
              dates: '(Recent)',
              bullets: [
                'Built practical internship-ready outputs in team and solo projects.',
                'Documented features and collaborated with mentors and peers.'
              ]
            }
          ]
        },
        {
          id: 'education',
          title: 'Education',
          type: 'education',
          items: [
            {
              title: 'Bachelor Degree',
              subtitle: 'University',
              meta: 'Expected Graduation: TBD'
            }
          ]
        },
        {
          id: 'skills',
          title: 'Skills',
          type: 'skills',
          items: [
            {
              title: 'Core Skills',
              subtitle: 'Communication, Problem Solving, Teamwork'
            }
          ]
        }
      ]
    };
  }

  function normalizeState(raw) {
    var base = defaultState();
    if (!raw || typeof raw !== 'object') return base;
    var profile = raw.profile && typeof raw.profile === 'object' ? raw.profile : {};
    base.profile.name = String(profile.name || base.profile.name || '');
    base.profile.contact = String(profile.contact || base.profile.contact || '');
    base.profile.summary = String(profile.summary || base.profile.summary || '');
    base.profile.headline = String(profile.headline || base.profile.headline || '');

    if (Array.isArray(raw.sections) && raw.sections.length) {
      base.sections = raw.sections.map(function (s, i) {
        var section = {
          id: String((s && s.id) || ('section_' + i)),
          title: String((s && s.title) || 'Section'),
          type: String((s && s.type) || 'generic'),
          items: Array.isArray(s && s.items) ? s.items : []
        };

        section.items = section.items.map(function (it) {
          return {
            role: String((it && it.role) || ''),
            company: String((it && it.company) || ''),
            location: String((it && it.location) || ''),
            dates: String((it && it.dates) || ''),
            bullets: Array.isArray(it && it.bullets) ? it.bullets.map(String) : [],
            title: String((it && it.title) || ''),
            subtitle: String((it && it.subtitle) || ''),
            meta: String((it && it.meta) || '')
          };
        });

        return section;
      });
    }

    return base;
  }

  var state = defaultState();
  var sourceMode = 'link';
  var sourceType = 'linkedin';
  var sourceUrl = '';
  var autoSaveTimer = null;

  var messageEl = document.getElementById('cvBuilderMessage');
  var sourceCard = document.getElementById('cvSourceCard');
  var builderCard = document.getElementById('cvBuilderCard');
  var sourceTypeEl = document.getElementById('cvSourceType');
  var sourceUrlEl = document.getElementById('cvSourceUrl');
  var sourceFieldsEl = document.getElementById('cvLinkFields');
  var sourceModeStatusEl = document.getElementById('cvSourceModeStatus');
  var lastSaveStatusEl = document.getElementById('cvLastSaveStatus');
  var sectionsStatusEl = document.getElementById('cvSectionsStatus');
  var experienceStatusEl = document.getElementById('cvExperienceStatus');
  var scoreGaugeEl = document.getElementById('cvScoreGauge');
  var scoreValueEl = document.getElementById('cvScoreValue');
  var scoreLabelEl = document.getElementById('cvScoreLabel');
  var scoreNoteEl = document.getElementById('cvScoreNote');
  var cvHeaderBlock = document.getElementById('cvHeaderBlock');
  var cvSections = document.getElementById('cvSections');
  var cvPaper = document.getElementById('cvPaper');

  function setMessage(text, isError) {
    messageEl.textContent = text || '';
    messageEl.style.color = isError ? '#b91c1c' : '#0f766e';
  }

  function nowStamp() {
    var d = new Date();
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
  }

  function updateStats() {
    sourceModeStatusEl.textContent = sourceMode;
    sectionsStatusEl.textContent = String((state.sections || []).length);
    var exp = (state.sections || []).find(function (s) { return s.id === 'experience'; });
    experienceStatusEl.textContent = String(exp && exp.items ? exp.items.length : 0);
  }

  function showBuilder() {
    builderCard.hidden = false;
  }

  function syncSourceFields() {
    var selected = document.querySelector('input[name="source_mode"]:checked');
    sourceMode = selected ? selected.value : 'link';
    sourceType = String(sourceTypeEl.value || 'linkedin');
    sourceUrl = String(sourceUrlEl.value || '').trim();
    sourceFieldsEl.style.display = sourceMode === 'link' ? 'block' : 'none';
    updateStats();
  }

  async function api(action, payload) {
    var fd = new FormData();
    fd.append('action', action);
    Object.keys(payload || {}).forEach(function (key) {
      fd.append(key, payload[key]);
    });
    var res = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });
    return res.json();
  }

  function renderHeader() {
    cvHeaderBlock.innerHTML = ''
      + '<div class="cvb-name">' + escapeHtml(state.profile.name || 'Your Name') + '</div>'
      + '<div class="cvb-contact">' + escapeHtml(state.profile.contact || '') + (state.profile.contact ? ' | ' : '') + escapeHtml(state.profile.headline || '') + '</div>'
      + '<div class="cvb-summary">' + escapeHtml(state.profile.summary || '') + '</div>';
  }

  function renderSectionItem(section, item) {
    if (section.type === 'experience') {
      var roleLine = (item.role || 'Role') + (item.company ? ', ' + item.company : '');
      var bullets = (item.bullets || []).map(function (bullet) {
        return '<li>' + escapeHtml(bullet) + '</li>';
      }).join('');
      return ''
        + '<article class="cvb-item" draggable="true">'
        + '  <div class="cvb-item-head">'
        + '    <div>'
        + '      <h5 class="cvb-item-title">' + escapeHtml(roleLine) + '</h5>'
        + '      <div class="cvb-item-sub">' + escapeHtml(item.location || '') + ' - ' + escapeHtml(item.dates || '') + '</div>'
        + '    </div>'
        + '    <span class="cvb-drag-handle"><i class="fas fa-grip-lines"></i></span>'
        + '  </div>'
        + '  <ul>' + bullets + '</ul>'
        + '</article>';
    }

    return ''
      + '<article class="cvb-item" draggable="true">'
      + '  <div class="cvb-item-head">'
      + '    <div>'
      + '      <h5 class="cvb-item-title dark">' + escapeHtml(item.title || '') + '</h5>'
      + '      <div class="cvb-item-sub">' + escapeHtml(item.subtitle || '') + '</div>'
      + (item.meta ? '<div class="cvb-item-sub">' + escapeHtml(item.meta) + '</div>' : '')
      + '    </div>'
      + '    <span class="cvb-drag-handle"><i class="fas fa-grip-lines"></i></span>'
      + '  </div>'
      + '</article>';
  }

  function renderSections() {
    cvSections.innerHTML = state.sections.map(function (section) {
      return ''
        + '<section class="cvb-section" data-section-id="' + escapeHtml(section.id) + '" draggable="true">'
        + '  <div class="cvb-section-head">'
        + '    <h4>' + escapeHtml(section.title) + '</h4>'
        + '    <span class="cvb-drag-handle"><i class="fas fa-grip-vertical"></i></span>'
        + '  </div>'
        + '  <div class="cvb-item-list" data-item-list="' + escapeHtml(section.id) + '">'
        + section.items.map(function (item) { return renderSectionItem(section, item); }).join('')
        + '  </div>'
        + '</section>';
    }).join('');

    enableSectionSorting();
    enableItemSorting();
  }

  function renderBuilder() {
    renderHeader();
    renderSections();
    updateStats();
  }

  function setStateFromPayload(payload) {
    state = normalizeState(payload || {});
    showBuilder();
    renderBuilder();
    requestScore();
  }

  function setBreakdownCell(pctId, barId, value) {
    var v = Math.max(0, Math.min(100, Number(value || 0)));
    var pctEl = document.getElementById(pctId);
    var barEl = document.getElementById(barId);
    if (pctEl) pctEl.textContent = String(v) + '%';
    if (barEl) barEl.style.width = String(v) + '%';
  }

  function updateScorePanel(data) {
    var score = Math.max(0, Math.min(100, Number((data || {}).score || 0)));
    var dashOffset = Math.round(302 - ((score / 100) * 302));
    if (scoreGaugeEl) scoreGaugeEl.setAttribute('stroke-dashoffset', String(dashOffset));
    if (scoreValueEl) scoreValueEl.textContent = String(score);
    if (scoreLabelEl) {
      scoreLabelEl.textContent = score >= 80 ? 'Excellent resume quality' : (score >= 65 ? 'Good progress' : 'Needs improvement');
    }

    var breakdown = (data || {}).breakdown || {};
    setBreakdownCell('cvScoreContent', 'cvScoreContentBar', breakdown.content);
    setBreakdownCell('cvScoreFormatting', 'cvScoreFormattingBar', breakdown.formatting);
    setBreakdownCell('cvScoreKeywords', 'cvScoreKeywordsBar', breakdown.keywords);
    setBreakdownCell('cvScoreImpact', 'cvScoreImpactBar', breakdown.impact);

    if (scoreNoteEl) {
      scoreNoteEl.textContent = String((data || {}).note || 'Score generated from your current CV.');
    }
  }

  async function requestScore() {
    try {
      var res = await api('score_cv', {
        cv_json: JSON.stringify(state)
      });
      if (!res || !res.ok) {
        throw new Error((res && res.message) ? res.message : 'Unable to score CV.');
      }
      updateScorePanel(res);
    } catch (err) {
      if (scoreNoteEl) {
        scoreNoteEl.textContent = err && err.message ? err.message : 'Unable to score CV right now.';
      }
    }
  }

  function reindexSectionsFromDom() {
    var ids = Array.prototype.map.call(cvSections.querySelectorAll('.cvb-section'), function (el) {
      return String(el.getAttribute('data-section-id') || '');
    });

    state.sections.sort(function (a, b) {
      return ids.indexOf(a.id) - ids.indexOf(b.id);
    });

    scheduleAutoSave();
  }

  function reorderItemsFromDom(sectionId, container) {
    var section = state.sections.find(function (s) { return s.id === sectionId; });
    if (!section) return;
    var indices = Array.prototype.map.call(container.querySelectorAll('.cvb-item'), function (el) {
      return Number(el.getAttribute('data-item-index'));
    }).filter(function (n) { return !isNaN(n); });

    if (indices.length !== section.items.length) return;

    section.items = indices.map(function (idx) {
      return section.items[idx];
    });

    renderBuilder();
    scheduleAutoSave();
    requestScore();
  }

  function enableSortable(container, itemSelector, onDropDone) {
    var dragged = null;

    container.addEventListener('dragstart', function (e) {
      var item = e.target.closest(itemSelector);
      if (!item || !container.contains(item)) return;
      dragged = item;
      dragged.classList.add('dragging');
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', 'drag');
      }
    });

    container.addEventListener('dragover', function (e) {
      if (!dragged) return;
      e.preventDefault();
      var target = e.target.closest(itemSelector);
      if (!target || target === dragged || !container.contains(target)) return;
      var rect = target.getBoundingClientRect();
      var after = e.clientY > rect.top + (rect.height / 2);
      target.classList.add('cvb-drop-over');
      if (after) {
        target.parentNode.insertBefore(dragged, target.nextSibling);
      } else {
        target.parentNode.insertBefore(dragged, target);
      }
    });

    container.addEventListener('dragleave', function (e) {
      var target = e.target.closest(itemSelector);
      if (target) target.classList.remove('cvb-drop-over');
    });

    container.addEventListener('drop', function (e) {
      if (!dragged) return;
      e.preventDefault();
      Array.prototype.forEach.call(container.querySelectorAll(itemSelector), function (el) {
        el.classList.remove('cvb-drop-over');
        el.classList.remove('dragging');
      });
      dragged = null;
      if (typeof onDropDone === 'function') onDropDone();
    });

    container.addEventListener('dragend', function () {
      Array.prototype.forEach.call(container.querySelectorAll(itemSelector), function (el) {
        el.classList.remove('cvb-drop-over');
        el.classList.remove('dragging');
      });
      dragged = null;
    });
  }

  function enableSectionSorting() {
    enableSortable(cvSections, '.cvb-section', function () {
      reindexSectionsFromDom();
    });
  }

  function enableItemSorting() {
    state.sections.forEach(function (section) {
      var list = cvSections.querySelector('[data-item-list="' + section.id.replace(/"/g, '') + '"]');
      if (!list) return;
      Array.prototype.forEach.call(list.querySelectorAll('.cvb-item'), function (itemEl, idx) {
        itemEl.setAttribute('data-item-index', String(idx));
      });
      enableSortable(list, '.cvb-item', function () {
        reorderItemsFromDom(section.id, list);
      });
    });
  }

  async function saveCvToDatabase(manual) {
    try {
      var payload = {
        source_mode: sourceMode,
        source_type: sourceType,
        source_url: sourceUrl,
        cv_json: JSON.stringify(state)
      };
      var res = await api('save_cv', payload);
      if (!res || !res.ok) {
        throw new Error((res && res.message) ? res.message : 'Unable to save CV.');
      }
      lastSaveStatusEl.textContent = nowStamp();
      requestScore();
      if (manual) {
        setMessage('CV saved to database.', false);
      }
    } catch (err) {
      setMessage(err && err.message ? err.message : 'Save failed.', true);
    }
  }

  function scheduleAutoSave() {
    if (autoSaveTimer) {
      clearTimeout(autoSaveTimer);
    }
    autoSaveTimer = setTimeout(function () {
      saveCvToDatabase(false);
    }, 1000);
  }

  async function loadSavedCv(showMessages) {
    var res = await api('load_cv', {});
    if (!res || !res.ok) {
      if (showMessages) {
        setMessage((res && res.message) ? res.message : 'Unable to load saved CV.', true);
      }
      return false;
    }

    if (!res.has_cv) {
      if (showMessages) {
        setMessage('No saved CV found yet.', false);
      }
      return false;
    }

    sourceMode = String(res.source_mode || 'profile');
    sourceType = String(res.source_type || 'linkedin');
    sourceUrl = String(res.source_url || '');

    var modeRadio = document.querySelector('input[name="source_mode"][value="' + sourceMode + '"]');
    if (modeRadio) modeRadio.checked = true;
    sourceTypeEl.value = sourceType;
    sourceUrlEl.value = sourceUrl;
    syncSourceFields();

    setStateFromPayload(res.cv || {});
    lastSaveStatusEl.textContent = res.updated_at || nowStamp();
    if (showMessages) {
      setMessage('Loaded saved CV from database.', false);
    }
    return true;
  }

  document.getElementById('cvSourceForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    syncSourceFields();

    if (sourceMode === 'link' && !sourceUrl) {
      setMessage('Please provide a URL for link import.', true);
      return;
    }

    var buildBtn = document.getElementById('cvBuildBtn');
    buildBtn.disabled = true;
    setMessage('Building your CV. Please wait...', false);

    try {
      var res = await api('build_cv', {
        source_mode: sourceMode,
        source_type: sourceType,
        source_url: sourceUrl
      });

      if (!res || !res.ok) {
        throw new Error((res && res.message) ? res.message : 'Unable to build CV right now.');
      }

      setStateFromPayload(res.cv || {});
      lastSaveStatusEl.textContent = res.updated_at || nowStamp();
      setMessage(res.note || 'CV generated successfully.', false);
    } catch (err) {
      setMessage(err && err.message ? err.message : 'Build failed.', true);
    } finally {
      buildBtn.disabled = false;
    }
  });

  document.getElementById('cvSaveBtn').addEventListener('click', function () {
    saveCvToDatabase(true);
  });

  document.getElementById('cvLoadBtn').addEventListener('click', function () {
    loadSavedCv(true).catch(function () {
      setMessage('Unable to load saved CV.', true);
    });
  });

  document.getElementById('cvAddExperienceBtn').addEventListener('click', function () {
    var exp = state.sections.find(function (s) { return s.id === 'experience'; });
    if (!exp) {
      exp = { id: 'experience', title: 'Experience', type: 'experience', items: [] };
      state.sections.unshift(exp);
    }

    exp.items.push({
      role: 'New Role',
      company: 'Company Name',
      location: 'City, Country',
      dates: '(Start - End)',
      bullets: ['Describe one measurable achievement.']
    });

    renderBuilder();
    scheduleAutoSave();
    requestScore();
  });

  document.getElementById('cvSavePdfBtn').addEventListener('click', function () {
    if (!cvPaper) {
      setMessage('CV preview is not ready yet.', true);
      return;
    }

    var printWindow = window.open('', '_blank', 'width=900,height=1200');
    if (!printWindow) {
      setMessage('Please allow popups to save as PDF.', true);
      return;
    }

    var html = ''
      + '<!doctype html><html><head><meta charset="utf-8">'
      + '<title>SkillHive CV</title>'
      + '<style>'
      + 'body{margin:0;background:#f3f4f6;font-family:Poppins,sans-serif;padding:24px;}'
      + '.paper{max-width:794px;min-height:1123px;margin:0 auto;background:#fffefc;border:1px solid #cbd5e1;box-shadow:none;padding:28px;}'
      + '.cvb-header{border-bottom:1px solid #e2e8f0;padding-bottom:10px;margin-bottom:12px;}'
      + '.cvb-name{font-size:1.1rem;font-weight:700;color:#0f172a;}'
      + '.cvb-contact{font-size:.8rem;color:#334155;margin-top:4px;}'
      + '.cvb-summary{font-size:.82rem;color:#475569;margin-top:8px;line-height:1.45;}'
      + '.cvb-sections{display:flex;flex-direction:column;gap:10px;}'
      + '.cvb-section{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#fff;}'
      + '.cvb-section-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;}'
      + '.cvb-section-head h4{margin:0;font-size:.95rem;color:#0f172a;}'
      + '.cvb-item-list{display:flex;flex-direction:column;gap:8px;}'
      + '.cvb-item{border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff;}'
      + '.cvb-item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}'
      + '.cvb-item-title{margin:0;font-size:.86rem;color:#16a34a;font-weight:700;}'
      + '.cvb-item-title.dark{color:#111827;}'
      + '.cvb-item-sub{margin:4px 0;font-size:.78rem;color:#4b5563;}'
      + '.cvb-item ul{margin:0;padding-left:18px;}'
      + '.cvb-item li{font-size:.78rem;color:#374151;margin-bottom:3px;}'
      + '.cvb-drag-handle{display:none !important;}'
      + '@page{size:A4 portrait;margin:14mm;}'
      + '</style></head><body>'
      + '<div class="paper">' + cvPaper.innerHTML + '</div>'
      + '</body></html>';

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(function () {
      printWindow.print();
    }, 250);
  });

  Array.prototype.forEach.call(document.querySelectorAll('input[name="source_mode"]'), function (el) {
    el.addEventListener('change', syncSourceFields);
  });

  sourceTypeEl.addEventListener('change', syncSourceFields);
  sourceUrlEl.addEventListener('input', syncSourceFields);

  syncSourceFields();
  updateStats();

});
</script>
