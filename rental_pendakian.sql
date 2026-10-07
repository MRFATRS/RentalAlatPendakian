-- =====================================================
-- DATABASE: rental_pendakian
-- Import lewat phpMyAdmin (XAMPP) -> tab Import
-- =====================================================
CREATE DATABASE IF NOT EXISTS rental_pendakian
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE rental_pendakian;

-- 1. USERS (guest tidak disimpan; hanya yang register)
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nama          VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,            -- simpan hasil password_hash()
  no_whatsapp   VARCHAR(20)  NOT NULL,
  foto_identitas VARCHAR(255) NOT NULL,           -- path upload KTP / SIM
  jenis_identitas ENUM('KTP','SIM') NOT NULL DEFAULT 'KTP',
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  status_akun   ENUM('menunggu','terverifikasi','ditolak') NOT NULL DEFAULT 'menunggu',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. ADMINS (akun admin terpisah dari akun user)
CREATE TABLE admins (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL, -- simpan hasil password_hash() PHP
  nama       VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Akun admin awal untuk pengujian.
-- Username: admin
-- Password sementara: UbahSebelumProduksi!2026
-- Ganti password ini setelah login pertama sebelum aplikasi digunakan publik.
INSERT INTO admins (username, password, nama) VALUES
  ('admin', '$2y$10$0fLxJD7cFnp4.d47JWKXkOiqnXB7H/L0ATsdRja0c8RHzuzkt0ery', 'Administrator');

-- 3. KATEGORI (Tenda, Carrier, Sleeping Bag, dll)
CREATE TABLE categories (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nama  VARCHAR(50) NOT NULL,
  slug  VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4. PRODUK / ALAT
CREATE TABLE products (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  category_id     INT NOT NULL,
  nama            VARCHAR(150) NOT NULL,
  deskripsi       TEXT,
  harga_per_hari  INT NOT NULL,
  deposit         INT NOT NULL DEFAULT 0,         -- legacy field; not used in rental pricing
  gambar          VARCHAR(255),
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- 5. VARIAN / WARNA (stok disimpan di sini)
CREATE TABLE product_variants (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  product_id  INT NOT NULL,
  nama_varian VARCHAR(50) NOT NULL,               -- contoh: "Hijau", "Merah", "60L"
  stok_total  INT NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. BOOKING (header transaksi)
CREATE TABLE bookings (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  kode_booking       VARCHAR(20) NOT NULL UNIQUE,  -- contoh: RNT-20261007-001
  user_id            INT NOT NULL,
  tgl_mulai          DATE NOT NULL,
  tgl_selesai        DATE NOT NULL,
  durasi_hari        INT NOT NULL,
  subtotal           INT NOT NULL,                 -- biaya sewa: harga_per_hari x qty x durasi
  total_deposit      INT NOT NULL DEFAULT 0,        -- legacy field; new bookings always store 0
  total_bayar        INT NOT NULL,                  -- biaya sewa only
  metode_pengambilan ENUM('ambil_toko','kurir') NOT NULL DEFAULT 'ambil_toko',
  alamat_kirim       TEXT NULL,
  skema_bayar        ENUM('lunas') NOT NULL DEFAULT 'lunas',
  status             ENUM('menunggu_verifikasi','disetujui','sedang_disewa','selesai','denda','dibatalkan')
                     NOT NULL DEFAULT 'menunggu_verifikasi',
  tgl_kembali_aktual DATE NULL,
  catatan_admin      TEXT NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 7. DETAIL ITEM BOOKING
CREATE TABLE booking_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  booking_id      INT NOT NULL,
  variant_id      INT NOT NULL,
  qty             INT NOT NULL,
  harga_per_hari  INT NOT NULL,                    -- snapshot harga saat booking
  deposit         INT NOT NULL DEFAULT 0,           -- legacy field; new booking items always store 0
  subtotal        INT NOT NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id)
) ENGINE=InnoDB;

-- 8. PEMBAYARAN
CREATE TABLE payments (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  booking_id     INT NOT NULL,
  jenis          ENUM('lunas','denda') NOT NULL,
  jumlah         INT NOT NULL,
  metode         VARCHAR(50),                      -- QRIS / Transfer Bank / Bayar di Tempat
  order_id       VARCHAR(100) NULL,
  bukti_bayar    VARCHAR(255) NULL,
  status         ENUM('pending','berhasil','gagal','kadaluarsa') NOT NULL DEFAULT 'pending',
  status_pembayaran ENUM('menunggu_verifikasi','lunas','ditolak','bayar_di_tempat','belum_dibayar')
                     NOT NULL DEFAULT 'belum_dibayar',
  waktu_upload   DATETIME NULL,
  waktu_verifikasi DATETIME NULL,
  diverifikasi_oleh INT NULL,
  paid_at        DATETIME NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (diverifikasi_oleh) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. DENDA (terlambat / barang rusak)
CREATE TABLE fines (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL,
  jenis       ENUM('terlambat','rusak','hilang') NOT NULL,
  jumlah      INT NOT NULL,
  keterangan  TEXT,
  status      ENUM('belum_dibayar','lunas') NOT NULL DEFAULT 'belum_dibayar',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- DATA AWAL
-- =====================================================
INSERT INTO categories (nama, slug) VALUES
 ('Tenda','tenda'), ('Carrier','carrier'), ('Sleeping Bag','sleeping-bag'),
 ('Alat Masak','alat-masak'), ('Penerangan','penerangan');

INSERT INTO products (category_id, nama, deskripsi, harga_per_hari) VALUES
 (1, 'Tenda Dome Kapasitas 4', 'Tenda dome double layer untuk 4 orang.', 40000),
 (2, 'Carrier 60L',            'Carrier 60 liter dengan rain cover.',     25000);

INSERT INTO product_variants (product_id, nama_varian, stok_total) VALUES
 (1, 'Hijau', 5), (1, 'Oranye', 3),
 (2, 'Hitam', 6), (2, 'Merah', 4);

-- Akun admin: buat lewat script PHP sekali jalan supaya password di-hash dengan benar:
--   echo password_hash('admin123', PASSWORD_DEFAULT);
-- lalu INSERT INTO users (..., role, status_akun) VALUES (..., 'admin', 'terverifikasi');

-- =====================================================
-- QUERY CEK KETERSEDIAAN (dipakai di fitur "Cek Ketersediaan Stok")
-- Status yang MENGUNCI stok: menunggu_verifikasi, disetujui, sedang_disewa, denda
-- Dua rentang tanggal bentrok jika: tgl_mulai_lama <= end_baru AND tgl_selesai_lama >= start_baru
-- =====================================================
-- SELECT v.stok_total - COALESCE(SUM(bi.qty), 0) AS stok_tersedia
-- FROM product_variants v
-- LEFT JOIN booking_items bi ON bi.variant_id = v.id
-- LEFT JOIN bookings b ON b.id = bi.booking_id
--      AND b.status IN ('menunggu_verifikasi','disetujui','sedang_disewa','denda')
--      AND b.tgl_mulai <= :end_date AND b.tgl_selesai >= :start_date
-- WHERE v.id = :variant_id
-- GROUP BY v.id;
