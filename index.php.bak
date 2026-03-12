<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SkillHive — BatState-U OJT Platform</title>
  <link rel="stylesheet" href="assets/css/skillhive.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<!-- TOP NAV -->
<nav class="top-nav">
  <a href="index.php" class="logo-area">
    <svg class="logo-icon" viewBox="0 0 32 32" fill="none">
      <path d="M16 2L4 8v8c0 7.2 5.2 13.9 12 15.5C23.8 29.9 28 23.2 28 16V8L16 2z" fill="#C8102E" opacity=".9"/>
      <path d="M13 16.5l-2-2-1.4 1.4 3.4 3.4 6.4-6.4-1.4-1.4L13 16.5z" fill="white"/>
    </svg>
    <span class="logo-text"><span class="skill">Skill</span><span class="hive">Hive</span></span>
  </a>
  <div class="top-nav-right">
    <?php if ($isLoggedIn): ?>
      <?php
        $dashboardUrl = 'layout.php';
        if ($role === 'admin') {
            $dashboardUrl = 'layout.php?page=admin/dashboard';
        }
      ?>
      <a href="<?php echo $dashboardUrl; ?>" class="nav-btn">Dashboard</a>
    <?php else: ?>
      <a href="pages/auth/login.php" class="nav-btn">Sign In</a>
      <a href="pages/auth/register.php" class="nav-btn red">Get Started</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-text">
    <div class="hero-badge">🎓 Batangas State University — OJT Platform</div>
    <h1>Your Internship Journey <span>Starts Here</span></h1>
    <p>SkillHive connects BatState-U students with top companies through AI-powered matching, real-time tracking, and smart evaluation tools — all in one platform.</p>
    <div class="hero-btns">
      <a href="pages/auth/register.php" class="btn btn-gold btn-lg">Get Started Free</a>
      <a href="#features" class="btn btn-lg btn-outline-white">Explore Features</a>
    </div>
    <div class="hero-stats">
      <div>
        <div class="hero-stat-val">1,200+</div>
        <div class="hero-stat-label">Students Placed</div>
      </div>
      <div>
        <div class="hero-stat-val">350+</div>
        <div class="hero-stat-label">Partner Companies</div>
      </div>
      <div>
        <div class="hero-stat-val">94%</div>
        <div class="hero-stat-label">Satisfaction Rate</div>
      </div>
    </div>
  </div>
  <div class="hero-visual">
    <div class="float-card">
      <div class="fc-label">AI Match Found</div>
      <div class="fc-title">Software Engineering Intern — Accenture Philippines</div>
      <div class="fc-match">92% Match</div>
      <div class="fc-bar"><div class="fc-fill" style="width:92%"></div></div>
    </div>
    <div class="float-card2">
      <div class="fc-label">OJT Progress</div>
      <div class="fc-title">Week 5 of 13 — 152 hrs logged</div>
      <div style="display:flex;gap:10px;align-items:center;margin-top:6px;">
        <div class="fc-bar" style="flex:1"><div class="fc-fill gold" style="width:38%"></div></div>
        <span style="color:var(--gold);font-weight:800;font-size:12px;">38%</span>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="section-label"><span>What We Offer</span></div>
  <div class="section-title">Everything You Need for a <span>Successful OJT</span></div>
  <div class="section-sub">From finding the perfect company to completing your internship — SkillHive supports every step of your journey.</div>
  <div class="feature-grid">
    <div class="feature-card">
      <div class="fc-icon">🤖</div>
      <h3>AI-Powered Matching</h3>
      <p>Our intelligent algorithm matches students with companies based on skills, location, and career goals — not just keywords.</p>
    </div>
    <div class="feature-card">
      <div class="fc-icon">🔍</div>
      <h3>External Listings</h3>
      <p>Browse thousands of internship opportunities from LinkedIn, Jobstreet, and local BatState-U partner companies in one place.</p>
    </div>
    <div class="feature-card">
      <div class="fc-icon">📄</div>
      <h3>Resume Scorer</h3>
      <p>Get instant AI feedback on your resume and cover letter with actionable tips to improve your chances of getting hired.</p>
    </div>
    <div class="feature-card">
      <div class="fc-icon">🎙️</div>
      <h3>Mock Interview Coach</h3>
      <p>Practice common HR and technical questions with our AI interviewer and receive detailed feedback to sharpen your answers.</p>
    </div>
    <div class="feature-card">
      <div class="fc-icon">⏱️</div>
      <h3>Hour Tracker</h3>
      <p>Digitally log your daily tasks and hours. Get supervisor sign-off and automatic OJT hour computation.</p>
    </div>
    <div class="feature-card">
      <div class="fc-icon">💬</div>
      <h3>Integrated Messaging</h3>
      <p>Stay connected with coordinators, supervisors, and classmates through secure in-app messaging with notifications.</p>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how">
  <div class="section-label"><span>How It Works</span></div>
  <div class="section-title">Get Started in <span>4 Simple Steps</span></div>
  <div class="steps-grid">
    <div class="step-card">
      <div class="step-num">1</div>
      <h3>Create Your Profile</h3>
      <p>Sign up as a Student, Employer, or OJT Adviser. Complete your profile with skills and career interests.</p>
    </div>
    <div class="step-card">
      <div class="step-num">2</div>
      <h3>AI Matching</h3>
      <p>Our AI analyzes your profile and recommends the best internship opportunities tailored to your skills.</p>
    </div>
    <div class="step-card">
      <div class="step-num">3</div>
      <h3>Apply & Get Placed</h3>
      <p>Send your application directly through the platform. Track status in real time and receive instant notifications.</p>
    </div>
    <div class="step-card">
      <div class="step-num">4</div>
      <h3>Complete & Evaluate</h3>
      <p>Log your OJT hours, submit evaluations, and earn your completion certificate — all within SkillHive.</p>
    </div>
  </div>
