<?php
/**
 * Script to generate dummy analytics data
 * Run once to populate database with sample application data for testing
 * Access: /SkillHive/pages/employer/generate_dummy_data.php
 */
require_once __DIR__ . '/../../backend/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['confirm'])) {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Generate Dummy Analytics Data</title>
    <style>
      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
      }
      .card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        background: #fff;
      }
      h1 {
        margin: 0 0 16px 0;
        font-size: 24px;
      }
      .warning {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 16px;
        border-radius: 6px;
        margin: 16px 0;
        font-size: 14px;
      }
      .info {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        padding: 16px;
        border-radius: 6px;
        margin: 16px 0;
        font-size: 14px;
      }
      button {
        background: #0f766e;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        margin-top: 16px;
      }
      button:hover {
        background: #134e4a;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Generate Dummy Analytics Data</h1>
      <div class="info">
        <strong>What this does:</strong> Creates sample application data for the last 30 days for testing the analytics dashboard.
      </div>
      <div class="warning">
        <strong>Warning:</strong> This will create new application records. Make sure you want to add dummy data before proceeding.
      </div>
      <form method="POST">
        <button type="submit">Generate Dummy Data</button>
      </form>
      <p style="font-size: 12px; color: #6b7280; margin-top: 16px;">
        This script will add ~300-500 sample applications across your internships from the last 30 days.
      </p>
    </div>
  </body>
  </html>
  <?php
  exit;
}

try {
  // Get all employers with internships
  $stmt = $pdo->query('
    SELECT DISTINCT i.employer_id, e.company_name
    FROM internship i
    INNER JOIN employer e ON i.employer_id = e.employer_id
    WHERE i.status = "Active"
  ');
  
  $employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  $totalAdded = 0;
  
  foreach ($employers as $employer) {
    $employerId = (int)$employer['employer_id'];
    
    // Get internships for this employer
    $stmt = $pdo->prepare('
      SELECT internship_id FROM internship
      WHERE employer_id = :employer_id AND status = "Active"
      LIMIT 3
    ');
    $stmt->execute([':employer_id' => $employerId]);
    $internships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($internships)) {
      continue;
    }
    
    // Get active students
    $stmt = $pdo->query('
      SELECT student_id FROM student LIMIT 50
    ');
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
      continue;
    }
    
    // Generate applications for last 30 days
    $insertStmt = $pdo->prepare('
      INSERT INTO application (internship_id, student_id, status, compatibility_score, application_date)
      VALUES (:internship_id, :student_id, :status, :score, :date)
    ');
    
    $statuses = ['Pending', 'Accepted', 'Rejected'];
    $today = new DateTime();
    
    for ($i = 0; $i < 100; $i++) {
      $randomInternship = $internships[array_rand($internships)];
      $randomStudent = $students[array_rand($students)];
      $randomStatus = $statuses[array_rand($statuses)];
      $randomScore = rand(60, 95);
      
      // Random date in last 30 days
      $daysAgo = rand(0, 29);
      $date = (clone $today)->modify("-{$daysAgo} days")->format('Y-m-d');
      
      $insertStmt->execute([
        ':internship_id' => $randomInternship['internship_id'],
        ':student_id' => $randomStudent['student_id'],
        ':status' => $randomStatus,
        ':score' => $randomScore,
        ':date' => $date
      ]);
      
      $totalAdded++;
    }
  }
  
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Dummy Data Generated</title>
    <style>
      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
      }
      .card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        background: #fff;
      }
      h1 {
        margin: 0 0 16px 0;
        font-size: 24px;
        color: #10b981;
      }
      .success {
        background: #d1fae5;
        border-left: 4px solid #10b981;
        padding: 16px;
        border-radius: 6px;
        margin: 16px 0;
      }
      a {
        color: #0f766e;
        text-decoration: none;
        font-weight: 600;
      }
      a:hover {
        text-decoration: underline;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>✓ Dummy Data Generated Successfully</h1>
      <div class="success">
        <strong><?php echo number_format($totalAdded); ?> sample applications</strong> have been added to your database across your internships for the last 30 days.
      </div>
      <p>You can now view the analytics dashboard to see the charts and metrics.</p>
      <p><a href="/SkillHive/layout.php?page=employer/analytics">View Analytics Dashboard →</a></p>
    </div>
  </body>
  </html>
  <?php

} catch (Exception $e) {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Error</title>
    <style>
      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
      }
      .card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        background: #fff;
      }
      .error {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        padding: 16px;
        border-radius: 6px;
        margin: 16px 0;
        color: #991b1b;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Error</h1>
      <div class="error">
        <strong>An error occurred:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
      </div>
    </div>
  </body>
  </html>
  <?php
}
