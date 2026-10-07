<?php include 'includes/header.php'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama = trim($_POST['nama']); $email = trim($_POST['email']); $wa = trim($_POST['wa']); $pw = $_POST['password'];
  $f = $_FILES['identitas'] ?? null; $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
  $cek = $pdo->prepare("SELECT id FROM users WHERE email=?"); $cek->execute([$email]);
  if (!$nama || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) $err = 'Data tidak valid (password minimal 6 karakter).';
  elseif ($cek->fetch()) $err = 'Email sudah terdaftar.';
  elseif (!$f || $f['error'] !== 0 || !in_array($ext, ['jpg','jpeg','png']) || $f['size'] > 2*1024*1024) $err = 'Foto KTP/SIM wajib (JPG/PNG, maks 2MB).';
  else {
    $file = bin2hex(random_bytes(8)) . '.' . $ext; move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $file);
    $pdo->prepare("INSERT INTO users (nama,email,password,no_whatsapp,foto_identitas,jenis_identitas,status_akun) VALUES (?,?,?,?,?,?, 'terverifikasi')")
        ->execute([$nama, $email, password_hash($pw, PASSWORD_DEFAULT), $wa, $file, $_POST['jenis'] === 'SIM' ? 'SIM' : 'KTP']);
    header('Location: login.php?pesan=ok'); exit;
  }
} ?>
<div class="box narrow"><h2>Daftar</h2><?php if ($err) echo '<div class="alert err">' . e($err) . '</div>'; ?>
<form method="post" enctype="multipart/form-data">
<label>Nama</label><input name="nama" required><label>Email</label><input type="email" name="email" required>
<label>No. WhatsApp</label><input name="wa" required><label>Password</label><input type="password" name="password" required>
<label>Jenis Identitas</label><select name="jenis"><option>KTP</option><option>SIM</option></select>
<label>Upload Foto KTP / SIM</label><input type="file" name="identitas" accept="image/*" required>
<button class="btn">Daftar</button></form></div><?php include 'includes/footer.php'; ?>
