<?php
require_once __DIR__ . '/../includes/admin_products.php';

$productId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($productId === false || $productId === null || $productId < 1) {
  header('Location: index.php?pesan=invalid');
  exit;
}
$productQuery = $pdo->prepare(
  'SELECT id,category_id,nama,deskripsi,harga_per_hari,gambar FROM products WHERE id=?'
);
$productQuery->execute([$productId]);
$product = $productQuery->fetch();
if (!$product) {
  header('Location: index.php?pesan=not_found');
  exit;
}
$categories = $pdo->query('SELECT id,nama FROM categories ORDER BY nama')->fetchAll();
$variants = admin_product_stock_variants($pdo, $productId);
$currentStock = array_sum(array_column($variants, 'stok_total'));
$form = [
  'nama' => $product['nama'],
  'category_id' => $product['category_id'],
  'harga_per_hari' => $product['harga_per_hari'],
  'stok' => $currentStock,
  'deskripsi' => $product['deskripsi'],
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form = $_POST;
  if (!admin_product_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } else {
    $validated = admin_product_validate($pdo, $_POST);
    if (isset($validated['error'])) {
      $error = $validated['error'];
    } else {
      $upload = admin_product_upload($_FILES['gambar'] ?? []);
      if (isset($upload['error'])) {
        $error = $upload['error'];
      } else {
        $newImage = $upload['filename'];
        try {
          $pdo->beginTransaction();
          if ($newImage) {
            $update = $pdo->prepare(
              'UPDATE products SET category_id=?,nama=?,deskripsi=?,harga_per_hari=?,gambar=? WHERE id=?'
            );
            $update->execute([
              $validated['data']['category_id'],
              $validated['data']['nama'],
              $validated['data']['deskripsi'],
              $validated['data']['harga_per_hari'],
              $newImage,
              $productId,
            ]);
          } else {
            $update = $pdo->prepare(
              'UPDATE products SET category_id=?,nama=?,deskripsi=?,harga_per_hari=? WHERE id=?'
            );
            $update->execute([
              $validated['data']['category_id'],
              $validated['data']['nama'],
              $validated['data']['deskripsi'],
              $validated['data']['harga_per_hari'],
              $productId,
            ]);
          }
          admin_product_set_stock($pdo, $productId, $validated['data']['stok']);
          $pdo->commit();

          if ($newImage && $product['gambar'] && !admin_product_remove_image($product['gambar'])) {
            header('Location: index.php?pesan=updated_image_cleanup');
          } else {
            header('Location: index.php?pesan=updated');
          }
          exit;
        } catch (PDOException $exception) {
          if ($pdo->inTransaction()) { $pdo->rollBack(); }
          if ($newImage) { admin_product_remove_image($newImage); }
          $error = admin_product_store_error($exception);
        } catch (DomainException $exception) {
          if ($pdo->inTransaction()) { $pdo->rollBack(); }
          if ($newImage) { admin_product_remove_image($newImage); }
          $error = $exception->getMessage();
        }
      }
    }
  }
}

$csrfToken = admin_product_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Produk · Admin Rental Pendakian</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav><a class="brand" href="index.php">Admin · Rental Pendakian</a><div><a href="index.php">Kembali ke Dashboard</a><a href="logout.php">Logout Admin</a></div></nav>
<main class="admin-dashboard">
  <div class="admin-page-heading"><div><h1>Edit Produk</h1><p>Perbarui detail produk dan stoknya.</p></div></div>
  <section class="admin-form-card">
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form class="admin-product-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="id" value="<?= (int)$productId ?>">
      <label for="nama">Nama produk</label>
      <input id="nama" name="nama" maxlength="150" value="<?= e($form['nama'] ?? '') ?>" required>
      <label for="category_id">Kategori</label>
      <select id="category_id" name="category_id" required>
        <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"<?= (string)($form['category_id'] ?? '') === (string)$category['id'] ? ' selected' : '' ?>><?= e($category['nama']) ?></option><?php endforeach; ?>
      </select>
      <div class="admin-form-row">
        <div><label for="harga_per_hari">Harga sewa per hari (Rp)</label><input id="harga_per_hari" name="harga_per_hari" type="number" min="1" step="1" value="<?= e($form['harga_per_hari'] ?? '') ?>" required></div>
        <div><label for="stok">Stok total</label><input id="stok" name="stok" type="number" min="0" step="1" value="<?= e($form['stok'] ?? '0') ?>" required></div>
      </div>
      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="deskripsi" rows="5" maxlength="10000"><?= e($form['deskripsi'] ?? '') ?></textarea>
      <?php if ($product['gambar'] && is_file(__DIR__ . '/../uploads/produk/' . basename($product['gambar']))): ?>
        <div class="admin-current-image"><span>Foto saat ini</span><img src="../uploads/produk/<?= e(basename($product['gambar'])) ?>" alt="<?= e($product['nama']) ?>"></div>
      <?php endif; ?>
      <label for="gambar">Ganti foto <span class="admin-label-note">(opsional · JPG, PNG, WEBP · maksimal 3 MB)</span></label>
      <input id="gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp">
      <div class="admin-form-actions"><button class="btn" type="submit">Simpan Perubahan</button><a class="btn alt" href="index.php">Batal</a></div>
    </form>
  </section>
</main>
</body>
</html>
