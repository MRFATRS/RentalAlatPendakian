# ⛺ Rental Alat Pendakian (Web-Based Application)

Website sistem informasi penyewaan alat pendakian gunung berbasis PHP Native dan MySQL (PDO). Dibuat untuk memenuhi tugas kuliah.

---

## 🚀 Fitur Utama
- **Sisi Pengguna (User):**
  - Melihat katalog alat pendakian (tenda, carrier, sleeping bag, dll).
  - Melakukan booking/pemesanan alat.
  - Mode Guest dan fitur akun (Login & Daftar).
- **Sisi Admin:**
  - Login Admin yang aman.
  - **Kelola Booking:** Melihat daftar pesanan masuk, detail penyewa, tanggal sewa, total harga, KTP/SIM, dan mengubah status sewa (Disetujui/Siap Diambil, dll).
  - **CRUD Data Produk:** Menambah, melihat, mengubah, dan menghapus data produk alat pendakian.

---

## 🛠️ Teknologi yang Digunakan
- **Bahasa Pemrograman:** PHP (PDO)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3
- **Server Lokal:** XAMPP (Apache)

---

## ⚙️ Cara Instalasi & Menjalankan di Lokal (XAMPP)

1. **Clone atau Download Repository ini:**
   Letakkan folder project ke dalam direktori server lokal XAMPP Anda:
   `C:\xampp\htdocs\Rentalalatpendakian\`

2. **Nyalakan XAMPP:**
   Buka aplikasi XAMPP Control Panel, lalu klik **Start** pada modul **Apache** dan **MySQL**.

3. **Import Database:**
   - Buka browser dan akses `http://localhost/phpmyadmin/`
   - Buat database baru dengan nama yang sesuai (misalnya: `rental_alat_pendakian`).
   - Import file `.sql` database project Anda yang ada di dalam folder (jika ada).

4. **Konfigurasi Koneksi Database:**
   Sesuaikan pengaturan database di file koneksi Anda (biasanya di folder `config/database.php`) jika nama database lokal Anda berbeda.

5. **Database yang sudah terpasang:**
   Untuk database lama yang sudah memiliki tabel `product_variants`, jalankan satu kali
   `database/migrations/20261009_product_variant_management.sql` melalui phpMyAdmin.
   Instalasi baru yang mengimpor `rental_pendakian.sql` tidak memerlukan migrasi ini.
   Varian yang tidak lagi dipakai akan dinonaktifkan jika masih dirujuk riwayat booking.

6. **Akses Website:**
   - **Halaman Utama (User):** `http://localhost/Rentalalatpendakian/`
   - **Halaman Login Admin:** `http://localhost/Rentalalatpendakian/admin/login.php`

---

## 👥 Kontributor / Kolaborator
- Dibuat oleh: [Kelompok 5]
- Dikembangkan bersama: 
1. MUHAMMAD FATURRAHMAN SYAKIB
2. FARRIS KAMAL DWI HERMIN
3. MUHAMMAD TSANI AQIL AUGUST PUT
4. MUHAMMAD HAIKAL OMAR MULYANA
5. MUHAMMAD HAFIZH ALFARIDZI
6. ⁠RADITYA REIVA
