<?php
require_once __DIR__ . '/../../../private/auth.php';
json_headers();
echo json_encode(['csrf' => csrf_token()]);