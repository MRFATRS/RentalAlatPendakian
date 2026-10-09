<?php
require_once __DIR__ . '/../includes/manual_payments.php';
admin_required();
$paymentId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($paymentId === false || $paymentId === null || $paymentId < 1) {
  header('Location: pembayaran.php');
  exit;
}

$paymentQuery = $pdo->prepare(
  'SELECT pay.*,b.kode_booking,b.user_id,b.tgl_mulai,b.tgl_selesai,b.durasi_hari,b.subtotal,
      b.total_bayar,b.status AS status_booking,b.metode_pengambilan,
      u.nama AS nama_user,u.email,u.no_whatsapp
   FROM payments pay
   JOIN bookings b ON b.id=pay.booking_id
   JOIN users u ON u.id=b.user_id
   WHERE pay.id=?'
);
$paymentQuery->execute([$paymentId]);
$payment = $paymentQuery->fetch();
if (!$payment) {
  header('Location: pembayaran.php');
  exit;
}
$itemQuery = $pdo->prepare(
  'SELECT p.nama,v.nama_varian,v.is_default,bi.qty,bi.harga_per_hari,bi.subtotal
   FROM booking_items bi
   JOIN product_variants v ON v.id=bi.variant_id
   JOIN products p ON p.id=v.product_id
   WHERE bi.booking_id=?'
);
$itemQuery->execute([$payment['booking_id']]);
$items = $itemQuery->fetchAll();
$error = '';
$success = '';
$csrfToken = payment_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  if (!payment_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Silakan muat ulang halaman.';
  } elseif ($action === 'terima') {
    try {
      $pdo->beginTransaction();
      $lock = $pdo->prepare('SELECT status_pembayaran,metode,bukti_bayar FROM payments WHERE id=? FOR UPDATE');
      $lock->execute([$paymentId]);
      $current = $lock->fetch();
      $bookingLock = $pdo->prepare('SELECT status FROM bookings WHERE id=? FOR UPDATE');
      $bookingLock->execute([(int)$payment['booking_id']]);
      $currentBookingStatus = $bookingLock->fetchColumn();
      $isCash = $current && payment_method_key($current['metode']) === 'bayar_di_tempat';
      if (!$current || !in_array($current['status_pembayaran'], ['menunggu_verifikasi', 'bayar_di_tempat'], true)) {
        $pdo->rollBack();
        $error = 'Pembayaran tidak lagi berstatus menunggu verifikasi. Muat ulang halaman.';
      } elseif (!in_array($currentBookingStatus, ['menunggu_verifikasi', 'disetujui'], true)) {
        $pdo->rollBack();
        $error = 'Booking sudah dibatalkan atau selesai sehingga pembayaran tidak dapat dikonfirmasi.';
      } elseif (!$isCash && !$current['bukti_bayar']) {
        $pdo->rollBack();
        $error = 'Bukti pembayaran belum tersedia.';
      } else {
        $updatePayment = $pdo->prepare(
          "UPDATE payments SET status='berhasil',status_pembayaran='lunas',paid_at=NOW(),
              waktu_verifikasi=NOW(),diverifikasi_oleh=? WHERE id=?"
        );
        $updatePayment->execute([(int)$_SESSION['admin_id'], $paymentId]);
        $updateBooking = $pdo->prepare(
          "UPDATE bookings SET status='disetujui' WHERE id=? AND status='menunggu_verifikasi'"
        );
        $updateBooking->execute([(int)$payment['booking_id']]);
        $pdo->commit();
        header('Location: pembayaran_detail.php?id=' . (int)$paymentId . '&pesan=accepted');
        exit;
      }
    } catch (PDOException $exception) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $error = 'Konfirmasi pembayaran gagal disimpan. Periksa database dan coba kembali.';
    }
  } elseif ($action === 'tolak') {
    try {
      $pdo->beginTransaction();
      $lock = $pdo->prepare('SELECT status_pembayaran,metode,bukti_bayar FROM payments WHERE id=? FOR UPDATE');
      $lock->execute([$paymentId]);
      $current = $lock->fetch();
      $bookingLock = $pdo->prepare('SELECT status FROM bookings WHERE id=? FOR UPDATE');
      $bookingLock->execute([(int)$payment['booking_id']]);
      $currentBookingStatus = $bookingLock->fetchColumn();

      if (!$current || $current['status_pembayaran'] !== 'menunggu_verifikasi') {
        $pdo->rollBack();
        $error = 'Pembayaran tidak lagi berstatus menunggu verifikasi. Muat ulang halaman.';
      } elseif (payment_method_key($current['metode']) === 'bayar_di_tempat') {
        $pdo->rollBack();
        $error = 'Pembayaran di tempat tidak dapat ditolak sebelum diterima.';
      } elseif (!$current['bukti_bayar']) {
        $pdo->rollBack();
        $error = 'Bukti pembayaran belum tersedia.';
      } elseif (!in_array($currentBookingStatus, ['menunggu_verifikasi', 'disetujui'], true)) {
        $pdo->rollBack();
        $error = 'Booking sudah dibatalkan atau selesai sehingga pembayaran tidak dapat ditolak.';
      } else {
        $update = $pdo->prepare(
          "UPDATE payments SET status='gagal',status_pembayaran='ditolak',
              waktu_verifikasi=NOW(),diverifikasi_oleh=? WHERE id=? AND status_pembayaran='menunggu_verifikasi'"
        );
        $update->execute([(int)$_SESSION['admin_id'], $paymentId]);
        if ($currentBookingStatus === 'disetujui') {
          $resetBooking = $pdo->prepare("UPDATE bookings SET status='menunggu_verifikasi' WHERE id=? AND status='disetujui'");
          $resetBooking->execute([(int)$payment['booking_id']]);
        }
        $pdo->commit();
        header('Location: pembayaran_detail.php?id=' . (int)$paymentId . '&pesan=rejected');
        exit;
      }
    } catch (PDOException $exception) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $error = 'Penolakan pembayaran gagal disimpan. Periksa database dan coba kembali.';
    }
  } else {
    $error = 'Tindakan ini tidak sesuai dengan status pembayaran saat ini.';
  }
}

