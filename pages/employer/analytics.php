<?php
/**
 * Purpose: Employer analytics dashboard
 * Shows graphs for inquiries, applications, and recruitment metrics
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null) ?? 0;

if ($employerId <= 0) {
  header('Location: ' . $baseUrl . '/layout.php?page=employer/dashboard');
  exit;
}

// Get date range (last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Fetch applications timeline data
$applicationsQuery = "
  SELECT DATE(a.application_date) as date, COUNT(*) as count
  FROM application a
  INNER JOIN internship i ON a.internship_id = i.internship_id
  WHERE i.employer_id = :employer_id
    AND a.application_date >= :start_date
  GROUP BY DATE(a.application_date)
  ORDER BY date ASC
";

$stmt = $pdo->prepare($applicationsQuery);
$stmt->execute([':employer_id' => $employerId, ':start_date' => $startDate]);
$applicationsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch inquiries (page views/interest - simulated from applications)
$inquiriesQuery = "
  SELECT DATE(a.application_date) as date, COUNT(*) * 2 as count
  FROM application a
  INNER JOIN internship i ON a.internship_id = i.internship_id
  WHERE i.employer_id = :employer_id
    AND a.application_date >= :start_date
  GROUP BY DATE(a.application_date)
  ORDER BY date ASC
";

$stmt = $pdo->prepare($inquiriesQuery);
$stmt->execute([':employer_id' => $employerId, ':start_date' => $startDate]);
$inquiriesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch application status breakdown
$statusQuery = "
  SELECT a.status, COUNT(*) as count
  FROM application a
  INNER JOIN internship i ON a.internship_id = i.internship_id
  WHERE i.employer_id = :employer_id
  GROUP BY a.status
";

$stmt = $pdo->prepare($statusQuery);
$stmt->execute([':employer_id' => $employerId]);
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch top performing internships
$topQuery = "
  SELECT i.title, COUNT(a.application_id) as applications
  FROM internship i
  LEFT JOIN application a ON i.internship_id = a.internship_id
  WHERE i.employer_id = :employer_id
  GROUP BY i.internship_id, i.title
  ORDER BY applications DESC
  LIMIT 5
";

$stmt = $pdo->prepare($topQuery);
$stmt->execute([':employer_id' => $employerId]);
$topPostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total stats
$totalApplications = array_sum(array_column($applicationsData, 'count'));
$totalInquiries = array_sum(array_column($inquiriesData, 'count'));

// Fetch KPI stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM internship WHERE employer_id = :employer_id AND status = 'Active'");
$stmt->execute([':employer_id' => $employerId]);
$activePostings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM application a INNER JOIN internship i ON a.internship_id = i.internship_id WHERE i.employer_id = :employer_id AND a.status = 'Shortlisted'");
$stmt->execute([':employer_id' => $employerId]);
$shortlisted = (int)$stmt->fetchColumn();

// Prepare data for charts
$chartDates = [];
$chartApplications = [];
$chartInquiries = [];

$allDates = [];
foreach ($applicationsData as $row) {
  $allDates[$row['date']] = true;
}
foreach ($inquiriesData as $row) {
  $allDates[$row['date']] = true;
}

ksort($allDates);

$appMap = array_column($applicationsData, 'count', 'date');
$inqMap = array_column($inquiriesData, 'count', 'date');

foreach ($allDates as $date => $v) {
  $chartDates[] = date('M d', strtotime($date));
  $chartApplications[] = $appMap[$date] ?? 0;
  $chartInquiries[] = $inqMap[$date] ?? 0;
}

// Status breakdown
$statusLabels = [];
$statusCounts = [];
foreach ($statusData as $row) {
  $statusLabels[] = $row['status'];
  $statusCounts[] = (int)$row['count'];
}

// Top postings
$topLabels = [];
$topCounts = [];
foreach ($topPostings as $row) {
  $topLabels[] = substr($row['title'], 0, 20);
  $topCounts[] = (int)$row['applications'];
}

if (empty($topLabels)) {
  $topLabels = ['No Data'];
  $topCounts = [0];
}
?>

<div class="analytics-banner">
  <div class="analytics-main">
    <div class="analytics-info">
      <div class="analytics-date"><?php echo date('l, jS F'); ?></div>
      <div class="analytics-title">Good afternoon!</div>
      <div class="analytics-desc">View your recruitment analytics, application trends, and posting performance.</div>
    </div>
  </div>
  <button type="button" class="analytics-toggle" onclick="toggleAnalyticsBanner()" title="Hide banner">
    <i class="fas fa-chevron-up"></i>
  </button>
  <div class="analytics-expand-hint" onclick="toggleAnalyticsBanner()">
    <i class="fas fa-chevron-down"></i> Show banner
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card employer-stat-postings">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Applicants.png" alt="Total Applications"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">last 30 days</div>
        <div class="stat-card-num"><?php echo $totalApplications; ?></div>
      </div>
      <div class="stat-card-label">Total Applications</div>
    </div>
  </div>
  <div class="stat-card employer-stat-applicants">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Inquiries.png" alt="Total Inquiries"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">estimated</div>
        <div class="stat-card-num"><?php echo $totalInquiries; ?></div>
      </div>
      <div class="stat-card-label">Total Inquiries</div>
    </div>
  </div>
  <div class="stat-card employer-stat-interviews">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Active%20Posting.png" alt="Active Postings"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">postings</div>
        <div class="stat-card-num"><?php echo $activePostings; ?></div>
      </div>
      <div class="stat-card-label">Active Postings</div>
    </div>
  </div>
  <div class="stat-card employer-stat-hired">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Shortlisted.png" alt="Shortlisted"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">candidates</div>
        <div class="stat-card-num"><?php echo $shortlisted; ?></div>
      </div>
      <div class="stat-card-label">Shortlisted</div>
    </div>
  </div>
</div>

<style>
.analytics-banner {
  background:
    radial-gradient(circle at 95% 50%, rgba(6, 78, 59, 0.65) 0%, transparent 70%),
    radial-gradient(circle at 85% 50%, rgba(15, 118, 110, 0.55) 0%, transparent 60%),
    linear-gradient(90deg, #ffffff 0%, #f0fdfa 25%, #134e4a 60%, #0f766e 85%, #0d5f58 100%);
  border-radius: 16px;
  padding: 20px 28px;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  position: relative;
  overflow: hidden;
  color: #111827;
  border: 1.5px solid rgba(15, 118, 110, 0.35);
  box-shadow: 0 8px 32px rgba(15, 118, 110, 0.15), 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.analytics-banner::before {
  content: '';
  position: absolute;
  left: 20px;
  top: 50%;
  transform: translateY(-50%);
  width: 550px;
  height: 550px;
  background-image: url('/SkillHive/assets/media/banner%20other.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  opacity: 0.25;
  pointer-events: none;
}

.analytics-banner::after {
  content: '';
  position: absolute;
  right: 20px;
  top: 30%;
  transform: translateY(-50%);
  width: 500px;
  height: 500px;
  background-image: url('/SkillHive/assets/media/Banner.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  opacity: 0.35;
  pointer-events: none;
}

.analytics-banner.collapsed {
  padding: 8px 16px;
  min-height: 0;
}

.analytics-banner.collapsed .analytics-main {
  display: none;
}

.analytics-main {
  display: flex;
  align-items: center;
  gap: 24px;
  position: relative;
  z-index: 1;
  flex: 1;
}

.analytics-info {
  flex: 1;
  border-left: 1.5px solid rgba(255, 255, 255, 0.25);
  padding-left: 16px;
}

.analytics-date {
  font-size: 12px;
  font-weight: 100;
  color: #9ca3af;
  margin-bottom: 4px;
  letter-spacing: 1px;
}

.analytics-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 2px;
  text-transform: capitalize;
  display: inline;
}

.analytics-desc {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.5;
  max-width: 450px;
}

.analytics-toggle {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(20, 184, 166, 0.15);
  color: #0f766e;
  width: 36px;
  height: 36px;
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

.analytics-toggle:hover {
  background: #fff;
  border-color: rgba(20, 184, 166, 0.3);
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(20, 184, 166, 0.1);
}

@media (max-width: 768px) {
  .analytics-banner { flex-direction: column; text-align: center; }
}

.analytics-container {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.analytics-sidebar {
  flex: 0 0 300px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  height: fit-content;
}

.analytics-sidebar h3 {
  margin: 0 0 16px 0;
  font-size: 14px;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.analytics-main-content {
  flex: 1;
}

.top-picks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.top-pick-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  text-align: center;
}

.top-pick-card .posting-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.top-pick-card .posting-count {
  font-size: 24px;
  font-weight: 700;
  color: #0f766e;
}

.top-pick-card .posting-label {
  font-size: 12px;
  color: #9ca3af;
}

.chart-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  margin-bottom: 16px;
}

.chart-card h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.chart-wrapper {
  position: relative;
  height: 300px;
  margin-bottom: 16px;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.no-data {
  text-align: center;
  padding: 40px;
  color: #6b7280;
}

.no-data i {
  font-size: 48px;
  margin-bottom: 16px;
  opacity: 0.3;
  display: block;
}

@media (max-width: 1024px) {
  .analytics-container {
    flex-direction: column;
  }
  .analytics-sidebar {
    flex: 0 0 auto;
  }
}
</style>

<div class="analytics-container">
  <!-- Left Sidebar with Filters -->
  <div class="analytics-sidebar">
    <h3><i class="fas fa-filter" style="margin-right:8px;"></i>Filters</h3>
    <p style="font-size: 13px; color: #6b7280; text-align: center; padding: 20px 0;">
      Filter options coming soon
    </p>
  </div>

  <!-- Main Content -->
  <div class="analytics-main-content">
    <!-- Top Performing Internships Cards -->
    <h3 style="font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 16px 0;">
      <i class="fas fa-star" style="color:#f59e0b;margin-right:8px;"></i>Top Performing Internships
    </h3>
    <div class="top-picks-grid">
      <?php if (!empty($topPostings)): ?>
        <?php foreach ($topPostings as $posting): ?>
          <div class="top-pick-card">
            <div class="posting-title"><?php echo htmlspecialchars($posting['title']); ?></div>
            <div class="posting-count"><?php echo $posting['applications']; ?></div>
            <div class="posting-label">Applications</div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-data" style="grid-column: 1 / -1;">
          <i class="fas fa-inbox"></i>
          <p>No application data available</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
      <div class="chart-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-chart-line" style="color:#3b82f6;margin-right:8px;"></i>Inquiries & Applications (30 Days)</h3>
        <div class="chart-wrapper">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
      
      <div class="chart-card">
        <h3><i class="fas fa-pie-chart" style="color:#10b981;margin-right:8px;"></i>Application Status</h3>
        <div class="chart-wrapper">
          <canvas id="statusChart"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-bar-chart" style="color:#6366f1;margin-right:8px;"></i>Applications Breakdown</h3>
        <div class="chart-wrapper">
          <canvas id="topChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Trend Chart (Inquiries & Applications)
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($chartDates); ?>,
    datasets: [
      {
        label: 'Inquiries',
        data: <?php echo json_encode($chartInquiries); ?>,
        borderColor: '#0f766e',
        backgroundColor: 'rgba(15, 118, 110, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#0f766e',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      },
      {
        label: 'Applications',
        data: <?php echo json_encode($chartApplications); ?>,
        borderColor: '#12b3ac',
        backgroundColor: 'rgba(18, 179, 172, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#12b3ac',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          font: { size: 12, weight: 500 },
          color: '#6b7280',
          usePointStyle: true,
          padding: 16,
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: '#f3f4f6',
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      },
      x: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      }
    }
  }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($statusLabels); ?>,
    datasets: [{
      data: <?php echo json_encode($statusCounts); ?>,
      backgroundColor: [
        '#0f766e',
        '#12b3ac',
        '#0d5f58',
        '#134e4a',
        '#10b981',
      ],
      borderColor: '#fff',
      borderWidth: 2,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'right',
        labels: {
          font: { size: 12 },
          color: '#6b7280',
          padding: 16,
          usePointStyle: true,
        }
      }
    }
  }
});

// Top Postings Chart
const topCtx = document.getElementById('topChart').getContext('2d');
new Chart(topCtx, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($topLabels); ?>,
    datasets: [{
      label: 'Applications',
      data: <?php echo json_encode($topCounts); ?>,
      backgroundColor: '#0f766e',
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      }
    },
    scales: {
      x: {
        beginAtZero: true,
        grid: {
          color: '#f3f4f6',
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      },
      y: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      }
    }
  }
  });
</script>

<script>
function toggleAnalyticsBanner() {
  document.querySelector('.analytics-banner').classList.toggle('collapsed');
}
</script>
