<?php
require_once __DIR__ . '/../../../private/auth.php';
json_headers();
ensure_csrf();
$_SESSION = [];
session_destroy();
echo json_encode(['ok'=>true]);