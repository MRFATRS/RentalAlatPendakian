<?php include 'includes/header.php';
$st = $pdo->prepare("SELECT id,nama,deskripsi,harga_per_hari,gambar FROM products WHERE id=? AND is_active=1"); $st->execute([$_GET['id'] ?? 0]);
$p = $st->fetch(); if (!$p) { echo '<p>Alat tidak ditemukan.</p>'; include 'includes/footer.php'; exit; }
$v = $pdo->prepare("SELECT id,nama_varian FROM product_variants WHERE product_id=? AND is_active=1 AND is_default=0 ORDER BY id"); $v->execute([$p['id']]); $varian = $v->fetchAll(); ?>
<div class="box"><h2><?= e($p['nama']) ?></h2>
<?php if ($p['gambar']): ?><img src="uploads/produk/<?= e($p['gambar']) ?>" alt="<?= e($p['nama']) ?>" style="max-width:100%;max-height:320px;border-radius:8px"><?php endif; ?>
<p><?= nl2br(e($p['deskripsi'])) ?></p>
<p class="price"><?= rp($p['harga_per_hari']) ?> / hari</p>
<form action="booking.php" method="get">
<?php if ($varian): ?>
<label for="variant-id">Pilih Varian / Warna</label>
<select id="variant-id" name="vid" required><option value="">Pilih varian</option><?php foreach ($varian as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['nama_varian']) ?></option><?php endforeach; ?></select>
<?php else: ?>
<p class="muted">Produk ini tidak memiliki pilihan varian.</p>
<input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
<?php endif; ?>
<button class="btn">Sewa Sekarang / Cek Stok</button></form></div>
<?php include 'includes/footer.php'; ?>
