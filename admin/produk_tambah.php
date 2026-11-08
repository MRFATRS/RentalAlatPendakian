<?php
require_once __DIR__ . '/../includes/admin_products.php';

$categories = $pdo->query('SELECT id,nama FROM categories ORDER BY nama')->fetchAll();
$form = ['nama' => '', 'category_id' => '', 'harga_per_hari' => '', 'stok' => '0', 'deskripsi' => ''];
$variantNames = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form = $_POST;
  $variantNames = is_array($_POST['varian'] ?? null) ? $_POST['varian'] : [];
  if (!admin_product_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } elseif (!$categories) {
    $error = 'Belum ada kategori. Tambahkan kategori sebelum membuat produk.';
  } else {
    $validated = admin_product_validate($pdo, $_POST);
    if (isset($validated['error'])) {
      $error = $validated['error'];
    } else {
      $upload = admin_product_upload_many($_FILES['fotos'] ?? []);
      if (isset($upload['error'])) {
        $error = $upload['error'];
      } elseif (!$upload['files']) {
        $error = 'Minimal satu foto produk wajib diunggah.';
      } else {
        $photoLabels = $_POST['warna_baru'] ?? [];
        if (!is_array($photoLabels) || count($photoLabels) !== count($upload['files'])) {
          $error = 'Periksa kembali label setiap foto.';
        } else {
          foreach ($photoLabels as $label) {
            $validatedLabel = admin_product_image_color($label);
            if (isset($validatedLabel['error'])) { $error = $validatedLabel['error']; break; }
          }
        }
        $primaryChoice = (string)($_POST['foto_utama'] ?? 'new:0');
        if (!preg_match('/^new:(\d+)$/', $primaryChoice, $primaryMatch)
            || (int)$primaryMatch[1] >= count($upload['files'])) {
          $error = 'Pilih foto utama produk yang valid.';
        }
        if ($error) {
          foreach ($upload['files'] as $image) { admin_product_remove_image($image['filename']); }
        } else {
          $primaryIndex = (int)$primaryMatch[1];
          $photoLabels = array_map(static function($label){
            return trim($label);
          }, $photoLabels);
          try {
            $pdo->beginTransaction();
            $primaryImage = $upload['files'][$primaryIndex]['filename'];
            $insert = $pdo->prepare(
              'INSERT INTO products (category_id,nama,deskripsi,harga_per_hari,gambar) VALUES (?,?,?,?,?)'
            );
            $insert->execute([
              $validated['data']['category_id'],
              $validated['data']['nama'],
              $validated['data']['deskripsi'],
              $validated['data']['harga_per_hari'],
              $primaryImage,
            ]);
            $productId = (int)$pdo->lastInsertId();
            $insertPhoto = $pdo->prepare(
              'INSERT INTO produk_foto (product_id,nama_foto,warna,is_primary,urutan) VALUES (?,?,?,?,?)'
            );
            foreach ($upload['files'] as $index => $image) {
              $insertPhoto->execute([
                $productId,
                $image['filename'],
                $photoLabels[$index],
                $index === $primaryIndex ? 1 : 0,
                $index,
              ]);
            }
            admin_product_sync_variants($pdo, $productId, $validated['data']['stok'], $validated['data']['varian']);
            $pdo->commit();
            header('Location: index.php?pesan=created');
            exit;
          } catch (PDOException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            foreach ($upload['files'] as $image) { admin_product_remove_image($image['filename']); }
            $error = admin_product_store_error($exception);
          } catch (DomainException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            foreach ($upload['files'] as $image) { admin_product_remove_image($image['filename']); }
            $error = $exception->getMessage();
          }
        }
      }
    }
  }
}

$csrfToken = admin_product_csrf_token();
require_once __DIR__ . '/../includes/admin_layout.php';
?>
<?php admin_layout_start('Tambah Produk', 'products'); ?>
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
      <?php admin_product_variant_fields($variantNames); ?>
      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="deskripsi" rows="5" maxlength="10000"><?= e($form['deskripsi'] ?? '') ?></textarea>
      <label for="fotos">Foto produk <span class="admin-label-note">(JPG, JPEG, PNG, WEBP · maksimal 3 MB per foto · maks. 12 foto)</span></label>
      <input id="fotos" name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-product-photo-input>
      <p class="admin-variant-help">Pilih beberapa foto sekaligus. Tambahkan label warna dan pilih foto utama dari pratinjau.</p>
      <div class="admin-photo-list" data-product-photo-list aria-live="polite"></div>
      <div class="admin-form-actions"><button class="btn" type="submit">Tambah Produk</button><a class="btn alt" href="index.php">Batal</a></div>
    </form>
  </section>
  <script src="../assets/js/admin-product-images.js" defer></script>
<?php admin_layout_end(); ?>
