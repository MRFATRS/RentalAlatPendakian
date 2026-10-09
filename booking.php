<?php
require_once __DIR__ . '/includes/manual_payments.php';
login_required();
$paymentConfig = require __DIR__ . '/config/payment.php';
$qrisImageAvailable = payment_qris_image_available();

$variantId = filter_var($_GET['vid'] ?? $_POST['vid'] ?? null, FILTER_VALIDATE_INT);
$variantId = $variantId !== false && $variantId !== null && $variantId > 0 ? $variantId : 0;
$productId = filter_var($_GET['pid'] ?? $_POST['pid'] ?? null, FILTER_VALIDATE_INT);
$productId = $productId !== false && $productId !== null && $productId > 0 ? $productId : 0;
if ($variantId > 0) {
  $productQuery = $pdo->prepare(
    'SELECT v.id,v.nama_varian,v.stok_total,v.is_default,p.id AS product_id,p.nama,p.harga_per_hari
     FROM product_variants v JOIN products p ON p.id=v.product_id
     WHERE v.id=? AND v.is_active=1 AND v.is_default=0 AND p.is_active=1'
  );
  $productQuery->execute([$variantId]);
} else {
  $productQuery = $pdo->prepare(
    'SELECT v.id,v.nama_varian,v.stok_total,v.is_default,p.id AS product_id,p.nama,p.harga_per_hari
     FROM product_variants v JOIN products p ON p.id=v.product_id
     WHERE v.product_id=? AND v.is_active=1 AND v.is_default=1 AND p.is_active=1'
  );
  $productQuery->execute([$productId]);
}
$item = $productQuery->fetch();
if (!$item) {
  http_response_code(404);
  include 'includes/header.php';
  echo '<div class="alert err">Varian produk tidak ditemukan atau tidak tersedia.</div>';
  include 'includes/footer.php';
  exit;
}
$bookingVariantId = (int)$item['id'];

