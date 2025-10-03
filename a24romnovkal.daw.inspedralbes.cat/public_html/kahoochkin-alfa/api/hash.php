<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd = $_POST['pwd'] ?? '';
    if (!$pwd) {
        echo "No password given!";
        exit;
    }
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    echo "Password: " . htmlspecialchars($pwd) . "<br>";
    echo "Hash: " . htmlspecialchars($hash) . "<br>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Hash Generator</title></head>
<body>
  <form method="post">
    <label>Password: <input type="text" name="pwd"></label>
    <button type="submit">Generate hash</button>
  </form>
</body>
</html>