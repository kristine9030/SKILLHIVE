<?php
require_once __DIR__ . '/../../backend/db_connect.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

if (!function_exists('adviser_settings_defaults')) {
	function adviser_settings_defaults(): array
	{
		return [
			'show_companies_banner' => true,
			'show_evaluation_banner' => true,
			'show_analytics_graphs' => true,
			'enable_journal_print_layout' => true,
			'analytics_banner_palette' => true,
			'table_density' => 'comfortable',
		];
	}
}

if (!function_exists('adviser_settings_ensure_table')) {
	function adviser_settings_ensure_table(PDO $pdo): void
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS adviser_module_settings (
				adviser_id INT UNSIGNED NOT NULL PRIMARY KEY,
				settings_json LONGTEXT NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
		);
	}
}

if (!function_exists('adviser_settings_load')) {
	function adviser_settings_load(PDO $pdo, int $adviserId): array
	{
		$defaults = adviser_settings_defaults();

		if ($adviserId <= 0) {
			return $defaults;
		}

		adviser_settings_ensure_table($pdo);

		$stmt = $pdo->prepare('SELECT settings_json FROM adviser_module_settings WHERE adviser_id = :adviser_id LIMIT 1');
		$stmt->execute([':adviser_id' => $adviserId]);
		$json = (string)($stmt->fetchColumn() ?: '');
		$decoded = json_decode($json, true);

		if (!is_array($decoded)) {
			return $defaults;
		}

		return array_merge($defaults, $decoded);
	}
}

if (!function_exists('adviser_settings_save')) {
	function adviser_settings_save(PDO $pdo, int $adviserId, array $settings): void
	{
		if ($adviserId <= 0) {
			return;
		}

		adviser_settings_ensure_table($pdo);

		$stmt = $pdo->prepare(
			'INSERT INTO adviser_module_settings (adviser_id, settings_json)
			 VALUES (:adviser_id, :settings_json)
			 ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json), updated_at = CURRENT_TIMESTAMP'
		);

		$stmt->execute([
			':adviser_id' => $adviserId,
			':settings_json' => (string)json_encode($settings, JSON_UNESCAPED_UNICODE),
		]);
	}
}

$settings = adviser_settings_defaults();
$errorMessage = '';
$successMessage = '';

if ($adviserId > 0) {
	try {
		$settings = adviser_settings_load($pdo, $adviserId);
	} catch (Throwable $e) {
		$settings = adviser_settings_defaults();
	}
}