if (($_GET['pesan'] ?? '') === 'accepted') { $success = 'Pembayaran dikonfirmasi lunas.'; }
if (($_GET['pesan'] ?? '') === 'rejected') { $success = 'Pembayaran ditolak. User dapat mengirim ulang bukti.'; }
$proof = basename((string)$payment['bukti_bayar']);
$proofPath = __DIR__ . '/../uploads/bukti_pembayaran/' . $proof;
?>
<?php require_once __DIR__ . '/../includes/admin_layout.php'; admin_layout_start('Detail Pembayaran', 'payments'); ?>
  <div class="admin-page-heading"><div><h1>Detail Pembayaran</h1><p>Booking <?= e($payment['kode_booking']) ?></p></div><a class="btn alt" href="pembayaran.php">Kembali</a></div>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert ok"><?= e($success) ?></div><?php endif; ?>
  <div class="payment-detail-grid">
    <section class="admin-section">
      <h2>Informasi User</h2>
      <dl class="payment-detail-list">
        <div><dt>Nama</dt><dd><?= e($payment['nama_user']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($payment['email']) ?></dd></div>
        <div><dt>WhatsApp</dt><dd><?= e($payment['no_whatsapp']) ?></dd></div>
        <div><dt>Booking</dt><dd><?= e($payment['kode_booking']) ?></dd></div>
        <div><dt>Status booking</dt><dd><?= e(STATUS_LABEL[$payment['status_booking']] ?? $payment['status_booking']) ?></dd></div>
        <div><dt>Tanggal sewa</dt><dd><?= e($payment['tgl_mulai']) ?> s.d. <?= e($payment['tgl_selesai']) ?> (<?= (int)$payment['durasi_hari'] ?> hari)</dd></div>
        <div><dt>Metode pengambilan</dt><dd><?= $payment['metode_pengambilan'] === 'kurir' ? 'Kurir' : 'Ambil di toko' ?></dd></div>
      </dl>
      <h3>Alat yang disewa</h3>
      <?php foreach ($items as $item): ?>
        <p><?= e($item['nama']) ?><?= (int)$item['is_default'] === 1 ? '' : ' (' . e($item['nama_varian']) . ')' ?> × <?= (int)$item['qty'] ?> · <?= rp($item['subtotal']) ?></p>
      <?php endforeach; ?>
    </section>
    <section class="admin-section">
      <h2>Informasi Pembayaran</h2>
      <dl class="payment-detail-list">
        <div><dt>Biaya sewa</dt><dd><?= rp($payment['subtotal']) ?></dd></div>
        <div><dt>Total pembayaran</dt><dd><?= rp($payment['total_bayar']) ?></dd></div>
        <div><dt>Jumlah pembayaran</dt><dd><?= rp($payment['jumlah']) ?></dd></div>
        <div><dt>Metode</dt><dd><?= e(payment_method_label($payment['metode'])) ?></dd></div>
        <div><dt>Status pembayaran</dt><dd><span class="payment-status status-<?= e($payment['status_pembayaran']) ?>"><?= e(payment_status_label($payment['status_pembayaran'])) ?></span></dd></div>
        <div><dt>Waktu upload</dt><dd><?= e($payment['waktu_upload'] ?? '—') ?></dd></div>
        <div><dt>Waktu verifikasi</dt><dd><?= e($payment['waktu_verifikasi'] ?? '—') ?></dd></div>
      </dl>
      <?php if ($payment['bukti_bayar'] && is_file($proofPath)): ?>
        <a class="payment-proof-link" href="../uploads/bukti_pembayaran/<?= e($proof) ?>" target="_blank" rel="noopener">
          <img class="payment-proof-image" src="../uploads/bukti_pembayaran/<?= e($proof) ?>" alt="Bukti pembayaran booking <?= e($payment['kode_booking']) ?>">
          Buka gambar bukti ukuran penuh
        </a>
      <?php elseif ($payment['metode'] !== 'bayar_di_tempat'): ?><p class="alert">Belum ada bukti pembayaran.</p>
      <?php else: ?><p class="alert">Pembayaran dilakukan di tempat. Konfirmasi setelah uang diterima.</p><?php endif; ?>

      <?php if (in_array($payment['status_pembayaran'], ['menunggu_verifikasi', 'bayar_di_tempat'], true)): ?>
        <div class="admin-form-actions">
          <?php if ($payment['status_pembayaran'] === 'bayar_di_tempat' || $payment['bukti_bayar']): ?>
            <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin mengonfirmasi pembayaran ini?')">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="id" value="<?= (int)$paymentId ?>">
              <input type="hidden" name="action" value="terima">
              <button class="btn" type="submit"><?= $payment['status_pembayaran'] === 'bayar_di_tempat' ? 'Konfirmasi Pembayaran Diterima' : 'Terima Pembayaran' ?></button>
            </form>
          <?php endif; ?>
          <?php if ($payment['status_pembayaran'] === 'menunggu_verifikasi' && $payment['bukti_bayar']): ?>
            <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin menolak bukti pembayaran ini?')">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="id" value="<?= (int)$paymentId ?>">
              <input type="hidden" name="action" value="tolak">
              <button class="btn admin-button-danger" type="submit">Tolak Pembayaran</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php admin_layout_end(); ?>
