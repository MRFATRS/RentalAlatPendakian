<?php include 'includes/header.php';
$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
$slug = $_GET['kategori'] ?? '';
$sql = "SELECT p.*, c.nama AS kategori, c.slug AS kategori_slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1";
$args = [];
if ($slug) { $sql .= " AND c.slug=?"; $args[] = $slug; }
$st = $pdo->prepare($sql); $st->execute($args); $produk = $st->fetchAll();
$imageByCategory = [
  'tenda' => 'tenda.jpg',
  'carrier' => 'carrier.jpg',
  'sleeping-bag' => 'sleeping-bag.jpg',
  'alat-masak' => 'alat-masak.jpg',
  'penerangan' => 'penerangan.jpg',
];
$categoryIcons = [
  'tenda' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 20 9-16 9 16H3Z"/><path d="m12 4 3 16M12 13l-3 7"/></svg>',
  'carrier' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5a4 4 0 0 1 8 0"/><path d="M6 7h12l2 13H4L6 7Z"/><path d="M8 11h8v5H8zM6 9l-2 2m14-2 2 2"/></svg>',
  'sleeping-bag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18h18M5 18v-5a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v5"/><path d="M7 10V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v3"/></svg>',
  'alat-masak' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h16l-2 10H6L4 10Z"/><path d="M3 7h18M8 4v3m8-3v3M8 14h8"/></svg>',
  'penerangan' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m14 3 7 7-10 10-7-7L14 3Z"/><path d="m11 6 7 7M3 21l3-3M6 10l8 8"/></svg>',
];
$activeCategory = '';
foreach ($cats as $category) {
  if ($category['slug'] === $slug) { $activeCategory = $category['nama']; break; }
}
?>
<section class="catalog-hero">
  <div class="hero-copy">
    <span class="hero-eyebrow">Teman perjalananmu</span>
    <h1>Siapkan Perlengkapan Pendakianmu</h1>
    <p>Temukan dan sewa perlengkapan pendakian berkualitas untuk menemani perjalananmu.</p>
    <a class="btn btn-light" href="#produk">Lihat Peralatan <span aria-hidden="true">→</span></a>
  </div>
</section>

<section id="produk" aria-labelledby="catalog-title">
  <div class="catalog-heading">
    <div>
      <h2 id="catalog-title">Katalog Alat Pendakian</h2>
      <p>Pilih perlengkapan yang pas untuk petualangan berikutnya.</p>
    </div>
  </div>
  <div class="category-list" aria-label="Filter kategori">
    <a class="chip <?= !$slug ? 'on' : '' ?>" href="index.php">
      <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg></span>
      Semua
    </a>
    <?php foreach ($cats as $c): ?>
      <a class="chip <?= $slug === $c['slug'] ? 'on' : '' ?>" href="?kategori=<?= e($c['slug']) ?>">
        <span class="category-icon" aria-hidden="true"><?= $categoryIcons[$c['slug']] ?? '' ?></span>
        <?= e($c['nama']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($produk): ?>
    <div class="grid">
      <?php foreach ($produk as $p):
        $fallbackImage = 'assets/images/catalog/' . ($imageByCategory[$p['kategori_slug']] ?? $imageByCategory['tenda']);
        $uploadedImage = !empty($p['gambar']) ? basename($p['gambar']) : '';
        $image = $uploadedImage && is_file(__DIR__ . '/uploads/produk/' . $uploadedImage)
          ? 'uploads/produk/' . $uploadedImage
          : $fallbackImage;
        $deskripsi = trim((string)($p['deskripsi'] ?? ''));
        if ($deskripsi === '') { $deskripsi = 'Perlengkapan ' . strtolower($p['kategori']) . ' untuk menemani perjalanan pendakianmu.'; }
      ?>
        <article class="card">
          <div class="card-image">
            <img src="<?= e($image) ?>" alt="<?= e($p['nama']) ?>" loading="lazy">
          </div>
          <div class="card-content">
            <span class="category-badge"><?= e($p['kategori']) ?></span>
            <h3><?= e($p['nama']) ?></h3>
            <p class="card-description"><?= e($deskripsi) ?></p>
            <div class="card-bottom">
              <div class="price"><?= rp($p['harga_per_hari']) ?><small>per hari</small></div>
              <a class="btn" href="produk.php?id=<?= (int)$p['id'] ?>">Lihat Detail <span aria-hidden="true">→</span></a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon" aria-hidden="true">⛰</div>
      <h3><?= $activeCategory ? 'Belum ada alat di kategori ' . e($activeCategory) : 'Katalog belum tersedia' ?></h3>
      <p>Coba pilih kategori lain untuk menemukan perlengkapan yang kamu butuhkan.</p>
      <a class="btn" href="index.php">Lihat Semua Peralatan</a>
    </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
