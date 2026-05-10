<?php
/**
 * Student Banner Component
 * OJT-style banner for student pages
 */
$bannerDate = $bannerDate ?? date('l, jS F');
$bannerTitle = $bannerTitle ?? 'Welcome back';
$bannerDescription = $bannerDescription ?? 'Here\'s what\'s happening with your internship journey.';
$bannerShowToggle = $bannerShowToggle ?? true;
$baseUrl = $baseUrl ?? '/SkillHive';
?>
<style>
.student-ojt-banner {
  background:
    radial-gradient(95% 70% at 26% 44%, rgba(255, 255, 255, 0.24) 0%, rgba(255, 255, 255, 0.1) 18%, rgba(255, 255, 255, 0) 48%),
    radial-gradient(150% 200% at 80% 14%, rgba(37, 168, 158, 0.56) 0%, rgba(9, 22, 24, 0) 46%),
    linear-gradient(135deg, #010101 0%, #020202 62%, #0a1f1e 82%, #0f4e49 100%);
  border-radius: 16px;
  padding: 20px 28px;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  position: relative;
  overflow: hidden;
  color: #ffffff;
  border: 1.5px solid rgba(15, 118, 110, 0.35);
  box-shadow: 0 8px 32px rgba(15, 118, 110, 0.15), 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
  min-height: auto;
}

.student-ojt-banner::before {
  content: '';
  position: absolute;
  left: 40px;
  top: 50%;
  transform: translateY(-50%);
  width: 400px;
  height: 400px;
  background-image: url('/SkillHive/assets/media/banner%20other.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  opacity: 0.4;
  pointer-events: none;
}

.student-ojt-banner::after {
  content: '';
  position: absolute;
  right: 30px;
  top: 40%;
  transform: translateY(-50%);
  width: 380px;
  height: 380px;
  background-image: url('/SkillHive/assets/media/Banner.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  opacity: 0.45;
  pointer-events: none;
}

.student-ojt-banner.collapsed {
  padding: 8px 16px;
  min-height: auto;
}

.student-ojt-banner.collapsed .student-ojt-main {
  display: none;
}

.student-ojt-main {
  display: flex;
  align-items: center;
  gap: 24px;
  position: relative;
  z-index: 1;
  flex: 1;
}

.student-ojt-info {
  flex: 1;
  border-left: 2px solid rgba(13, 59, 54, 0.3);
  padding-left: 20px;
}

.student-ojt-date {
  font-size: 12px;
  font-weight: 100;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 4px;
  letter-spacing: 1px;
}

.student-ojt-title {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 2px;
  text-transform: capitalize;
  display: inline;
}

.student-ojt-desc {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
  line-height: 1.5;
  max-width: 450px;
}

.student-ojt-toggle {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #ffffff;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 2;
  font-size: 13px;
}

.student-ojt-toggle:hover {
  background: rgba(255, 255, 255, 0.25);
  border-color: rgba(255, 255, 255, 0.35);
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(255, 255, 255, 0.1);
}

.student-ojt-expand-hint {
  display: none;
  text-align: center;
  font-size: 13px;
  color: #0f766e;
  font-weight: 500;
  opacity: 0.8;
  cursor: pointer;
  padding: 4px 0;
  width: 100%;
  transition: opacity 0.2s ease;
}

.student-ojt-expand-hint:hover {
  opacity: 1;
}

.student-ojt-banner.collapsed .student-ojt-expand-hint {
  display: block;
}

.student-ojt-banner:not(.collapsed) .student-ojt-expand-hint {
  display: none !important;
}

@media (max-width: 768px) {
  .student-ojt-banner {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 18px 16px;
    text-align: left;
  }
  .student-ojt-banner::before {
    left: -120px;
    width: 300px;
    height: 300px;
    opacity: 0.24;
  }
  .student-ojt-banner::after {
    right: -120px;
    top: 55%;
    width: 280px;
    height: 280px;
    opacity: 0.24;
  }
  .student-ojt-main {
    gap: 0;
    padding-right: 44px;
  }
  .student-ojt-info {
    border-left: 0;
    padding-left: 0;
  }
  .student-ojt-date {
    font-size: 11px;
  }
  .student-ojt-title {
    display: block;
    font-size: 16px;
    line-height: 1.35;
  }
  .student-ojt-desc {
    max-width: none;
    font-size: 13px;
    line-height: 1.55;
    margin-top: 4px;
  }
  .student-ojt-toggle {
    top: 12px;
    right: 12px;
    width: 34px;
    height: 34px;
  }
  .student-ojt-banner.collapsed {
    padding: 8px 12px;
  }
}
</style>

<div class="student-ojt-banner" id="studentBanner">
  <div class="student-ojt-main">
    <div class="student-ojt-info">
      <div class="student-ojt-date"><?php echo htmlspecialchars($bannerDate); ?></div>
      <div class="student-ojt-title"><?php echo htmlspecialchars($bannerTitle); ?></div>
      <div class="student-ojt-desc"><?php echo htmlspecialchars($bannerDescription); ?></div>
    </div>
  </div>
  <?php if ($bannerShowToggle): ?>
  <button type="button" class="student-ojt-toggle" onclick="toggleStudentBanner()" title="Hide banner">
    <i class="fas fa-chevron-up"></i>
  </button>
  <div class="student-ojt-expand-hint" onclick="toggleStudentBanner()">
    <i class="fas fa-chevron-down"></i> Show banner
  </div>
  <?php endif; ?>
</div>

<script>
function toggleStudentBanner() {
  const banner = document.getElementById('studentBanner');
  if (!banner) return;
  const icon = banner.querySelector('.student-ojt-toggle i');
  banner.classList.toggle('collapsed');
  if (icon) {
    icon.className = banner.classList.contains('collapsed') ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
  }
}
</script>
