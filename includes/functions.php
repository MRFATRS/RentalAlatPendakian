<?php
session_start();
require_once __DIR__ . '/../config/database.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n){ return 'Rp ' . number_format($n, 0, ',', '.'); }
function login_required(){
  if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    $_SESSION['user_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php?pesan=wajib'); exit;
  }
}
function admin_required(){
  if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: login.php?pesan=wajib'); exit;
  }
}
function stok_tersedia($pdo, $vid, $start, $end){
  $q = $pdo->prepare("SELECT v.stok_total - COALESCE((SELECT SUM(bi.qty) FROM booking_items bi
        JOIN bookings b ON b.id = bi.booking_id
        WHERE bi.variant_id = v.id AND b.status IN ('menunggu_verifikasi','disetujui','sedang_disewa','denda')
        AND COALESCE((SELECT pay.status_pembayaran FROM payments pay
          WHERE pay.booking_id=b.id ORDER BY pay.id DESC LIMIT 1), '') <> 'ditolak'
        AND b.tgl_mulai <= ? AND b.tgl_selesai >= ?), 0) FROM product_variants v WHERE v.id = ?");
  $q->execute([$end, $start, $vid]);
  return (int)$q->fetchColumn();
}
const STATUS_LABEL = [
  'menunggu_verifikasi' => 'Menunggu Verifikasi Admin', 'disetujui' => 'Sewa Disetujui / Siap Diambil',
  'sedang_disewa' => 'Sedang Disewa (Aktif)', 'selesai' => 'Selesai', 'denda' => 'Denda / Terlambat', 'dibatalkan' => 'Dibatalkan',
];
