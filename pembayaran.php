<?php
require_once __DIR__ . '/includes/manual_payments.php';
login_required();
$paymentConfig = require __DIR__ . '/config/payment.php';
$qrisImageAvailable = payment_qris_image_available();
$paymentId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($paymentId === false || $paymentId === null || $paymentId < 1) {
  header('Location: riwayat.php');
  exit;
}

$paymentQuery = $pdo->prepare(
  "SELECT pay.id,pay.booking_id,pay.jumlah,pay.metode,pay.status,pay.status_pembayaran,pay.bukti_bayar,
      b.kode_booking,b.user_id,b.subtotal,b.total_bayar,b.tgl_mulai,b.tgl_selesai,
      GROUP_CONCAT(CONCAT(p.nama,CASE WHEN v.is_default=1 THEN '' ELSE CONCAT(' ',v.nama_varian) END,' x',bi.qty) SEPARATOR ', ') AS items
   FROM payments pay
   JOIN bookings b ON b.id=pay.booking_id
   JOIN booking_items bi ON bi.booking_id=b.id
   JOIN product_variants v ON v.id=bi.variant_id
   JOIN products p ON p.id=v.product_id
   WHERE pay.id=? AND b.user_id=?
   GROUP BY pay.id,b.id"
);
$paymentQuery->execute([$paymentId, (int)$_SESSION['user_id']]);
$payment = $paymentQuery->fetch();
if (!$payment) {
  http_response_code(404);
  include 'includes/header.php';
  echo '<div class="alert err">Pembayaran tidak ditemukan.</div>';
  include 'includes/footer.php';
  exit;
}

$storedMethod = payment_method_key($payment['metode']);
$canResubmit = in_array($payment['status_pembayaran'], ['ditolak', 'belum_dibayar'], true)
  && $storedMethod !== 'bayar_di_tempat'
  && $payment['status'] !== 'berhasil';
$method = in_array($storedMethod, ['qris', 'transfer_bank'], true) ? $storedMethod : 'qris';
$error = '';
$csrfToken = payment_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $method = (string)($_POST['metode_pembayaran'] ?? '');
  if (!payment_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } elseif (!$canResubmit) {
    $error = 'Pembayaran ini tidak dapat diubah.';
  } elseif (!in_array($method, ['qris', 'transfer_bank'], true)) {
    $error = 'Pilih metode pembayaran yang valid.';
  } elseif (!payment_method_available($method, $paymentConfig)) {
    $error = $method === 'qris'
      ? 'Gambar QRIS merchant tidak tersedia atau tidak valid. Silakan pilih metode pembayaran lain atau hubungi admin.'
      : 'Transfer bank belum tersedia. Admin perlu mengisi konfigurasi rekening di config/payment.php.';
  } else {
    $upload = payment_upload_proof($_FILES['bukti_pembayaran'] ?? []);
    if (isset($upload['error'])) {
      $error = $upload['error'];
    } else {
      try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare(
          'SELECT id,status_pembayaran,bukti_bayar FROM payments WHERE id=? AND booking_id=? FOR UPDATE'
        );
        $lock->execute([$paymentId, $payment['booking_id']]);
        $current = $lock->fetch();
        if (!$current || !in_array($current['status_pembayaran'], ['ditolak', 'belum_dibayar'], true)) {
          $pdo->rollBack();
          payment_remove_proof($upload['filename']);
          $error = 'Status pembayaran sudah berubah dan tidak dapat dikirim ulang.';
        } else {
          $update = $pdo->prepare(
            "UPDATE payments SET metode=?,bukti_bayar=?,status='pending',
                status_pembayaran='menunggu_verifikasi',waktu_upload=NOW(),
                waktu_verifikasi=NULL,diverifikasi_oleh=NULL,paid_at=NULL
             WHERE id=? AND booking_id=?"
          );
          $update->execute([$method, $upload['filename'], $paymentId, $payment['booking_id']]);
          $pdo->commit();
          payment_remove_proof($current['bukti_bayar']);
          header('Location: riwayat.php?pesan=bukti_dikirim');
          exit;
        }
      } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        payment_remove_proof($upload['filename']);
        $error = 'Bukti pembayaran gagal disimpan. Periksa database dan coba kembali.';
      }
    }
  }
}

