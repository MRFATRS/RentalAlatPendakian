<?php
require_once __DIR__ . '/../includes/admin_products.php';

$productId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($productId === false || $productId === null || $productId < 1) {
  header('Location: index.php?pesan=invalid');
  exit;
}
$productQuery = $pdo->prepare(
  'SELECT p.id,p.nama,p.gambar,c.nama AS kategori FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?'
);
$productQuery->execute([$productId]);
$product = $productQuery->fetch();
if (!$product) {
  header('Location: index.php?pesan=not_found');
  exit;
}
$imageQuery = $pdo->prepare('SELECT nama_foto FROM produk_foto WHERE product_id=?');
$imageQuery->execute([$productId]);
$productImages = array_column($imageQuery->fetchAll(), 'nama_foto');
$referenceQuery = $pdo->prepare(
  'SELECT COUNT(*) FROM booking_items bi JOIN product_variants v ON v.id=bi.variant_id WHERE v.product_id=?'
);
$referenceQuery->execute([$productId]);
$bookingReferences = (int)$referenceQuery->fetchColumn();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!admin_product_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } elseif ($bookingReferences > 0) {
    $error = 'Produk tidak dapat dihapus karena sudah tercatat pada booking. Ini menjaga relasi dan riwayat transaksi tetap utuh.';
  } else {
    try {
      $pdo->beginTransaction();
      $delete = $pdo->prepare('DELETE FROM products WHERE id=?');
      $delete->execute([$productId]);
      if ($delete->rowCount() !== 1) {
        throw new RuntimeException('Produk sudah tidak ditemukan.');
      }
      $pdo->commit();
      if ($product['gambar']) { $productImages[] = $product['gambar']; }
      $imageRemoved = true;
      foreach (array_unique($productImages) as $filename) {
        if (!admin_product_remove_unreferenced_image($pdo, $filename)) { $imageRemoved = false; }
      }
      header('Location: index.php?pesan=' . ($imageRemoved ? 'deleted' : 'deleted_no_image'));
      exit;
    } catch (PDOException $exception) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $error = admin_product_store_error($exception);
    } catch (RuntimeException $exception) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $error = $exception->getMessage();
    }
  }
}

$csrfToken = admin_product_csrf_token();
require_once __DIR__ . '/../includes/admin_layout.php';
?>
<?php admin_layout_start('Hapus Produk', 'products'); ?>
  <div class="admin-page-heading"><div><h1>Hapus Produk</h1><p>Periksa produk sebelum menghapusnya.</p></div></div>
  <section class="admin-form-card admin-delete-card">
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($bookingReferences > 0): ?>
      <div class="alert">Produk ini terkait dengan <?= $bookingReferences ?> detail booking. Penghapusan dinonaktifkan agar riwayat transaksi tidak rusak.</div>
    <?php else: ?>
      <div class="alert err">Produk dan seluruh foto yang terkait akan dihapus permanen.</div>
    <?php endif; ?>
    <h2><?= e($product['nama']) ?></h2>
    <p class="muted">Kategori: <?= e($product['kategori']) ?></p>
    <?php foreach ($productImages as $filename):
      $filename = basename($filename);
      if (!is_file(__DIR__ . '/../uploads/produk/' . $filename)) { continue; }
    ?>
      <img class="admin-delete-image" src="../uploads/produk/<?= e($filename) ?>" alt="<?= e($product['nama']) ?>">
    <?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="id" value="<?= (int)$productId ?>">
      <div class="admin-form-actions">
        <?php if ($bookingReferences === 0): ?><button class="btn admin-button-danger" type="submit">Ya, Hapus Produk</button><?php endif; ?>
        <a class="btn alt" href="index.php">Batal</a>
      </div>
    </form>
  </section>
<?php admin_layout_end(); ?>
