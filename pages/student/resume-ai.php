<?php
$resumeAiErrors = [];
$resumeAiSuccess = '';
$resumeFile = '';
$resumePath = '';
$storedReadiness = null;

if (isset($pdo, $userId)) {
    $stmt = $pdo->prepare('SELECT resume_file, internship_readiness_score FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([(int) $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumeFile = trim((string) ($row['resume_file'] ?? ''));
    $storedReadiness = isset($row['internship_readiness_score']) ? (float) $row['internship_readiness_score'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'upload_resume_ai') {
    $uploadDir = __DIR__ . '/../../assets/backend/uploads/resumes';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['resume'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $resumeAiErrors[] = 'Please choose a resume file to upload.';
    } elseif ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $resumeAiErrors[] = 'Resume upload failed. Please try again.';
    } else {
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            $resumeAiErrors[] = 'Only PDF, DOC, and DOCX files are allowed.';
        }

        if ((int) ($file['size'] ?? 0) > (5 * 1024 * 1024)) {
            $resumeAiErrors[] = 'Resume must be 5MB or smaller.';
        }

        if (!$resumeAiErrors) {
            $filename = 'resume_ai_' . (int) $userId . '_' . time() . '.' . $ext;
            $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                $resumeAiErrors[] = 'Unable to save uploaded resume.';
            } else {
                $stmt = $pdo->prepare('UPDATE student SET resume_file = ?, updated_at = NOW() WHERE student_id = ?');
                $stmt->execute([$filename, (int) $userId]);
                $resumeFile = $filename;
                $resumeAiSuccess = 'Resume uploaded successfully. Your insights are refreshed.';
            }
        }
    }
}

if ($resumeFile !== '') {
    $resumePath = $baseUrl . '/assets/backend/uploads/resumes/' . rawurlencode($resumeFile);
}

$hasResume = $resumeFile !== '';
$score = $hasResume ? 84 : 54;
if ($storedReadiness !== null) {
    $score = max(40, min(98, (int) round($storedReadiness)));
}
$circumference = 314;
$dashOffset = (int) round($circumference - (($score / 100) * $circumference));

$contentScore = min(99, $score + 6);
$formatScore = min(99, $score + 2);
$keywordScore = max(35, $score - 3);
$impactScore = max(30, $score - 8);

$suggestions = $hasResume
    ? [
        ['type' => 'good', 'title' => 'Strong Resume Foundation', 'text' => 'Your uploaded resume is readable and has a clear structure.'],
        ['type' => 'warn', 'title' => 'Add Quantified Outcomes', 'text' => 'Use impact metrics, e.g. "Reduced load time by 30%".'],
        ['type' => 'warn', 'title' => 'Improve Skills Grouping', 'text' => 'Group skills by category like Frontend, Backend, and Tools.'],
        ['type' => 'risk', 'title' => 'Expand ATS Keywords', 'text' => 'Add role-specific keywords such as Agile, Git, REST API, and testing.'],
      ]
    : [
        ['type' => 'warn', 'title' => 'Upload Your Resume First', 'text' => 'Upload a file to generate a more accurate AI review.'],
        ['type' => 'warn', 'title' => 'Prepare Result-Oriented Bullets', 'text' => 'Highlight measurable achievements, not only responsibilities.'],
        ['type' => 'warn', 'title' => 'Optimize For ATS', 'text' => 'Use exact terms from internship job descriptions.'],
      ];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Resume AI</h2>
    <p class="page-subtitle">Get your resume scored and improved with AI suggestions.</p>
  </div>
</div>

<?php if ($resumeAiErrors): ?>
  <div class="error-msg" style="margin-bottom:12px;"><?php echo htmlspecialchars(implode(' ', $resumeAiErrors)); ?></div>
<?php elseif ($resumeAiSuccess !== ''): ?>
  <div class="success-banner" style="margin-bottom:12px;"><?php echo htmlspecialchars($resumeAiSuccess); ?></div>
<?php endif; ?>

