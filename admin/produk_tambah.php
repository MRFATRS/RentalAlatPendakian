<?php
require_once __DIR__ . '/../includes/admin_products.php';

$categories = $pdo->query('SELECT id,nama FROM categories ORDER BY nama')->fetchAll();
$form = ['nama' => '', 'category_id' => '', 'harga_per_hari' => '', 'stok' => '0', 'deskripsi' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form = $_POST;
  if (!admin_product_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } elseif (!$categories) {
    $error = 'Belum ada kategori. Tambahkan kategori sebelum membuat produk.';
  } else {
    $validated = admin_product_validate($pdo, $_POST);
    if (isset($validated['error'])) {
      $error = $validated['error'];
    } else {
      $upload = admin_product_upload($_FILES['gambar'] ?? []);
      if (isset($upload['error'])) {
        $error = $upload['error'];
      } elseif (!$upload['filename']) {
        $error = 'Foto produk wajib diunggah.';
      } else {
        $image = $upload['filename'];
        try {
          $pdo->beginTransaction();
          $insert = $pdo->prepare(
            'INSERT INTO products (category_id,nama,deskripsi,harga_per_hari,gambar) VALUES (?,?,?,?,?)'
          );
          $insert->execute([
            $validated['data']['category_id'],
            $validated['data']['nama'],
            $validated['data']['deskripsi'],
            $validated['data']['harga_per_hari'],
            $image,
          ]);
          admin_product_set_stock($pdo, $pdo->lastInsertId(), $validated['data']['stok']);
          $pdo->commit();
          header('Location: index.php?pesan=created');
          exit;
        } catch (PDOException $exception) {
          if ($pdo->inTransaction()) { $pdo->rollBack(); }
          admin_product_remove_image($image);
          $error = admin_product_store_error($exception);
        } catch (DomainException $exception) {
          if ($pdo->inTransaction()) { $pdo->rollBack(); }
          admin_product_remove_image($image);
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
  <title>Tambah Produk · Admin Rental Pendakian</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav><a class="brand" href="index.php">Admin · Rental Pendakian</a><div><a href="index.php">Kembali ke Dashboard</a><a href="logout.php">Logout Admin</a></div></nav>
<main class="admin-dashboard">
  <div class="admin-page-heading"><div><h1>Tambah Produk</h1><p>Isi detail perlengkapan yang akan ditampilkan di katalog.</p></div></div>
  <section class="admin-form-card">
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form class="admin-product-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <label for="nama">Nama produk</label>
      <input id="nama" name="nama" maxlength="150" value="<?= e($form['nama'] ?? '') ?>" required>
      <label for="category_id">Kategori</label>
      <select id="category_id" name="category_id" required>
        <option value="">Pilih kategori</option>
        <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"<?= (string)($form['category_id'] ?? '') === (string)$category['id'] ? ' selected' : '' ?>><?= e($category['nama']) ?></option><?php endforeach; ?>
      </select>
      <div class="admin-form-row">
        <div><label for="harga_per_hari">Harga sewa per hari (Rp)</label><input id="harga_per_hari" name="harga_per_hari" type="number" min="1" step="1" value="<?= e($form['harga_per_hari'] ?? '') ?>" required></div>
        <div><label for="stok">Stok</label><input id="stok" name="stok" type="number" min="0" step="1" value="<?= e($form['stok'] ?? '0') ?>" required></div>
      </div>
      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="deskripsi" rows="5" maxlength="10000"><?= e($form['deskripsi'] ?? '') ?></textarea>
      <label for="gambar">Foto produk <span class="admin-label-note">(JPG, PNG, WEBP · maksimal 3 MB)</span></label>
      <input id="gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp" required>
      <div class="admin-form-actions"><button class="btn" type="submit">Tambah Produk</button><a class="btn alt" href="index.php">Batal</a></div>
    </form>
  </section>
</main>
</body>
</html>
