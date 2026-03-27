<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;
$baseUrl = '/SkillHive';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SkillHive – Intelligent Internship & Employability System</title>
  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/skillhive.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<!-- NAV -->
<nav class="land-nav">
  <a href="<?php echo $baseUrl; ?>/index.php" class="land-logo">
    <div class="logo-icon"><i class="fas fa-hexagon-nodes"></i></div>
    SkillHive
  </a>
  <ul class="land-nav-links">
    <li><a href="#hero">Home</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#pricing">Pricing</a></li>
    <li><a href="#testimonials">Testimonials</a></li>
  </ul>
  <div class="land-nav-cta">
    <?php if ($isLoggedIn): ?>
      <a href="<?php echo $baseUrl; ?>/layout.php" class="btn btn-primary">Dashboard</a>
    <?php else: ?>
      <a href="<?php echo $baseUrl; ?>/pages/auth/login.php" class="btn btn-ghost">Sign In</a>
      <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-primary">Get Started</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="land-hero" id="hero">
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="hero-blob hero-blob-3"></div>
  <div class="hero-content">
    <div class="hero-badge"><i class="fas fa-bolt"></i> AI-Powered Internship Matching</div>
    <h1 class="hero-title">Revolutionize Your <span class="highlight">Internship</span> Experience with AI</h1>
    <p class="hero-desc">Automate candidate screening, smart matching, and internship monitoring with AI to hire faster, smarter, and fairer.</p>
    <div class="hero-actions">
      <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-primary btn-lg">Get Started Free <i class="fas fa-arrow-right"></i></a>
      <a href="#features" class="btn btn-ghost btn-lg">Explore Features <i class="fas fa-play"></i></a>
    </div>
    <div class="hero-trust">
      <div class="trust-avatars">
        <span style="background:#06B6D4">JS</span>
        <span style="background:#10B981">MR</span>
        <span style="background:#F59E0B">AL</span>
        <span style="background:#111">KP</span>
      </div>
      <div class="trust-text">Trusted by <strong>1.2k+</strong> students & companies</div>
    </div>
  </div>
  <div class="hero-visual">
    <div class="hero-cards-wrap">
      <div class="hero-card-float hcf-1">
        <div class="hcf-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-circle" style="color:#10B981"></i></div>
        <div><div class="hcf-label">Applications</div><div class="hcf-value">12 Active</div></div>
      </div>
      <div class="hero-card-main">
        <div class="hc-header">
          <div class="hc-title">Top Match For You</div>
          <div class="match-badge">87% fit</div>
        </div>
        <div class="hc-company">
          <div class="co-logo" style="background:linear-gradient(135deg,#06B6D4,#10B981)">G</div>
          <div><div style="font-weight:700;font-size:.88rem">Google Philippines</div><small>UI/UX Design Internship</small></div>
        </div>
        <div class="hc-skills">
          <span class="skill-chip match">Figma ✓</span>
          <span class="skill-chip match">React ✓</span>
          <span class="skill-chip gap">Flutter ↑</span>
        </div>
        <div class="hc-score-bar"><div class="hc-score-fill" style="width:87%"></div></div>
        <div class="hc-score-label"><span>Compatibility</span><span>87%</span></div>
      </div>
      <div class="hero-card-float hcf-2">
        <div class="hcf-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-clock" style="color:#F59E0B"></i></div>
        <div><div class="hcf-label">Hours Logged</div><div class="hcf-value">248 hrs</div></div>
      </div>
      <div class="hero-card-float hcf-3">
        <div class="hcf-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-robot" style="color:#06B6D4"></i></div>
        <div><div class="hcf-label">AI Resume Score</div><div class="hcf-value">92 / 100</div></div>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-band">
  <div class="stat-item"><div class="stat-num">70%</div><div class="stat-label">Faster Time-to-Hire</div></div>
  <div class="stat-item"><div class="stat-num">50%</div><div class="stat-label">Reduce Hiring Costs</div></div>
  <div class="stat-item"><div class="stat-num">79+</div><div class="stat-label">Partner Companies</div></div>
  <div class="stat-item"><div class="stat-num">1.2k+</div><div class="stat-label">Students Placed</div></div>
  <div class="stat-item"><div class="stat-num">98%</div><div class="stat-label">Satisfaction Rate</div></div>
</div>

