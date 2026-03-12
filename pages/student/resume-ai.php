<div class="page-header">
  <div>
    <h2 class="page-title">Resume AI</h2>
    <p class="page-subtitle">Get your resume scored and improved with AI suggestions.</p>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Upload Section -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Upload Resume</h3></div>
      <div class="upload-zone" id="uploadZone">
        <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:#ccc;margin-bottom:12px"></i>
        <div style="font-weight:600;font-size:.95rem;margin-bottom:4px">Drop your resume here</div>
        <div style="font-size:.82rem;color:#999;margin-bottom:14px">Supports PDF, DOCX (max 5MB)</div>
        <button class="btn btn-primary btn-sm">Browse Files</button>
      </div>
    </div>

    <!-- AI Suggestions -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>AI Improvement Suggestions</h3></div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div style="padding:14px;background:#f0fdf4;border-radius:10px;border-left:3px solid #10B981">
          <div style="font-weight:600;font-size:.85rem;color:#10B981;margin-bottom:4px"><i class="fas fa-check-circle"></i> Strong Summary</div>
          <div style="font-size:.82rem;color:#666">Your professional summary effectively highlights key skills and career goals.</div>
        </div>
        <div style="padding:14px;background:#fefce8;border-radius:10px;border-left:3px solid #F59E0B">
          <div style="font-weight:600;font-size:.85rem;color:#F59E0B;margin-bottom:4px"><i class="fas fa-exclamation-triangle"></i> Add Quantifiable Achievements</div>
          <div style="font-size:.82rem;color:#666">Include metrics like "Increased conversion by 25%" instead of "Improved website performance."</div>
        </div>
        <div style="padding:14px;background:#fefce8;border-radius:10px;border-left:3px solid #F59E0B">
          <div style="font-weight:600;font-size:.85rem;color:#F59E0B;margin-bottom:4px"><i class="fas fa-exclamation-triangle"></i> Skills Section Formatting</div>
          <div style="font-size:.82rem;color:#666">Group skills by category (Frontend, Backend, Tools) for better readability.</div>
        </div>
        <div style="padding:14px;background:#fef2f2;border-radius:10px;border-left:3px solid #EF4444">
          <div style="font-weight:600;font-size:.85rem;color:#EF4444;margin-bottom:4px"><i class="fas fa-times-circle"></i> Missing Keywords</div>
          <div style="font-size:.82rem;color:#666">Add industry keywords: "Agile", "Git", "REST API" to improve ATS compatibility.</div>
        </div>
      </div>
    </div>

    <!-- Mock Interview -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Mock Interview Bot</h3></div>
      <div class="chat-area" style="max-height:300px;overflow-y:auto">
        <div class="chat-msg bot">
          <div class="chat-bubble">Hi! I'm your AI interview coach. Tell me which position you're applying for, and I'll generate practice questions.</div>
        </div>
        <div class="chat-msg user">
          <div class="chat-bubble">I'm applying for a UI/UX Design internship at Google.</div>
        </div>
        <div class="chat-msg bot">
          <div class="chat-bubble">Great choice! Here are some questions you might get:
            <br><br>1. Walk me through your design process for a mobile app.
            <br>2. How would you improve Google Maps for users in the Philippines?
            <br>3. Describe a time you received critical feedback on your design.
            <br><br>Would you like to practice answering any of these?</div>
        </div>
      </div>
      <div class="chat-input">
        <input type="text" placeholder="Type your answer...">
        <button class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Score Gauge -->
    <div class="panel-card" style="text-align:center">
      <div class="panel-card-header" style="justify-content:center"><h3>Resume Score</h3></div>
      <div class="score-gauge">
        <svg width="120" height="120">
          <circle cx="60" cy="60" r="50" stroke="#F0F0F0" stroke-width="8" fill="none"/>
          <circle cx="60" cy="60" r="50" fill="none" stroke="#06B6D4" stroke-width="8" stroke-linecap="round" stroke-dasharray="314" stroke-dashoffset="25" transform="rotate(-90,60,60)"/>
        </svg>
        <div class="score-gauge-val">92</div>
      </div>
      <div style="font-size:.82rem;color:#999;margin-top:8px">Excellent — Top 15%</div>
      <div style="margin-top:10px;font-size:.78rem;color:#10B981"><i class="fas fa-arrow-up"></i> +8 pts from last version</div>
    </div>

    <!-- Score Breakdown -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Breakdown</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div>
          <div class="skill-bar-header"><span>Content Quality</span><span>95%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:95%;background:#10B981"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Formatting</span><span>90%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:90%;background:#06B6D4"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Keywords / ATS</span><span>85%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:85%;background:#F59E0B"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Impact Statements</span><span>78%</span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:78%;background:#EF4444"></div></div>
        </div>
      </div>
    </div>

    <!-- Readiness -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Internship Readiness</h3></div>
      <div style="text-align:center">
        <div style="font-size:2rem;font-weight:800;color:#111">85<span style="font-size:1rem;color:#999">/100</span></div>
        <div style="font-size:.78rem;color:#10B981;margin-top:4px">Ready for applications!</div>
      </div>
    </div>
  </div>
</div>
