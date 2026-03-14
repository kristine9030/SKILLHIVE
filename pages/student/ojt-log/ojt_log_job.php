<?php

function ojt_log_load_logs(PDO $pdo, ?array $ojt, int $limit = 30): array
{
  if (!$ojt) {
    return [];
  }

  $stmt = $pdo->prepare('SELECT * FROM daily_log WHERE record_id = ? ORDER BY log_date DESC, log_id DESC LIMIT ' . max(1, (int) $limit));
  $stmt->execute([(int) $ojt['record_id']]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
