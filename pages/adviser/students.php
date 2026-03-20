<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/students/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

$currentFilters = [
	'search' => trim((string)($_GET['search'] ?? '')),
	'department' => trim((string)($_GET['department'] ?? '')),
	'status' => trim((string)($_GET['status'] ?? '')),
];

$pageData = [
	'selected' => ['search' => '', 'department' => '', 'status' => ''],
	'filter_options' => ['departments' => [], 'statuses' => []],
	'rows' => [],
];

if ($adviserId > 0) {
	try {
		$pageData = getAdviserStudentsPageData($pdo, $adviserId, $currentFilters);
	} catch (Throwable $e) {
		$pageData = $pageData;
	}
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
?>

<div class="page-header">
	<div>
		<h2 class="page-title">My Students</h2>
		<p class="page-subtitle">Track intern placement, OJT progress, and requirements of your assigned students.</p>
	</div>
</div>

<form class="filter-row" method="get" action="<?php echo $baseUrl; ?>/layout.php">
	<input type="hidden" name="page" value="adviser/students">

	<div class="topbar-search" style="flex:1;max-width:280px">
		<i class="fas fa-search"></i>
		<input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_students_escape($selected['search'] ?? ''); ?>">
	</div>

	<select class="filter-select" name="department">
		<option value="">All Departments</option>
		<?php foreach (($filterOptions['departments'] ?? []) as $departmentOption): ?>
			<option value="<?php echo adviser_students_escape($departmentOption); ?>" <?php echo ($selected['department'] ?? '') === $departmentOption ? 'selected' : ''; ?>><?php echo adviser_students_escape($departmentOption); ?></option>
		<?php endforeach; ?>
	</select>

	<select class="filter-select" name="status">
		<option value="">All Status</option>
		<?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
			<option value="<?php echo adviser_students_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo adviser_students_escape($statusOption); ?></option>
		<?php endforeach; ?>
	</select>

	<button class="btn btn-ghost btn-sm" type="submit">Apply</button>
</form>

<div class="panel-card" style="min-height:520px;">
	<div class="panel-card-header"><h3>Assigned Students</h3></div>
	<div class="app-table-wrap" style="min-height:430px;">
		<table class="app-table">
			<thead>
				<tr>
					<th>Student</th>
					<th>Department</th>
					<th>Company / Status</th>
					<th>OJT Hours</th>
					<th>Requirements</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($rows)): ?>
					<?php foreach ($rows as $row): ?>
						<?php
						$studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
						$companyName = trim((string)($row['company_name'] ?? ''));
						$internshipTitle = trim((string)($row['internship_title'] ?? ''));
						$subtitle = trim((string)($row['program'] ?? '')) . ' • ' . ($companyName !== '' ? $companyName : 'No company assigned');
						$hoursCompleted = (float)($row['hours_completed'] ?? 0);
						$hoursRequired = (float)($row['hours_required'] ?? 0);
						$totalRequirements = (int)($row['total_requirements'] ?? 0);
						$internshipId = isset($row['internship_id']) ? (int)$row['internship_id'] : 0;
						?>
						<tr>
							<td>
								<div style="display:flex;align-items:center;gap:10px">
									<div class="topbar-avatar" style="width:32px;height:32px;font-size:.72rem"><?php echo adviser_students_escape($row['initials'] ?? 'NA'); ?></div>
									<div>
										<div style="font-weight:600;font-size:.86rem"><?php echo adviser_students_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></div>
										<div style="font-size:.74rem;color:#999"><?php echo adviser_students_escape((string)($row['year_level'] ?? 'N/A')); ?> · <?php echo adviser_students_escape((string)($row['program'] ?? 'N/A')); ?></div>
									</div>
								</div>
							</td>
							<td><span class="status-pill status-shortlisted"><?php echo adviser_students_escape((string)($row['department'] ?? 'Unassigned')); ?></span></td>
							<td>
								<div style="font-weight:600;font-size:.83rem"><?php echo adviser_students_escape($companyName !== '' ? $companyName : 'No company assigned'); ?></div>
								<div style="font-size:.74rem;color:#999"><?php echo adviser_students_escape($internshipTitle !== '' ? $internshipTitle : 'No internship title'); ?></div>
								<div style="margin-top:6px;"><span class="status-pill <?php echo adviser_students_escape((string)($row['status_class'] ?? 'status-pending')); ?>"><?php echo adviser_students_escape((string)($row['status_label'] ?? 'Pending')); ?></span></div>
							</td>
							<td>
								<div style="display:flex;align-items:center;gap:8px;min-width:140px;">
									<div class="progress-bar" style="flex:1"><div class="progress-fill" style="width:<?php echo (int)($row['progress_percent'] ?? 0); ?>%;background:#3B82F6"></div></div>
									<span style="font-size:.74rem;color:#666;"><?php echo (int)round($hoursCompleted); ?>/<?php echo (int)round($hoursRequired); ?></span>
								</div>
							</td>
							<td>
								<div style="display:flex;align-items:center;gap:8px;min-width:130px;">
									<div class="progress-bar" style="flex:1"><div class="progress-fill js-req-progress-fill" data-student-id="<?php echo (int)($row['student_id'] ?? 0); ?>" style="width:<?php echo (int)($row['requirements_completion'] ?? 0); ?>%;background:#EF4444"></div></div>
									<span class="js-req-progress-text" data-student-id="<?php echo (int)($row['student_id'] ?? 0); ?>" data-total="<?php echo $totalRequirements > 0 ? $totalRequirements : 0; ?>" style="font-size:.74rem;color:#EF4444;"><?php echo (int)($row['requirements_submitted'] ?? 0); ?>/<?php echo $totalRequirements > 0 ? $totalRequirements : 0; ?></span>
								</div>
							</td>
							<td>
								<div style="display:flex;gap:8px;flex-wrap:wrap;">
									<button
										class="btn btn-primary btn-sm js-open-requirements-btn"
										type="button"
										onclick="openRequirementsModal(this)"
										data-name="<?php echo adviser_students_escape($studentName !== '' ? $studentName : 'Student'); ?>"
										data-subtitle="<?php echo adviser_students_escape($subtitle); ?>"
										data-submitted="<?php echo (int)($row['requirements_submitted'] ?? 0); ?>"
										data-pending="<?php echo (int)($row['requirements_pending'] ?? 0); ?>"
										data-completion="<?php echo (int)($row['requirements_completion'] ?? 0); ?>"
										data-student-id="<?php echo (int)($row['student_id'] ?? 0); ?>"
										data-internship-id="<?php echo $internshipId > 0 ? $internshipId : ''; ?>"
									>
										Requirements
									</button>
									<?php if (!empty($row['email'])): ?>
										<a class="btn btn-ghost btn-sm" href="mailto:<?php echo adviser_students_escape((string)$row['email']); ?>"><i class="fas fa-envelope"></i></a>
									<?php else: ?>
										<button class="btn btn-ghost btn-sm" type="button" disabled><i class="fas fa-envelope"></i></button>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr>
						<td colspan="6" style="text-align:center;color:#9ca3af;padding:30px 12px;">No students found for the selected filters.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div id="requirementsModal" style="position:fixed;inset:0;background:rgba(0,0,0,.40);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:1200;padding:16px;">
	<div style="background:#fff;width:720px;max-width:100%;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.2);padding:24px;max-height:90vh;overflow:auto;">
		<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
			<div>
				<h2 id="requirementsTitle" style="font-size:1.05rem;font-weight:700;margin:0;">Juan dela Cruz – Requirements Checklist</h2>
				<p id="requirementsSubtitle" style="font-size:.82rem;color:#6b7280;margin:4px 0 0;">BSCS • Google Philippines</p>
			</div>
			<button type="button" onclick="closeRequirementsModal()" style="border:none;background:transparent;color:#9ca3af;font-size:1.2rem;cursor:pointer;line-height:1;">✕</button>
		</div>

		<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px;">
			<div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:14px;text-align:center;">
				<p id="requirementsSubmitted" style="font-size:1.5rem;font-weight:700;color:#4f46e5;margin:0;">5</p>
				<p style="font-size:.75rem;color:#6b7280;margin:4px 0 0;">Submitted</p>
			</div>
			<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px;text-align:center;">
				<p id="requirementsPending" style="font-size:1.5rem;font-weight:700;color:#f97316;margin:0;">9</p>
				<p style="font-size:.75rem;color:#6b7280;margin:4px 0 0;">Pending</p>
			</div>
			<div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:12px;padding:14px;text-align:center;">
				<p id="requirementsCompletion" style="font-size:1.5rem;font-weight:700;color:#16a34a;margin:0;">36%</p>
				<p style="font-size:.75rem;color:#6b7280;margin:4px 0 0;">Completion</p>
			</div>
		</div>

		<div style="width:100%;background:#e5e7eb;height:8px;border-radius:999px;margin-bottom:20px;overflow:hidden;">
			<div id="requirementsProgressBar" style="height:8px;border-radius:999px;background:linear-gradient(90deg,#6366f1,#2dd4bf);width:36%;"></div>
		</div>

		<div id="requirementsChecklist" style="display:flex;flex-direction:column;gap:10px;max-height:300px;overflow-y:auto;padding-right:4px;"></div>

		<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
			<button type="button" class="btn btn-ghost btn-sm" onclick="closeRequirementsModal()">Close</button>
			<button id="requirementsSaveBtn" type="button" class="btn btn-primary btn-sm" onclick="saveRequirementsChanges()"><i class="fas fa-save"></i> Save Changes</button>
		</div>
	</div>