</section>

<!-- FOR EVERYONE -->
<section class="roles-section" id="roles">
  <div class="section-label"><span style="color:#FF8A9A">Who It's For</span></div>
  <div class="section-title" style="color:white">Built for <span>Everyone</span> in the OJT Journey</div>
  <div class="roles-grid">
    <a href="pages/auth/register.php" class="role-card">
      <div class="role-card-icon">🎓</div>
      <h3>Students</h3>
      <p>Find the perfect OJT placement, track your hours, and build your career profile from day one.</p>
      <ul class="role-card-features">
        <li>AI-powered job matching</li>
        <li>Resume scorer & feedback</li>
        <li>Digital hour tracker</li>
        <li>Mock interview practice</li>
      </ul>
    </a>
    <a href="pages/auth/register.php" class="role-card">
      <div class="role-card-icon">🏢</div>
      <h3>Employers / Companies</h3>
      <p>Post internship openings and discover pre-vetted, skill-matched candidates from BatState-U.</p>
      <ul class="role-card-features">
        <li>Post internship listings</li>
        <li>Browse matched applicants</li>
        <li>Monitor intern progress</li>
        <li>Submit performance evaluations</li>
      </ul>
    </a>
    <a href="pages/auth/register.php" class="role-card">
      <div class="role-card-icon">👨‍🏫</div>
      <h3>OJT Advisers / Professors</h3>
      <p>Oversee your students' entire OJT journey — from application to completion — in one dashboard.</p>
      <ul class="role-card-features">
        <li>Student monitoring</li>
        <li>Company verification</li>
        <li>OJT progress tracking</li>
        <li>Evaluation management</li>
      </ul>
    </a>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <h2>Ready to Start Your OJT Journey?</h2>
  <p>Join thousands of BatState-U students and companies already using SkillHive.</p>
  <div class="cta-btns">
    <a href="pages/auth/register.php" class="btn btn-lg" style="background:white;color:var(--red);font-weight:800;">Create Free Account</a>
    <a href="pages/auth/login.php" class="btn btn-lg btn-outline-white">Sign In</a>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer-bar">
  <div>
    <div class="logo-text" style="font-size:15px;"><span class="skill">Skill</span><span class="hive">Hive</span></div>
    <div class="footer-note" style="margin-top:4px;">&copy; <?php echo date('Y'); ?> Batangas State University — OJT Management Platform</div>
  </div>
  <div class="footer-links">
    <a href="#">Privacy Policy</a>
    <a href="#">Terms of Use</a>
    <a href="#">Support</a>
  </div>
</footer>

</body>
</html>

