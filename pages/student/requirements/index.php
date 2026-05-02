<?php
/**
 * pages/student/requirements/index.php
 * Student Requirements Submission Interface
 */
require_once __DIR__ . '/../../../backend/db_connect.php';

if (!isset($userId)) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
}
if (!isset($baseUrl)) {
    $baseUrl = '/SkillHive';
}

// ─── FETCH ALL REQUIREMENTS + STUDENT STATUS ─────────────────────────────────
$reqStmt = $pdo->prepare(
    'SELECT
        r.requirement_id,
        r.name,
        r.description,
        r.phase,
        r.is_mandatory,
        r.sort_order,
        sr.req_submission_id,
        sr.status,
        sr.file_path,
        sr.submitted_at,
        sr.reviewed_at,
        sr.notes AS adviser_notes,
        sr.deadline
     FROM requirement r
     LEFT JOIN student_requirement sr
       ON sr.requirement_id = r.requirement_id AND sr.student_id = ?
     WHERE r.applicable_to IN (\'Student\', \'Both\')
     ORDER BY r.sort_order ASC, r.requirement_id ASC'
);
$reqStmt->execute([$userId]);
$requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// ─── STATS ────────────────────────────────────────────────────────────────────
$totalCount     = count($requirements);
$pendingCount   = 0;
$submittedCount = 0;
$approvedCount  = 0;
$rejectedCount  = 0;

foreach ($requirements as $req) {
    $st = $req['status'] ?? null;
    if ($st === 'Approved') {
        $approvedCount++;
    } elseif ($st === 'Submitted') {
        $submittedCount++;
    } elseif ($st === 'Rejected') {
        $rejectedCount++;
    } else {
        $pendingCount++;
    }
}

$uploadUrl = $baseUrl . '/pages/student/requirements/requirements_upload.php';
$uploadBase = $baseUrl . '/assets/backend/uploads/';

// Group requirements by phase
$phases = [];
foreach ($requirements as $req) {
    $phases[$req['phase']][] = $req;
}

// Phase order
$phaseOrder = ['Pre-OJT', 'During OJT', 'Post-OJT'];
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h2 class="page-title">Requirements</h2>
        <p class="page-subtitle">Submit and track your OJT document requirements.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <div class="req-filter-wrap">
            <select id="reqFilterStatus" class="form-control" style="min-width:140px;font-size:.85rem;">
                <option value="all">All Requirements</option>
                <option value="Pending">Pending Only</option>
                <option value="Submitted">Submitted</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="stat-cards" style="margin-bottom:24px;">
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon" style="background:linear-gradient(135deg,#12b3ac,#0a8a85);display:flex;align-items:center;justify-content:center;border-radius:14px;width:52px;height:52px;">
            <i class="fas fa-clipboard-list" style="font-size:1.5rem;color:#fff;"></i>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-num-row">
                <div class="stat-card-num"><?php echo $totalCount; ?></div>
            </div>
            <div class="stat-card-label">Total Requirements</div>
        </div>
    </div>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;border-radius:14px;width:52px;height:52px;">
            <i class="fas fa-clock" style="font-size:1.5rem;color:#fff;"></i>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-num-row">
                <div class="stat-card-num"><?php echo $pendingCount; ?></div>
            </div>
            <div class="stat-card-label">Pending</div>
        </div>
    </div>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;border-radius:14px;width:52px;height:52px;">
            <i class="fas fa-paper-plane" style="font-size:1.5rem;color:#fff;"></i>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-num-row">
                <div class="stat-card-num"><?php echo $submittedCount; ?></div>
            </div>
            <div class="stat-card-label">Submitted</div>
        </div>
    </div>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon" style="background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;border-radius:14px;width:52px;height:52px;">
            <i class="fas fa-check-circle" style="font-size:1.5rem;color:#fff;"></i>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-num-row">
                <div class="stat-card-num"><?php echo $approvedCount; ?></div>
            </div>
            <div class="stat-card-label">Approved</div>
        </div>
    </div>
</div>

<?php if ($rejectedCount > 0): ?>
<div class="alert-banner" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-exclamation-circle" style="color:#ef4444;font-size:1.1rem;"></i>
    <span style="color:#ef4444;font-weight:600;"><?php echo $rejectedCount; ?> requirement<?php echo $rejectedCount > 1 ? 's' : ''; ?> rejected.</span>
    <span style="color:#999;font-size:.85rem;">Please re-upload the corrected files.</span>
</div>
<?php endif; ?>

<!-- Requirements by Phase -->
<?php foreach ($phaseOrder as $phase):
    if (!isset($phases[$phase])) continue;
    $phaseReqs = $phases[$phase];

    $phaseIcons = [
        'Pre-OJT'    => 'fas fa-file-alt',
        'During OJT' => 'fas fa-briefcase',
        'Post-OJT'   => 'fas fa-flag-checkered',
    ];
    $phaseColors = [
        'Pre-OJT'    => '#12b3ac',
        'During OJT' => '#6366f1',
        'Post-OJT'   => '#f59e0b',
    ];
    $phaseIcon  = $phaseIcons[$phase]  ?? 'fas fa-folder';
    $phaseColor = $phaseColors[$phase] ?? '#12b3ac';
?>
<div class="panel-card req-phase-section" data-phase="<?php echo htmlspecialchars($phase); ?>" style="margin-bottom:24px;">
    <div class="panel-card-header" style="padding-bottom:0;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="background:<?php echo $phaseColor; ?>22;border-radius:8px;padding:6px 10px;">
                <i class="<?php echo $phaseIcon; ?>" style="color:<?php echo $phaseColor; ?>;font-size:1rem;"></i>
            </span>
            <h3 style="margin:0;"><?php echo htmlspecialchars($phase); ?></h3>
        </div>
    </div>

    <!-- Requirements Table -->
    <div class="req-table-wrap" style="overflow-x:auto;margin-top:16px;">
        <table class="req-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid rgba(255,255,255,.06);">
                    <th style="text-align:left;padding:10px 16px;font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;width:35%;">Requirement</th>
                    <th style="text-align:left;padding:10px 16px;font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;width:20%;">Description</th>
                    <th style="text-align:center;padding:10px 16px;font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;width:12%;">Status</th>
                    <th style="text-align:center;padding:10px 16px;font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;width:13%;">Deadline</th>
                    <th style="text-align:right;padding:10px 16px;font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;width:20%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($phaseReqs as $req):
                    $status    = $req['status'] ?? 'Pending';
                    $filePath  = $req['file_path'] ?? null;
                    $reqId     = (int) $req['requirement_id'];
                    $deadline  = $req['deadline'] ?? null;
                    $notes     = $req['adviser_notes'] ?? null;
                    $mandatory = (bool) $req['is_mandatory'];

                    $statusClass = match($status) {
                        'Approved'  => 'status-accepted',
                        'Submitted' => 'status-interview',
                        'Rejected'  => 'status-rejected',
                        default     => 'status-pending',
                    };
                    $statusIcon = match($status) {
                        'Approved'  => 'fa-check-circle',
                        'Submitted' => 'fa-paper-plane',
                        'Rejected'  => 'fa-times-circle',
                        default     => 'fa-clock',
                    };

                    // Deadline warning
                    $deadlineWarning = '';
                    if ($deadline && $status !== 'Approved') {
                        $daysLeft = (int) ceil((strtotime($deadline) - time()) / 86400);
                        if ($daysLeft < 0) {
                            $deadlineWarning = 'overdue';
                        } elseif ($daysLeft <= 3) {
                            $deadlineWarning = 'soon';
                        }
                    }
                ?>
                <tr class="req-row"
                    data-status="<?php echo htmlspecialchars($status); ?>"
                    style="border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s;">
                    <!-- Name -->
                    <td style="padding:14px 16px;vertical-align:middle;">
                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <?php if ($mandatory): ?>
                            <span style="color:#ef4444;font-size:.7rem;margin-top:2px;" title="Required">*</span>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600;color:var(--text);font-size:.9rem;"><?php echo htmlspecialchars($req['name']); ?></div>
                                <?php if ($notes && $status === 'Rejected'): ?>
                                <div style="font-size:.75rem;color:#ef4444;margin-top:4px;"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($notes); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <!-- Description -->
                    <td style="padding:14px 16px;vertical-align:middle;">
                        <div style="font-size:.8rem;color:#888;line-height:1.4;"><?php echo htmlspecialchars($req['description'] ?? '—'); ?></div>
                    </td>
                    <!-- Status -->
                    <td style="padding:14px 16px;text-align:center;vertical-align:middle;">
                        <span class="status-pill <?php echo $statusClass; ?>" style="display:inline-flex;align-items:center;gap:5px;">
                            <i class="fas <?php echo $statusIcon; ?>" style="font-size:.7rem;"></i>
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </td>
                    <!-- Deadline -->
                    <td style="padding:14px 16px;text-align:center;vertical-align:middle;">
                        <?php if ($deadline): ?>
                        <span style="font-size:.8rem;
                            color:<?php echo $deadlineWarning === 'overdue' ? '#ef4444' : ($deadlineWarning === 'soon' ? '#f59e0b' : '#999'); ?>;
                            font-weight:<?php echo $deadlineWarning ? '700' : '400'; ?>;">
                            <?php if ($deadlineWarning === 'overdue'): ?>
                                <i class="fas fa-exclamation-triangle"></i>
                            <?php elseif ($deadlineWarning === 'soon'): ?>
                                <i class="fas fa-hourglass-half"></i>
                            <?php endif; ?>
                            <?php echo date('M j, Y', strtotime($deadline)); ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#555;font-size:.8rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Actions -->
                    <td style="padding:14px 16px;text-align:right;vertical-align:middle;">
                        <div style="display:flex;align-items:center;gap:8px;justify-content:flex-end;">
                            <?php if ($filePath): ?>
                            <a href="<?php echo htmlspecialchars($uploadBase . $filePath); ?>"
                               target="_blank"
                               class="btn btn-ghost btn-sm"
                               title="View submitted file"
                               style="font-size:.78rem;">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php endif; ?>

                            <?php if ($status !== 'Approved'): ?>
                            <button class="btn btn-primary btn-sm req-upload-btn"
                                    data-req-id="<?php echo $reqId; ?>"
                                    data-req-name="<?php echo htmlspecialchars($req['name'], ENT_QUOTES); ?>"
                                    style="font-size:.78rem;">
                                <i class="fas fa-upload"></i>
                                <?php echo $filePath ? 'Replace' : 'Upload'; ?>
                            </button>
                            <?php else: ?>
                            <span style="color:#10b981;font-size:.8rem;font-weight:600;">
                                <i class="fas fa-lock"></i> Approved
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Upload Modal -->
<div id="reqUploadModal" class="modal-overlay" aria-hidden="true" onclick="if(event.target===this){closeReqModal();}">
    <div class="modal" role="dialog" aria-modal="true" style="max-width:480px;width:95%;">
        <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:16px;margin-bottom:16px;">
            <h3 class="modal-title" id="reqModalTitle" style="margin:0;font-size:1rem;">Upload Requirement</h3>
            <button type="button" class="modal-close" onclick="closeReqModal()" aria-label="Close">&times;</button>
        </div>
        <div id="reqModalBody">
            <p id="reqModalDesc" style="color:#888;font-size:.85rem;margin-bottom:20px;"></p>

            <div id="reqDropZone" class="req-drop-zone" style="
                border:2px dashed rgba(18,179,172,.4);
                border-radius:12px;
                padding:32px 20px;
                text-align:center;
                cursor:pointer;
                transition:border-color .2s,background .2s;
                background:rgba(18,179,172,.03);
                margin-bottom:16px;">
                <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#12b3ac;margin-bottom:10px;display:block;"></i>
                <div style="color:#ccc;font-size:.9rem;margin-bottom:6px;">Drag & drop or click to select file</div>
                <div style="color:#666;font-size:.78rem;">PDF, Images, Word documents (max 10MB)</div>
            </div>

            <input type="file" id="reqFileInput" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" style="display:none;">
            <input type="hidden" id="reqModalReqId" value="">

            <div id="reqFilePreview" style="display:none;background:rgba(255,255,255,.04);border-radius:10px;padding:12px 16px;margin-bottom:16px;align-items:center;gap:12px;">
                <i class="fas fa-file" style="color:#12b3ac;font-size:1.2rem;"></i>
                <div style="flex:1;min-width:0;">
                    <div id="reqFileName" style="font-weight:600;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                    <div id="reqFileSize" style="color:#888;font-size:.75rem;"></div>
                </div>
                <button type="button" onclick="clearReqFile()" style="background:none;border:none;color:#888;cursor:pointer;padding:4px;"><i class="fas fa-times"></i></button>
            </div>

            <div id="reqUploadProgress" style="display:none;margin-bottom:16px;">
                <div style="background:rgba(255,255,255,.06);border-radius:50px;height:6px;overflow:hidden;">
                    <div id="reqProgressBar" style="height:100%;background:var(--primary);width:0%;transition:width .3s;border-radius:50px;"></div>
                </div>
                <div style="text-align:center;color:#888;font-size:.78rem;margin-top:6px;" id="reqProgressText">Uploading...</div>
            </div>

            <div id="reqUploadAlert" style="display:none;border-radius:8px;padding:10px 14px;font-size:.83rem;margin-bottom:14px;"></div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeReqModal()">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="reqSubmitBtn" onclick="submitReqFile()" disabled>
                    <i class="fas fa-upload"></i> Submit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- File Preview Modal -->
<div id="reqPreviewModal" class="modal-overlay" aria-hidden="true" onclick="if(event.target===this){closePreviewModal();}">
    <div class="modal" role="dialog" aria-modal="true" style="max-width:800px;width:95%;height:85vh;display:flex;flex-direction:column;">
        <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:16px;margin-bottom:0;flex-shrink:0;">
            <h3 class="modal-title" id="previewModalTitle" style="margin:0;font-size:1rem;">File Preview</h3>
            <button type="button" class="modal-close" onclick="closePreviewModal()" aria-label="Close">&times;</button>
        </div>
        <div id="previewContainer" style="flex:1;overflow:hidden;border-radius:0 0 12px 12px;background:#111;"></div>
    </div>
</div>

<style>
.req-drop-zone:hover, .req-drop-zone.dragover {
    border-color: #12b3ac !important;
    background: rgba(18,179,172,.07) !important;
}
.req-row:hover {
    background: rgba(255,255,255,.02) !important;
}
.req-table th, .req-table td {
    white-space: nowrap;
}
.req-table td:nth-child(2) {
    white-space: normal;
}
@media (max-width: 700px) {
    .req-table thead { display:none; }
    .req-table tr { display:block; border-bottom: 1px solid rgba(255,255,255,.06); padding: 12px 0; }
    .req-table td { display:block; padding: 6px 16px; text-align:left !important; }
    .req-table td:last-child > div { justify-content:flex-start !important; }
}
</style>

<script>
(function() {
    const uploadUrl = <?php echo json_encode($uploadUrl); ?>;
    let selectedFile = null;

    // ─── Filter ──────────────────────────────────────────────────────────────
    document.getElementById('reqFilterStatus').addEventListener('change', function() {
        const val = this.value;
        document.querySelectorAll('.req-row').forEach(function(row) {
            if (val === 'all') {
                row.style.display = '';
            } else {
                row.style.display = (row.dataset.status === val) ? '' : 'none';
            }
        });

        // Hide/show phase sections if all rows hidden
        document.querySelectorAll('.req-phase-section').forEach(function(section) {
            const visible = section.querySelectorAll('.req-row:not([style*="display: none"])');
            section.style.display = visible.length ? '' : 'none';
        });
    });

    // ─── Upload Button Click ──────────────────────────────────────────────────
    document.querySelectorAll('.req-upload-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openReqModal(this.dataset.reqId, this.dataset.reqName);
        });
    });

    // ─── Modal ────────────────────────────────────────────────────────────────
    window.openReqModal = function(reqId, reqName) {
        document.getElementById('reqModalReqId').value = reqId;
        document.getElementById('reqModalTitle').textContent = 'Upload: ' + reqName;
        document.getElementById('reqModalDesc').textContent = 'Submit your document for: ' + reqName;
        clearReqFile();
        hideAlert();
        document.getElementById('reqUploadModal').classList.add('open');
        document.getElementById('reqUploadModal').removeAttribute('aria-hidden');
    };

    window.closeReqModal = function() {
        document.getElementById('reqUploadModal').classList.remove('open');
        document.getElementById('reqUploadModal').setAttribute('aria-hidden', 'true');
    };

    window.closePreviewModal = function() {
        document.getElementById('reqPreviewModal').classList.remove('open');
        document.getElementById('reqPreviewModal').setAttribute('aria-hidden', 'true');
        document.getElementById('previewContainer').innerHTML = '';
    };

    // ─── Drag & Drop ─────────────────────────────────────────────────────────
    const dropZone = document.getElementById('reqDropZone');
    const fileInput = document.getElementById('reqFileInput');

    dropZone.addEventListener('click', function() { fileInput.click(); });

    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) handleFile(files[0]);
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) handleFile(this.files[0]);
    });

    function handleFile(file) {
        const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif',
                         'application/msword',
                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowed.includes(file.type) && !file.name.match(/\.(pdf|jpg|jpeg|png|gif|doc|docx)$/i)) {
            showAlert('error', 'Invalid file type. Please upload PDF, image, or Word document.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showAlert('error', 'File too large. Maximum size is 10MB.');
            return;
        }

        selectedFile = file;
        document.getElementById('reqFileName').textContent = file.name;
        document.getElementById('reqFileSize').textContent = formatBytes(file.size);
        document.getElementById('reqFilePreview').style.display = 'flex';
        document.getElementById('reqDropZone').style.display = 'none';
        document.getElementById('reqSubmitBtn').disabled = false;
        hideAlert();
    }

    window.clearReqFile = function() {
        selectedFile = null;
        fileInput.value = '';
        document.getElementById('reqFilePreview').style.display = 'none';
        document.getElementById('reqDropZone').style.display = 'block';
        document.getElementById('reqSubmitBtn').disabled = true;
    };

    // ─── Submit ───────────────────────────────────────────────────────────────
    window.submitReqFile = function() {
        if (!selectedFile) return;

        const reqId = document.getElementById('reqModalReqId').value;
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('requirement_id', reqId);
        formData.append('req_file', selectedFile);

        document.getElementById('reqSubmitBtn').disabled = true;
        document.getElementById('reqUploadProgress').style.display = 'block';
        document.getElementById('reqProgressBar').style.width = '0%';
        document.getElementById('reqProgressText').textContent = 'Uploading...';
        hideAlert();

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                document.getElementById('reqProgressBar').style.width = pct + '%';
                document.getElementById('reqProgressText').textContent = 'Uploading... ' + pct + '%';
            }
        });

        xhr.addEventListener('load', function() {
            document.getElementById('reqUploadProgress').style.display = 'none';
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.ok) {
                    showAlert('success', res.message || 'File submitted successfully!');
                    setTimeout(function() {
                        closeReqModal();
                        location.reload();
                    }, 1200);
                } else {
                    showAlert('error', res.error || 'Upload failed.');
                    document.getElementById('reqSubmitBtn').disabled = false;
                }
            } catch (e) {
                showAlert('error', 'Server error. Please try again.');
                document.getElementById('reqSubmitBtn').disabled = false;
            }
        });

        xhr.addEventListener('error', function() {
            document.getElementById('reqUploadProgress').style.display = 'none';
            showAlert('error', 'Network error. Please check your connection.');
            document.getElementById('reqSubmitBtn').disabled = false;
        });

        xhr.send(formData);
    };

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function showAlert(type, msg) {
        const el = document.getElementById('reqUploadAlert');
        el.style.display = 'block';
        if (type === 'success') {
            el.style.background = 'rgba(16,185,129,.1)';
            el.style.color = '#10b981';
            el.style.border = '1px solid rgba(16,185,129,.2)';
            el.innerHTML = '<i class="fas fa-check-circle"></i> ' + escHtml(msg);
        } else {
            el.style.background = 'rgba(239,68,68,.08)';
            el.style.color = '#ef4444';
            el.style.border = '1px solid rgba(239,68,68,.2)';
            el.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + escHtml(msg);
        }
    }

    function hideAlert() {
        document.getElementById('reqUploadAlert').style.display = 'none';
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Keyboard close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeReqModal();
            closePreviewModal();
        }
    });
})();
</script>
