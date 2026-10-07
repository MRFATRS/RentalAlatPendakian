<?php require_once __DIR__ . '/functions.php'; ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rental Alat Pendakian</title><link rel="stylesheet" href="assets/css/style.css"></head><body>
<nav><a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">⛰</span><span>Rental <span class="brand-sub">Pendakian</span></span></a><div>
<?php if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'user'): ?>
  <a href="riwayat.php">Sewa Saya</a>
  <a href="logout.php">Logout <?= e($_SESSION['user_username']) ?></a>
<?php else: ?><span class="guest-label">Mode Guest</span><a href="login.php">Login</a><a href="register.php">Daftar</a><?php endif; ?>
</div></nav><main>