<!-- WHY -->
<section class="land-section" style="background:#fff">
  <div style="text-align:center;margin-bottom:0">
    <div class="section-tag">Why SkillHive</div>
    <h2 class="section-title" style="text-align:center">Why Traditional Hiring<br>Processes Are Holding You Back</h2>
    <p class="section-desc" style="margin:0 auto;text-align:center">Manual screening, bias in selection, and lack of tracking — SkillHive solves them all with AI.</p>
  </div>
  <div class="why-grid">
    <div class="why-card">
      <div class="why-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-file-alt" style="color:#06B6D4"></i></div>
      <h3>Smart Resume Screening</h3>
      <p>AI-powered resume analysis that scores and ranks candidates instantly based on job requirements.</p>
    </div>
    <div class="why-card">
      <div class="why-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-brain" style="color:#10B981"></i></div>
      <h3>AI Sentiment Analysis</h3>
      <p>Video interviews with AI sentiment analysis to detect candidate confidence and communication skills.</p>
    </div>
    <div class="why-card">
      <div class="why-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-chart-line" style="color:#F59E0B"></i></div>
      <h3>Data-Driven Decisions</h3>
      <p>Bias-free matching powered by skills matrix and academic performance data.</p>
    </div>
    <div class="why-card">
      <div class="why-icon" style="background:rgba(239,68,68,.1)"><i class="fas fa-clock" style="color:#EF4444"></i></div>
      <h3>Hiring AI Reports</h3>
      <p>Comprehensive analytics dashboards for placement rate, skill gaps, and hiring trends.</p>
    </div>
    <div class="why-card">
      <div class="why-icon" style="background:rgba(6,182,212,.15)"><i class="fas fa-shield-alt" style="color:#06B6D4"></i></div>
      <h3>Candidate Personality</h3>
      <p>Psychometric assessments and behavioral analysis for better cultural fit matching.</p>
    </div>
    <div class="why-card">
      <div class="why-icon" style="background:rgba(16,185,129,.15)"><i class="fas fa-tasks" style="color:#10B981"></i></div>
      <h3>AI Skill Assessments</h3>
      <p>Automated skill tests with real-world scenarios to validate candidate abilities objectively.</p>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-showcase" id="features">
  <div class="section-tag">Features</div>
  <h2 class="section-title">Meet SkillHive AI – Your End-to-End<br>Intelligent Hiring Assistant</h2>
  <div class="feat-tabs">
    <button class="feat-tab active" onclick="switchFeatTab(this,'ft-profile')">Digital Profile</button>
    <button class="feat-tab" onclick="switchFeatTab(this,'ft-match')">AI Matching</button>
    <button class="feat-tab" onclick="switchFeatTab(this,'ft-marketplace')">Marketplace</button>
    <button class="feat-tab" onclick="switchFeatTab(this,'ft-resume')">CV Builder</button>
    <button class="feat-tab" onclick="switchFeatTab(this,'ft-tracker')">App Tracker</button>
    <button class="feat-tab" onclick="switchFeatTab(this,'ft-ojt')">OJT Monitor</button>
  </div>

  <div class="feat-content active" id="ft-profile">
    <div class="feat-text">
      <h3>Smart Digital Profile & Portfolio</h3>
      <p>Build a comprehensive professional profile linked to your academic records, skills matrix, and portfolio — all in one place.</p>
      <ul class="feat-list">
        <li><i class="fas fa-check"></i> Academic info auto-linked (program, department)</li>
        <li><i class="fas fa-check"></i> Technical & soft skills matrix</li>
        <li><i class="fas fa-check"></i> AI-powered resume builder</li>
        <li><i class="fas fa-check"></i> Portfolio uploads & certifications</li>
        <li><i class="fas fa-check"></i> Availability & preferred industry settings</li>
      </ul>
    </div>
    <div class="feat-visual">
      <div class="mini-card">
        <div class="mini-label" style="margin-bottom:8px">Profile Completeness</div>
        <div style="display:flex;align-items:center;gap:12px">
          <div style="position:relative;width:60px;height:60px">
            <svg width="60" height="60"><circle cx="30" cy="30" r="24" stroke="#F0F0F0" stroke-width="5" fill="none"/><circle cx="30" cy="30" r="24" fill="none" stroke="#06B6D4" stroke-width="5" stroke-linecap="round" stroke-dasharray="150" stroke-dashoffset="22" transform="rotate(-90,30,30)"/></svg>
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:800;font-size:.8rem;color:#06B6D4">85%</div>
          </div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:.88rem;margin-bottom:6px">Juan dela Cruz</div>
            <div class="skill-chip match" style="font-size:.7rem">BS Computer Science</div>
          </div>
        </div>
      </div>
      <div class="mini-card">
        <div class="mini-label" style="margin-bottom:10px">Skills Matrix</div>
        <div class="skill-bar-item"><div class="skill-bar-header"><span>React.js</span><span>90%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:90%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div></div></div>
        <div class="skill-bar-item"><div class="skill-bar-header"><span>UI/UX Design</span><span>75%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:75%;background:linear-gradient(90deg,#F59E0B,#10B981)"></div></div></div>
        <div class="skill-bar-item"><div class="skill-bar-header"><span>Python</span><span>60%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:60%;background:linear-gradient(90deg,#10B981,#06B6D4)"></div></div></div>
      </div>
    </div>
  </div>

  <div class="feat-content" id="ft-match">
    <div class="feat-text">
      <h3>AI Internship Compatibility Matching</h3>
      <p>Our AI engine analyzes your skills, academic background, and preferences to find the best-fit internship opportunities.</p>
      <ul class="feat-list">
        <li><i class="fas fa-check"></i> Match percentage score (e.g., 87% fit)</li>
        <li><i class="fas fa-check"></i> Skill alignment analysis</li>
        <li><i class="fas fa-check"></i> Recommended internships feed</li>
        <li><i class="fas fa-check"></i> "Why this matches you" explanation</li>
        <li><i class="fas fa-check"></i> Skill gap suggestions & learning paths</li>
      </ul>
    </div>
    <div class="feat-visual">
      <div class="mini-card">
        <div class="mini-card-header"><div class="mini-label">AI Match Score</div><div class="match-badge">87% fit</div></div>
        <div style="font-weight:700;margin-bottom:6px;font-size:.88rem">Google – UI/UX Intern</div>
        <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:87%;background:linear-gradient(90deg,#10B981,#06B6D4);height:8px;border-radius:50px"></div></div>
      </div>
      <div class="mini-card">
        <div class="mini-label" style="margin-bottom:10px">Why This Matches You</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <div class="mini-row"><span>Figma proficiency</span><span style="color:#10B981">✓ Match</span></div>
          <div class="mini-row"><span>React experience</span><span style="color:#10B981">✓ Match</span></div>
          <div class="mini-row"><span>Flutter skills</span><span style="color:#06B6D4">↑ Improve</span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="feat-content" id="ft-marketplace">
    <div class="feat-text">
      <h3>Internship Marketplace</h3>
      <p>Browse hundreds of verified internship opportunities filtered by industry, location, and your skill set.</p>
      <ul class="feat-list">
        <li><i class="fas fa-check"></i> Internal & verified company listings</li>
        <li><i class="fas fa-check"></i> Filter by industry, location, allowance</li>
        <li><i class="fas fa-check"></i> Remote / on-site / hybrid options</li>
        <li><i class="fas fa-check"></i> Bookmark and compare internships</li>
        <li><i class="fas fa-check"></i> One-click application system</li>
      </ul>
    </div>
    <div class="feat-visual">
      <div class="mini-card">
        <div class="mini-label" style="margin-bottom:10px">Live Listings</div>
        <div class="mini-row"><span style="font-weight:600">Meta – Product Design</span><span style="background:rgba(16,185,129,.1);color:#10B981;padding:2px 8px;border-radius:50px;font-size:.7rem">Remote</span></div>
        <div class="mini-row"><span style="font-weight:600">Shopify – Frontend Dev</span><span style="background:rgba(6,182,212,.1);color:#06B6D4;padding:2px 8px;border-radius:50px;font-size:.7rem">On-site</span></div>
        <div class="mini-row"><span style="font-weight:600">Grab – Data Science</span><span style="background:rgba(245,158,11,.1);color:#F59E0B;padding:2px 8px;border-radius:50px;font-size:.7rem">Hybrid</span></div>
      </div>
    </div>
  </div>

  <div class="feat-content" id="ft-resume">
    <div class="feat-text">
      <h3>AI Resume & Interview Assistant</h3>
      <p>Get your resume scored against job descriptions and practice mock interviews with our AI chatbot.</p>
      <ul class="feat-list">
        <li><i class="fas fa-check"></i> Resume scoring against job description</li>
        <li><i class="fas fa-check"></i> AI-powered improvement suggestions</li>
        <li><i class="fas fa-check"></i> Interview question generator</li>
        <li><i class="fas fa-check"></i> Mock interview chatbot</li>
        <li><i class="fas fa-check"></i> Internship readiness score</li>
      </ul>
    </div>
    <div class="feat-visual">
      <div class="mini-card" style="text-align:center">
        <div class="mini-label" style="margin-bottom:8px">Resume Score</div>
        <div style="font-family:'Poppins', sans-serif;font-size:2.5rem;font-weight:600;color:#111">92</div>
        <div style="font-size:.75rem;color:#999">/100 · Excellent</div>
        <div style="margin-top:10px;font-size:.78rem;color:#10B981"><i class="fas fa-arrow-up"></i> +8 pts from last version</div>
      </div>
    </div>
  </div>

  <div class="feat-content" id="ft-tracker">
    <div class="feat-text">
      <h3>Application & Status Tracker</h3>
      <p>Track every application in real-time from submission to final decision with automated reminders.</p>
      <ul class="feat-list">
        <li><i class="fas fa-check"></i> Real-time application status updates</li>
        <li><i class="fas fa-check"></i> Interview scheduling system</li>
        <li><i class="fas fa-check"></i> Automated email reminders</li>
        <li><i class="fas fa-check"></i> Timeline visualization</li>
        <li><i class="fas fa-check"></i> Acceptance/rejection notifications</li>
      </ul>
    </div>
    <div class="feat-visual">
      <div class="mini-card">
        <div class="mini-label" style="margin-bottom:10px">My Applications</div>
        <div class="mini-row"><span>Google Internship</span><span class="status-pill status-interview">Interview</span></div>
        <div class="mini-row"><span>Meta Design</span><span class="status-pill status-shortlisted">Shortlisted</span></div>
        <div class="mini-row"><span>Grab Data</span><span class="status-pill status-pending">Pending</span></div>
        <div class="mini-row"><span>Shopify Dev</span><span class="status-pill status-accepted">Accepted</span></div>
      </div>
    </div>
  </div>

  <div class="feat-content" id="ft-ojt">
    <div class="feat-text">
      <h3>OJT Monitoring & Hour Tracker</h3>
      <p>Log your daily accomplishments, track internship hours, and visualize your progress throughout your OJT.</p>
      <ul class="feat-list">
        <li><i class="fas fa-check"></i> Daily/weekly accomplishment logs</li>
        <li><i class="fas fa-check"></i> Auto-computed internship hours</li>
        <li><i class="fas fa-check"></i> Task submission system</li>
        <li><i class="fas fa-check"></i> Progress visualization dashboard</li>
        <li><i class="fas fa-check"></i> Supervisor feedback integration</li>
      </ul>
    </div>
    <div class="feat-visual">
      <div class="mini-card">
        <div class="mini-label" style="margin-bottom:10px">OJT Progress</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div><div style="font-family:'Poppins', sans-serif;font-weight:600;font-size:1.4rem;color:#111">248</div><div style="font-size:.72rem;color:#999">of 400 hours</div></div>
          <div style="font-size:.78rem;color:#10B981">62% complete</div>
        </div>
        <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:62%;background:linear-gradient(90deg,#06B6D4,#10B981);height:10px;border-radius:50px"></div></div>
      </div>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="land-section" id="pricing" style="background:#F7F7F7">
  <div style="text-align:center">
    <div class="section-tag">Pricing</div>
    <h2 class="section-title" style="text-align:center">Flexible Pricing Plans<br>for Every Team</h2>
  </div>
  <div class="pricing-grid">
    <div class="price-card">
      <div class="price-plan">Starter</div>
      <div class="price-desc">Perfect for students exploring internships</div>
      <div class="price-amount">Free</div>
      <div class="price-period">Forever free</div>
      <ul class="price-features">
        <li class="price-feature"><i class="fas fa-check"></i> Basic profile & resume builder</li>
        <li class="price-feature"><i class="fas fa-check"></i> Up to 5 applications/month</li>
        <li class="price-feature"><i class="fas fa-check"></i> Basic AI matching</li>
        <li class="price-feature"><i class="fas fa-check"></i> Application tracker</li>
      </ul>
      <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-ghost" style="width:100%;justify-content:center">Get Started</a>
    </div>
    <div class="price-card featured">
      <div class="price-badge">Most Popular</div>
      <div class="price-plan">Pro</div>
      <div class="price-desc">For serious job seekers & companies</div>
      <div class="price-amount">₱99<span>/mo</span></div>
      <div class="price-period">Billed monthly</div>
      <ul class="price-features">
        <li class="price-feature"><i class="fas fa-check"></i> Unlimited applications</li>
        <li class="price-feature"><i class="fas fa-check"></i> Full AI matching & insights</li>
        <li class="price-feature"><i class="fas fa-check"></i> CV Builder with AI import</li>
        <li class="price-feature"><i class="fas fa-check"></i> Mock interview chatbot</li>
        <li class="price-feature"><i class="fas fa-check"></i> OJT hour tracker</li>
        <li class="price-feature"><i class="fas fa-check"></i> AI recognition certificate</li>
      </ul>
      <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-white" style="width:100%;justify-content:center">Get Started</a>
    </div>
    <div class="price-card">
      <div class="price-plan">Enterprise</div>
      <div class="price-desc">For universities & large employers</div>
      <div class="price-amount">₱149<span>/mo</span></div>
      <div class="price-period">Per seat, billed annually</div>
      <ul class="price-features">
        <li class="price-feature"><i class="fas fa-check"></i> Unlimited candidates</li>
        <li class="price-feature"><i class="fas fa-check"></i> Dedicated account manager</li>
        <li class="price-feature"><i class="fas fa-check"></i> Full analytics suite</li>
        <li class="price-feature"><i class="fas fa-check"></i> MOA management</li>
        <li class="price-feature"><i class="fas fa-check"></i> Custom integrations</li>
      </ul>
      <button class="btn btn-ghost" style="width:100%;justify-content:center">Contact Sales</button>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="land-section" id="testimonials" style="background:#fff">
  <div style="text-align:center">
    <div class="section-tag">Inspiring Stories</div>
    <h2 class="section-title" style="text-align:center">What Our Users Are Saying</h2>
  </div>
  <div class="testimonials-grid">
    <div class="testi-card">
      <div class="testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
      <p class="testi-text">"A game-changer for our recruitment team. The automated scheduling feature saves us countless hours every week."</p>
      <div class="testi-author"><div class="testi-avatar" style="background:linear-gradient(135deg,#06B6D4,#10B981)">DP</div><div><div class="testi-name">David Park</div><div class="testi-role">Talent Acquisition, NovaCare</div></div></div>
    </div>
    <div class="testi-card">
      <div class="testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
      <p class="testi-text">"SkillHive helped us cut our hiring time in half while improving candidate quality. The AI assessments are remarkably accurate."</p>
      <div class="testi-author"><div class="testi-avatar" style="background:linear-gradient(135deg,#F59E0B,#EF4444)">SJ</div><div><div class="testi-name">Sarah Jameson</div><div class="testi-role">HR Director, TechBridge</div></div></div>
    </div>
    <div class="testi-card">
      <div class="testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
      <p class="testi-text">"Found my first internship within a week! The AI matching showed me exactly why I was a good fit and what I needed to improve."</p>
      <div class="testi-author"><div class="testi-avatar" style="background:linear-gradient(135deg,#10B981,#06B6D4)">RM</div><div><div class="testi-name">Rafi M.</div><div class="testi-role">Student, UP Diliman</div></div></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <h2>Ready to Transform Your Internship Journey?</h2>
  <p>Join thousands of students and companies already using SkillHive to connect talent with opportunity.</p>
  <div class="cta-actions">
    <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-white btn-lg"><i class="fas fa-user-graduate"></i> I'm a Student</a>
    <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-lg" style="background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.2)"><i class="fas fa-building"></i> I'm an Employer</a>
    <a href="<?php echo $baseUrl; ?>/pages/auth/register.php" class="btn btn-lg" style="background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.2)"><i class="fas fa-chalkboard-teacher"></i> I'm an Adviser</a>
  </div>
</section>

<!-- FOOTER -->
<footer class="land-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="land-logo"><div class="logo-icon"><i class="fas fa-hexagon-nodes"></i></div>SkillHive</div>
      <p>Helping students find the right opportunities and enabling companies to hire faster through smart, simple, and reliable technology.</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul class="footer-links">
        <li><a href="#">Find Jobs</a></li>
        <li><a href="#">Post a Job</a></li>
        <li><a href="#">Browse Companies</a></li>
        <li><a href="#">Career Resources</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Company</h4>
      <ul class="footer-links">
        <li><a href="#">About Us</a></li>
        <li><a href="#">How it Works</a></li>
        <li><a href="#">FAQs</a></li>
        <li><a href="#">Contacts</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <ul class="footer-links">
        <li><a href="#">Privacy Policy</a></li>
        <li><a href="#">Terms of Service</a></li>
        <li><a href="#">Support</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?php echo date('Y'); ?> SkillHive. Connecting talent with opportunity, faster.</span>
    <div class="footer-socials">
      <a href="#"><i class="fab fa-facebook-f"></i></a>
      <a href="#"><i class="fab fa-twitter"></i></a>
      <a href="#"><i class="fab fa-linkedin-in"></i></a>
      <a href="#"><i class="fab fa-youtube"></i></a>
    </div>
  </div>
</footer>

<script>
function switchFeatTab(btn, tabId) {
  document.querySelectorAll('.feat-tab').forEach(function(t) { t.classList.remove('active'); });
  document.querySelectorAll('.feat-content').forEach(function(c) { c.classList.remove('active'); });
  btn.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}
</script>
</body>
</html>
