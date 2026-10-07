<?php include 'includes/header.php'; login_required();
$vid = (int)($_GET['vid'] ?? $_POST['vid'] ?? 0);
$st = $pdo->prepare("SELECT v.*, p.nama, p.harga_per_hari, p.deposit FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=?");
$st->execute([$vid]); $it = $st->fetch(); if (!$it) { echo '<p>Varian tidak ditemukan.</p>'; include 'includes/footer.php'; exit; }
$msg = ''; $hit = null; $start = $_POST['start'] ?? ''; $end = $_POST['end'] ?? ''; $qty = max(1, (int)($_POST['qty'] ?? 1));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $d1 = date_create($start); $d2 = date_create($end);
  if (!$d1 || !$d2 || $d1 < date_create('today') || $d2 <= $d1) $msg = 'Tanggal tidak valid (mulai minimal hari ini, selesai setelah mulai).';
  else {
    $sisa = stok_tersedia($pdo, $vid, $start, $end);
    if ($sisa < $qty) $msg = "Stok tidak tersedia pada tanggal ini (sisa $sisa unit). Coba tanggal lain.";
    else {
      $hari = $d1->diff($d2)->days; $sub = $hari * $it['harga_per_hari'] * $qty; $dep = $it['deposit'] * $qty;
      $hit = ['hari' => $hari, 'sub' => $sub, 'dep' => $dep, 'total' => $sub + $dep];
      if (isset($_POST['pesan'])) {
        $skema = $_POST['skema'] === 'dp50' ? 'dp50' : 'lunas'; $metode = $_POST['metode'] === 'kurir' ? 'kurir' : 'ambil_toko';
        $bayar = $skema === 'dp50' ? (int)ceil($hit['total'] / 2) : $hit['total']; $kode = 'RNT-' . date('Ymd') . '-' . rand(100, 999);
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO bookings (kode_booking,user_id,tgl_mulai,tgl_selesai,durasi_hari,subtotal,total_deposit,total_bayar,metode_pengambilan,alamat_kirim,skema_bayar) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$kode, $_SESSION['user_id'], $start, $end, $hari, $sub, $dep, $hit['total'], $metode, $_POST['alamat'] ?? null, $skema]);
        $bid = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO booking_items (booking_id,variant_id,qty,harga_per_hari,deposit,subtotal) VALUES (?,?,?,?,?,?)")
            ->execute([$bid, $vid, $qty, $it['harga_per_hari'], $dep, $sub]);
        $pdo->prepare("INSERT INTO payments (booking_id,jenis,jumlah,metode) VALUES (?,?,?,?)")
            ->execute([$bid, $skema === 'dp50' ? 'dp' : 'lunas', $bayar, $_POST['gateway']]);
        $pdo->commit(); header('Location: riwayat.php?baru=' . $kode); exit;
      }
    }
  }
} ?>
<div class="box"><h2>Sewa: <?= e($it['nama']) ?> (<?= e($it['nama_varian']) ?>)</h2>
<p class="muted"><?= rp($it['harga_per_hari']) ?>/hari · Deposit <?= rp($it['deposit']) ?>/unit</p>
<?php if ($msg) echo '<div class="alert err">' . e($msg) . '</div>'; ?>
<form method="post"><input type="hidden" name="vid" value="<?= $vid ?>">
<label>Tanggal Mulai</label><input type="date" name="start" value="<?= e($start) ?>" min="<?= date('Y-m-d') ?>" required>
<label>Tanggal Selesai</label><input type="date" name="end" value="<?= e($end) ?>" required>
<label>Jumlah Unit</label><input type="number" name="qty" min="1" value="<?= $qty ?>" required>
<button class="btn alt">Cek Ketersediaan Stok</button>
<?php if ($hit): ?><hr><div class="alert ok">✅ Stok tersedia.<br>Durasi: <?= $hit['hari'] ?> hari · Subtotal: <?= rp($hit['sub']) ?> · Deposit: <?= rp($hit['dep']) ?><br><b>Total: <?= rp($hit['total']) ?></b></div>
<label>Metode Pengambilan</label><select name="metode"><option value="ambil_toko">Ambil di Toko</option><option value="kurir">Kirim Kurir</option></select>
<label>Alamat Pengiriman (jika kurir)</label><textarea name="alamat"></textarea>
<label>Skema Pembayaran</label><select name="skema"><option value="dp50">DP 50%</option><option value="lunas">Lunas 100%</option></select>
<label>Metode Pembayaran</label><select name="gateway"><option>Virtual Account</option><option>E-Wallet</option><option>Transfer Bank</option></select>
<button class="btn" name="pesan" value="1">Buat Pesanan</button><?php endif; ?></form></div>
<?php include 'includes/footer.php'; ?>
