<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../private/auth.php';
require_once __DIR__ . '/../../../private/db.php';
json_headers();

//admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!is_admin()) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }
  ensure_csrf();

  $p = json_decode(file_get_contents('php://input'), true) ?? [];
  $action = $p['action'] ?? '';

  if ($action === 'update_quiz') {
    $id    = (int)($p['id'] ?? 0);
    $uid   = trim($p['quiz_uid'] ?? '');
    $title = trim($p['title'] ?? '');
    if ($id <= 0 || $uid === '') {
      http_response_code(400); echo json_encode(['error'=>'id and quiz_uid required']); exit;
    }
    $st = $mysqli->prepare("UPDATE quizzes SET quiz_uid=?, title=? WHERE id=?");
    $st->bind_param("ssi", $uid, $title, $id);
    if (!$st->execute()) { http_response_code(500); echo json_encode(['error'=>'update failed']); exit; }
    echo json_encode(['ok'=>true]); exit;
  }

  if ($action === 'add_question') {
    $quiz_id  = (int)($p['quiz_id'] ?? 0);
    $pregunta = trim($p['pregunta'] ?? '');
    $imatge   = trim($p['imatge'] ?? '');
    $ans = [
      1 => trim($p['ans_1'] ?? ''),
      2 => trim($p['ans_2'] ?? ''),
      3 => trim($p['ans_3'] ?? ''),
      4 => trim($p['ans_4'] ?? ''),
    ];
    $corr = (int)($p['resposta_correcta'] ?? 1);
    if ($quiz_id<=0 || $pregunta==='' || $ans[1]==='' || $ans[2]==='' || $ans[3]==='' || $ans[4]==='' || $corr<1 || $corr>4) {
      http_response_code(400); echo json_encode(['error'=>'Bad payload']); exit;
    }

    $mysqli->begin_transaction();
    try {
      $img = ($imatge === '') ? null : $imatge;
      $st = $mysqli->prepare("INSERT INTO questions (quiz_id, pregunta, resposta_correcta, imatge) VALUES (?,?,?,?)");
      $st->bind_param("isis", $quiz_id, $pregunta, $corr, $img);
      if (!$st->execute()) throw new Exception('Q insert failed');
      $qid = $st->insert_id; $st->close();

      $st = $mysqli->prepare("INSERT INTO answers (question_id, ordre, etiqueta) VALUES (?,?,?)");
      foreach ([1,2,3,4] as $i) {
        $st->bind_param("iis", $qid, $i, $ans[$i]);
        if (!$st->execute()) throw new Exception('A insert failed');
      }
      $st->close();

      $mysqli->commit();
      echo json_encode(['ok'=>true, 'id'=>$qid]); exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      http_response_code(500);
      echo json_encode([
        'error' => 'insert failed',
        'msg'   => $e->getMessage(),   // <— show exception text
        'sql'   => $mysqli->error      // <— show mysqli error if any
    ]);
      exit;
    }
  }

  if ($action === 'update_question') {
    $qid      = (int)($p['question_id'] ?? 0);
    $pregunta = trim($p['pregunta'] ?? '');
    $imatge   = trim($p['imatge'] ?? '');
    $ans = [
      1 => trim($p['ans_1'] ?? ''),
      2 => trim($p['ans_2'] ?? ''),
      3 => trim($p['ans_3'] ?? ''),
      4 => trim($p['ans_4'] ?? ''),
    ];
    $corr = (int)($p['resposta_correcta'] ?? 1);
    if ($qid<=0 || $pregunta==='' || $ans[1]==='' || $ans[2]==='' || $ans[3]==='' || $ans[4]==='' || $corr<1 || $corr>4) {
      http_response_code(400); echo json_encode(['error'=>'Bad payload']); exit;
    }

    $mysqli->begin_transaction();
    try {
      $img = ($imatge === '') ? null : $imatge;
      $st = $mysqli->prepare("UPDATE questions SET pregunta=?, resposta_correcta=?, imatge=? WHERE id=?");
      $st->bind_param("sisi", $pregunta, $corr, $img, $qid);
      if (!$st->execute()) throw new Exception('Q update failed');
      $st->close();

      $st = $mysqli->prepare("DELETE FROM answers WHERE question_id=?");
      $st->bind_param("i", $qid);
      if (!$st->execute()) throw new Exception('A delete failed');
      $st->close();

      $st = $mysqli->prepare("INSERT INTO answers (question_id, ordre, etiqueta) VALUES (?,?,?)");
      foreach ([1,2,3,4] as $i) {
        $st->bind_param("iis", $qid, $i, $ans[$i]);
        if (!$st->execute()) throw new Exception('A insert failed');
      }
      $st->close();

      $mysqli->commit();
      echo json_encode(['ok'=>true]); exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      http_response_code(500); echo json_encode(['error'=>'update failed']); exit;
    }
  }

  if ($action === 'delete_question') {
    $qid = (int)($p['question_id'] ?? 0);
    if ($qid <= 0) { http_response_code(400); echo json_encode(['error'=>'question_id required']); exit; }

    $mysqli->begin_transaction();
    try {
      $st = $mysqli->prepare("DELETE FROM answers WHERE question_id=?");
      $st->bind_param("i", $qid);
      if (!$st->execute()) throw new Exception('A delete failed');
      $st->close();

      $st = $mysqli->prepare("DELETE FROM questions WHERE id=?");
      $st->bind_param("i", $qid);
      if (!$st->execute()) throw new Exception('Q delete failed');
      $st->close();

      $mysqli->commit();
      echo json_encode(['ok'=>true]); exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      http_response_code(500); echo json_encode(['error'=>'delete failed']); exit;
    }
  }

  http_response_code(400);
  echo json_encode(['error'=>'Unknown action']); exit;
}
//END ADMIN

