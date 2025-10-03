<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../private/db.php';

header('Content-Type: application/json; charset=utf-8');

// Sanity check the connection (optional but helpful)
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
  http_response_code(500);
  echo json_encode(['items'=>[], 'error'=>'DB not initialized']);
  exit;
}

// Minimal public list (no auth)
$sql = "SELECT quiz_uid, COALESCE(NULLIF(title,''), quiz_uid) AS title
        FROM quizzes
        ORDER BY title ASC";
$res = $mysqli->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['items'=>[], 'error'=>$mysqli->error]);
  exit;
}

$items = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);