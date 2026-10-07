<?php include 'includes/header.php'; login_required();
$st = $pdo->prepare("SELECT b.*, GROUP_CONCAT(CONCAT(p.nama,' ',v.nama_varian,' x',bi.qty) SEPARATOR ', ') AS items
 FROM bookings b JOIN booking_items bi ON bi.booking_id=b.id JOIN product_variants v ON v.id=bi.variant_id JOIN products p ON p.id=v.product_id
 WHERE b.user_id=? GROUP BY b.id ORDER BY b.id DESC"); $st->execute([$_SESSION['user_id']]); $rows = $st->fetchAll(); ?>
<h2>Sewa Saya</h2><?php if (isset($_GET['baru'])) echo '<div class="alert ok">Pesanan ' . e($_GET['baru']) . ' dibuat. Menunggu verifikasi admin.</div>'; ?>
<table><tr><th>Kode</th><th>Alat</th><th>Tanggal</th><th>Total</th><th>Status</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['kode_booking']) ?></td><td><?= e($r['items']) ?></td>
<td><?= e($r['tgl_mulai']) ?> s.d. <?= e($r['tgl_selesai']) ?></td><td><?= rp($r['total_bayar']) ?></td><td><b><?= STATUS_LABEL[$r['status']] ?></b></td></tr><?php endforeach; ?>
</table><?php if (!$rows) echo '<p>Belum ada transaksi.</p>'; include 'includes/footer.php'; ?>
