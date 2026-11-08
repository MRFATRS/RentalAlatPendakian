<?php
require_once __DIR__ . '/functions.php';
admin_required();

function admin_product_csrf_token(){
  if (empty($_SESSION['admin_product_csrf'])) {
    $_SESSION['admin_product_csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['admin_product_csrf'];
}

function admin_product_verify_csrf($token){
  return isset($_SESSION['admin_product_csrf'])
    && is_string($token)
    && hash_equals($_SESSION['admin_product_csrf'], $token);
}

function admin_product_validate(PDO $pdo, array $input){
  $nama = trim((string)($input['nama'] ?? ''));
  $deskripsi = trim((string)($input['deskripsi'] ?? ''));
  $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
  $harga = filter_var($input['harga_per_hari'] ?? null, FILTER_VALIDATE_INT);
  $stok = filter_var($input['stok'] ?? null, FILTER_VALIDATE_INT);
  $rawVariants = $input['varian'] ?? [];

  if ($nama === '' || strlen($nama) > 150) {
    return ['error' => 'Nama produk wajib diisi dan maksimal 150 karakter.'];
  }
  if ($categoryId === false || $categoryId === null) {
    return ['error' => 'Pilih kategori yang valid.'];
  }
  if ($harga === false || $harga < 1) {
    return ['error' => 'Harga sewa harus berupa bilangan bulat lebih dari 0.'];
  }
  if ($stok === false || $stok < 0) {
    return ['error' => 'Stok harus berupa bilangan bulat minimal 0.'];
  }
  if (strlen($deskripsi) > 10000) {
    return ['error' => 'Deskripsi maksimal 10.000 karakter.'];
  }
  if (!is_array($rawVariants) || count($rawVariants) > 50) {
    return ['error' => 'Jumlah varian maksimal 50.'];
  }

  $variants = [];
  $variantKeys = [];
  foreach ($rawVariants as $variantName) {
    if (!is_string($variantName)) {
      return ['error' => 'Nama varian tidak valid.'];
    }
    $variantName = trim($variantName);
    if ($variantName === '') {
      return ['error' => 'Nama varian tidak boleh kosong. Hapus baris yang tidak digunakan.'];
    }
    if (strlen($variantName) > 50) {
      return ['error' => 'Nama varian maksimal 50 karakter.'];
    }
    $variantKey = function_exists('mb_strtolower')
      ? mb_strtolower($variantName, 'UTF-8')
      : strtolower($variantName);
    if (isset($variantKeys[$variantKey])) {
      return ['error' => 'Nama varian tidak boleh duplikat dalam produk yang sama.'];
    }
    $variantKeys[$variantKey] = true;
    $variants[] = $variantName;
  }

  $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id=?');
  $categoryCheck->execute([$categoryId]);
  if (!$categoryCheck->fetchColumn()) {
    return ['error' => 'Kategori tidak ditemukan.'];
  }

  return [
    'data' => [
      'nama' => $nama,
      'category_id' => $categoryId,
      'harga_per_hari' => $harga,
      'stok' => $stok,
      'deskripsi' => $deskripsi,
      'varian' => $variants,
    ],
  ];
}

function admin_product_upload(array $file){
  $result = admin_product_upload_many($file);
  if (isset($result['error'])) { return $result; }
  return ['filename' => $result['files'][0]['filename'] ?? null];
}

function admin_product_upload_many(array $files){
  if (!isset($files['error'])) { return ['files' => []]; }
  if (is_array($files['error'])) {
    $entries = [];
    foreach ($files['error'] as $index => $error) {
      if ($error === UPLOAD_ERR_NO_FILE) { continue; }
      $entries[] = [
        'error' => $error,
        'name' => $files['name'][$index] ?? '',
        'tmp_name' => $files['tmp_name'][$index] ?? '',
        'size' => $files['size'][$index] ?? 0,
      ];
    }
  } elseif ($files['error'] === UPLOAD_ERR_NO_FILE) {
    return ['files' => []];
  } else {
    $entries = [$files];
  }

  if (count($entries) > 12) {
    return ['error' => 'Maksimal 12 foto dapat diunggah dalam satu kali proses.'];
  }
  $directory = dirname(__DIR__) . '/uploads/produk';
  if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    return ['error' => 'Folder upload foto tidak dapat dibuat.'];
  }
  if (!is_writable($directory)) {
    return ['error' => 'Folder uploads/produk tidak memiliki izin tulis.'];
  }

  $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  $uploaded = [];
  foreach ($entries as $file) {
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
      $error = ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? null) === UPLOAD_ERR_FORM_SIZE
        ? 'Ukuran setiap foto maksimal 3 MB.'
        : 'Foto gagal diunggah. Silakan coba kembali.';
      foreach ($uploaded as $image) { admin_product_remove_image($image['filename']); }
      return ['error' => $error];
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || (int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 3 * 1024 * 1024) {
      foreach ($uploaded as $image) { admin_product_remove_image($image['filename']); }
      return ['error' => 'Ukuran setiap foto harus lebih dari 0 dan maksimal 3 MB.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($imageInfo === false || !isset($extensions[$mime]) || $imageInfo['mime'] !== $mime) {
      foreach ($uploaded as $image) { admin_product_remove_image($image['filename']); }
      return ['error' => 'Format setiap foto harus JPG, JPEG, PNG, atau WEBP yang valid.'];
    }
    if ($imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > 10000 || $imageInfo[1] > 10000
        || $imageInfo[0] * $imageInfo[1] > 40000000) {
      foreach ($uploaded as $image) { admin_product_remove_image($image['filename']); }
      return ['error' => 'Dimensi foto terlalu besar. Gunakan foto maksimal 10.000 piksel per sisi dan 40 megapiksel.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
      foreach ($uploaded as $image) { admin_product_remove_image($image['filename']); }
      return ['error' => 'Foto tidak dapat disimpan ke folder produk.'];
    }
    $uploaded[] = ['filename' => $filename, 'original_name' => (string)($file['name'] ?? '')];
  }
  return ['files' => $uploaded];
}

function admin_product_file_at(array $files, $key){
  if (!isset($files['error']) || !is_array($files['error']) || !array_key_exists($key, $files['error'])) {
    return ['error' => UPLOAD_ERR_NO_FILE];
  }
  $file = [];
  foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
    $file[$field] = $files[$field][$key] ?? null;
  }
  return $file;
}

function admin_product_images(PDO $pdo, $productId){
  $query = $pdo->prepare(
    'SELECT id,product_id,nama_foto,warna,is_primary,urutan
     FROM produk_foto WHERE product_id=?
     ORDER BY is_primary DESC,urutan ASC,id ASC'
  );
  $query->execute([$productId]);
  return $query->fetchAll();
}

function admin_product_image_color($value){
  if (!is_string($value)) { return ['error' => 'Label warna tidak valid.']; }
  $value = trim($value);
  if (strlen($value) > 50) { return ['error' => 'Label warna maksimal 50 karakter.']; }
  return ['value' => $value];
}

function admin_product_remove_image($filename){
  $filename = basename((string)$filename);
  if ($filename === '') { return true; }
  $path = dirname(__DIR__) . '/uploads/produk/' . $filename;
  return !is_file($path) || unlink($path);
}

function admin_product_remove_unreferenced_image(PDO $pdo, $filename){
  $filename = basename((string)$filename);
  if ($filename === '') { return true; }
  $reference = $pdo->prepare(
    'SELECT 1 FROM produk_foto WHERE nama_foto=? LIMIT 1'
  );
  $reference->execute([$filename]);
  if ($reference->fetchColumn()) { return true; }
  $legacyReference = $pdo->prepare('SELECT 1 FROM products WHERE gambar=? LIMIT 1');
  $legacyReference->execute([$filename]);
  if ($legacyReference->fetchColumn()) { return true; }
  return admin_product_remove_image($filename);
}

function admin_product_stock_variants(PDO $pdo, $productId){
  $st = $pdo->prepare(
    "SELECT v.id, v.nama_varian, v.stok_total, v.is_default,
      COALESCE(SUM(CASE WHEN b.status IN ('menunggu_verifikasi','disetujui','sedang_disewa','denda')
        THEN bi.qty ELSE 0 END), 0) AS stok_dipesan
     FROM product_variants v
     LEFT JOIN booking_items bi ON bi.variant_id=v.id
     LEFT JOIN bookings b ON b.id=bi.booking_id
     WHERE v.product_id=? AND v.is_active=1
     GROUP BY v.id, v.nama_varian, v.stok_total, v.is_default
     ORDER BY v.id"
  );
  $st->execute([$productId]);
  return $st->fetchAll();
}

function admin_product_managed_variants(PDO $pdo, $productId){
  $st = $pdo->prepare(
    'SELECT id,nama_varian FROM product_variants
     WHERE product_id=? AND is_active=1 AND is_default=0 ORDER BY id'
  );
  $st->execute([$productId]);
  return $st->fetchAll();
}

function admin_product_sync_variants(PDO $pdo, $productId, $stock, array $names){
  $productLock = $pdo->prepare('SELECT id FROM products WHERE id=? FOR UPDATE');
  $productLock->execute([$productId]);
  if (!$productLock->fetchColumn()) {
    throw new DomainException('Produk tidak ditemukan.');
  }

  $current = admin_product_stock_variants($pdo, $productId);
  $currentManaged = [];
  $defaultVariant = null;
  foreach ($current as $variant) {
    if ((int)$variant['is_default'] === 1) {
      $defaultVariant = $variant;
    } else {
      $key = function_exists('mb_strtolower')
        ? mb_strtolower($variant['nama_varian'], 'UTF-8')
        : strtolower($variant['nama_varian']);
      $currentManaged[$key] = $variant;
    }
  }

  $targets = [];
  $retireIds = [];
  if ($names) {
    foreach ($current as $variant) {
      if ((int)$variant['is_default'] === 1) {
        $retireIds[] = (int)$variant['id'];
      }
    }
    foreach ($names as $name) {
      $key = function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);
      if (isset($currentManaged[$key])) {
        $existing = $currentManaged[$key];
        if ($existing['nama_varian'] !== $name) {
          $rename = $pdo->prepare(
            'UPDATE product_variants SET nama_varian=? WHERE id=? AND product_id=?'
          );
          $rename->execute([$name, $existing['id'], $productId]);
          $existing['nama_varian'] = $name;
        }
        $targets[] = $existing;
        unset($currentManaged[$key]);
      } else {
        $insert = $pdo->prepare(
          'INSERT INTO product_variants (product_id,nama_varian,stok_total,is_default,is_active)
           VALUES (?,?,0,0,1)'
        );
        $insert->execute([$productId, $name]);
        $targets[] = [
          'id' => (int)$pdo->lastInsertId(),
          'nama_varian' => $name,
          'stok_total' => 0,
          'stok_dipesan' => 0,
          'is_default' => 0,
        ];
      }
    }
    foreach ($currentManaged as $variant) {
      $retireIds[] = (int)$variant['id'];
    }
  } else {
    foreach ($current as $variant) {
      if ((int)$variant['is_default'] !== 1) {
        $retireIds[] = (int)$variant['id'];
      }
    }
    if ($defaultVariant) {
      $targets[] = $defaultVariant;
    } else {
      $insert = $pdo->prepare(
        'INSERT INTO product_variants (product_id,nama_varian,stok_total,is_default,is_active)
         VALUES (?,?,0,1,1)'
      );
      $insert->execute([$productId, 'Stok']);
      $targets[] = [
        'id' => (int)$pdo->lastInsertId(),
        'nama_varian' => 'Stok',
        'stok_total' => 0,
        'stok_dipesan' => 0,
        'is_default' => 1,
      ];
    }
  }

  if ($retireIds) {
    $checkReservations = $pdo->prepare(
      "SELECT COUNT(*) FROM booking_items bi
       JOIN bookings b ON b.id=bi.booking_id
       WHERE bi.variant_id=?
         AND b.status IN ('menunggu_verifikasi','disetujui','sedang_disewa','denda')"
    );
    $usageCheck = $pdo->prepare('SELECT COUNT(*) FROM booking_items WHERE variant_id=?');
    $deactivate = $pdo->prepare('UPDATE product_variants SET is_active=0 WHERE id=? AND product_id=?');
    $delete = $pdo->prepare('DELETE FROM product_variants WHERE id=? AND product_id=?');
    foreach (array_unique($retireIds) as $variantId) {
      $checkReservations->execute([$variantId]);
      if ((int)$checkReservations->fetchColumn() > 0) {
        throw new DomainException('Varian yang masih memiliki booking aktif tidak dapat dihapus atau dinonaktifkan.');
      }
      $usageCheck->execute([$variantId]);
      if ((int)$usageCheck->fetchColumn() > 0) {
        $deactivate->execute([$variantId, $productId]);
      } else {
        $delete->execute([$variantId, $productId]);
      }
    }
  }

  $reserved = array_sum(array_map(static function($variant){
    return (int)$variant['stok_dipesan'];
  }, $targets));
  if ($stock < $reserved) {
    throw new DomainException("Stok tidak dapat kurang dari total unit yang sedang disewa/dipesan ({$reserved}).");
  }

  $remaining = $stock - $reserved;
  $allocated = [];
  foreach ($targets as $variant) {
    $minimum = (int)$variant['stok_dipesan'];
    $existingExtra = max(0, (int)$variant['stok_total'] - $minimum);
    $extra = min($remaining, $existingExtra);
    $allocated[$variant['id']] = $minimum + $extra;
    $remaining -= $extra;
  }
  if ($remaining > 0 && $targets) {
    $share = intdiv($remaining, count($targets));
    $remainder = $remaining % count($targets);
    foreach ($targets as $index => $variant) {
      $allocated[$variant['id']] += $share + ($index < $remainder ? 1 : 0);
    }
  }
  $update = $pdo->prepare('UPDATE product_variants SET stok_total=? WHERE id=? AND product_id=?');
  foreach ($allocated as $variantId => $variantStock) {
    $update->execute([$variantStock, $variantId, $productId]);
  }
}

