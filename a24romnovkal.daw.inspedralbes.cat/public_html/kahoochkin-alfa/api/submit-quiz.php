<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../private/auth.php';
require_once __DIR__ . '/../../../private/db.php';
json_headers();

// If you enable CSRF on fetch POSTs, uncomment:
// ensure_csrf();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['quizId'], $payload['choices']) || !is_array($payload['choices'])) {
  http_response_code(400); echo json_encode(['error'=>'Bad payload']); exit;
}
$quizUid = $payload['quizId'];
$choices = $payload['choices'];

// Resolve quiz id
$stmt = $mysqli->prepare("SELECT id FROM quizzes WHERE quiz_uid = ? LIMIT 1");
$stmt->bind_param("s", $quizUid);
$stmt->execute();
$stmt->bind_result($quizId);
if (!$stmt->fetch()) { $stmt->close(); http_response_code(400); echo json_encode(['error'=>'Unknown quizId']); exit; }
$stmt->close();

// Build answer key: qid -> correct (1..4)
$stmt = $mysqli->prepare("SELECT id, resposta_correcta FROM questions WHERE quiz_id = ?");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$stmt->bind_result($qid, $corr);
$key = []; $total = 0;
while ($stmt->fetch()) { $key[(int)$qid] = (int)$corr; $total++; }
$stmt->close();

// Score
$score = 0;
foreach ($choices as $c) {
  $qid2 = $c['qid'] ?? null;
  $rid2 = $c['rid'] ?? null;   // may be null on timeout
  if ($qid2 === null || $rid2 === null) continue;
  $qid2 = (int)$qid2; $rid2 = (int)$rid2;
  if (isset($key[$qid2]) && $key[$qid2] === $rid2) $score++;
}

// (Optional) Log result if logged in
$userId = current_user_id();   // null if guest
if ($userId !== null) {
  $st = $mysqli->prepare("INSERT INTO results (user_id, quiz_id, score, total) VALUES (?,?,?,?)");
  $st->bind_param("iiii", $userId, $quizId, $score, $total);
  $st->execute(); $st->close();
}

echo json_encode(['score'=>$score, 'total'=>$total], JSON_UNESCAPED_UNICODE);