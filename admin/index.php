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
      $requiresPaid = in_array($_POST['status'], ['disetujui', 'sedang_disewa', 'selesai', 'denda'], true);
      if ($requiresPaid) {
        $paymentStatusQuery = $pdo->prepare('SELECT status_pembayaran FROM payments WHERE booking_id=? ORDER BY id DESC LIMIT 1');
        $paymentStatusQuery->execute([$bookingId]);
        $bookingPaymentStatus = $paymentStatusQuery->fetchColumn();
        if ($bookingPaymentStatus && $bookingPaymentStatus !== 'lunas') {
          header('Location: index.php?pesan=payment_required#bookings');
          exit;
        }
      }
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
$recentBookings = $pdo->query(
  "SELECT b.id,b.kode_booking,b.tgl_mulai,b.tgl_selesai,b.subtotal,b.status,u.nama
   FROM bookings b JOIN users u ON u.id=b.user_id
   ORDER BY b.created_at DESC,b.id DESC LIMIT 5"
)->fetchAll();
$stats = [
  'products' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
  'bookings' => (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
  'pending_payments' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status_pembayaran='menunggu_verifikasi'")->fetchColumn(),
  'active_bookings' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('disetujui','sedang_disewa')")->fetchColumn(),
];
$csrfToken = admin_product_csrf_token();
$messages = [
  'created' => ['ok', 'Produk berhasil ditambahkan.'],
  'updated' => ['ok', 'Produk berhasil diperbarui.'],
  'deleted' => ['ok', 'Produk berhasil dihapus.'],
  'deleted_no_image' => ['err', 'Produk berhasil dihapus, tetapi file fotonya gagal dihapus dari server.'],
  'booking_updated' => ['ok', 'Status booking berhasil diperbarui.'],
  'payment_required' => ['err', 'Booking belum dapat diproses sebelum pembayaran dikonfirmasi lunas.'],
  'password_diubah' => ['ok', 'Password berhasil diubah.'],
  'csrf' => ['err', 'Permintaan tidak valid atau kedaluwarsa. Silakan coba kembali.'],
  'invalid' => ['err', 'Data perubahan status tidak valid.'],
  'not_found' => ['err', 'Produk tidak ditemukan.'],
];
$notice = $messages[$_GET['pesan'] ?? ''] ?? null;
require_once __DIR__ . '/../includes/admin_layout.php';
?>
<?php admin_layout_start('Dashboard Admin', 'dashboard'); ?>
  <section class="admin-welcome">
    <div>
      <h2>Selamat datang kembali, Admin <span aria-hidden="true">👋</span></h2>
      <p>Kelola produk, booking, dan pembayaran rental alat pendakian.</p>
    </div>
    <div class="admin-page-actions">
      <a class="btn alt" href="pembayaran.php">Lihat Pembayaran</a>
      <a class="btn" href="produk_tambah.php">Tambah Produk</a>
    </div>
  </section>
  <?php if ($notice): ?><div class="alert <?= e($notice[0]) ?>"><?= e($notice[1]) ?></div><?php endif; ?>

  <section class="admin-stat-grid" aria-label="Statistik dashboard">
    <article class="admin-stat-card"><span class="admin-stat-icon admin-stat-products"><?= admin_layout_icon('products') ?></span><div><span>Total Produk</span><strong><?= $stats['products'] ?></strong><small>Produk terdaftar</small></div></article>
    <article class="admin-stat-card"><span class="admin-stat-icon admin-stat-bookings"><?= admin_layout_icon('bookings') ?></span><div><span>Total Booking</span><strong><?= $stats['bookings'] ?></strong><small>Seluruh pemesanan</small></div></article>
    <article class="admin-stat-card admin-stat-pending"><span class="admin-stat-icon"><?= admin_layout_icon('payments') ?></span><div><span>Menunggu Verifikasi</span><strong><?= $stats['pending_payments'] ?></strong><small>Pembayaran perlu diperiksa</small></div></article>
    <article class="admin-stat-card"><span class="admin-stat-icon admin-stat-active"><?= admin_layout_icon('dashboard') ?></span><div><span>Booking Aktif</span><strong><?= $stats['active_bookings'] ?></strong><small>Disetujui atau sedang disewa</small></div></article>
  </section>

  <section class="admin-section admin-recent-section" aria-labelledby="recent-bookings-title">
    <div class="admin-section-heading">
      <div><h2 id="recent-bookings-title">Booking Terbaru</h2><p>Pemesanan terkini dari pelanggan.</p></div>
      <a class="admin-text-link" href="#bookings">Lihat semua booking <span aria-hidden="true">→</span></a>
    </div>
    <?php if ($recentBookings): ?>
      <div class="admin-table-wrap">
        <table class="admin-table admin-recent-table">
          <thead><tr><th>Kode Booking</th><th>Penyewa</th><th>Tanggal Sewa</th><th>Biaya Sewa</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recentBookings as $booking):
            $bookingStatusClass = 'status-' . $booking['status'];
          ?>
            <tr>
              <td><strong class="admin-booking-code"><?= e($booking['kode_booking']) ?></strong></td>
              <td><?= e($booking['nama']) ?></td>
              <td><?= e($booking['tgl_mulai']) ?> <span class="admin-date-separator">—</span> <?= e($booking['tgl_selesai']) ?></td>
              <td class="admin-table-price"><?= rp($booking['subtotal']) ?></td>
              <td><span class="booking-status-badge <?= e($bookingStatusClass) ?>"><span></span><?= e(STATUS_LABEL[$booking['status']] ?? $booking['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="admin-empty admin-recent-empty"><h3>Belum ada booking</h3><p>Booking terbaru akan muncul di sini.</p></div>
    <?php endif; ?>
  </section>

  <section class="admin-section" id="products" aria-labelledby="products-title">
    <div class="admin-section-heading"><div><h2 id="products-title">Kelola Produk</h2><p>Atur katalog alat yang tersedia untuk disewa.</p></div><span class="admin-count-badge"><?= count($products) ?> produk</span></div>
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

  <section class="admin-section" id="bookings" aria-labelledby="bookings-title">
    <div class="admin-section-heading"><div><h2 id="bookings-title">Kelola Booking</h2><p>Perbarui status sewa dan periksa identitas pelanggan.</p></div><span class="admin-count-badge"><?= count($bookings) ?> booking</span></div>
    <?php if ($bookings): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Kode</th><th>Penyewa</th><th>Tanggal</th><th>Biaya Sewa</th><th>KTP/SIM</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($bookings as $booking): ?>
            <tr>
              <td><?= e($booking['kode_booking']) ?></td>
              <td><?= e($booking['nama']) ?><br><small><?= e($booking['no_whatsapp']) ?></small></td>
              <td><?= e($booking['tgl_mulai']) ?> → <?= e($booking['tgl_selesai']) ?></td>
              <td><?= rp($booking['subtotal']) ?></td>
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
<?php admin_layout_end(); ?>