function admin_product_variant_fields(array $names){
  ?>
  <fieldset class="admin-variant-fieldset">
    <legend>Varian / warna <span class="admin-label-note">(opsional)</span></legend>
    <p class="admin-variant-help">Tambahkan pilihan yang dapat dipilih pelanggan. Kosongkan daftar jika produk tidak memiliki varian.</p>
    <div class="admin-variant-list" data-variant-list>
      <?php foreach ($names as $name): ?>
        <div class="admin-variant-row">
          <input name="varian[]" maxlength="50" value="<?= e(is_string($name) ? $name : '') ?>" aria-label="Nama varian" placeholder="Contoh: Hitam, Merah, 60L">
          <button class="btn alt admin-variant-remove" type="button">Hapus</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn alt admin-variant-add" type="button">Tambah Varian</button>
  </fieldset>
  <script>
  document.querySelectorAll('[data-variant-list]').forEach((list) => {
    const addButton = list.parentElement.querySelector('.admin-variant-add');
    addButton.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'admin-variant-row';
      row.innerHTML = '<input name="varian[]" maxlength="50" aria-label="Nama varian" placeholder="Contoh: Hitam, Merah, 60L"><button class="btn alt admin-variant-remove" type="button">Hapus</button>';
      list.append(row);
      row.querySelector('input').focus();
    });
    list.addEventListener('click', (event) => {
      if (event.target.closest('.admin-variant-remove')) {
        event.target.closest('.admin-variant-row').remove();
      }
    });
  });
  </script>
  <?php
}

function admin_product_store_error(PDOException $exception){
  if ($exception->getCode() === '23000') {
    return 'Perubahan tidak dapat disimpan karena produk atau kategorinya masih digunakan oleh transaksi.';
  }
  return 'Terjadi kesalahan database saat menyimpan produk.';
}
