<?php
$DB_HOST = "localhost";
$DB_USER = "a24romnovkal_kahoochkin";
$DB_PASS = "Roma0802hestia)";
$DB_NAME = "a24romnovkal_kahoochkin";

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>'DB connection failed']);
  exit;
}
$mysqli->set_charset('utf8mb4');