$error = '';
$calculation = null;
$start = (string)($_POST['start'] ?? '');
$end = (string)($_POST['end'] ?? '');
$qtyValue = $_POST['qty'] ?? '1';
$quantity = filter_var($qtyValue, FILTER_VALIDATE_INT);
$quantity = $quantity !== false && $quantity > 0 ? $quantity : 1;
$pickupMethod = (string)($_POST['metode_pengambilan'] ?? 'ambil_toko');
$paymentMethod = (string)($_POST['metode_pembayaran'] ?? 'qris');
$csrfToken = payment_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!payment_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } else {
    $startDate = DateTime::createFromFormat('!Y-m-d', $start);
    $endDate = DateTime::createFromFormat('!Y-m-d', $end);
    $today = new DateTime('today');
    if (!$startDate || $startDate->format('Y-m-d') !== $start
      || !$endDate || $endDate->format('Y-m-d') !== $end
      || $startDate < $today || $endDate <= $startDate) {
      $error = 'Tanggal tidak valid (mulai minimal hari ini dan tanggal selesai harus setelah tanggal mulai).';
    } elseif ($quantity < 1 || $quantity > 100) {
      $error = 'Jumlah unit harus antara 1 dan 100.';
    } elseif (!in_array($pickupMethod, ['ambil_toko', 'kurir'], true)) {
      $error = 'Pilih metode pengambilan yang valid.';
    } elseif (!in_array($paymentMethod, ['qris', 'transfer_bank', 'bayar_di_tempat'], true)) {
      $error = 'Pilih metode pembayaran yang valid.';
    } elseif (!payment_method_available($paymentMethod, $paymentConfig)) {
      $error = $paymentMethod === 'qris'
        ? 'Gambar QRIS merchant tidak tersedia atau tidak valid. Silakan pilih metode pembayaran lain atau hubungi admin.'
        : 'Transfer bank belum tersedia. Admin perlu mengisi konfigurasi rekening di config/payment.php.';
    } else {
      $duration = (int)$startDate->diff($endDate)->days;
      $subtotal = $duration * (int)$item['harga_per_hari'] * $quantity;
      $total = $subtotal;
      $paymentAmount = $total;
      $calculation = [
        'duration' => $duration,
        'subtotal' => $subtotal,
        'total' => $total,
        'payment_amount' => $paymentAmount,
      ];

      if (isset($_POST['pesan'])) {
        $proofFilename = null;
        if ($paymentMethod !== 'bayar_di_tempat') {
          $upload = payment_upload_proof($_FILES['bukti_pembayaran'] ?? []);
          if (isset($upload['error'])) {
            $error = $upload['error'];
          } else {
            $proofFilename = $upload['filename'];
          }
        }

        if ($error === '') {
          try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id FROM product_variants WHERE id=? AND is_active=1 FOR UPDATE');
            $lock->execute([$bookingVariantId]);
            if (!$lock->fetchColumn() || stok_tersedia($pdo, $bookingVariantId, $start, $end) < $quantity) {
              $pdo->rollBack();
              if ($proofFilename) { payment_remove_proof($proofFilename); }
              $error = 'Stok tidak tersedia pada tanggal ini. Silakan pilih tanggal atau jumlah lain.';
            } else {
              $bookingCode = 'RNT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
              $bookingInsert = $pdo->prepare(
                'INSERT INTO bookings
                  (kode_booking,user_id,tgl_mulai,tgl_selesai,durasi_hari,subtotal,total_bayar,
                   metode_pengambilan,alamat_kirim,skema_bayar)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
              );
              $bookingInsert->execute([
                $bookingCode,
                (int)$_SESSION['user_id'],
                $start,
                $end,
                $duration,
                $subtotal,
                $total,
                $pickupMethod,
                $pickupMethod === 'kurir' ? trim((string)($_POST['alamat'] ?? '')) : null,
                'lunas',
              ]);
              $bookingId = (int)$pdo->lastInsertId();
              $itemInsert = $pdo->prepare(
                'INSERT INTO booking_items (booking_id,variant_id,qty,harga_per_hari,subtotal)
                 VALUES (?,?,?,?,?)'
              );
              $itemInsert->execute([$bookingId, $bookingVariantId, $quantity, $item['harga_per_hari'], $subtotal]);

              $paymentStatus = $paymentMethod === 'bayar_di_tempat'
                ? 'bayar_di_tempat'
                : 'menunggu_verifikasi';
              $legacyStatus = 'pending';
              $paymentInsert = $pdo->prepare(
                'INSERT INTO payments
                  (booking_id,jenis,jumlah,metode,bukti_bayar,status,status_pembayaran,waktu_upload)
                 VALUES (?,?,?,?,?,?,?,?)'
              );
              $paymentInsert->execute([
                $bookingId,
                'lunas',
                $paymentAmount,
                $paymentMethod,
                $proofFilename,
                $legacyStatus,
                $paymentStatus,
                $proofFilename ? date('Y-m-d H:i:s') : null,
              ]);
              $pdo->commit();
              header('Location: riwayat.php?baru=' . rawurlencode($bookingCode));
              exit;
            }
          } catch (PDOException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($proofFilename) { payment_remove_proof($proofFilename); }
            $error = 'Booking atau pembayaran gagal disimpan. Periksa database dan coba kembali.';
          }
        }
      }
    }
  }
}

