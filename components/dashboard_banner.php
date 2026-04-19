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
$bannerDaysText = $bannerDaysText ?? 'LAST 7 DAYS';
$bannerStats = $bannerStats ?? [];

$baseUrl = $baseUrl ?? '/SkillHive';
$beeMascotPath = $baseUrl . '/assets/media/Bee.png';
?>

<style>
  .dashboard-banner {
    background: linear-gradient(135deg, #0a0e27 0%, #162550 40%, #1a3a5c 70%, #0f2a45 100%);
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
    box-shadow: 0 12px 40px rgba(10, 14, 39, 0.4), 0 0 1px rgba(255, 255, 255, 0.1) inset;
    flex-direction: row-reverse;
    transition: box-shadow 0.3s ease;
  }
  
  .dashboard-banner:hover {
    box-shadow: 0 16px 50px rgba(10, 14, 39, 0.5), 0 0 1px rgba(255, 255, 255, 0.15) inset;
  }

  .dashboard-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255, 255, 255, 0.03);
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
    color: #fcd34d;
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

<div class="dashboard-banner">
  <div class="banner-content">
    <div class="banner-greeting"><?php echo htmlspecialchars($bannerGreeting); ?>, <span style="font-weight:600;"><?php echo htmlspecialchars($bannerUserName); ?></span>!</div>
    <div class="banner-user-name"><?php echo htmlspecialchars($bannerTitle); ?></div>
    <div class="banner-description">
      Guide students through their journey, provide <span class="banner-emphasis">endorsements</span>, 
      <span class="banner-emphasis">monitor progress</span>, and help them succeed in their 
      <span class="banner-emphasis">internship placements</span>.
    </div>
  </div>

  <div class="banner-mascot">
    <img src="<?php echo htmlspecialchars($beeMascotPath); ?>" alt="SkillHive Bee Mascot">
  </div>
</div>
