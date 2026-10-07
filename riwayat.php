<?php
require_once __DIR__ . '/includes/manual_payments.php';
login_required();
$st = $pdo->prepare(
  "SELECT b.id,b.kode_booking,b.tgl_mulai,b.tgl_selesai,b.durasi_hari,b.subtotal,b.total_bayar,b.status AS status_booking,
      item_list.items,pay.id AS payment_id,pay.metode,pay.jumlah AS payment_amount,
      pay.status_pembayaran,pay.bukti_bayar
   FROM bookings b
   JOIN (
     SELECT bi.booking_id,
       GROUP_CONCAT(CONCAT(p.nama,' ',v.nama_varian,' x',bi.qty) SEPARATOR ', ') AS items
     FROM booking_items bi
     JOIN product_variants v ON v.id=bi.variant_id
     JOIN products p ON p.id=v.product_id
     GROUP BY bi.booking_id
   ) item_list ON item_list.booking_id=b.id
   LEFT JOIN payments pay ON pay.id=(
     SELECT MAX(latest.id) FROM payments latest WHERE latest.booking_id=b.id
   )
   WHERE b.user_id=?
   ORDER BY b.id DESC"
);
$st->execute([(int)$_SESSION['user_id']]);
$rows = $st->fetchAll();
$bookingStatusLabels = [
  'menunggu_verifikasi' => 'Menunggu Verifikasi',
  'disetujui' => 'Diproses',
  'sedang_disewa' => 'Sedang Disewa',
  'selesai' => 'Selesai',
  'denda' => 'Denda / Terlambat',
  'dibatalkan' => 'Dibatalkan',
];
include 'includes/header.php';
?>
<h2>Sewa Saya</h2>
<?php if (isset($_GET['baru'])): ?><div class="alert ok">Booking <?= e($_GET['baru']) ?> dibuat. Periksa status pembayaran di bawah ini.</div><?php endif; ?>
<?php if (($_GET['pesan'] ?? '') === 'bukti_dikirim'): ?><div class="alert ok">Bukti pembayaran berhasil dikirim. Admin akan memeriksanya.</div><?php endif; ?>
<?php if ($rows): ?>
  <div class="payment-history-list">
    <?php foreach ($rows as $row):
      $method = payment_method_key($row['metode']);
      $canResubmit = $row['payment_id']
        && in_array($row['status_pembayaran'], ['ditolak', 'belum_dibayar'], true)
        && $method !== 'bayar_di_tempat';
    ?>
      <article class="payment-history-card">
        <div class="payment-history-heading">
          <strong>Booking #<?= e($row['kode_booking']) ?></strong>
          <span><?= e($bookingStatusLabels[$row['status_booking']] ?? $row['status_booking']) ?></span>
        </div>
        <p><?= e($row['items']) ?></p>
        <dl>
          <div><dt>Tanggal sewa</dt><dd><?= e($row['tgl_mulai']) ?> s.d. <?= e($row['tgl_selesai']) ?></dd></div>
          <div><dt>Lama sewa</dt><dd><?= (int)$row['durasi_hari'] ?> hari</dd></div>
          <div><dt>Biaya sewa</dt><dd><?= rp($row['subtotal']) ?></dd></div>
          <div><dt>Total pembayaran</dt><dd><?= rp($row['total_bayar']) ?></dd></div>
          <div><dt>Metode pembayaran</dt><dd><?= e(payment_method_label($row['metode'])) ?></dd></div>
          <div><dt>Jumlah tagihan</dt><dd><?= $row['payment_amount'] !== null ? rp($row['payment_amount']) : '—' ?></dd></div>
          <div><dt>Status pembayaran</dt><dd><span class="payment-status status-<?= e($row['status_pembayaran'] ?? 'belum_dibayar') ?>"><?= e(payment_status_label($row['status_pembayaran'])) ?></span></dd></div>
        </dl>
        <?php if ($canResubmit): ?>
          <a class="btn" href="pembayaran.php?id=<?= (int)$row['payment_id'] ?>">Kirim / Perbaiki Bukti Pembayaran</a>
        <?php elseif ($row['status_pembayaran'] === 'bayar_di_tempat'): ?>
          <p class="alert">Bayar saat pengambilan alat. Booking akan diproses setelah pembayaran dikonfirmasi Admin.</p>
        <?php elseif ($row['status_pembayaran'] === 'ditolak'): ?>
          <p class="alert err">Pembayaran ditolak. Hubungi admin untuk bantuan.</p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p>Belum ada transaksi.</p>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
