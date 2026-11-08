<?php include 'includes/header.php';
require_once __DIR__ . '/includes/product_images.php';
$st = $pdo->prepare("SELECT p.id,p.nama,p.deskripsi,p.harga_per_hari,p.gambar,c.slug AS kategori_slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.is_active=1"); $st->execute([$_GET['id'] ?? 0]);
$p = $st->fetch(); if (!$p) { echo '<p>Alat tidak ditemukan.</p>'; include 'includes/footer.php'; exit; }
$v = $pdo->prepare("SELECT id,nama_varian FROM product_variants WHERE product_id=? AND is_active=1 AND is_default=0 ORDER BY id"); $v->execute([$p['id']]); $varian = $v->fetchAll();
$fallbackImages = [
  'tenda' => 'tenda.jpg', 'carrier' => 'carrier.jpg', 'sleeping-bag' => 'sleeping-bag.jpg',
  'alat-masak' => 'alat-masak.jpg', 'penerangan' => 'penerangan.jpg',
];
$gallery = product_gallery_images_for_product(
  $pdo,
  $p,
  'assets/images/catalog/' . ($fallbackImages[$p['kategori_slug']] ?? $fallbackImages['tenda'])
); ?>
<section class="box product-detail">
  <h2><?= e($p['nama']) ?></h2>
  <div class="product-detail-layout">
    <div><?php product_gallery_render($gallery, $p['nama'], true); ?></div>
    <div class="product-detail-info">
      <?php if ($p['deskripsi'] !== ''): ?><p><?= nl2br(e($p['deskripsi'])) ?></p><?php endif; ?>
      <p class="price"><?= rp($p['harga_per_hari']) ?> / hari</p>
      <form action="booking.php" method="get">
<?php if ($varian): ?>
<label for="variant-id">Pilih Varian / Warna</label>
<select id="variant-id" name="vid" required><option value="">Pilih varian</option><?php foreach ($varian as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['nama_varian']) ?></option><?php endforeach; ?></select>
<?php else: ?>
<p class="muted">Produk ini tidak memiliki pilihan varian.</p>
<input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
<?php endif; ?>
        <button class="btn">Sewa Sekarang / Cek Stok</button>
      </form>
    </div>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
