<?php
require_once __DIR__ . '/includes/manual_payments.php';
login_required();
$paymentConfig = require __DIR__ . '/config/payment.php';

$variantId = filter_var($_GET['vid'] ?? $_POST['vid'] ?? null, FILTER_VALIDATE_INT);
$variantId = $variantId !== false && $variantId !== null && $variantId > 0 ? $variantId : 0;
$productQuery = $pdo->prepare(
  'SELECT v.id,v.nama_varian,v.stok_total,p.id AS product_id,p.nama,p.harga_per_hari,p.deposit
   FROM product_variants v JOIN products p ON p.id=v.product_id
   WHERE v.id=? AND p.is_active=1'
);
$productQuery->execute([$variantId]);
$item = $productQuery->fetch();
if (!$item) {
  http_response_code(404);
  include 'includes/header.php';
  echo '<div class="alert err">Varian produk tidak ditemukan atau tidak tersedia.</div>';
  include 'includes/footer.php';
  exit;
}

$error = '';
$calculation = null;
$start = (string)($_POST['start'] ?? '');
$end = (string)($_POST['end'] ?? '');
$qtyValue = $_POST['qty'] ?? '1';
$quantity = filter_var($qtyValue, FILTER_VALIDATE_INT);
$quantity = $quantity !== false && $quantity > 0 ? $quantity : 1;
$pickupMethod = (string)($_POST['metode_pengambilan'] ?? 'ambil_toko');
$paymentMethod = (string)($_POST['metode_pembayaran'] ?? 'qris');
$scheme = (string)($_POST['skema'] ?? 'lunas');
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
    } elseif (!in_array($scheme, ['dp50', 'lunas'], true)) {
      $error = 'Pilih skema pembayaran yang valid.';
    } elseif (!in_array($paymentMethod, ['qris', 'transfer_bank', 'bayar_di_tempat'], true)) {
      $error = 'Pilih metode pembayaran yang valid.';
    } elseif (!payment_method_available($paymentMethod, $paymentConfig)) {
      $error = 'Transfer bank belum tersedia. Admin perlu mengisi konfigurasi rekening di config/payment.php.';
    } else {
      $duration = (int)$startDate->diff($endDate)->days;
      $subtotal = $duration * (int)$item['harga_per_hari'] * $quantity;
      $deposit = (int)$item['deposit'] * $quantity;
      $total = $subtotal + $deposit;
      $paymentAmount = $scheme === 'dp50' ? (int)ceil($total / 2) : $total;
      $calculation = [
        'duration' => $duration,
        'subtotal' => $subtotal,
        'deposit' => $deposit,
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
            $lock = $pdo->prepare('SELECT id FROM product_variants WHERE id=? FOR UPDATE');
            $lock->execute([$variantId]);
            if (!$lock->fetchColumn() || stok_tersedia($pdo, $variantId, $start, $end) < $quantity) {
              $pdo->rollBack();
              if ($proofFilename) { payment_remove_proof($proofFilename); }
              $error = 'Stok tidak tersedia pada tanggal ini. Silakan pilih tanggal atau jumlah lain.';
            } else {
              $bookingCode = 'RNT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
              $bookingInsert = $pdo->prepare(
                'INSERT INTO bookings
                  (kode_booking,user_id,tgl_mulai,tgl_selesai,durasi_hari,subtotal,total_deposit,total_bayar,
                   metode_pengambilan,alamat_kirim,skema_bayar)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
              );
              $bookingInsert->execute([
                $bookingCode,
                (int)$_SESSION['user_id'],
                $start,
                $end,
                $duration,
                $subtotal,
                $deposit,
                $total,
                $pickupMethod,
                $pickupMethod === 'kurir' ? trim((string)($_POST['alamat'] ?? '')) : null,
                $scheme,
              ]);
              $bookingId = (int)$pdo->lastInsertId();
              $itemInsert = $pdo->prepare(
                'INSERT INTO booking_items (booking_id,variant_id,qty,harga_per_hari,deposit,subtotal)
                 VALUES (?,?,?,?,?,?)'
              );
              $itemInsert->execute([$bookingId, $variantId, $quantity, $item['harga_per_hari'], $deposit, $subtotal]);

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
                $scheme === 'dp50' ? 'dp' : 'lunas',
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
  <h2>Sewa: <?= e($item['nama']) ?> (<?= e($item['nama_varian']) ?>)</h2>
  <p class="muted"><?= rp($item['harga_per_hari']) ?>/hari · Deposit <?= rp($item['deposit']) ?>/unit</p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" id="booking-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="vid" value="<?= (int)$variantId ?>">
    <label for="start">Tanggal Mulai</label><input id="start" type="date" name="start" value="<?= e($start) ?>" min="<?= date('Y-m-d') ?>" required>
    <label for="end">Tanggal Selesai</label><input id="end" type="date" name="end" value="<?= e($end) ?>" min="<?= e($start ?: date('Y-m-d')) ?>" required>
    <label for="qty">Jumlah Unit</label><input id="qty" type="number" name="qty" min="1" max="100" value="<?= (int)$quantity ?>" required>
    <button class="btn alt" type="submit" name="cek" value="1">Cek Ketersediaan & Total</button>

    <?php if ($calculation): ?>
      <hr>
      <div class="alert ok">
        Durasi: <?= (int)$calculation['duration'] ?> hari · Subtotal: <?= rp($calculation['subtotal']) ?>
        · Deposit: <?= rp($calculation['deposit']) ?><br>
        <strong>Total booking: <?= rp($calculation['total']) ?></strong>
      </div>
      <label for="metode_pengambilan">Metode Pengambilan</label>
      <select id="metode_pengambilan" name="metode_pengambilan">
        <option value="ambil_toko"<?= $pickupMethod === 'ambil_toko' ? ' selected' : '' ?>>Ambil di Toko</option>
        <option value="kurir"<?= $pickupMethod === 'kurir' ? ' selected' : '' ?>>Kirim Kurir</option>
      </select>
      <label for="alamat">Alamat Pengiriman (jika kurir)</label>
      <textarea id="alamat" name="alamat"><?= e($_POST['alamat'] ?? '') ?></textarea>
      <label for="skema">Skema Pembayaran</label>
      <select id="skema" name="skema">
        <option value="dp50"<?= $scheme === 'dp50' ? ' selected' : '' ?>>DP 50%</option>
        <option value="lunas"<?= $scheme === 'lunas' ? ' selected' : '' ?>>Lunas 100%</option>
      </select>

      <fieldset class="payment-methods">
        <legend>Metode Pembayaran</legend>
        <label class="payment-method-option"><input type="radio" name="metode_pembayaran" value="qris"<?= $paymentMethod === 'qris' ? ' checked' : '' ?>><span><strong>QRIS</strong><small>Bayar menggunakan QRIS</small></span></label>
        <label class="payment-method-option<?= payment_method_available('transfer_bank', $paymentConfig) ? '' : ' is-disabled' ?>">
          <input type="radio" name="metode_pembayaran" value="transfer_bank"<?= $paymentMethod === 'transfer_bank' ? ' checked' : '' ?><?= payment_method_available('transfer_bank', $paymentConfig) ? '' : ' disabled' ?>>
          <span><strong>Transfer Bank</strong><small><?= payment_method_available('transfer_bank', $paymentConfig) ? 'Transfer ke rekening rental' : 'Belum tersedia: lengkapi config/payment.php' ?></small></span>
        </label>
        <label class="payment-method-option"><input type="radio" name="metode_pembayaran" value="bayar_di_tempat"<?= $paymentMethod === 'bayar_di_tempat' ? ' checked' : '' ?>><span><strong>Bayar di Tempat</strong><small>Bayar saat mengambil alat</small></span></label>
      </fieldset>

      <section class="payment-instructions" data-payment-panel="qris"<?= $paymentMethod === 'qris' ? '' : ' hidden' ?>>
        <h3>Pembayaran QRIS</h3>
        <p>Total pembayaran: <strong><?= rp($calculation['payment_amount']) ?></strong></p>
        <img class="merchant-qris" src="assets/images/QRIS/qris-rental.png" alt="QRIS Merchant Rental Alat Pendakian">
        <p>Silakan scan QRIS menggunakan aplikasi pembayaran yang mendukung QRIS.</p>
      </section>
      <section class="payment-instructions" data-payment-panel="transfer_bank"<?= $paymentMethod === 'transfer_bank' ? '' : ' hidden' ?>>
        <h3>Pembayaran Transfer Bank</h3>
        <p>Bank: <strong><?= e($paymentConfig['bank_name']) ?></strong><br>
          Nomor rekening: <strong><?= e($paymentConfig['account_number']) ?></strong><br>
          Atas nama: <strong><?= e($paymentConfig['account_holder']) ?></strong></p>
        <p>Total pembayaran: <strong><?= rp($calculation['payment_amount']) ?></strong></p>
        <p>Silakan transfer sesuai nominal pembayaran, kemudian unggah bukti transfer.</p>
      </section>
      <section class="payment-instructions" data-payment-panel="bayar_di_tempat"<?= $paymentMethod === 'bayar_di_tempat' ? '' : ' hidden' ?>>
        <h3>Bayar di Tempat</h3>
        <p>Pembayaran dilakukan saat pengambilan alat. Pesanan tidak dianggap lunas sampai Admin mengonfirmasi pembayaran diterima.</p>
        <p>Total pembayaran: <strong><?= rp($calculation['payment_amount']) ?></strong></p>
      </section>
      <div class="payment-proof-field" data-payment-proof>
        <label for="proof-file" data-proof-label>Bukti Pembayaran</label>
        <input id="proof-file" type="file" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <small>Format JPG, JPEG, PNG, WEBP · maksimal 2 MB.</small>
      </div>
      <button class="btn booking-submit" type="submit" name="pesan" value="1">Buat Booking & Kirim Pembayaran</button>
    <?php endif; ?>
  </form>
</div>
<script>
(() => {
  const form = document.getElementById('booking-form');
  const radios = form.querySelectorAll('input[name="metode_pembayaran"]');
  const panels = form.querySelectorAll('[data-payment-panel]');
  const proofField = form.querySelector('[data-payment-proof]');
  const proofInput = document.getElementById('proof-file');
  const proofLabel = form.querySelector('[data-proof-label]');
  const updateMethod = () => {
    const selected = form.querySelector('input[name="metode_pembayaran"]:checked');
    panels.forEach((panel) => { panel.hidden = panel.dataset.paymentPanel !== selected?.value; });
    const proofRequired = selected?.value === 'qris' || selected?.value === 'transfer_bank';
    proofField.hidden = !proofRequired;
    proofInput.required = proofRequired;
    proofLabel.textContent = selected?.value === 'transfer_bank' ? 'Bukti Transfer' : 'Bukti Pembayaran';
  };
  radios.forEach((radio) => radio.addEventListener('change', updateMethod));
  updateMethod();
})();
</script>
<?php include 'includes/footer.php'; ?>