</div>

<script>
var requirementsEndpoint = '<?php echo $baseUrl; ?>/pages/adviser/students/requirements_data.php';
var requirementsContext = {
	studentId: 0,
	internshipId: '',
	canEdit: false,
	activeButton: null
};

function escapeHtml(value) {
	return String(value == null ? '' : value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function renderRequirementsChecklist(phases) {
	var container = document.getElementById('requirementsChecklist');
	if (!container) return;

	var orderedPhases = ['Pre-OJT', 'During OJT', 'Post-OJT'];
	var html = '';

	if (!requirementsContext.canEdit) {
		html += '<div style="border:1px solid #fde68a;background:#fffbeb;border-radius:12px;padding:12px 14px;color:#92400e;font-size:.8rem;">This student has no internship context yet. Checklist is view-only for now.</div>';
	}

	orderedPhases.forEach(function (phaseName) {
		var phaseRows = (phases && phases[phaseName]) ? phases[phaseName] : [];
		html += '<p style="font-size:.72rem;font-weight:700;color:#9ca3af;margin:10px 0 0;letter-spacing:.06em;">' + escapeHtml(phaseName.toUpperCase()) + ' PHASE</p>';

		if (!phaseRows.length) {
			html += '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:12px 14px;color:#9ca3af;font-size:.8rem;">No requirements found.</div>';
			return;
		}

		phaseRows.forEach(function (item) {
			var isSubmitted = !!item.is_submitted;
			var boxBorder = isSubmitted ? '#bbf7d0' : '#e5e7eb';
			var boxBg = isSubmitted ? '#f0fdf4' : '#fff';
			var statusColor = isSubmitted ? '#16a34a' : '#ef4444';
			var statusText = item.status || (isSubmitted ? 'Submitted' : 'Pending');
			var dateText = item.date_label ? ('📅 ' + item.date_label) : statusText;
			var requirementId = Number(item.requirement_id || 0);

			html += '<div style="display:flex;align-items:center;justify-content:space-between;border:1px solid ' + boxBorder + ';background:' + boxBg + ';border-radius:12px;padding:12px 14px;">';
			html += '<div style="display:flex;align-items:center;gap:10px;">';
			html += '<input type="checkbox" class="js-requirement-checkbox" data-requirement-id="' + requirementId + '" ' + (isSubmitted ? 'checked' : '') + ' ' + (requirementsContext.canEdit ? '' : 'disabled') + ' style="width:18px;height:18px;' + (requirementsContext.canEdit ? 'cursor:pointer;' : 'cursor:not-allowed;') + (isSubmitted ? 'accent-color:#22c55e;' : '') + '">';
			html += '<p style="font-size:.85rem;margin:0;">' + escapeHtml(item.name || 'Requirement') + '</p>';
			html += '</div>';
			html += '<div style="display:flex;align-items:center;gap:8px;font-size:.72rem;">';
			html += '<span style="background:#e0e7ff;color:#4f46e5;padding:4px 8px;border-radius:999px;">' + escapeHtml(item.phase || phaseName) + '</span>';
			html += '<span style="color:' + statusColor + ';">' + escapeHtml(dateText) + '</span>';
			html += '</div>';
			html += '</div>';
		});
	});

	container.innerHTML = html;

	if (requirementsContext.canEdit) {
		var checkboxes = container.querySelectorAll('.js-requirement-checkbox');
		checkboxes.forEach(function (checkbox) {
			checkbox.addEventListener('change', function () {
				toggleRequirementCheckbox(checkbox);
			});
		});
	}
}

function setRequirementsSummary(summary) {
	var submittedEl = document.getElementById('requirementsSubmitted');
	var pendingEl = document.getElementById('requirementsPending');
	var completionEl = document.getElementById('requirementsCompletion');
	var progressBarEl = document.getElementById('requirementsProgressBar');

	var submittedValue = Number((summary && summary.submitted) || 0);
	var pendingValue = Number((summary && summary.pending) || 0);
	var completionValue = Number((summary && summary.completion) || 0);

	if (submittedEl) submittedEl.textContent = submittedValue;
	if (pendingEl) pendingEl.textContent = pendingValue;
	if (completionEl) completionEl.textContent = completionValue + '%';
	if (progressBarEl) progressBarEl.style.width = completionValue + '%';

	syncRequirementsRowSummary(submittedValue, completionValue);
}

function syncRequirementsRowSummary(submittedValue, completionValue) {
	var studentId = Number(requirementsContext.studentId || 0);
	if (studentId <= 0) return;

	var fillEl = document.querySelector('.js-req-progress-fill[data-student-id="' + studentId + '"]');
	if (fillEl) {
		fillEl.style.width = Number(completionValue || 0) + '%';
	}

	var textEl = document.querySelector('.js-req-progress-text[data-student-id="' + studentId + '"]');
	if (textEl) {
		var total = Number(textEl.getAttribute('data-total') || '0');
		textEl.textContent = Number(submittedValue || 0) + '/' + (total > 0 ? total : 0);
	}

	if (requirementsContext.activeButton) {
		requirementsContext.activeButton.setAttribute('data-submitted', String(Number(submittedValue || 0)));
		requirementsContext.activeButton.setAttribute('data-completion', String(Number(completionValue || 0)));
		var totalRequirements = textEl ? Number(textEl.getAttribute('data-total') || '0') : 0;
		var pendingValue = Math.max(0, totalRequirements - Number(submittedValue || 0));
		requirementsContext.activeButton.setAttribute('data-pending', String(pendingValue));
	}
}

function loadRequirementsData() {
	if (requirementsContext.studentId <= 0) {
		setRequirementsErrorState();
		return;
	}

	var query = '?student_id=' + encodeURIComponent(requirementsContext.studentId);
	if (requirementsContext.internshipId !== '') {
		query += '&internship_id=' + encodeURIComponent(requirementsContext.internshipId);
	}

	fetch(requirementsEndpoint + query, { credentials: 'same-origin' })
		.then(function (response) {
			return response.json().then(function (payload) {
				if (!response.ok || !payload || payload.success !== true) {
					throw new Error((payload && payload.message) ? payload.message : 'Failed request');
				}
				return payload;
			});
		})
		.then(function (payload) {
			requirementsContext.canEdit = !!payload.can_edit;
			if (!requirementsContext.internshipId && payload.internship_id_context) {
				requirementsContext.internshipId = String(payload.internship_id_context);
			}
			setRequirementsSummary(payload.summary || {});
			renderRequirementsChecklist(payload.phases || {});
		})
		.catch(function (error) {
			setRequirementsErrorStateWithMessage(error && error.message ? error.message : 'Unable to load requirements right now.');
		});
}

function toggleRequirementCheckbox(checkbox) {
	var requirementId = Number(checkbox.getAttribute('data-requirement-id') || '0');
	if (requirementsContext.studentId <= 0 || requirementId <= 0) {
		checkbox.checked = !checkbox.checked;
		return;
	}

	checkbox.disabled = true;

	var formBody = new URLSearchParams();
	formBody.append('action', 'toggle_requirement');
	formBody.append('student_id', String(requirementsContext.studentId));
	formBody.append('internship_id', requirementsContext.internshipId || '');
	formBody.append('requirement_id', String(requirementId));
	formBody.append('is_checked', checkbox.checked ? '1' : '0');

	fetch(requirementsEndpoint, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		credentials: 'same-origin',
		body: formBody.toString()
	})
		.then(function (response) {
			return response.json().then(function (payload) {
				if (!response.ok || !payload || payload.success !== true) {
					throw new Error((payload && payload.message) ? payload.message : 'Failed update');
				}
				return payload;
			});
		})
		.then(function (payload) {
			requirementsContext.canEdit = !!payload.can_edit;
			if (!requirementsContext.internshipId && payload.internship_id_context) {
				requirementsContext.internshipId = String(payload.internship_id_context);
			}
			setRequirementsSummary(payload.summary || {});
			renderRequirementsChecklist(payload.phases || {});
		})
		.catch(function (error) {
			checkbox.checked = !checkbox.checked;
			checkbox.disabled = false;
			setRequirementsErrorStateWithMessage(error && error.message ? error.message : 'Unable to update requirement status right now.');
		});
}

function setRequirementsLoadingState() {
	var container = document.getElementById('requirementsChecklist');
	if (!container) return;
	container.innerHTML = '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:14px;color:#6b7280;font-size:.82rem;">Loading requirements...</div>';
}

function setRequirementsErrorState() {
	var container = document.getElementById('requirementsChecklist');
	if (!container) return;
	container.innerHTML = '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:12px;padding:14px;color:#b91c1c;font-size:.82rem;">Unable to load requirements right now.</div>';
}

function setRequirementsErrorStateWithMessage(message) {
	var container = document.getElementById('requirementsChecklist');
	if (!container) return;
	container.innerHTML = '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:12px;padding:14px;color:#b91c1c;font-size:.82rem;">' + escapeHtml(message || 'Unable to load requirements right now.') + '</div>';
}

function openRequirementsModal(button) {
	var modal = document.getElementById('requirementsModal');
	if (!modal) return;

	var name = button.getAttribute('data-name') || 'Student';
	var subtitle = button.getAttribute('data-subtitle') || 'Program • Company';
	var submitted = button.getAttribute('data-submitted') || '0';
	var pending = button.getAttribute('data-pending') || '0';
	var completion = button.getAttribute('data-completion') || '0';
	var studentId = button.getAttribute('data-student-id') || '0';
	var internshipId = button.getAttribute('data-internship-id') || '';

	var titleEl = document.getElementById('requirementsTitle');
	var subtitleEl = document.getElementById('requirementsSubtitle');
	if (titleEl) titleEl.textContent = name + ' – Requirements Checklist';
	if (subtitleEl) subtitleEl.textContent = subtitle;
	setRequirementsSummary({
		submitted: Number(submitted || 0),
		pending: Number(pending || 0),
		completion: Number(completion || 0)
	});
	requirementsContext.studentId = Number(studentId || 0);
	requirementsContext.internshipId = internshipId;
	requirementsContext.canEdit = false;
	requirementsContext.activeButton = button;

	setRequirementsLoadingState();
	modal.style.display = 'flex';
	loadRequirementsData();
}

function saveRequirementsChanges() {
	var button = document.getElementById('requirementsSaveBtn');
	if (button) {
		button.disabled = true;
		button.innerHTML = '<i class="fas fa-check"></i> Saved';
	}

	setTimeout(function () {
		closeRequirementsModal();
		if (button) {
			button.disabled = false;
			button.innerHTML = '<i class="fas fa-save"></i> Save Changes';
		}
	}, 250);
}

function closeRequirementsModal() {
	var modal = document.getElementById('requirementsModal');
	if (!modal) return;
	modal.style.display = 'none';
	requirementsContext.activeButton = null;
}

document.addEventListener('click', function (event) {
	var modal = document.getElementById('requirementsModal');
	if (!modal || modal.style.display !== 'flex') return;
	if (event.target === modal) {
		closeRequirementsModal();
	}
});

document.addEventListener('keydown', function (event) {
	if (event.key === 'Escape') {
		closeRequirementsModal();
	}
});
</script>