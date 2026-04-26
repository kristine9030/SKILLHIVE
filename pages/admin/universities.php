<?php
require_once __DIR__ . '/../../backend/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php');
    exit;
}

$baseUrl = $baseUrl ?? '/SkillHive';

$studentDomains = $pdo->query("SELECT SUBSTRING_INDEX(email, '@', -1) AS domain, COUNT(*) AS cnt FROM student GROUP BY domain")
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$adviserDomains = $pdo->query("SELECT SUBSTRING_INDEX(email, '@', -1) AS domain, COUNT(*) AS cnt FROM internship_adviser GROUP BY domain")
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$ojtDomains = $pdo->query("SELECT SUBSTRING_INDEX(s.email, '@', -1) AS domain, COUNT(*) AS cnt FROM ojt_record r JOIN student s ON s.student_id = r.student_id WHERE r.completion_status = 'Ongoing' GROUP BY domain")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$seedThemes = [
    'batstateu' => ['name' => 'Batangas State University', 'abbr' => 'BatStateU', 'color' => '#D10000', 'gradA' => '#12b3ac', 'gradB' => '#12b3ac', 'gradC' => '#FCA5A5', 'region' => 'Region IV-A (CALABARZON)', 'domains' => ['g.batstate-u.edu.ph', 'batstateu.edu.ph']],
    'pup' => ['name' => 'Polytechnic University of the Philippines', 'abbr' => 'PUP', 'color' => '#12b3ac', 'gradA' => '#12b3ac', 'gradB' => '#12b3ac', 'gradC' => '#9ee7e1', 'region' => 'NCR', 'domains' => ['pup.edu.ph']],
    'uplb' => ['name' => 'University of the Philippines Los Banos', 'abbr' => 'UPLB', 'color' => '#15803D', 'gradA' => '#14532D', 'gradB' => '#22C55E', 'gradC' => '#86EFAC', 'region' => 'Region IV-A (CALABARZON)', 'domains' => ['uplb.edu.ph']],
    'tip' => ['name' => 'Technological Institute of the Philippines', 'abbr' => 'TIP', 'color' => '#7C2D12', 'gradA' => '#7C2D12', 'gradB' => '#EA580C', 'gradC' => '#FDBA74', 'region' => 'NCR', 'domains' => ['tip.edu.ph']],
    'dlsu' => ['name' => 'De La Salle University', 'abbr' => 'DLSU', 'color' => '#065F46', 'gradA' => '#064E3B', 'gradB' => '#12b3ac', 'gradC' => '#6EE7B7', 'region' => 'NCR', 'domains' => ['dlsu.edu.ph']],
    'ateneo' => ['name' => 'Ateneo de Manila University', 'abbr' => 'ADMU', 'color' => '#12b3ac', 'gradA' => '#12b3ac', 'gradB' => '#12b3ac', 'gradC' => '#bfe7e4', 'region' => 'NCR', 'domains' => ['admu.edu.ph']],
    'ust' => ['name' => 'University of Santo Tomas', 'abbr' => 'UST', 'color' => '#B45309', 'gradA' => '#92400E', 'gradB' => '#12b3ac', 'gradC' => '#FDE68A', 'region' => 'NCR', 'domains' => ['ust.edu.ph']],
    'mapua' => ['name' => 'Mapua University', 'abbr' => 'Mapua', 'color' => '#9F1239', 'gradA' => '#881337', 'gradB' => '#F43F5E', 'gradC' => '#FDA4AF', 'region' => 'NCR', 'domains' => ['mapua.edu.ph']],
    'feu' => ['name' => 'Far Eastern University', 'abbr' => 'FEU', 'color' => '#166534', 'gradA' => '#14532D', 'gradB' => '#16A34A', 'gradC' => '#BBF7D0', 'region' => 'NCR', 'domains' => ['feu.edu.ph']],
    'neu' => ['name' => 'New Era University', 'abbr' => 'NEU', 'color' => '#12b3ac', 'gradA' => '#12b3ac', 'gradB' => '#12b3ac', 'gradC' => '#bfe7e4', 'region' => 'NCR', 'domains' => ['neu.edu.ph']],
    'bulsu' => ['name' => 'Bulacan State University', 'abbr' => 'BulSU', 'color' => '#0369A1', 'gradA' => '#0C4A6E', 'gradB' => '#0284C7', 'gradC' => '#BAE6FD', 'region' => 'Region III', 'domains' => ['bulsu.edu.ph']],
    'pnu' => ['name' => 'Philippine Normal University', 'abbr' => 'PNU', 'color' => '#6B21A8', 'gradA' => '#581C87', 'gradB' => '#12b3ac', 'gradC' => '#E9D5FF', 'region' => 'NCR', 'domains' => ['pnu.edu.ph']],
];