if (empty($_SESSION['adviser_settings_csrf']) || !is_string($_SESSION['adviser_settings_csrf'])) {
	$_SESSION['adviser_settings_csrf'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	if (!hash_equals((string)($_SESSION['adviser_settings_csrf'] ?? ''), $token)) {
		$errorMessage = 'Invalid session token. Please refresh and try again.';
	} elseif ($adviserId <= 0) {
		$errorMessage = 'Unable to save settings for this adviser account.';
	} else {
		$density = trim((string)($_POST['table_density'] ?? 'comfortable'));
		if (!in_array($density, ['comfortable', 'compact'], true)) {
			$density = 'comfortable';
		}

		$settings = [
			'show_companies_banner' => !empty($_POST['show_companies_banner']),
			'show_evaluation_banner' => !empty($_POST['show_evaluation_banner']),
			'show_analytics_graphs' => !empty($_POST['show_analytics_graphs']),
			'enable_journal_print_layout' => !empty($_POST['enable_journal_print_layout']),
			'analytics_banner_palette' => !empty($_POST['analytics_banner_palette']),
			'table_density' => $density,
		];

		try {
			adviser_settings_save($pdo, $adviserId, $settings);
			$_SESSION['adviser_module_settings'] = $settings;
			$successMessage = 'Module settings saved successfully.';
		} catch (Throwable $e) {
			$errorMessage = 'Could not save settings right now. Please try again.';
		}
	}
}

$_SESSION['adviser_module_settings'] = $settings;
?>

<style>
	.adviser-settings-page {
		display: flex;
		flex-direction: column;
		gap: 20px;
	}

	.adviser-settings-panel {
		background: var(--card);
		border: 1px solid var(--border);
		border-radius: var(--radius);
		box-shadow: var(--card-shadow);
		padding: 24px;
	}

	.adviser-settings-title {
		margin: 0;
		font-size: 1.2rem;
		font-weight: 800;
		color: var(--text);
	}

	.adviser-settings-subtitle {
		margin: 8px 0 0;
		font-size: .88rem;
		color: var(--text3);
	}

	.adviser-settings-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 14px;
		margin-top: 18px;
	}

	.adviser-settings-card {
		border: 1px solid var(--border);
		border-radius: 14px;
		padding: 14px;
		background: #fff;
	}

	.adviser-settings-card h4 {
		margin: 0 0 4px;
		font-size: .9rem;
		font-weight: 700;
		color: var(--text);
	}

	.adviser-settings-card p {
		margin: 0;
		font-size: .8rem;
		color: var(--text3);
		line-height: 1.45;
	}

	.adviser-settings-toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		margin-top: 10px;
	}

	.adviser-settings-switch {
		position: relative;
		width: 44px;
		height: 24px;
		border-radius: 999px;
		background: #cbd5e1;
		transition: background .2s ease;
		flex-shrink: 0;
	}

	.adviser-settings-switch::after {
		content: '';
		position: absolute;
		top: 3px;
		left: 3px;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		background: #fff;
		transition: transform .2s ease;
	}

	.adviser-settings-toggle input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	.adviser-settings-toggle input:checked + .adviser-settings-switch {
		background: #12b3ac;
	}

	.adviser-settings-toggle input:checked + .adviser-settings-switch::after {
		transform: translateX(20px);
	}

	.adviser-settings-select {
		margin-top: 8px;
		min-height: 38px;
		border: 1px solid var(--border);
		border-radius: 10px;
		padding: 8px 10px;
		font-size: .84rem;
		color: var(--text);
		width: 100%;
		background: #fff;
	}

	.adviser-settings-actions {
		margin-top: 18px;
		display: flex;
		justify-content: flex-end;
	}

	.adviser-settings-btn {
		min-height: 40px;
		padding: 0 18px;
		border-radius: 999px;
		border: 0;
		background: #111;
		color: #fff;
		font-size: .85rem;
		font-weight: 700;
		cursor: pointer;
	}

	.adviser-settings-message {
		padding: 12px 14px;
		border-radius: 12px;
		font-size: .82rem;
		font-weight: 600;
	}

	.adviser-settings-message.ok {
		background: #f0fdf4;
		border: 1px solid #bbf7d0;
		color: #15803d;
	}

	.adviser-settings-message.error {
		background: #fff1f2;
		border: 1px solid #fecaca;
		color: #12b3ac;
	}

	@media (max-width: 900px) {
		.adviser-settings-grid {
			grid-template-columns: 1fr;
		}
	}
</style>

