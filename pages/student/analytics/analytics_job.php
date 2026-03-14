<?php

function analytics_job_load(PDO $pdo, int $studentId): array
{
  return analytics_load_data($pdo, $studentId);
}