$seedUniversities = [];
foreach ($seedThemes as $key => $theme) {
    $studentCount = 0;
    $adviserCount = 0;
    $ojtCount = 0;

    foreach ($theme['domains'] as $domain) {
        $studentCount += (int) ($studentDomains[$domain] ?? 0);
        $adviserCount += (int) ($adviserDomains[$domain] ?? 0);
        $ojtCount += (int) ($ojtDomains[$domain] ?? 0);
    }

    $seedUniversities[] = [
        'key' => $key,
        'name' => $theme['name'],
        'abbr' => $theme['abbr'],
        'color' => $theme['color'],
        'gradA' => $theme['gradA'],
        'gradB' => $theme['gradB'],
        'gradC' => $theme['gradC'],
        'region' => $theme['region'],
        'students' => $studentCount,
        'advisers' => $adviserCount,
        'ojtsActive' => $ojtCount,
        'status' => ($studentCount + $adviserCount + $ojtCount) > 0 ? 'active' : 'inactive',
    ];
}

$activeUniversityCount = count(array_filter($seedUniversities, static function ($university) {
    return $university['status'] === 'active';
}));
$totalStudents = array_sum(array_column($seedUniversities, 'students'));
$totalAdvisers = array_sum(array_column($seedUniversities, 'advisers'));
$totalOjts = array_sum(array_column($seedUniversities, 'ojtsActive'));
$regions = array_values(array_unique(array_column($seedUniversities, 'region')));
sort($regions);
?>

<div class="admin-page univ-page" id="adminUniversitiesPage">
  <section class="admin-card" style="margin-bottom:0">
    <div class="univ-page-head">
      <div>
        <div class="univ-page-title">University Management</div>
        <div class="univ-page-copy">Manage registered universities and their branding the same way as the reference admin view.</div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <span class="admin-badge-subtle"><i class="fas fa-university"></i> <?= number_format($activeUniversityCount) ?> active campuses</span>
        <button type="button" class="btn btn-primary" style="font-size:.82rem" onclick="openUniversityModal()"><i class="fas fa-plus"></i> Add University</button>
      </div>
    </div>
  </section>

  <section class="admin-stat-grid">
    <article class="admin-stat-card" style="--admin-accent:#050505;--admin-accent-soft:rgba(17,24,39,0.08)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Tracked Universities</div>
        <div class="admin-stat-icon"><i class="fas fa-university"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format(count($seedUniversities)) ?></div>
      <div class="admin-stat-note">Preset registry modeled from the reference design, with live campus counts layered in.</div>
    </article>
    <article class="admin-stat-card" style="--admin-accent:#12b3ac;--admin-accent-soft:rgba(6,182,212,0.1)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Linked Students</div>
        <div class="admin-stat-icon"><i class="fas fa-user-graduate"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($totalStudents) ?></div>
      <div class="admin-stat-note">Students grouped by university email domains currently stored in the system.</div>
    </article>
    <article class="admin-stat-card" style="--admin-accent:#12b3ac;--admin-accent-soft:rgba(15,103,101,0.12)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Linked Advisers</div>
        <div class="admin-stat-icon"><i class="fas fa-chalkboard-user"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($totalAdvisers) ?></div>
      <div class="admin-stat-note">Advisers are inferred from the existing internship adviser table domains.</div>
    </article>
    <article class="admin-stat-card" style="--admin-accent:#12b3ac;--admin-accent-soft:rgba(16,185,129,0.1)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Active OJT</div>
        <div class="admin-stat-icon"><i class="fas fa-briefcase"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($totalOjts) ?></div>
      <div class="admin-stat-note">Only ongoing OJT records are included in these university rollups.</div>
    </article>
  </section>

  <section class="univ-filter-row">
    <label class="univ-search">
      <i class="fas fa-search"></i>
      <input type="text" id="univSearchInput" placeholder="Search universities...">
    </label>
    <select id="univRegionFilter" class="univ-select">
      <option value="">All Regions</option>
      <?php foreach ($regions as $region): ?>
      <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
      <?php endforeach; ?>
    </select>
  </section>

  <section>
    <div class="univ-grid" id="univCardsGrid"></div>
    <div class="univ-empty" id="univEmptyState" style="display:none">
      <i class="fas fa-university"></i>
      <div style="font-weight:700;color:#111">No universities match the current filter.</div>
      <div style="margin-top:6px">Try another search term or clear the selected region.</div>
    </div>
  </section>
