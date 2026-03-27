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
    'preferred_industry' => '',
    'linkedin_url' => '',
    'github_url' => '',
    'portfolio_url' => '',
];

if (isset($pdo) && $pdo instanceof PDO && $userId > 0) {
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, program, department, preferred_industry, linkedin_url, github_url, portfolio_url FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $student = array_merge($student, $row);
    }
}

$defaultName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
if ($defaultName === '') {
    $defaultName = (string) ($_SESSION['user_name'] ?? 'Student Name');
}

$defaultHeadlineParts = array_filter([
    trim((string) ($student['program'] ?? '')),
    trim((string) ($student['department'] ?? '')),
]);
$defaultHeadline = $defaultHeadlineParts ? implode(' - ', $defaultHeadlineParts) : 'Internship Candidate';

$defaultSummary = 'Motivated student seeking internship opportunities and focused on practical project impact.';
$defaultContact = trim((string) ($student['email'] ?? ($_SESSION['user_email'] ?? '')));
?>

<div class="page-header">
  <div>
    <h2 class="page-title">CV Builder</h2>
    <p class="page-subtitle">Build your CV from a link, your SkillHive profile, or from scratch.</p>
  </div>
</div>

<div class="feed-layout cvb-layout">
  <div class="feed-main">
    <div class="panel-card" id="cvSourceCard">
      <div class="panel-card-header">
        <h3>Choose CV Source</h3>
      </div>
      <form id="cvSourceForm" class="cvb-form" novalidate>
        <div class="cvb-radio-group">
          <label class="cvb-radio-item">
            <input type="radio" name="source_mode" value="link" checked>
            <span>Import using a link</span>
          </label>
          <label class="cvb-radio-item">
            <input type="radio" name="source_mode" value="profile">
            <span>Use my SkillHive profile</span>
          </label>
          <label class="cvb-radio-item">
            <input type="radio" name="source_mode" value="scratch">
            <span>Start from scratch</span>
          </label>
        </div>

        <div id="cvLinkFields">
          <div class="cvb-row">
            <div class="cvb-col">
              <label for="cvSourceType">Link type</label>
              <select id="cvSourceType" name="source_type" class="cvb-input">
                <option value="linkedin">LinkedIn</option>
                <option value="github">GitHub</option>
                <option value="portfolio">Portfolio</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="cvb-col cvb-col-wide">
              <label for="cvSourceUrl">Profile URL</label>
              <input id="cvSourceUrl" name="source_url" type="url" class="cvb-input" placeholder="https://www.linkedin.com/in/your-name">
            </div>
          </div>
          <div class="cvb-hint">Choose which link to use, then paste the URL to generate your CV.</div>
        </div>

        <div class="cvb-actions">
          <button type="submit" class="btn btn-primary btn-sm" id="cvBuildBtn">Build CV</button>
          <button type="button" class="btn btn-ghost btn-sm" id="cvLoadBtn">Load Saved CV</button>
        </div>
      </form>
      <div id="cvBuilderMessage" class="cvb-message" aria-live="polite"></div>
    </div>

    <div class="panel-card" id="cvBuilderCard" hidden>
      <div class="panel-card-header">
        <h3>CV Preview</h3>
        <div style="display:flex;gap:8px">
          <button type="button" class="btn btn-ghost btn-sm" id="cvSavePdfBtn"><i class="fas fa-file-pdf"></i> Save PDF</button>
          <button type="button" class="btn btn-primary btn-sm" id="cvSaveBtn">Save CV</button>
          <button type="button" class="btn btn-ghost btn-sm" id="cvAddExperienceBtn">Add Experience</button>
        </div>
      </div>

      <div class="cvb-paper" id="cvPaper">
        <div class="cvb-header" id="cvHeaderBlock"></div>
        <div class="cvb-sections" id="cvSections"></div>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <div class="panel-card" id="cvScoreCard">
      <div class="panel-card-header"><h3>Resume Score</h3></div>
      <div class="cvb-score-wrap">
        <div class="cvb-score-ring">
          <svg width="112" height="112" viewBox="0 0 112 112" aria-hidden="true">
            <circle cx="56" cy="56" r="48" stroke="#e2e8f0" stroke-width="8" fill="none"></circle>
            <circle id="cvScoreGauge" cx="56" cy="56" r="48" stroke="#0ea5e9" stroke-width="8" fill="none" stroke-linecap="round" stroke-dasharray="302" stroke-dashoffset="302" transform="rotate(-90 56 56)"></circle>
          </svg>
          <div class="cvb-score-value" id="cvScoreValue">0</div>
        </div>
        <div class="cvb-score-label" id="cvScoreLabel">No score yet</div>
      </div>
      <div class="cvb-breakdown" id="cvBreakdown">
        <div class="cvb-break-row">
          <span>Content</span>
          <strong id="cvScoreContent">0%</strong>
        </div>
        <div class="skill-bar-bg"><div class="skill-bar-fill" id="cvScoreContentBar" style="width:0%;background:#10b981"></div></div>

        <div class="cvb-break-row">
          <span>Formatting</span>
          <strong id="cvScoreFormatting">0%</strong>
        </div>
        <div class="skill-bar-bg"><div class="skill-bar-fill" id="cvScoreFormattingBar" style="width:0%;background:#06b6d4"></div></div>

        <div class="cvb-break-row">
          <span>Keywords</span>
          <strong id="cvScoreKeywords">0%</strong>
        </div>
        <div class="skill-bar-bg"><div class="skill-bar-fill" id="cvScoreKeywordsBar" style="width:0%;background:#f59e0b"></div></div>

        <div class="cvb-break-row">
          <span>Impact</span>
          <strong id="cvScoreImpact">0%</strong>
        </div>
        <div class="skill-bar-bg"><div class="skill-bar-fill" id="cvScoreImpactBar" style="width:0%;background:#ef4444"></div></div>
      </div>
      <div class="cvb-score-note" id="cvScoreNote">Build or load a CV to get a score.</div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Status</h3></div>
      <div class="mini-row"><span>Source mode</span><strong id="cvSourceModeStatus">-</strong></div>
      <div class="mini-row"><span>Last save</span><strong id="cvLastSaveStatus">Not saved</strong></div>
      <div class="mini-row"><span>Sections</span><strong id="cvSectionsStatus">0</strong></div>
      <div class="mini-row"><span>Experience entries</span><strong id="cvExperienceStatus">0</strong></div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Tips</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.82rem;color:#64748b">
        <div>Drag sections to reorder your CV layout.</div>
        <div>Drag entries inside Experience to change chronology.</div>
        <div>Save after edits so your CV stays in the database.</div>
      </div>
    </div>
  </div>