/*
  Modes:
  - Player: GET ?quiz=brands-v1&shuffle=1 -> { quizId, preguntes:[{...}] }
  - Admin:  GET ?id=123                   -> { quiz:{...}, questions:[...] }
*/
if (isset($_GET['id'])) {
  // admin GET (editor)
  if (!is_admin()) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }
  $quizId = (int)$_GET['id'];
  if ($quizId <= 0) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }

  $st = $mysqli->prepare("SELECT id, quiz_uid, title FROM quizzes WHERE id=?");
  $st->bind_param("i", $quizId); $st->execute();
  $quiz = $st->get_result()->fetch_assoc(); $st->close();
  if (!$quiz) { http_response_code(404); echo json_encode(['error'=>'Quiz not found']); exit; }

  $qs = [];
  $st = $mysqli->prepare("SELECT id, pregunta, resposta_correcta, imatge FROM questions WHERE quiz_id=? ORDER BY id ASC");
  $st->bind_param("i", $quizId); $st->execute();
  $r = $st->get_result();
  while ($row = $r->fetch_assoc()) {
    $row['ans'] = [];
    $row['corr'] = (int)$row['resposta_correcta'];
    unset($row['resposta_correcta']);
    $qs[$row['id']] = $row;
  }
  $st->close();

  if ($qs) {
    $ids = array_keys($qs);
    $place = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT question_id, ordre, etiqueta FROM answers WHERE question_id IN ($place) ORDER BY question_id, ordre";
    $st = $mysqli->prepare($sql);
    $st->bind_param($types, ...$ids); $st->execute();
    $res = $st->get_result();
    while ($a = $res->fetch_assoc()) {
      $qs[$a['question_id']]['ans'][(int)$a['ordre']] = $a['etiqueta'];
    }
    $st->close();
  }

  echo json_encode(['quiz'=>$quiz, 'questions'=>array_values($qs)], JSON_UNESCAPED_UNICODE); exit;
}

//PLAYER VIEW
$quizUid = $_GET['quiz'] ?? 'brands-v1';
$shuffle = isset($_GET['shuffle']) && $_GET['shuffle'] === '1';

$st = $mysqli->prepare("SELECT id FROM quizzes WHERE quiz_uid = ? LIMIT 1");
$st->bind_param("s", $quizUid); $st->execute(); $st->bind_result($qid);
if (!$st->fetch()) { $st->close(); http_response_code(404); echo json_encode(['error'=>'Quiz not found']); exit; }
$st->close();

$sql = "
  SELECT q.id, q.pregunta, q.imatge, a.ordre, a.etiqueta
  FROM questions q
  LEFT JOIN answers a ON a.question_id = q.id
  WHERE q.quiz_id = ?
  ORDER BY q.id ASC, a.ordre ASC
";
$st = $mysqli->prepare($sql);
$st->bind_param("i", $qid); $st->execute();
$st->bind_result($qid2, $pregunta, $imatge, $opt_id, $etiqueta);

$questions = []; $ix = [];
while ($st->fetch()) {
  if (!isset($ix[$qid2])) {
    $ix[$qid2] = count($questions);
    $questions[] = [
      'id'        => (int)$qid2,
      'pregunta'  => (string)$pregunta,
      'imatge'    => $imatge !== null ? (string)$imatge : null,
      'respostes' => []
    ];
  }
  if ($opt_id !== null) {
    $questions[$ix[$qid2]]['respostes'][] = ['id'=>(int)$opt_id, 'etiqueta'=>(string)$etiqueta];
  }
}
$st->close();

if ($shuffle) {
  foreach ($questions as &$q) { if (!empty($q['respostes'])) shuffle($q['respostes']); }
  unset($q);
  shuffle($questions);
}

echo json_encode(['quizId'=>$quizUid, 'preguntes'=>$questions], JSON_UNESCAPED_UNICODE);