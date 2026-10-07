-- Upgrade an existing rental_pendakian database (run once).
-- Fresh imports already receive these columns from rental_pendakian.sql.
ALTER TABLE payments
  ADD COLUMN status_pembayaran ENUM(
    'menunggu_verifikasi',
    'lunas',
    'ditolak',
    'bayar_di_tempat',
    'belum_dibayar'
  ) NOT NULL DEFAULT 'belum_dibayar' AFTER status,
  ADD COLUMN waktu_upload DATETIME NULL AFTER status_pembayaran,
  ADD COLUMN waktu_verifikasi DATETIME NULL AFTER waktu_upload,
  ADD COLUMN diverifikasi_oleh INT NULL AFTER waktu_verifikasi,
  ADD CONSTRAINT fk_payments_admin_verifier
    FOREIGN KEY (diverifikasi_oleh) REFERENCES admins(id) ON DELETE SET NULL;

UPDATE payments
SET status_pembayaran = CASE
  WHEN status = 'berhasil' THEN 'lunas'
  WHEN status = 'gagal' THEN 'ditolak'
  WHEN bukti_bayar IS NOT NULL AND status = 'pending' THEN 'menunggu_verifikasi'
  ELSE 'belum_dibayar'
END,
waktu_upload = CASE
  WHEN bukti_bayar IS NOT NULL THEN created_at
  ELSE NULL
END;