include 'includes/header.php';
?>
<section class="box payment-resubmit">
  <h2>Kirim Bukti Pembayaran</h2>
  <p class="muted">Booking <?= e($payment['kode_booking']) ?> · <?= e($payment['items']) ?></p>
  <p>Biaya sewa: <strong><?= rp($payment['subtotal']) ?></strong><br>
    Total pembayaran: <strong><?= rp($payment['total_bayar']) ?></strong><br>
    Jumlah pembayaran: <strong><?= rp($payment['jumlah']) ?></strong></p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$canResubmit): ?>
    <div class="alert">Pembayaran berstatus <?= e(payment_status_label($payment['status_pembayaran'])) ?> dan tidak dapat diubah dari halaman ini.</div>
  <?php else: ?>
    <p>Pembayaran sebelumnya ditolak atau belum memiliki bukti. Pilih metode, lakukan pembayaran, lalu unggah bukti yang benar.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="id" value="<?= (int)$paymentId ?>">
      <fieldset class="payment-methods">
        <legend>Metode Pembayaran</legend>
        <label class="payment-method-option<?= $qrisImageAvailable ? '' : ' is-disabled' ?>"><input type="radio" name="metode_pembayaran" value="qris"<?= $method === 'qris' ? ' checked' : '' ?><?= $qrisImageAvailable ? '' : ' disabled' ?>><span><strong>QRIS</strong><small><?= $qrisImageAvailable ? 'Scan QRIS merchant dan masukkan jumlah yang tertera' : 'QRIS sementara tidak tersedia' ?></small></span></label>
        <label class="payment-method-option">
          <input type="radio" name="metode_pembayaran" value="transfer_bank"<?= $method === 'transfer_bank' ? ' checked' : '' ?>>
          <span><strong>Transfer Bank</strong><small>Bayar melalui transfer ke rekening kami</small></span>
        </label>
      </fieldset>
      <div class="payment-instructions">
        <h3>Informasi Pembayaran</h3>
        <div data-resubmit-panel="qris"<?= $method === 'qris' ? '' : ' hidden' ?>>
          <?php if ($qrisImageAvailable): ?>
            <img class="merchant-qris" src="<?= e(payment_qris_image_url()) ?>" alt="QRIS statis merchant Rental Alat Pendakian" data-qris-image>
            <div class="qris-image-error" data-qris-image-error hidden>Gambar QRIS gagal dimuat. Muat ulang halaman atau pilih metode pembayaran lain.</div>
            <p>QRIS ini menggunakan kode statis. Masukkan tepat <strong><?= rp($payment['jumlah']) ?></strong> di aplikasi pembayaran, kemudian unggah bukti transaksi.</p>
          <?php else: ?>
            <div class="alert err qris-image-error">Gambar QRIS merchant tidak ditemukan atau formatnya tidak valid. QRIS tidak dapat digunakan saat ini.</div>
          <?php endif; ?>
        </div>
        <div data-resubmit-panel="transfer_bank"<?= $method === 'transfer_bank' ? '' : ' hidden' ?>>
          <p>Nama Bank: <strong><?= e($paymentConfig['bank']['nama_bank']) ?></strong><br>
            Nomor Rekening: <strong><?= e($paymentConfig['bank']['nomor_rekening']) ?></strong><br>
            Atas Nama: <strong><?= e($paymentConfig['bank']['nama_pemilik']) ?></strong></p>
          <p>Total pembayaran: <strong><?= rp($payment['jumlah']) ?></strong></p>
          <p>Silakan transfer sesuai total pembayaran, kemudian upload bukti transfer.</p>
        </div>
        <label for="payment-proof">Bukti Pembayaran</label>
        <input id="payment-proof" type="file" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
        <small>Format JPG, JPEG, PNG, WEBP · maksimal 2 MB.</small>
      </div>
      <button class="btn" type="submit">Kirim Bukti Pembayaran</button>
    </form>
  <?php endif; ?>
  <p><a href="riwayat.php">Kembali ke riwayat</a></p>
</section>
<script>
(() => {
  const radios = document.querySelectorAll('input[name="metode_pembayaran"]');
  const panels = document.querySelectorAll('[data-resubmit-panel]');
  const qrisOption = document.querySelector('input[name="metode_pembayaran"][value="qris"]');
  const qrisImage = document.querySelector('[data-qris-image]');
  const qrisImageError = document.querySelector('[data-qris-image-error]');
  const updateMethod = (method) => {
    panels.forEach((panel) => { panel.hidden = panel.dataset.resubmitPanel !== method; });
  };
  radios.forEach((radio) => radio.addEventListener('change', () => updateMethod(radio.value)));
  if (qrisImage && qrisImageError && qrisOption) {
    const showQrisError = () => {
      qrisImage.hidden = true;
      qrisImageError.hidden = false;
      qrisOption.checked = false;
      qrisOption.disabled = true;
      const fallbackOption = Array.from(radios).find((radio) => !radio.disabled);
      if (fallbackOption) {
        fallbackOption.checked = true;
        updateMethod(fallbackOption.value);
      }
    };
    qrisImage.addEventListener('error', showQrisError);
    if (qrisImage.complete && qrisImage.naturalWidth === 0) showQrisError();
  }
})();
</script>
<?php include 'includes/footer.php'; ?>
