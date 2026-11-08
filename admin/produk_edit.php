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
$variantNames = array_column(admin_product_managed_variants($pdo, $productId), 'nama_varian');
$currentStock = array_sum(array_column($variants, 'stok_total'));
$images = admin_product_images($pdo, $productId);
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
  $variantNames = is_array($_POST['varian'] ?? null) ? $_POST['varian'] : [];
  if (!admin_product_verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman dan coba kembali.';
  } else {
    $validated = admin_product_validate($pdo, $_POST);
    if (isset($validated['error'])) {
      $error = $validated['error'];
    } else {
      $deletedIds = $_POST['hapus_foto'] ?? [];
      if (!is_array($deletedIds)) {
        $error = 'Daftar foto yang akan dihapus tidak valid.';
        $deletedIds = [];
      }
      $deletedIds = array_values(array_unique(array_map('strval', $deletedIds)));
      $imageById = [];
      foreach ($images as $image) { $imageById[(string)$image['id']] = $image; }
      foreach ($deletedIds as $deletedId) {
        if (!isset($imageById[$deletedId])) { $error = 'Foto yang dipilih tidak ditemukan.'; break; }
      }

      $colors = $_POST['warna'] ?? [];
      if (!is_array($colors)) {
        $error = 'Label foto tidak valid.';
        $colors = [];
      }
      $normalizedColors = [];
      foreach ($images as $image) {
        $colorResult = admin_product_image_color($colors[(string)$image['id']] ?? (string)($image['warna'] ?? ''));
        if (isset($colorResult['error'])) { $error = $colorResult['error']; break; }
        $normalizedColors[(string)$image['id']] = $colorResult['value'];
      }

      $newUpload = ['files' => []];
      $replacementFiles = [];
      if (!$error) {
        foreach ($images as $image) {
          $imageId = (string)$image['id'];
          if (in_array($imageId, $deletedIds, true)) { continue; }
          $replacement = admin_product_upload_many(admin_product_file_at($_FILES['ganti_foto'] ?? [], $imageId));
          if (isset($replacement['error'])) {
            $error = $replacement['error'];
            break;
          }
          if ($replacement['files']) {
            $replacementFiles[$imageId] = $replacement['files'][0]['filename'];
          }
        }
      }
      if (!$error) {
        $newUpload = admin_product_upload_many($_FILES['fotos'] ?? []);
        if (isset($newUpload['error'])) {
          $error = $newUpload['error'];
        }
      }

      $newColors = $_POST['warna_baru'] ?? [];
      if (!$error && (!is_array($newColors) || count($newColors) !== count($newUpload['files']))) {
        $error = 'Periksa kembali label setiap foto baru.';
      }
      if (!$error) {
        foreach ($newColors as $label) {
          $colorResult = admin_product_image_color($label);
          if (isset($colorResult['error'])) { $error = $colorResult['error']; break; }
        }
      }

      $remainingImages = count($images) - count($deletedIds);
      if (!$error && $remainingImages + count($newUpload['files']) > 12) {
        $error = 'Setiap produk maksimal memiliki 12 foto.';
      }
      $primaryChoice = (string)($_POST['foto_utama'] ?? '');
      if (!$primaryChoice) {
        foreach ($images as $image) {
          if ((int)$image['is_primary'] === 1 && !in_array((string)$image['id'], $deletedIds, true)) {
            $primaryChoice = 'existing:' . $image['id'];
            break;
          }
        }
        if (!$primaryChoice && $remainingImages > 0) {
          foreach ($images as $image) {
            if (!in_array((string)$image['id'], $deletedIds, true)) {
              $primaryChoice = 'existing:' . $image['id'];
              break;
            }
          }
        }
        if (!$primaryChoice && $newUpload['files']) { $primaryChoice = 'new:0'; }
        if (!$primaryChoice) { $primaryChoice = 'none'; }
      }
      $primaryImageId = null;
      $primaryNewIndex = null;
      if (!$error && preg_match('/^existing:(\d+)$/', $primaryChoice, $match)) {
        $primaryImageId = $match[1];
        if (!isset($imageById[$primaryImageId]) || in_array($primaryImageId, $deletedIds, true)) {
          $error = 'Foto utama harus dipilih dari foto yang tetap disimpan.';
        }
      } elseif (!$error && preg_match('/^new:(\d+)$/', $primaryChoice, $match)) {
        $primaryNewIndex = (int)$match[1];
        if ($primaryNewIndex >= count($newUpload['files'])) {
          $error = 'Pilih foto utama produk yang valid.';
        }
      } elseif (!$error && $primaryChoice === 'none' && $remainingImages + count($newUpload['files']) === 0) {
        $primaryChoice = 'none';
      } elseif (!$error) {
        $error = 'Pilih foto utama produk yang valid.';
      }

      if ($error) {
        foreach ($replacementFiles as $filename) { admin_product_remove_image($filename); }
        foreach ($newUpload['files'] as $image) { admin_product_remove_image($image['filename']); }
      } else {
        $newColors = array_map(static function($label){ return trim($label); }, $newColors);
        $oldFilesToRemove = [];
        $finalFilenames = [];
        try {
          $pdo->beginTransaction();
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

          $deletePhoto = $pdo->prepare('DELETE FROM produk_foto WHERE id=? AND product_id=?');
          $updatePhoto = $pdo->prepare(
            'UPDATE produk_foto SET nama_foto=?,warna=?,is_primary=0,urutan=? WHERE id=? AND product_id=?'
          );
          $order = 0;
          foreach ($images as $image) {
            $imageId = (string)$image['id'];
            if (in_array($imageId, $deletedIds, true)) {
              $deletePhoto->execute([$image['id'], $productId]);
              $oldFilesToRemove[] = $image['nama_foto'];
              continue;
            }
            $filename = $replacementFiles[$imageId] ?? $image['nama_foto'];
            if (isset($replacementFiles[$imageId])) { $oldFilesToRemove[] = $image['nama_foto']; }
            $updatePhoto->execute([$filename, $normalizedColors[$imageId], $order++, $image['id'], $productId]);
            $finalFilenames[(string)$image['id']] = $filename;
          }

          $insertPhoto = $pdo->prepare(
            'INSERT INTO produk_foto (product_id,nama_foto,warna,is_primary,urutan) VALUES (?,?,?,0,?)'
          );
          $newPhotoIds = [];
          foreach ($newUpload['files'] as $index => $image) {
            $insertPhoto->execute([$productId, $image['filename'], $newColors[$index], $order++]);
            $newPhotoIds[$index] = (int)$pdo->lastInsertId();
            $finalFilenames['new:' . $index] = $image['filename'];
          }

          $pdo->prepare('UPDATE produk_foto SET is_primary=0 WHERE product_id=?')->execute([$productId]);
          $primaryFilename = null;
          if ($primaryChoice !== 'none') {
            $selectedPrimaryId = $primaryImageId !== null
              ? (int)$primaryImageId
              : $newPhotoIds[$primaryNewIndex];
            $pdo->prepare('UPDATE produk_foto SET is_primary=1 WHERE id=? AND product_id=?')
              ->execute([$selectedPrimaryId, $productId]);
            $primaryFilename = $primaryImageId !== null
              ? $finalFilenames[$primaryImageId]
              : $finalFilenames['new:' . $primaryNewIndex];
          }
          $pdo->prepare('UPDATE products SET gambar=? WHERE id=?')->execute([$primaryFilename, $productId]);
          admin_product_sync_variants($pdo, $productId, $validated['data']['stok'], $validated['data']['varian']);
          $pdo->commit();

          $cleanupFailed = false;
          foreach (array_unique($oldFilesToRemove) as $filename) {
            if (!admin_product_remove_unreferenced_image($pdo, $filename)) { $cleanupFailed = true; }
          }
          header('Location: index.php?pesan=' . ($cleanupFailed ? 'updated_image_cleanup' : 'updated'));
          exit;
        } catch (PDOException $exception) {
          if ($pdo->inTransaction()) { $pdo->rollBack(); }
          foreach ($replacementFiles as $filename) { admin_product_remove_image($filename); }
          foreach ($newUpload['files'] as $image) { admin_product_remove_image($image['filename']); }
          $error = admin_product_store_error($exception);
        } catch (DomainException $exception) {
          if ($pdo->inTransaction()) { $pdo->rollBack(); }
          foreach ($replacementFiles as $filename) { admin_product_remove_image($filename); }
          foreach ($newUpload['files'] as $image) { admin_product_remove_image($image['filename']); }
          $error = $exception->getMessage();
        }
      }
    }
  }
}

