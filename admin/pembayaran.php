<?php
require_once __DIR__ . '/../includes/manual_payments.php';
admin_required();

$rows = $pdo->query(
  "SELECT pay.id AS payment_id,pay.jumlah,pay.metode,pay.status_pembayaran,pay.bukti_bayar,
      pay.waktu_upload,pay.created_at,b.id AS booking_id,b.kode_booking,b.subtotal,
      u.nama AS nama_user,
      GROUP_CONCAT(CONCAT(p.nama,' x',bi.qty) SEPARATOR ', ') AS items
   FROM payments pay
   JOIN bookings b ON b.id=pay.booking_id
   JOIN users u ON u.id=b.user_id
   LEFT JOIN booking_items bi ON bi.booking_id=b.id
   LEFT JOIN product_variants v ON v.id=bi.variant_id
   LEFT JOIN products p ON p.id=v.product_id
   WHERE pay.id=(
     SELECT MAX(latest.id) FROM payments latest WHERE latest.booking_id=pay.booking_id
   )
   GROUP BY pay.id,b.id,u.id
   ORDER BY pay.created_at DESC"
)->fetchAll();
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['menunggu_verifikasi', 'lunas', 'ditolak', 'bayar_di_tempat', 'belum_dibayar'];
if (in_array($statusFilter, $allowedStatuses, true)) {
  $rows = array_values(array_filter($rows, static function($row) use ($statusFilter) {
    return $row['status_pembayaran'] === $statusFilter;
  }));
}
?>
<?php require_once __DIR__ . '/../includes/admin_layout.php'; admin_layout_start('Pembayaran', 'payments'); ?>
<div class="admin-page-heading">
  <div><h1>Pembayaran</h1><p>Periksa bukti pembayaran dan status transaksi booking.</p></div>
  <a class="btn alt" href="index.php">Kembali ke Dashboard</a>
</div>
<section class="admin-section">
  <div class="payment-filter">
    <a class="chip <?= $statusFilter === '' ? 'on' : '' ?>" href="pembayaran.php">Semua</a>
    <?php foreach ($allowedStatuses as $status): ?>
      <a class="chip <?= $statusFilter === $status ? 'on' : '' ?>" href="pembayaran.php?status=<?= e($status) ?>"><?= e(payment_status_label($status)) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($rows): ?>
    <div class="admin-table-wrap">
      <table class="admin-table payment-admin-table">
        <thead><tr><th>Booking</th><th>User</th><th>Alat</th><th>Biaya Sewa</th><th>Jumlah Dibayar</th><th>Metode</th><th>Status Pembayaran</th><th>Bukti</th><th>Waktu</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['kode_booking']) ?></td>
            <td><?= e($row['nama_user']) ?></td>
            <td><?= e($row['items'] ?? '—') ?></td>
            <td><?= rp($row['subtotal']) ?></td>
            <td><?= rp($row['jumlah']) ?></td>
            <td><?= e(payment_method_label($row['metode'])) ?></td>
            <td><span class="payment-status status-<?= e($row['status_pembayaran']) ?>"><?= e(payment_status_label($row['status_pembayaran'])) ?></span></td>
            <td><?php if ($row['bukti_bayar'] && is_file(__DIR__ . '/../uploads/bukti_pembayaran/' . basename($row['bukti_bayar']))): ?>
              <a href="../uploads/bukti_pembayaran/<?= e(basename($row['bukti_bayar'])) ?>" target="_blank" rel="noopener">Lihat Bukti</a>
            <?php else: ?><span class="muted">Tidak ada</span><?php endif; ?></td>
            <td><?= e($row['waktu_upload'] ?: $row['created_at']) ?></td>
            <td><a class="btn admin-button-small" href="pembayaran_detail.php?id=<?= (int)$row['payment_id'] ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?><div class="admin-empty"><h3>Tidak ada pembayaran</h3><p>Belum ada transaksi untuk filter ini.</p></div><?php endif; ?>
</section>
<?php admin_layout_end(); ?>
