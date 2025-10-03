<?php
session_start();
function json_headers(){ header('Content-Type: application/json; charset=utf-8'); }
function current_user_id(){ return $_SESSION['uid'] ?? null; }
function current_role(){ return $_SESSION['role'] ?? null; }
function is_admin(){ return current_role()==='admin'; }

// Simple CSRF (optional for fetch POSTs)
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_token(){ return $_SESSION['csrf']; }
function ensure_csrf(){
  $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
  if ($hdr !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error'=>'Bad CSRF token']); exit;
  }
}