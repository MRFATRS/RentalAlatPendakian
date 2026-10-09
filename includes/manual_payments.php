<?php
require_once __DIR__ . '/functions.php';

function payment_csrf_token(){
  if (empty($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['payment_csrf'];
}

function payment_verify_csrf($token){
  return isset($_SESSION['payment_csrf'])
    && is_string($token)
    && hash_equals($_SESSION['payment_csrf'], $token);
}

function payment_qris_image_path(){
  return dirname(__DIR__) . '/assets/images/QRIS/qris-rental.png';
}

function payment_qris_image_available(){
  $path = payment_qris_image_path();
  if (!is_file($path) || !is_readable($path)) {
    return false;
  }
  $image = @getimagesize($path);
  return $image !== false && $image['mime'] === 'image/png';
}

function payment_qris_image_url(){
  return 'assets/images/QRIS/qris-rental.png';
}

function payment_upload_proof(array $file){
  if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    return ['error' => 'Unggah bukti pembayaran terlebih dahulu.'];
  }
  if (!is_uploaded_file($file['tmp_name']) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) {
    return ['error' => 'Ukuran bukti pembayaran maksimal 2 MB.'];
  }

  $imageInfo = @getimagesize($file['tmp_name']);
  if ($imageInfo === false) {
    return ['error' => 'File bukti pembayaran bukan gambar yang valid.'];
  }
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($extensions[$mime]) || $imageInfo['mime'] !== $mime) {
    return ['error' => 'Format bukti pembayaran harus JPG, JPEG, PNG, atau WEBP.'];
  }

  $directory = dirname(__DIR__) . '/uploads/bukti_pembayaran';
  if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    return ['error' => 'Folder penyimpanan bukti pembayaran tidak dapat dibuat.'];
  }
  if (!is_writable($directory)) {
    return ['error' => 'Folder uploads/bukti_pembayaran tidak memiliki izin tulis.'];
  }

  $filename = 'bukti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
  if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
    return ['error' => 'Bukti pembayaran gagal disimpan.'];
  }
  return ['filename' => $filename];
}

function payment_remove_proof($filename){
  $filename = basename((string)$filename);
  if ($filename === '') { return true; }
  $path = dirname(__DIR__) . '/uploads/bukti_pembayaran/' . $filename;
  return !is_file($path) || unlink($path);
}

function payment_method_key($method){
  $method = strtolower(trim((string)$method));
  return [
    'transfer bank' => 'transfer_bank',
    'bayar di tempat' => 'bayar_di_tempat',
  ][$method] ?? $method;
}

function payment_method_label($method){
  $method = payment_method_key($method);
  return [
    'qris' => 'QRIS',
    'transfer_bank' => 'Transfer Bank',
    'bayar_di_tempat' => 'Bayar di Tempat',
  ][$method] ?? 'Belum dipilih';
}

function payment_status_label($status){
  return [
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'lunas' => 'Lunas',
    'ditolak' => 'Ditolak',
    'bayar_di_tempat' => 'Bayar di Tempat',
    'belum_dibayar' => 'Belum Dibayar',
  ][$status] ?? 'Belum Dibayar';
}

function payment_method_available($method, array $bankConfig){
  if ($method === 'qris') {
    return payment_qris_image_available();
  }
  return in_array($method, ['qris', 'transfer_bank', 'bayar_di_tempat'], true);
}
