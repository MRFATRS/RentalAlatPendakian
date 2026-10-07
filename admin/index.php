<?php
require_once __DIR__ . '/../includes/admin_products.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!admin_product_verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: index.php?pesan=csrf');
    exit;
  }
  if (isset($_POST['status'], STATUS_LABEL[$_POST['status']])) {
    $bookingId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($bookingId !== false && $bookingId > 0) {
      $pdo->prepare("UPDATE bookings SET status=?, tgl_kembali_aktual=IF(?='selesai', CURDATE(), tgl_kembali_aktual) WHERE id=?")
          ->execute([$_POST['status'], $_POST['status'], $bookingId]);
      header('Location: index.php?pesan=booking_updated');
      exit;
    }
  }
  header('Location: index.php?pesan=invalid');
  exit;
}

$products = $pdo->query(
  "SELECT p.id, p.nama, p.deskripsi, p.harga_per_hari, p.gambar, p.is_active,
      c.nama AS kategori, COALESCE(SUM(v.stok_total), 0) AS stok
   FROM products p
   JOIN categories c ON c.id=p.category_id
   LEFT JOIN product_variants v ON v.product_id=p.id
   GROUP BY p.id, p.nama, p.deskripsi, p.harga_per_hari, p.gambar, p.is_active, c.nama
   ORDER BY p.id DESC"
)->fetchAll();
$bookings = $pdo->query(
  "SELECT b.*, u.nama, u.no_whatsapp, u.foto_identitas
   FROM bookings b JOIN users u ON u.id=b.user_id
   ORDER BY b.id DESC"
)->fetchAll();
$csrfToken = admin_product_csrf_token();
$messages = [
  'created' => ['ok', 'Produk berhasil ditambahkan.'],
  'updated' => ['ok', 'Produk berhasil diperbarui.'],
  'deleted' => ['ok', 'Produk berhasil dihapus.'],
  'deleted_no_image' => ['err', 'Produk berhasil dihapus, tetapi file fotonya gagal dihapus dari server.'],
  'booking_updated' => ['ok', 'Status booking berhasil diperbarui.'],
  'csrf' => ['err', 'Permintaan tidak valid atau kedaluwarsa. Silakan coba kembali.'],
  'invalid' => ['err', 'Data perubahan status tidak valid.'],
  'not_found' => ['err', 'Produk tidak ditemukan.'],
];
$notice = $messages[$_GET['pesan'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · Rental Pendakian</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="index.php">Admin · Rental Pendakian</a>
  <div><span><?= e($_SESSION['admin_username']) ?></span><a href="../index.php">Lihat Situs</a><a href="logout.php">Logout Admin</a></div>
</nav>
<main class="admin-dashboard">
  <div class="admin-page-heading">
    <div><h1>Kelola Produk</h1></div>
    <a class="btn" href="produk_tambah.php">+ Tambah Produk</a>
  </div>
  <?php if ($notice): ?><div class="alert <?= e($notice[0]) ?>"><?= e($notice[1]) ?></div><?php endif; ?>

  <section class="admin-section" aria-labelledby="products-title">
    <div class="admin-section-heading"><h2 id="products-title">Daftar Produk</h2><span><?= count($products) ?> produk</span></div>
    <?php if ($products): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>No</th><th>Foto</th><th>Nama Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($products as $index => $product): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><?php if ($product['gambar'] && is_file(__DIR__ . '/../uploads/produk/' . basename($product['gambar']))): ?>
                <img class="admin-product-thumb" src="../uploads/produk/<?= e(basename($product['gambar'])) ?>" alt="<?= e($product['nama']) ?>">
              <?php else: ?><span class="admin-no-photo">Belum ada foto</span><?php endif; ?></td>
              <td><strong><?= e($product['nama']) ?></strong><?php if (!$product['is_active']): ?><small class="admin-inactive">Nonaktif</small><?php endif; ?></td>
              <td><?= e($product['kategori']) ?></td>
              <td><?= rp($product['harga_per_hari']) ?>/hari</td>
              <td><?= (int)$product['stok'] ?></td>
              <td class="admin-description"><?= e($product['deskripsi'] ?? '') ?></td>
              <td><div class="admin-actions">
                <a class="btn admin-button-small" href="produk_edit.php?id=<?= (int)$product['id'] ?>">Edit</a>
                <a class="btn admin-button-small admin-button-danger" href="produk_hapus.php?id=<?= (int)$product['id'] ?>">Hapus</a>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="admin-empty"><h3>Belum ada produk</h3><p>Tambahkan produk pertama agar tampil di katalog.</p><a class="btn" href="produk_tambah.php">Tambah Produk</a></div>
    <?php endif; ?>
  </section>

  <section class="admin-section" aria-labelledby="bookings-title">
    <div class="admin-section-heading"><h2 id="bookings-title">Kelola Booking</h2><span><?= count($bookings) ?> booking</span></div>
    <?php if ($bookings): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Kode</th><th>Penyewa</th><th>Tanggal</th><th>Total</th><th>KTP/SIM</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($bookings as $booking): ?>
            <tr>
              <td><?= e($booking['kode_booking']) ?></td>
              <td><?= e($booking['nama']) ?><br><small><?= e($booking['no_whatsapp']) ?></small></td>
              <td><?= e($booking['tgl_mulai']) ?> → <?= e($booking['tgl_selesai']) ?></td>
              <td><?= rp($booking['total_bayar']) ?></td>
              <td><a href="../uploads/<?= e(basename($booking['foto_identitas'])) ?>" target="_blank" rel="noopener">Lihat</a></td>
              <td><form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                <?php foreach (STATUS_LABEL as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $booking['status'] ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
              </form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?><p class="muted">Belum ada booking.</p><?php endif; ?>
  </section>
</main>
</body>
</html>
