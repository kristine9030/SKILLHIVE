<?php
/**
 * Dashboard Banner Component
 * Displays a greeting banner with bee mascot and task info
 */

// Default values
$bannerGreeting = $bannerGreeting ?? 'Good afternoon';
$bannerUserName = $bannerUserName ?? 'User';
$bannerTitle = $bannerTitle ?? 'Keep up the pace';
$bannerDescription = $bannerDescription ?? 'You\'re doing great! Keep pushing forward with your goals.';
$bannerDescriptionHtml = $bannerDescriptionHtml ?? '';
$bannerClass = trim((string)($bannerClass ?? ''));
$bannerIsCompact = !empty($bannerIsCompact);
$bannerShowGreeting = array_key_exists('bannerShowGreeting', get_defined_vars()) ? (bool)$bannerShowGreeting : true;
$bannerShowMascot = array_key_exists('bannerShowMascot', get_defined_vars()) ? (bool)$bannerShowMascot : true;
$bannerDaysText = $bannerDaysText ?? 'LAST 7 DAYS';
$bannerStats = $bannerStats ?? [];

$baseUrl = $baseUrl ?? '/SkillHive';
$beeMascotPath = $baseUrl . '/assets/media/Bee.png';
?>

<style>
  .dashboard-banner {
    background: linear-gradient(135deg, #050505 0%, #050505 40%, #12b3ac 72%, #12b3ac 100%);
    border-radius: 16px;
    padding: 4px 16px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    position: relative;
    overflow: visible;
    color: white;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.24), 0 0 1px rgba(255, 255, 255, 0.1) inset;
    flex-direction: row-reverse;
    transition: box-shadow 0.3s ease;
  }

  .dashboard-banner.dashboard-banner--compact {
    padding: 12px 16px;
    min-height: 0;
    gap: 12px;
  }

  .dashboard-banner.dashboard-banner--compact .banner-content {
    padding: 0;
  }

  .dashboard-banner.dashboard-banner--compact .banner-greeting {
    font-size: 14px;
    margin-bottom: 2px;
  }

  .dashboard-banner.dashboard-banner--compact .banner-user-name {
    font-size: 24px;
    margin-bottom: 2px;
  }

  .dashboard-banner.dashboard-banner--compact .banner-description {
    font-size: 13px;
    line-height: 1.45;
    max-width: 100%;
  }
  
  .dashboard-banner:hover {
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.3), 0 0 1px rgba(255, 255, 255, 0.15) inset;
  }

  .dashboard-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(133, 233, 223, 0.15);
    border-radius: 50%;
    pointer-events: none;
  }

  .dashboard-banner::after {
    content: '';
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    width: 320px;
    height: 320px;
    background-image: url('/SkillHive/assets/media/low%20opacity%20banner.png');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.3;
    pointer-events: none;
  }

  .banner-content {
    flex: 1;
    position: relative;
    z-index: 1;
    padding: 4px 0;
  }

  .banner-greeting {
    font-size: 20px;
    font-weight: 500;
    opacity: 0.85;
    margin-bottom: 0px;
    text-transform: capitalize;
    letter-spacing: 0.5px;
  }

  .banner-user-name {
    font-size: 40px;
    font-weight: 700;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
  }

  .banner-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 6px;
    opacity: 0.95;
  }

  .banner-description {
    font-size: 16px;
    opacity: 0.85;
    line-height: 1.6;
    margin-bottom: 0;
    max-width: 450px;
    letter-spacing: 0.3px;
  }

  .banner-emphasis {
    font-weight: 700;
    opacity: 1;
    color: #baf4ee;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }

  .banner-stats {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
  }

  .banner-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .banner-stat-value {
    font-size: 20px;
    font-weight: 700;
  }

  .banner-stat-label {
    font-size: 12px;
    opacity: 0.75;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .banner-mascot {
    flex-shrink: 0;
    position: relative;
    z-index: 2;
  }

  .banner-mascot img {
    width: 240px;
    height: 240px;
    object-fit: contain;
    filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.3));
    animation: float 4s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite;
  }

  @keyframes float {
    0%, 100% {
      transform: translateY(0px) scale(1);
    }
    50% {
      transform: translateY(-12px) scale(1.02);
    }
  }

  /* Responsive */
  @media (max-width: 1024px) {
    .dashboard-banner {
      flex-direction: column;
      text-align: center;
      padding: 24px;
    }

    .banner-mascot img {
      width: 180px;
      height: 180px;
    }

    .banner-stats {
      justify-content: center;
    }

    .banner-description {
      max-width: 100%;
    }
  }

  @media (max-width: 768px) {
    .dashboard-banner {
      padding: 16px;
      gap: 16px;
    }

    .banner-user-name {
      font-size: 22px;
    }

    .banner-mascot img {
      width: 140px;
      height: 140px;
    }

    .banner-stats {
      gap: 16px;
    }
  }
</style>

<div class="dashboard-banner<?php echo $bannerIsCompact ? ' dashboard-banner--compact' : ''; ?><?php echo $bannerClass !== '' ? ' ' . htmlspecialchars($bannerClass) : ''; ?>">
  <div class="banner-content">
    <?php if ($bannerShowGreeting): ?>
      <div class="banner-greeting"><?php echo htmlspecialchars($bannerGreeting); ?>, <span style="font-weight:600;"><?php echo htmlspecialchars($bannerUserName); ?></span>!</div>
    <?php endif; ?>
    <div class="banner-user-name"><?php echo htmlspecialchars($bannerTitle); ?></div>
    <div class="banner-description">
      <?php if (trim((string)$bannerDescriptionHtml) !== ''): ?>
        <?php echo $bannerDescriptionHtml; ?>
      <?php else: ?>
        <?php echo htmlspecialchars($bannerDescription); ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($bannerShowMascot): ?>
    <div class="banner-mascot">
      <img src="<?php echo htmlspecialchars($beeMascotPath); ?>" alt="SkillHive Bee Mascot">
    </div>
  <?php endif; ?>
</div>