<div class="feed-layout">
  <div class="feed-main">
    <div class="panel-card">
      <div class="panel-card-header"><h3>Upload Resume</h3></div>
      <form method="post" enctype="multipart/form-data" id="resumeAiUploadForm">
        <input type="hidden" name="action" value="upload_resume_ai">
        <input type="file" name="resume" id="resumeAiFileInput" accept=".pdf,.doc,.docx" hidden>
        <div class="upload-zone" id="uploadZone" style="cursor:pointer;">
          <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:#ccc;margin-bottom:12px"></i>
          <div style="font-weight:600;font-size:.95rem;margin-bottom:4px">Drop your resume here</div>
          <div style="font-size:.82rem;color:#999;margin-bottom:12px">Supports PDF, DOC, DOCX (max 5MB)</div>
          <?php if ($hasResume): ?>
            <div style="font-size:.8rem;color:#374151;margin-bottom:12px;">Current: <?php echo htmlspecialchars($resumeFile); ?></div>
            <a href="<?php echo htmlspecialchars($resumePath); ?>" target="_blank" style="display:inline-block;margin-bottom:12px;font-size:.8rem;">View Current Resume</a><br>
          <?php endif; ?>
          <button type="button" class="btn btn-primary btn-sm" id="resumeAiBrowseBtn">Browse Files</button>
          <button type="submit" class="btn btn-ghost btn-sm" style="margin-left:8px;">Upload</button>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>AI Improvement Suggestions</h3></div>
      <div style="display:flex;flex-direction:column;gap:12px" id="resumeAiSuggestions">
        <?php foreach ($suggestions as $s): ?>
          <?php
            $bg = $s['type'] === 'good' ? '#f0fdf4' : ($s['type'] === 'risk' ? '#fef2f2' : '#fefce8');
            $border = $s['type'] === 'good' ? '#10B981' : ($s['type'] === 'risk' ? '#EF4444' : '#F59E0B');
            $icon = $s['type'] === 'good' ? 'check-circle' : ($s['type'] === 'risk' ? 'times-circle' : 'exclamation-triangle');
          ?>
          <div style="padding:14px;background:<?php echo $bg; ?>;border-radius:10px;border-left:3px solid <?php echo $border; ?>">
            <div style="font-weight:600;font-size:.85rem;color:<?php echo $border; ?>;margin-bottom:4px"><i class="fas fa-<?php echo $icon; ?>"></i> <?php echo htmlspecialchars($s['title']); ?></div>
            <div style="font-size:.82rem;color:#666"><?php echo htmlspecialchars($s['text']); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Mock Interview Bot</h3></div>
      <div class="chat-area" style="max-height:300px;overflow-y:auto" id="resumeAiChatArea">
        <div class="chat-msg bot">
          <div class="chat-bubble">Hi! I am your interview coach. Tell me the internship role you are applying for.</div>
        </div>
      </div>
      <div class="chat-input">
        <input type="text" id="resumeAiChatInput" placeholder="Type your answer...">
        <button class="btn btn-primary btn-sm" id="resumeAiChatSend" type="button"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <div class="panel-card" style="text-align:center">
      <div class="panel-card-header" style="justify-content:center"><h3>Resume Score</h3></div>
      <div class="score-gauge">
        <svg width="120" height="120">
          <circle cx="60" cy="60" r="50" stroke="#F0F0F0" stroke-width="8" fill="none"/>
          <circle id="resumeAiGauge" cx="60" cy="60" r="50" fill="none" stroke="#06B6D4" stroke-width="8" stroke-linecap="round" stroke-dasharray="314" stroke-dashoffset="<?php echo $dashOffset; ?>" transform="rotate(-90,60,60)"/>
        </svg>
        <div class="score-gauge-val" id="resumeAiScoreVal"><?php echo $score; ?></div>
      </div>
      <div style="font-size:.82rem;color:#999;margin-top:8px" id="resumeAiScoreLabel"><?php echo $score >= 80 ? 'Excellent' : ($score >= 65 ? 'Good progress' : 'Needs improvement'); ?> Resume Quality</div>
      <div style="margin-top:10px;font-size:.78rem;color:#10B981"><i class="fas fa-arrow-up"></i> Keep iterating after each upload</div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Breakdown</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div>
          <div class="skill-bar-header"><span>Content Quality</span><span id="resumeAiContentPct"><?php echo $contentScore; ?>%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" id="resumeAiContentBar" style="width:<?php echo $contentScore; ?>%;background:#10B981"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Formatting</span><span id="resumeAiFormattingPct"><?php echo $formatScore; ?>%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" id="resumeAiFormattingBar" style="width:<?php echo $formatScore; ?>%;background:#06B6D4"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Keywords / ATS</span><span id="resumeAiKeywordsPct"><?php echo $keywordScore; ?>%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" id="resumeAiKeywordsBar" style="width:<?php echo $keywordScore; ?>%;background:#F59E0B"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Impact Statements</span><span id="resumeAiImpactPct"><?php echo $impactScore; ?>%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" id="resumeAiImpactBar" style="width:<?php echo $impactScore; ?>%;background:#EF4444"></div></div>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Internship Readiness</h3></div>
      <div style="text-align:center">
        <div style="font-size:2rem;font-weight:800;color:#111" id="resumeAiReadiness"><?php echo $score; ?><span style="font-size:1rem;color:#999">/100</span></div>
        <div style="font-size:.78rem;color:#10B981;margin-top:4px" id="resumeAiReadinessLabel"><?php echo $score >= 75 ? 'Ready for applications!' : 'Improve your resume for better matching.'; ?></div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  function escapeHtml(text) {
    return String(text || '').replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
    });
  }

  function renderSuggestions(items) {
    var root = document.getElementById('resumeAiSuggestions');
    if (!root) return;
    if (!Array.isArray(items) || !items.length) return;
    root.innerHTML = items.map(function (s) {
      var type = s.type || 'warn';
      var bg = type === 'good' ? '#f0fdf4' : (type === 'risk' ? '#fef2f2' : '#fefce8');
      var border = type === 'good' ? '#10B981' : (type === 'risk' ? '#EF4444' : '#F59E0B');
      var icon = type === 'good' ? 'check-circle' : (type === 'risk' ? 'times-circle' : 'exclamation-triangle');
      return '<div style="padding:14px;background:' + bg + ';border-radius:10px;border-left:3px solid ' + border + '">'
        + '<div style="font-weight:600;font-size:.85rem;color:' + border + ';margin-bottom:4px"><i class="fas fa-' + icon + '"></i> ' + escapeHtml(s.title || 'Suggestion') + '</div>'
        + '<div style="font-size:.82rem;color:#666">' + escapeHtml(s.text || '') + '</div>'
        + '</div>';
    }).join('');
  }

  function updateScorePanel(score, breakdown) {
    var s = Number(score || 0);
    if (!s) return;
    var dashOffset = Math.round(314 - ((s / 100) * 314));
    var gauge = document.getElementById('resumeAiGauge');
    var val = document.getElementById('resumeAiScoreVal');
    var lbl = document.getElementById('resumeAiScoreLabel');
    var readiness = document.getElementById('resumeAiReadiness');
    var readinessLbl = document.getElementById('resumeAiReadinessLabel');
    if (gauge) gauge.setAttribute('stroke-dashoffset', String(dashOffset));
    if (val) val.textContent = String(s);
    if (lbl) lbl.textContent = (s >= 80 ? 'Excellent' : (s >= 65 ? 'Good progress' : 'Needs improvement')) + ' Resume Quality';
    if (readiness) readiness.innerHTML = String(s) + '<span style="font-size:1rem;color:#999">/100</span>';
    if (readinessLbl) readinessLbl.textContent = s >= 75 ? 'Ready for applications!' : 'Improve your resume for better matching.';

    var map = [
      ['content', 'resumeAiContentPct', 'resumeAiContentBar'],
      ['formatting', 'resumeAiFormattingPct', 'resumeAiFormattingBar'],
      ['keywords', 'resumeAiKeywordsPct', 'resumeAiKeywordsBar'],
      ['impact', 'resumeAiImpactPct', 'resumeAiImpactBar']
    ];
    map.forEach(function (m) {
      var v = Math.max(0, Math.min(100, Number((breakdown || {})[m[0]] || 0)));
      var pct = document.getElementById(m[1]);
      var bar = document.getElementById(m[2]);
      if (pct) pct.textContent = v + '%';
      if (bar) bar.style.width = v + '%';
    });
  }

  async function fetchLiveAnalysis() {
    var fd = new FormData();
    fd.append('action', 'analyze');
    var res = await fetch('<?php echo $baseUrl; ?>/pages/student/resume_ai_endpoint.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });
    var data = await res.json();
    if (!data || !data.ok) return;
    updateScorePanel(data.score, data.breakdown);
    renderSuggestions(data.suggestions || []);
  }

  var zone = document.getElementById('uploadZone');
  var fileInput = document.getElementById('resumeAiFileInput');
  var browseBtn = document.getElementById('resumeAiBrowseBtn');
  var form = document.getElementById('resumeAiUploadForm');
  if (zone && fileInput && form) {
    zone.addEventListener('dragover', function (e) {
      e.preventDefault();
      zone.style.borderColor = '#06B6D4';
    });
    zone.addEventListener('dragleave', function () {
      zone.style.borderColor = '';
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.style.borderColor = '';
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
      }
    });
    zone.addEventListener('click', function (e) {
      if (e.target && e.target.tagName === 'BUTTON') return;
      fileInput.click();
    });
    if (browseBtn) {
      browseBtn.addEventListener('click', function () {
        fileInput.click();
      });
    }
  }

  var chatArea = document.getElementById('resumeAiChatArea');
  var chatInput = document.getElementById('resumeAiChatInput');
  var chatSend = document.getElementById('resumeAiChatSend');
  function addMsg(role, text) {
    if (!chatArea) return;
    var wrap = document.createElement('div');
    wrap.className = 'chat-msg ' + role;
    var bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    bubble.textContent = text;
    wrap.appendChild(bubble);
    chatArea.appendChild(wrap);
    chatArea.scrollTop = chatArea.scrollHeight;
  }
  async function requestChatReply(userText) {
    var fd = new FormData();
    fd.append('action', 'chat');
    fd.append('message', userText);
    var res = await fetch('<?php echo $baseUrl; ?>/pages/student/resume_ai_endpoint.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });
    var data = await res.json();
    if (!data || !data.ok) {
      return data && data.message ? data.message : 'Unable to generate response right now.';
    }
    return data.reply || 'No response.';
  }
  if (chatSend && chatInput) {
    chatSend.addEventListener('click', async function () {
      var text = chatInput.value.trim();
      if (!text) return;
      addMsg('user', text);
      chatInput.value = '';
      addMsg('bot', 'Thinking...');
      var lastBot = chatArea ? chatArea.querySelector('.chat-msg.bot:last-child .chat-bubble') : null;
      var reply = await requestChatReply(text);
      if (lastBot) {
        lastBot.textContent = reply;
      } else {
        addMsg('bot', reply);
      }
    });
    chatInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        chatSend.click();
      }
    });
  }

  fetchLiveAnalysis().catch(function () {});
})();
</script>