<div class="page-header">
  <div>
    <h2 class="page-title">Evaluations</h2>
    <p class="page-subtitle">Rate and evaluate intern performance.</p>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Evaluation Form -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Submit Evaluation</h3></div>
      <form style="display:flex;flex-direction:column;gap:16px">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Select Intern</label>
            <select class="form-input">
              <option>— Choose Intern —</option>
              <option>Juan dela Cruz</option>
              <option>Maria Reyes</option>
              <option>Andre Lopez</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Evaluation Period</label>
            <select class="form-input">
              <option>Midterm</option>
              <option>Final</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Technical Skills</label>
          <div class="eval-stars" id="techStars">
            <i class="fas fa-star" onclick="setRating('techStars',1)"></i>
            <i class="fas fa-star" onclick="setRating('techStars',2)"></i>
            <i class="fas fa-star" onclick="setRating('techStars',3)"></i>
            <i class="fas fa-star" onclick="setRating('techStars',4)"></i>
            <i class="fas fa-star" onclick="setRating('techStars',5)"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Communication</label>
          <div class="eval-stars" id="commStars">
            <i class="fas fa-star" onclick="setRating('commStars',1)"></i>
            <i class="fas fa-star" onclick="setRating('commStars',2)"></i>
            <i class="fas fa-star" onclick="setRating('commStars',3)"></i>
            <i class="fas fa-star" onclick="setRating('commStars',4)"></i>
            <i class="fas fa-star" onclick="setRating('commStars',5)"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Work Ethic</label>
          <div class="eval-stars" id="ethicStars">
            <i class="fas fa-star" onclick="setRating('ethicStars',1)"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',2)"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',3)"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',4)"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',5)"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Comments & Feedback</label>
          <textarea class="form-input" rows="4" placeholder="Provide detailed feedback about the intern's performance..."></textarea>
        </div>

        <div><button type="button" class="btn btn-primary btn-sm">Submit Evaluation</button></div>
      </form>
    </div>

    <!-- Past Evaluations Table -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Past Evaluations</h3></div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr><th>Intern</th><th>Period</th><th>Technical</th><th>Comm.</th><th>Ethics</th><th>Overall</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Juan dela Cruz</td>
              <td>Midterm</td>
              <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.5</span></td>
              <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.0</span></td>
              <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 5.0</span></td>
              <td><span style="font-weight:700;color:#10B981">4.5</span></td>
            </tr>
            <tr>
              <td>Maria Reyes</td>
              <td>Midterm</td>
              <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.0</span></td>
              <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.5</span></td>
              <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.0</span></td>
              <td><span style="font-weight:700;color:#10B981">4.2</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Summary -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Eval Summary</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row"><span>Total Evaluations</span><span style="font-weight:700">6</span></div>
        <div class="mini-row"><span>Average Rating</span><span style="font-weight:700;color:#F59E0B"><i class="fas fa-star"></i> 4.3</span></div>
        <div class="mini-row"><span>Pending</span><span style="font-weight:700;color:#EF4444">2</span></div>
      </div>
    </div>

    <!-- Rating Guide -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Rating Guide</h3></div>
      <div style="display:flex;flex-direction:column;gap:6px;font-size:.82rem">
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 5</span><span>Outstanding</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 4</span><span>Very Good</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 3</span><span>Good</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 2</span><span>Fair</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 1</span><span>Needs Improvement</span></div>
      </div>
    </div>
  </div>
</div>

<script>
function setRating(groupId, rating) {
  var stars = document.getElementById(groupId).querySelectorAll('.fa-star');
  stars.forEach(function(star, index) {
    star.style.color = index < rating ? '#F59E0B' : '#ddd';
  });
}
</script>
