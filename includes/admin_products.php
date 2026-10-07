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
    ],
  ];
}

function admin_product_upload(array $file){
  if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    return ['filename' => null];
  }
  if ($file['error'] !== UPLOAD_ERR_OK) {
    return ['error' => 'Foto gagal diunggah. Silakan coba kembali.'];
  }
  if (!is_uploaded_file($file['tmp_name']) || $file['size'] > 3 * 1024 * 1024) {
    return ['error' => 'Ukuran foto maksimal 3 MB.'];
  }

  $imageInfo = @getimagesize($file['tmp_name']);
  if ($imageInfo === false) {
    return ['error' => 'File yang diunggah bukan gambar yang valid.'];
  }
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($extensions[$mime]) || $imageInfo['mime'] !== $mime) {
    return ['error' => 'Format foto harus JPG, PNG, atau WEBP.'];
  }

  $directory = dirname(__DIR__) . '/uploads/produk';
  if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    return ['error' => 'Folder upload foto tidak dapat dibuat.'];
  }
  if (!is_writable($directory)) {
    return ['error' => 'Folder uploads/produk tidak memiliki izin tulis.'];
  }

  $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
  if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
    return ['error' => 'Foto tidak dapat disimpan ke folder produk.'];
  }

  return ['filename' => $filename];
}

function admin_product_remove_image($filename){
  $filename = basename((string)$filename);
  if ($filename === '') { return true; }
  $path = dirname(__DIR__) . '/uploads/produk/' . $filename;
  return !is_file($path) || unlink($path);
}

function admin_product_stock_variants(PDO $pdo, $productId){
  $st = $pdo->prepare(
    "SELECT v.id, v.nama_varian, v.stok_total,
      COALESCE(SUM(CASE WHEN b.status IN ('menunggu_verifikasi','disetujui','sedang_disewa','denda')
        THEN bi.qty ELSE 0 END), 0) AS stok_dipesan
     FROM product_variants v
     LEFT JOIN booking_items bi ON bi.variant_id=v.id
     LEFT JOIN bookings b ON b.id=bi.booking_id
     WHERE v.product_id=?
     GROUP BY v.id, v.nama_varian, v.stok_total
     ORDER BY v.id"
  );
  $st->execute([$productId]);
  return $st->fetchAll();
}

function admin_product_set_stock(PDO $pdo, $productId, $stock){
  $variants = admin_product_stock_variants($pdo, $productId);
  if (!$variants) {
    $insert = $pdo->prepare('INSERT INTO product_variants (product_id,nama_varian,stok_total) VALUES (?,?,?)');
    $insert->execute([$productId, 'Stok', $stock]);
    return;
  }

  $reserved = array_sum(array_column($variants, 'stok_dipesan'));
  if ($stock < $reserved) {
    throw new DomainException("Stok tidak dapat kurang dari total unit yang sedang disewa/dipesan ({$reserved}).");
  }

  $remaining = $stock - $reserved;
  $allocated = [];
  foreach ($variants as $variant) {
    $minimum = (int)$variant['stok_dipesan'];
    $existingExtra = max(0, (int)$variant['stok_total'] - $minimum);
    $extra = min($remaining, $existingExtra);
    $allocated[$variant['id']] = $minimum + $extra;
    $remaining -= $extra;
  }
  if ($remaining > 0) {
    $firstId = $variants[0]['id'];
    $allocated[$firstId] += $remaining;
  }

  $update = $pdo->prepare('UPDATE product_variants SET stok_total=? WHERE id=? AND product_id=?');
  foreach ($allocated as $variantId => $variantStock) {
    $update->execute([$variantStock, $variantId, $productId]);
  }
}

function admin_product_store_error(PDOException $exception){
  if ($exception->getCode() === '23000') {
    return 'Perubahan tidak dapat disimpan karena produk atau kategorinya masih digunakan oleh transaksi.';
  }
  return 'Terjadi kesalahan database saat menyimpan produk.';
}
