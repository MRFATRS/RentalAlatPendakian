<?php
require_once __DIR__ . '/includes/functions.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $st = $pdo->prepare("SELECT * FROM users WHERE email=?"); $st->execute([trim($_POST['email'])]); $u = $st->fetch();
  if ($u && $u['role'] === 'user' && password_verify($_POST['password'], $u['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['user_username'] = $u['nama'];
    $_SESSION['user_role'] = 'user';
    $to = $_SESSION['user_redirect'] ?? 'index.php';
    unset($_SESSION['user_redirect']);
    if (strpos($to, '/') === 0 && strpos($to, '//') !== 0) {
      header('Location: ' . $to);
    } else {
      header('Location: index.php');
    }
    exit;
  }
  $err = 'Email atau password salah.';
}
include 'includes/header.php'; ?>
<div class="box narrow"><h2>Login</h2>
<?php if (($_GET['pesan'] ?? '') === 'wajib') echo '<div class="alert">🚨 Wajib login untuk melanjutkan sewa.</div>';
if (($_GET['pesan'] ?? '') === 'ok') echo '<div class="alert ok">Registrasi berhasil, silakan login.</div>';
if ($err) echo '<div class="alert err">' . e($err) . '</div>'; ?>
<form method="post"><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" required>
<button class="btn">Masuk</button> <a href="register.php">Belum punya akun?</a></form></div><?php include 'includes/footer.php'; ?>
