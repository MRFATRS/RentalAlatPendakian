<?php
require_once __DIR__ . '/../includes/functions.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $st = $pdo->prepare('SELECT id, username, password, nama FROM admins WHERE username=?');
  $st->execute([trim($_POST['username'])]);
  $admin = $st->fetch();

  if ($admin && password_verify($_POST['password'], $admin['password'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = 'admin';
    header('Location: index.php');
    exit;
  }
  $err = 'Username atau password admin salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin · Rental Alat Pendakian</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-login-page">
<main class="admin-login-main">
  <section class="admin-login-card" aria-labelledby="admin-login-title">
    <a class="admin-login-brand" href="../index.php">
      <span class="brand-mark" aria-hidden="true">⛰</span>
      <span>Rental <span class="brand-sub">Pendakian</span></span>
    </a>
    <div class="admin-login-heading">
      <span class="admin-login-eyebrow">Area administrator</span>
      <h1 id="admin-login-title">Login Admin</h1>
      <p>Masuk untuk mengelola Rental Pendakian</p>
    </div>
    <?php if (($_GET['pesan'] ?? '') === 'wajib'): ?><div class="alert">Silakan login sebagai admin untuk melanjutkan.</div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
    <form class="admin-login-form" method="post">
      <label for="username">Email / Username Admin</label>
      <input id="username" type="text" name="username" autocomplete="username" placeholder="Masukkan email atau username" required>
      <label for="password">Password</label>
      <div class="password-field">
        <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
        <button class="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false" data-password-toggle="password">Tampilkan</button>
      </div>
      <button class="btn admin-login-submit" type="submit">Masuk sebagai Admin</button>
    </form>
    <a class="admin-login-back" href="../index.php"><span aria-hidden="true">←</span> Kembali ke halaman utama</a>
  </section>
</main>
<script>
document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
  toggle.addEventListener('click', () => {
    const input = document.getElementById(toggle.dataset.passwordToggle);
    const showing = input.type === 'password';
    input.type = showing ? 'text' : 'password';
    toggle.textContent = showing ? 'Sembunyikan' : 'Tampilkan';
    toggle.setAttribute('aria-label', showing ? 'Sembunyikan password' : 'Tampilkan password');
    toggle.setAttribute('aria-pressed', String(showing));
  });
});
</script>
</body>
</html>