</div>

<div class="modal-overlay" id="univModal" onclick="if (event.target === this) closeUniversityModal();" style="z-index:2000;display:none">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title" id="univModalTitle">Add / Edit University</div>
      <button class="modal-close" type="button" onclick="closeUniversityModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">University Name</label>
      <input class="form-input" id="univModalName" type="text" placeholder="e.g. Batangas State University">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Short Name / Acronym</label>
        <input class="form-input" id="univModalAbbr" type="text" placeholder="e.g. BatStateU">
      </div>
      <div class="form-group">
        <label class="form-label">Region</label>
        <select class="form-input" id="univModalRegion">
          <?php foreach ($regions as $region): ?>
          <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
          <?php endforeach; ?>
          <option value="Region V">Region V</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Admin Email</label>
      <input class="form-input" id="univModalEmail" type="email" placeholder="admin@university.edu.ph">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Primary Color <span style="font-size:.75rem;color:var(--text3)">(University Brand)</span></label>
        <div style="display:flex;gap:10px;align-items:center">
          <input type="color" id="univModalColor" value="#D10000" style="width:52px;height:40px;border-radius:8px;border:1.5px solid var(--border);cursor:pointer">
          <input class="form-input" id="univModalColorHex" value="#D10000" placeholder="#D10000">
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <button type="button" onclick="setUniversityColor('#D10000')" style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#12b3ac,#12b3ac);border:2px solid #fff;box-shadow:0 0 0 2px #D10000;cursor:pointer" title="BatStateU Red"></button>
          <button type="button" onclick="setUniversityColor('#12b3ac')" style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#12b3ac,#12b3ac);border:2px solid #fff;box-shadow:0 0 0 2px #12b3ac;cursor:pointer" title="PUP Blue"></button>
          <button type="button" onclick="setUniversityColor('#15803D')" style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#14532D,#22C55E);border:2px solid #fff;box-shadow:0 0 0 2px #15803D;cursor:pointer" title="UPLB Green"></button>
          <button type="button" onclick="setUniversityColor('#7C2D12')" style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#7C2D12,#EA580C);border:2px solid #fff;box-shadow:0 0 0 2px #7C2D12;cursor:pointer" title="TIP Orange"></button>
          <button type="button" onclick="setUniversityColor('#065F46')" style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#064E3B,#12b3ac);border:2px solid #fff;box-shadow:0 0 0 2px #065F46;cursor:pointer" title="DLSU Green"></button>
          <button type="button" onclick="setUniversityColor('#6B21A8')" style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#581C87,#12b3ac);border:2px solid #fff;box-shadow:0 0 0 2px #6B21A8;cursor:pointer" title="PNU Purple"></button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Live Preview</label>
        <div class="univ-mini-preview">
          <div class="univ-mini-header">
            <div class="univ-mini-logo" id="prevLogo">UNI</div>
            <div class="univ-mini-name" id="prevName">University</div>
          </div>
          <div class="univ-mini-body">
            <div class="univ-mini-side" id="prevSidebarMini">
              <span></span><span></span><span></span><span></span>
            </div>
            <div class="univ-mini-main">
              <div class="univ-mini-bar"></div>
              <div class="univ-mini-line" style="width:88%"></div>
              <div class="univ-mini-actions">
                <span class="primary">Primary</span>
                <span class="tag">Active</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="button" class="btn btn-ghost" style="flex:1;justify-content:center" onclick="closeUniversityModal()">Cancel</button>
      <button type="button" class="btn btn-primary" style="flex:1;justify-content:center" onclick="saveUniversity()"><i class="fas fa-save"></i> Save University</button>
    </div>
  </div>