</div>

<style>
.cvb-layout .panel-card {
  overflow: visible;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  border: 1px solid #e6edf7;
}
.cvb-form { display:flex; flex-direction:column; gap:14px; }
.cvb-radio-group { display:flex; flex-direction:column; gap:8px; }
.cvb-radio-item { display:flex; align-items:center; gap:8px; font-size:.86rem; color:#334155; }
.cvb-radio-item input[type="radio"] { accent-color: #0ea5e9; }
.cvb-row { display:flex; gap:10px; flex-wrap:wrap; }
.cvb-col { flex:1; min-width:180px; display:flex; flex-direction:column; gap:6px; }
.cvb-col-wide { min-width:260px; }
.cvb-col label { font-size:.78rem; font-weight:600; color:#475569; }
.cvb-input {
  width:100%;
  border:1px solid #d1d5db;
  border-radius:10px;
  padding:10px 12px;
  font-size:.84rem;
  background:#fff;
  color:#1f2937;
}
.cvb-hint { font-size:.74rem; color:#64748b; }
.cvb-actions { display:flex; gap:8px; flex-wrap:wrap; }
.cvb-message { margin-top:10px; font-size:.82rem; color:#0f766e; min-height:20px; }

.cvb-paper {
  border:1px solid #cbd5e1;
  border-radius:2px;
  background: #fffefc;
  padding:28px;
  width:100%;
  max-width:794px;
  min-height:1123px;
  margin:0 auto;
  box-shadow:0 8px 24px rgba(15, 23, 42, .12);
}
.cvb-header {
  border-bottom:1px solid #e2e8f0;
  padding-bottom:10px;
  margin-bottom:12px;
}
.cvb-score-wrap { text-align:center; margin-bottom:10px; }
.cvb-score-ring { position:relative; width:112px; height:112px; margin:0 auto; }
.cvb-score-value {
  position:absolute;
  inset:0;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:800;
  font-size:1.4rem;
  color:#0f172a;
}
.cvb-score-label { margin-top:8px; font-size:.8rem; color:#64748b; }
.cvb-breakdown { display:flex; flex-direction:column; gap:5px; margin-top:12px; }
.cvb-break-row { display:flex; align-items:center; justify-content:space-between; font-size:.78rem; color:#334155; }
.cvb-score-note { margin-top:10px; font-size:.76rem; color:#64748b; }

#cvSourceCard {
  background: linear-gradient(180deg, #ffffff 0%, #f3faff 100%);
}

#cvScoreCard {
  background: linear-gradient(180deg, #f8fdff 0%, #eef9ff 100%);
}

.cvb-paper .cvb-section {
  background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
}

@media print {
  body * {
    visibility: hidden !important;
  }
  #cvBuilderCard,
  #cvBuilderCard * {
    visibility: visible !important;
  }
  #cvBuilderCard {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    box-shadow: none;
    border: none;
  }
  .panel-card-header,
  .cvb-drag-handle {
    display: none !important;
  }
  .cvb-paper {
    box-shadow: none !important;
    border: none !important;
    max-width: 100% !important;
    min-height: auto !important;
  }
}
.cvb-name { font-size:1.1rem; font-weight:700; color:#0f172a; }
.cvb-contact { font-size:.8rem; color:#334155; margin-top:4px; }
.cvb-summary { font-size:.82rem; color:#475569; margin-top:8px; line-height:1.45; }
.cvb-sections { display:flex; flex-direction:column; gap:10px; }
.cvb-section {
  border:1px solid #e2e8f0;
  border-radius:10px;
  padding:10px;
  background:#fff;
}
.cvb-section.dragging, .cvb-item.dragging { opacity:.45; }
.cvb-drop-over { outline:2px dashed #38bdf8; outline-offset:2px; }
.cvb-section-head {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:8px;
  margin-bottom:8px;
  cursor:grab;
}
.cvb-section-head h4 { margin:0; font-size:.95rem; color:#0f172a; }
.cvb-drag-handle {
  border:1px solid #d1d5db;
  border-radius:999px;
  width:28px;
  height:28px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  color:#64748b;
  background:#f8fafc;
}
.cvb-item-list { display:flex; flex-direction:column; gap:8px; }
.cvb-item {
  border:1px solid #e5e7eb;
  border-radius:8px;
  padding:8px;
  background:#fff;
}
.cvb-item-head {
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
}
.cvb-item-title { margin:0; font-size:.86rem; color:#16a34a; font-weight:700; }
.cvb-item-title.dark { color:#111827; }
.cvb-item-sub { margin:4px 0; font-size:.78rem; color:#4b5563; }
.cvb-item ul { margin:0; padding-left:18px; }
.cvb-item li { font-size:.78rem; color:#374151; margin-bottom:3px; }

@media (max-width: 992px) {
  .cvb-actions { width:100%; }
  .cvb-actions .btn { width:100%; justify-content:center; }
}
</style>

<script>
(function () {
  var endpoint = '<?php echo $baseUrl; ?>/pages/student/resume_ai_endpoint.php';

  function escapeHtml(text) {
    return String(text || '').replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
    });
  }

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

  loadSavedCv(false).catch(function () {
    setStateFromPayload(defaultState());
  });
})();
</script>
