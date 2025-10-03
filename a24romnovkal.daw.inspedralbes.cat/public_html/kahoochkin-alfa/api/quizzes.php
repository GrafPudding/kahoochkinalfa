<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../private/auth.php';
require_once __DIR__ . '/../../../private/db.php';
json_headers();

if (!is_admin()) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $res = $mysqli->query("SELECT id, quiz_uid, title FROM quizzes ORDER BY id DESC");
  $items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  ensure_csrf();
  $p = json_decode(file_get_contents('php://input'), true);
  $quiz_uid = trim($p['quiz_uid'] ?? '');
  $title    = trim($p['title'] ?? '');
  if ($quiz_uid===''){ http_response_code(400); echo json_encode(['error'=>'quiz_uid required']); exit; }
  $owner = current_user_id();
  
  $st = $mysqli->prepare("INSERT INTO quizzes (quiz_uid, title, owner_user_id) VALUES (?,?,?)");
  $st->bind_param("ssi", $quiz_uid, $title, $owner);
  if (!$st->execute()) { http_response_code(409); echo json_encode(['error'=>'quiz_uid exists?']); exit; }
  echo json_encode(['ok'=>true, 'id'=>$st->insert_id], JSON_UNESCAPED_UNICODE);
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);