<div class="adviser-settings-page">
	<div style="background:linear-gradient(90deg, #050505 0%, #12b3ac 40%, rgba(0, 0, 0, 0.38) 100%), url('/Skillhive/assets/media/element%203.png') right center / auto 100% no-repeat;border-radius:16px;padding:28px;margin-bottom:4px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(0, 0, 0, 0.44);">
		<div style="z-index:2;flex:1;">
			<h2 style="font-size:1.8rem;font-weight:900;margin:0 0 12px 0;line-height:1.2;color:white;">Adviser Module Settings</h2>
			<p style="font-size:0.95rem;margin:0;line-height:1.6;color:#e0e0e0;">Configure visual preferences and module behavior for Companies, Evaluation, Analytics, and Journals.</p>
		</div>
	</div>

	<?php if ($successMessage !== ''): ?>
		<div class="adviser-settings-message ok"><?php echo htmlspecialchars($successMessage); ?></div>
	<?php endif; ?>

	<?php if ($errorMessage !== ''): ?>
		<div class="adviser-settings-message error"><?php echo htmlspecialchars($errorMessage); ?></div>
	<?php endif; ?>

	<form method="post" class="adviser-settings-panel">
		<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['adviser_settings_csrf']); ?>">

		<h3 class="adviser-settings-title">Module Preferences</h3>
		<p class="adviser-settings-subtitle">These settings apply to your adviser module interface and are saved to your account.</p>

		<div class="adviser-settings-grid">
			<article class="adviser-settings-card">
				<h4>Companies Banner</h4>
				<p>Show the top banner on the Companies module.</p>
				<label class="adviser-settings-toggle">
					<span>Enable banner</span>
					<span style="position:relative;display:inline-flex;">
						<input type="checkbox" name="show_companies_banner" value="1" <?php echo !empty($settings['show_companies_banner']) ? 'checked' : ''; ?>>
						<span class="adviser-settings-switch"></span>
					</span>
				</label>
			</article>

			<article class="adviser-settings-card">
				<h4>Evaluation Banner</h4>
				<p>Show the top banner on the Evaluation module.</p>
				<label class="adviser-settings-toggle">
					<span>Enable banner</span>
					<span style="position:relative;display:inline-flex;">
						<input type="checkbox" name="show_evaluation_banner" value="1" <?php echo !empty($settings['show_evaluation_banner']) ? 'checked' : ''; ?>>
						<span class="adviser-settings-switch"></span>
					</span>
				</label>
			</article>

			<article class="adviser-settings-card">
				<h4>Analytics Graphs</h4>
				<p>Display chart blocks in the Analytics module.</p>
				<label class="adviser-settings-toggle">
					<span>Enable graphs</span>
					<span style="position:relative;display:inline-flex;">
						<input type="checkbox" name="show_analytics_graphs" value="1" <?php echo !empty($settings['show_analytics_graphs']) ? 'checked' : ''; ?>>
						<span class="adviser-settings-switch"></span>
					</span>
				</label>
			</article>

			<article class="adviser-settings-card">
				<h4>Journal Print Layout</h4>
				<p>Keep print-ready journal output enabled.</p>
				<label class="adviser-settings-toggle">
					<span>Enable print mode</span>
					<span style="position:relative;display:inline-flex;">
						<input type="checkbox" name="enable_journal_print_layout" value="1" <?php echo !empty($settings['enable_journal_print_layout']) ? 'checked' : ''; ?>>
						<span class="adviser-settings-switch"></span>
					</span>
				</label>
			</article>

			<article class="adviser-settings-card">
				<h4>Analytics Theme Match</h4>
				<p>Use banner-matched palette in Analytics.</p>
				<label class="adviser-settings-toggle">
					<span>Use banner palette</span>
					<span style="position:relative;display:inline-flex;">
						<input type="checkbox" name="analytics_banner_palette" value="1" <?php echo !empty($settings['analytics_banner_palette']) ? 'checked' : ''; ?>>
						<span class="adviser-settings-switch"></span>
					</span>
				</label>
			</article>

			<article class="adviser-settings-card">
				<h4>Table Density</h4>
				<p>Choose preferred spacing for list-style modules.</p>
				<select class="adviser-settings-select" name="table_density">
					<option value="comfortable" <?php echo (($settings['table_density'] ?? '') === 'comfortable') ? 'selected' : ''; ?>>Comfortable</option>
					<option value="compact" <?php echo (($settings['table_density'] ?? '') === 'compact') ? 'selected' : ''; ?>>Compact</option>
				</select>
			</article>
		</div>

		<div class="adviser-settings-actions">
			<button class="adviser-settings-btn" type="submit">Save Module Settings</button>
		</div>
	</form>
</div>
