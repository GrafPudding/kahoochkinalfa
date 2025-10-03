<?php
require_once __DIR__ . '/../../../private/auth.php';
require_once __DIR__ . '/../../../private/db.php';
json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method']); exit; }
ensure_csrf();

$payload = json_decode(file_get_contents('php://input'), true);
$user = trim($payload['username'] ?? '');
$pass = $payload['password'] ?? '';

$stmt = $mysqli->prepare("SELECT id, password_hash, role FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $user);
$stmt->execute();
$stmt->bind_result($uid, $hash, $role);

if ($stmt->fetch() && password_verify($pass, $hash)) {
  $_SESSION['uid'] = (int)$uid;
  $_SESSION['role'] = $role;
  echo json_encode(['ok'=>true, 'user'=>['id'=>(int)$uid, 'role'=>$role]]);
} else {
  http_response_code(401);
  echo json_encode(['ok'=>false, 'error'=>'Invalid credentials']);
}
$stmt->close();