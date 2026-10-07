<?php
require_once __DIR__ . '/../includes/functions.php';
admin_required();

$error = '';
$csrfKey = 'admin_password_csrf';
if (empty($_SESSION[$csrfKey])) {
  $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[$csrfKey];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = $_POST['csrf_token'] ?? null;
  $currentPassword = (string)($_POST['password_lama'] ?? '');
  $newPassword = (string)($_POST['password_baru'] ?? '');
  $confirmation = (string)($_POST['konfirmasi_password'] ?? '');

  if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } elseif ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
    $error = 'Semua kolom password wajib diisi.';
  } else {
    $adminId = (int)$_SESSION['admin_id'];
    $adminQuery = $pdo->prepare('SELECT password FROM admins WHERE id=?');
    $adminQuery->execute([$adminId]);
    $storedPasswordHash = $adminQuery->fetchColumn();

    if ($storedPasswordHash === false) {
      $error = 'Akun Admin tidak ditemukan. Silakan login kembali.';
    } elseif (!password_verify($currentPassword, $storedPasswordHash)) {
      $error = 'Password lama salah.';
    } elseif (strlen($newPassword) < 8) {
      $error = 'Password minimal 8 karakter.';
    } elseif (!hash_equals($newPassword, $confirmation)) {
      $error = 'Konfirmasi password tidak sesuai.';
    } else {
      $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
      $update = $pdo->prepare('UPDATE admins SET password=? WHERE id=? AND password=?');
      $update->execute([$newPasswordHash, $adminId, $storedPasswordHash]);

      if ($update->rowCount() !== 1) {
        $error = 'Password tidak dapat diperbarui. Muat ulang halaman dan coba kembali.';
      } else {
        unset($_SESSION[$csrfKey]);
        header('Location: index.php?pesan=password_diubah');
        exit;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ubah Password · Admin Rental Pendakian</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="index.php">Admin · Rental Pendakian</a>
  <div><span><?= e($_SESSION['admin_username']) ?></span><a href="index.php">Dashboard</a><a href="pembayaran.php">Pembayaran</a><a href="logout.php">Logout Admin</a></div>
</nav>
<main class="admin-dashboard">
  <div class="admin-page-heading">
    <div><h1>Ubah Password</h1><p>Perbarui password akun Admin Anda.</p></div>
  </div>
  <section class="admin-form-card">
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form class="admin-product-form" method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <label for="password_lama">Password Lama</label>
      <input id="password_lama" name="password_lama" type="password" autocomplete="current-password" required>
      <label for="password_baru">Password Baru</label>
      <input id="password_baru" name="password_baru" type="password" autocomplete="new-password" minlength="8" required>
      <label for="konfirmasi_password">Konfirmasi Password Baru</label>
      <input id="konfirmasi_password" name="konfirmasi_password" type="password" autocomplete="new-password" minlength="8" required>
      <div class="admin-form-actions">
        <button class="btn" type="submit">Simpan Password</button>
        <a class="btn alt" href="index.php">Batal</a>
      </div>
    </form>
  </section>
</main>
</body>
</html>