</div>

<div class="univ-toast-stack" id="univToastStack"></div>

<script>
const universityStorageKey = 'skillhive.admin.universities';
const universitySeedData = <?= json_encode($seedUniversities, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const universityRegions = <?= json_encode($regions, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let universityEditingKey = null;

function cloneUniversitySeed() {
  return universitySeedData.map(function (item) {
    return Object.assign({}, item);
  });
}

function loadUniversityRegistry() {
  const base = cloneUniversitySeed();
  const raw = localStorage.getItem(universityStorageKey);
  if (!raw) {
    return base;
  }

  try {
    const saved = JSON.parse(raw);
    const savedMap = new Map(saved.map(function (item) { return [item.key, item]; }));
    const merged = base.map(function (item) {
      const override = savedMap.get(item.key) || {};
      return Object.assign({}, item, override, {
        students: item.students,
        advisers: item.advisers,
        ojtsActive: item.ojtsActive,
      });
    });
    saved.forEach(function (item) {
      if (!merged.some(function (existing) { return existing.key === item.key; })) {
        merged.push(item);
      }
    });
    return merged;
  } catch (error) {
    return base;
  }
}

let universityRegistry = loadUniversityRegistry();

function persistUniversityRegistry() {
  localStorage.setItem(universityStorageKey, JSON.stringify(universityRegistry));
}

function escapeHtml(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function darkenColor(hex, amount) {
  const value = hex.replace('#', '');
  const red = Math.max(0, parseInt(value.substring(0, 2), 16) - amount);
  const green = Math.max(0, parseInt(value.substring(2, 4), 16) - amount);
  const blue = Math.max(0, parseInt(value.substring(4, 6), 16) - amount);
  return '#' + [red, green, blue].map(function (channel) { return channel.toString(16).padStart(2, '0'); }).join('');
}

function lightenColor(hex, amount) {
  const value = hex.replace('#', '');
  const red = Math.min(255, parseInt(value.substring(0, 2), 16) + amount);
  const green = Math.min(255, parseInt(value.substring(2, 4), 16) + amount);
  const blue = Math.min(255, parseInt(value.substring(4, 6), 16) + amount);
  return '#' + [red, green, blue].map(function (channel) { return channel.toString(16).padStart(2, '0'); }).join('');
}

function syncPreviewVariables(color) {
  const page = document.getElementById('adminUniversitiesPage');
  page.style.setProperty('--univ-preview-a', darkenColor(color, 24));
  page.style.setProperty('--univ-preview-b', color);
  page.style.setProperty('--univ-preview-c', lightenColor(color, 96));
  document.getElementById('prevSidebarMini').style.background = 'linear-gradient(160deg,' + darkenColor(color, 90) + ',' + darkenColor(color, 48) + ',' + darkenColor(color, 90) + ')';
}

function updateModalPreview() {
  const color = document.getElementById('univModalColor').value || '#D10000';
  const abbr = (document.getElementById('univModalAbbr').value || 'UNI').substring(0, 4).toUpperCase();
  const name = document.getElementById('univModalAbbr').value || document.getElementById('univModalName').value || 'University';
  document.getElementById('prevLogo').textContent = abbr;
  document.getElementById('prevName').textContent = name;
  syncPreviewVariables(color);
}

function showUniversityToast(message, type) {
  const icons = { success: 'fa-check-circle', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
  const stack = document.getElementById('univToastStack');
  const toast = document.createElement('div');
  toast.className = 'toast ' + (type === 'warning' ? 'warning' : 'success');
  toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + escapeHtml(message) + '</span>';
  stack.appendChild(toast);
  setTimeout(function () {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
  }, 2400);
  setTimeout(function () {
    toast.remove();
  }, 2800);
}

function renderUniversityCards() {
  const grid = document.getElementById('univCardsGrid');
  const empty = document.getElementById('univEmptyState');
  const search = document.getElementById('univSearchInput').value.trim().toLowerCase();
  const region = document.getElementById('univRegionFilter').value;
  const filtered = universityRegistry.filter(function (university) {
    const matchesSearch = !search || university.name.toLowerCase().includes(search) || university.abbr.toLowerCase().includes(search);
    const matchesRegion = !region || university.region === region;
    return matchesSearch && matchesRegion;
  });

  if (!filtered.length) {
    grid.innerHTML = '';
    empty.style.display = 'block';
    return;
  }

  empty.style.display = 'none';
  grid.innerHTML = filtered.map(function (university) {
    const mark = university.abbr.substring(0, 4).toUpperCase();
    const active = university.status !== 'inactive';
    return '<article class="univ-card" style="--univ-color:' + escapeHtml(university.color) + ';--univ-a:' + escapeHtml(university.gradA || darkenColor(university.color, 24)) + ';--univ-b:' + escapeHtml(university.gradB || university.color) + ';--univ-c:' + escapeHtml(university.gradC || lightenColor(university.color, 96)) + '">' +
      '<div class="univ-card-cover">' +
        '<div class="univ-card-top">' +
          '<div style="display:flex;align-items:center;gap:10px;min-width:0">' +
            '<div class="univ-mark">' + escapeHtml(mark) + '</div>' +
            '<div class="univ-cover-meta">' +
              '<div class="univ-cover-abbr">' + escapeHtml(university.abbr) + '</div>' +
              '<div class="univ-cover-region">' + escapeHtml(university.region) + '</div>' +
            '</div>' +
          '</div>' +
          '<span class="univ-status">' + (active ? '&#10003; Active' : 'Inactive') + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="univ-card-body">' +
        '<div class="univ-name">' + escapeHtml(university.name) + '</div>' +
        '<div class="univ-region-copy">' + escapeHtml(university.region) + '</div>' +
        '<div class="univ-stat-row">' +
          '<div class="univ-stat-box"><div class="univ-stat-value">' + Number(university.students || 0).toLocaleString() + '</div><div class="univ-stat-label">Students</div></div>' +
          '<div class="univ-stat-box"><div class="univ-stat-value">' + Number(university.advisers || 0).toLocaleString() + '</div><div class="univ-stat-label">Advisers</div></div>' +
          '<div class="univ-stat-box"><div class="univ-stat-value">' + Number(university.ojtsActive || 0).toLocaleString() + '</div><div class="univ-stat-label">OJTs</div></div>' +
        '</div>' +
        '<div class="univ-card-actions">' +
          '<button type="button" class="btn btn-primary grow" style="font-size:.74rem" onclick="previewUniversityTheme(\'' + escapeHtml(university.key) + '\')"><i class="fas fa-palette"></i> Preview</button>' +
          '<button type="button" class="btn btn-ghost" style="font-size:.74rem" onclick="openUniversityModal(\'' + escapeHtml(university.key) + '\')"><i class="fas fa-edit"></i></button>' +
          '<button type="button" class="btn btn-ghost" style="font-size:.74rem;color:' + (active ? 'var(--danger)' : 'var(--accent2)') + ';border-color:' + (active ? 'var(--danger)' : 'var(--accent2)') + '" onclick="toggleUniversityStatus(\'' + escapeHtml(university.key) + '\')"><i class="fas ' + (active ? 'fa-ban' : 'fa-check') + '"></i></button>' +
        '</div>' +
      '</div>' +
    '</article>';
  }).join('');
}

function previewUniversityTheme(key) {
  const university = universityRegistry.find(function (item) { return item.key === key; });
  if (!university) {
    return;
  }
  syncPreviewVariables(university.color);
  showUniversityToast('Previewing ' + university.abbr + ' branding.', 'info');
}

function openUniversityModal(key) {
  universityEditingKey = key || null;
  const modal = document.getElementById('univModal');
  const university = universityRegistry.find(function (item) { return item.key === key; });
  document.getElementById('univModalTitle').textContent = key ? 'Add / Edit University' : 'Add / Edit University';

  document.getElementById('univModalName').value = university ? university.name : '';
  document.getElementById('univModalAbbr').value = university ? university.abbr : '';
  document.getElementById('univModalRegion').value = university ? university.region : (universityRegions[0] || 'NCR');
  document.getElementById('univModalEmail').value = university && university.adminEmail ? university.adminEmail : '';
  document.getElementById('univModalColor').value = university ? university.color : '#D10000';
  document.getElementById('univModalColorHex').value = university ? university.color : '#D10000';
  updateModalPreview();
  modal.style.display = 'flex';
}

function closeUniversityModal() {
  document.getElementById('univModal').style.display = 'none';
}

function setUniversityColor(color) {
  document.getElementById('univModalColor').value = color;
  document.getElementById('univModalColorHex').value = color;
  updateModalPreview();
}

function toggleUniversityStatus(key) {
  universityRegistry = universityRegistry.map(function (item) {
    if (item.key !== key) {
      return item;
    }
    return Object.assign({}, item, { status: item.status === 'inactive' ? 'active' : 'inactive' });
  });
  persistUniversityRegistry();
  renderUniversityCards();
  const updated = universityRegistry.find(function (item) { return item.key === key; });
  showUniversityToast(updated.abbr + ' is now ' + updated.status + '.', updated.status === 'inactive' ? 'warning' : 'success');
}

function saveUniversity() {
  const name = document.getElementById('univModalName').value.trim();
  const abbr = document.getElementById('univModalAbbr').value.trim();
  const region = document.getElementById('univModalRegion').value;
  const email = document.getElementById('univModalEmail').value.trim();
  const color = document.getElementById('univModalColor').value;

  if (!name || !abbr) {
    showUniversityToast('Please fill in the university name and acronym.', 'warning');
    return;
  }

  const key = (universityEditingKey || abbr.toLowerCase().replace(/[^a-z0-9]+/g, '')) || 'university';
  const existing = universityRegistry.find(function (item) { return item.key === key; });
  const entry = Object.assign({}, existing || {
    students: 0,
    advisers: 0,
    ojtsActive: 0,
    status: 'active',
  }, {
    key: key,
    name: name,
    abbr: abbr,
    region: region,
    adminEmail: email,
    color: color,
    gradA: darkenColor(color, 24),
    gradB: color,
    gradC: lightenColor(color, 96),
  });

  if (existing) {
    universityRegistry = universityRegistry.map(function (item) {
      return item.key === key ? entry : item;
    });
  } else {
    universityRegistry.push(entry);
  }

  persistUniversityRegistry();
  renderUniversityCards();
  closeUniversityModal();
  showUniversityToast(abbr + ' saved successfully.', 'success');
}

document.getElementById('univSearchInput').addEventListener('input', renderUniversityCards);
document.getElementById('univRegionFilter').addEventListener('change', renderUniversityCards);
document.getElementById('univModalColor').addEventListener('input', function () {
  document.getElementById('univModalColorHex').value = this.value;
  updateModalPreview();
});
document.getElementById('univModalColorHex').addEventListener('input', function () {
  if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
    document.getElementById('univModalColor').value = this.value;
    updateModalPreview();
  }
});
document.getElementById('univModalAbbr').addEventListener('input', updateModalPreview);
document.getElementById('univModalName').addEventListener('input', updateModalPreview);

renderUniversityCards();
updateModalPreview();
</script>