$csrfToken = admin_product_csrf_token();
require_once __DIR__ . '/../includes/admin_layout.php';
?>
<?php admin_layout_start('Edit Produk', 'products'); ?>
  <div class="admin-page-heading"><div><h1>Edit Produk</h1><p>Perbarui detail, foto, warna, dan stok produk.</p></div></div>
  <section class="admin-form-card">
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form class="admin-product-form" method="post" enctype="multipart/form-data" data-edit-product-images>
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
      <?php admin_product_variant_fields($variantNames); ?>
      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="deskripsi" rows="5" maxlength="10000"><?= e($form['deskripsi'] ?? '') ?></textarea>
      <fieldset class="admin-photo-fieldset">
        <legend>Foto produk <span class="admin-label-note">(maksimal 12 foto)</span></legend>
        <p class="admin-variant-help">Foto yang sudah ada tetap tersimpan kecuali Anda memilih Hapus foto. Perubahan baru berlaku setelah disimpan.</p>
        <div class="admin-photo-list" data-existing-photo-list>
          <?php if (!$images): ?><p class="admin-variant-help">Belum ada foto tersimpan. Foto kategori akan digunakan sebagai tampilan cadangan.</p><?php endif; ?>
          <?php foreach ($images as $image):
            $filename = basename($image['nama_foto']);
            $imagePath = __DIR__ . '/../uploads/produk/' . $filename;
          ?>
            <article class="admin-photo-row" data-existing-photo>
              <?php if (is_file($imagePath)): ?>
                <img src="../uploads/produk/<?= e($filename) ?>" alt="Foto <?= e($image['warna'] ?: $product['nama']) ?>" data-photo-preview>
              <?php else: ?>
                <span class="admin-photo-missing" data-photo-preview>Foto tidak ditemukan</span>
              <?php endif; ?>
              <div class="admin-photo-fields">
                <label for="warna-<?= (int)$image['id'] ?>">Label warna</label>
                <input id="warna-<?= (int)$image['id'] ?>" name="warna[<?= (int)$image['id'] ?>]" maxlength="50" value="<?= e($image['warna'] ?? '') ?>" placeholder="Contoh: Merah">
                <label class="admin-photo-file-label" for="ganti-<?= (int)$image['id'] ?>">Ganti foto <span class="admin-label-note">(opsional)</span></label>
                <input id="ganti-<?= (int)$image['id'] ?>" name="ganti_foto[<?= (int)$image['id'] ?>]" type="file" accept="image/jpeg,image/png,image/webp" data-photo-replacement>
              </div>
              <div class="admin-photo-actions">
                <label class="admin-photo-primary"><input type="radio" name="foto_utama" value="existing:<?= (int)$image['id'] ?>"<?= (int)$image['is_primary'] === 1 ? ' checked' : '' ?>> Foto utama</label>
                <label class="admin-photo-delete"><input type="checkbox" name="hapus_foto[]" value="<?= (int)$image['id'] ?>" data-photo-delete> Hapus foto</label>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label for="fotos">Tambah foto <span class="admin-label-note">(JPG, JPEG, PNG, WEBP · maksimal 3 MB per foto)</span></label>
      <input id="fotos" name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-product-photo-input>
      <div class="admin-photo-list" data-product-photo-list aria-live="polite"></div>
      <div class="admin-form-actions"><button class="btn" type="submit">Simpan Perubahan</button><a class="btn alt" href="index.php">Batal</a></div>
    </form>
  </section>
  <script src="../assets/js/admin-product-images.js" defer></script>
<?php admin_layout_end(); ?>