include 'includes/header.php';
?>
<div class="box booking-checkout">
  <h2>Sewa: <?= e($item['nama']) ?><?= (int)$item['is_default'] === 1 ? '' : ' (' . e($item['nama_varian']) . ')' ?></h2>
  <p class="muted"><?= rp($item['harga_per_hari']) ?>/hari</p>
  <p class="muted">Jaminan: KTP/SIM wajib dibawa saat pengambilan alat.</p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" id="booking-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <?php if ((int)$item['is_default'] === 1): ?>
      <input type="hidden" name="pid" value="<?= (int)$item['product_id'] ?>">
    <?php else: ?>
      <input type="hidden" name="vid" value="<?= $bookingVariantId ?>">
    <?php endif; ?>
    <label for="start">Tanggal Mulai</label><input id="start" type="date" name="start" value="<?= e($start) ?>" min="<?= date('Y-m-d') ?>" required>
    <label for="end">Tanggal Selesai</label><input id="end" type="date" name="end" value="<?= e($end) ?>" min="<?= e($start ?: date('Y-m-d')) ?>" required>
    <label for="qty">Jumlah Unit</label><input id="qty" type="number" name="qty" min="1" max="100" value="<?= (int)$quantity ?>" required>
    <button class="btn alt" type="submit" name="cek" value="1">Cek Ketersediaan & Total</button>

    <?php if ($calculation): ?>
      <hr>
      <div class="alert ok">
        Durasi: <?= (int)$calculation['duration'] ?> hari<br>
        Biaya sewa: <strong><?= rp($calculation['subtotal']) ?></strong><br>
        <strong>Total pembayaran: <?= rp($calculation['total']) ?></strong>
      </div>
      <label for="metode_pengambilan">Metode Pengambilan</label>
      <select id="metode_pengambilan" name="metode_pengambilan">
        <option value="ambil_toko"<?= $pickupMethod === 'ambil_toko' ? ' selected' : '' ?>>Ambil di Toko</option>
        <option value="kurir"<?= $pickupMethod === 'kurir' ? ' selected' : '' ?>>Kirim Kurir</option>
      </select>
      <label for="alamat">Alamat Pengiriman (jika kurir)</label>
      <textarea id="alamat" name="alamat"><?= e($_POST['alamat'] ?? '') ?></textarea>

      <fieldset class="payment-methods">
        <legend>Metode Pembayaran</legend>
        <label class="payment-method-option<?= $qrisImageAvailable ? '' : ' is-disabled' ?>"><input type="radio" name="metode_pembayaran" value="qris"<?= $paymentMethod === 'qris' ? ' checked' : '' ?><?= $qrisImageAvailable ? '' : ' disabled' ?>><span><strong>QRIS</strong><small><?= $qrisImageAvailable ? 'Scan QRIS merchant, lalu unggah bukti pembayaran' : 'QRIS sementara tidak tersedia' ?></small></span></label>
        <label class="payment-method-option">
          <input type="radio" name="metode_pembayaran" value="transfer_bank"<?= $paymentMethod === 'transfer_bank' ? ' checked' : '' ?>>
          <span><strong>Transfer Bank</strong><small>Bayar melalui transfer ke rekening kami</small></span>
        </label>
        <label class="payment-method-option"><input type="radio" name="metode_pembayaran" value="bayar_di_tempat"<?= $paymentMethod === 'bayar_di_tempat' ? ' checked' : '' ?>><span><strong>Bayar di Tempat</strong><small>Bayar saat mengambil alat</small></span></label>
      </fieldset>

      <section class="payment-instructions qris-payment-card" data-payment-panel="qris"<?= $paymentMethod === 'qris' ? '' : ' hidden' ?>>
        <div class="qris-payment-heading">
          <span class="qris-payment-eyebrow">QRIS MERCHANT</span>
          <h3>Pembayaran QRIS</h3>
          <p>Scan QRIS merchant menggunakan aplikasi pembayaran pilihanmu.</p>
        </div>
        <div class="qris-amount">
          <span>Total yang harus dibayar</span>
          <strong><?= rp($calculation['payment_amount']) ?></strong>
        </div>
        <?php if ($qrisImageAvailable): ?>
          <div class="merchant-qris-frame">
            <img class="merchant-qris" src="<?= e(payment_qris_image_url()) ?>" alt="QRIS statis merchant Rental Alat Pendakian" data-qris-image>
            <div class="qris-image-error" data-qris-image-error hidden>
              Gambar QRIS gagal dimuat. Muat ulang halaman atau pilih metode pembayaran lain.
            </div>
          </div>
        <?php else: ?>
          <div class="alert err qris-image-error">Gambar QRIS merchant tidak ditemukan atau formatnya tidak valid. QRIS tidak dapat digunakan saat ini.</div>
        <?php endif; ?>
        <div class="qris-payment-steps">
          <strong>Petunjuk pembayaran</strong>
          <ol>
            <li>Masukkan nominal <strong><?= rp($calculation['payment_amount']) ?></strong> pada aplikasi pembayaran. QRIS ini menggunakan kode statis.</li>
            <li>Setelah pembayaran berhasil, simpan tangkapan layar atau bukti transaksi.</li>
            <li>Unggah bukti di bawah. Pembayaran baru diproses setelah diverifikasi admin.</li>
          </ol>
        </div>
        <div class="payment-proof-field qris-proof-field" data-payment-proof>
          <label for="qris-proof-file">Unggah Bukti Pembayaran QRIS</label>
          <input id="qris-proof-file" type="file" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          <small>Format JPG, JPEG, PNG, WEBP · maksimal 2 MB.</small>
        </div>
      </section>
      <section class="payment-instructions" data-payment-panel="transfer_bank"<?= $paymentMethod === 'transfer_bank' ? '' : ' hidden' ?>>
        <h3>PEMBAYARAN TRANSFER BANK</h3>
        <p>Nama Bank: <strong><?= e($paymentConfig['bank']['nama_bank']) ?></strong><br>
          Nomor Rekening: <strong><?= e($paymentConfig['bank']['nomor_rekening']) ?></strong><br>
          Atas Nama: <strong><?= e($paymentConfig['bank']['nama_pemilik']) ?></strong></p>
        <p>Total pembayaran: <strong><?= rp($calculation['payment_amount']) ?></strong></p>
        <p>Silakan transfer sesuai total pembayaran, kemudian upload bukti transfer.</p>
        <div data-proof-target="transfer_bank"></div>
      </section>
      <section class="payment-instructions" data-payment-panel="bayar_di_tempat"<?= $paymentMethod === 'bayar_di_tempat' ? '' : ' hidden' ?>>
        <h3>Bayar di Tempat</h3>
        <p>Pembayaran dilakukan saat pengambilan alat. Pesanan tidak dianggap lunas sampai Admin mengonfirmasi pembayaran diterima.</p>
        <p>Total pembayaran: <strong><?= rp($calculation['payment_amount']) ?></strong></p>
      </section>
      <button class="btn booking-submit" type="submit" name="pesan" value="1">Buat Booking & Kirim Pembayaran</button>
    <?php endif; ?>
  </form>
</div>
<script>
(() => {
  const form = document.getElementById('booking-form');
  const radios = form.querySelectorAll('input[name="metode_pembayaran"]');
  const panels = form.querySelectorAll('[data-payment-panel]');
  const qrisOption = form.querySelector('input[name="metode_pembayaran"][value="qris"]');
  const proofField = form.querySelector('[data-payment-proof]');
  if (!proofField) return;
  const proofInput = proofField.querySelector('input[type="file"]');
  const proofLabel = proofField.querySelector('label');
  const qrisImage = form.querySelector('[data-qris-image]');
  const qrisImageError = form.querySelector('[data-qris-image-error]');
  const updateMethod = () => {
    const selected = form.querySelector('input[name="metode_pembayaran"]:checked');
    panels.forEach((panel) => { panel.hidden = panel.dataset.paymentPanel !== selected?.value; });
    const requiresProof = selected?.value === 'qris' || selected?.value === 'transfer_bank';
    const proofTarget = selected?.value === 'transfer_bank'
      ? form.querySelector('[data-proof-target="transfer_bank"]')
      : form.querySelector('[data-payment-panel="qris"]');
    proofTarget.append(proofField);
    proofField.hidden = !requiresProof;
    proofInput.required = requiresProof;
    proofLabel.textContent = selected?.value === 'transfer_bank' ? 'Unggah Bukti Transfer' : 'Unggah Bukti Pembayaran QRIS';
  };
  if (qrisImage && qrisImageError && qrisOption) {
    const showQrisError = () => {
      qrisImage.hidden = true;
      qrisImageError.hidden = false;
      qrisOption.checked = false;
      qrisOption.disabled = true;
      const fallbackOption = Array.from(radios).find((radio) => !radio.disabled);
      if (fallbackOption) fallbackOption.checked = true;
      updateMethod();
    };
    qrisImage.addEventListener('error', showQrisError);
    if (qrisImage.complete && qrisImage.naturalWidth === 0) showQrisError();
  }
  radios.forEach((radio) => radio.addEventListener('change', updateMethod));
  updateMethod();
})();
</script>
<?php include 'includes/footer.php'; ?>
