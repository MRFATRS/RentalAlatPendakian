CREATE TABLE IF NOT EXISTS produk_foto (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  nama_foto VARCHAR(255) NOT NULL,
  warna VARCHAR(50) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  urutan INT NOT NULL DEFAULT 0,
  KEY idx_produk_foto_product_order (product_id, is_primary, urutan),
  CONSTRAINT fk_produk_foto_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO produk_foto (product_id, nama_foto, warna, is_primary, urutan)
SELECT p.id, p.gambar, NULL, 1, 0
FROM products p
WHERE p.gambar IS NOT NULL
  AND p.gambar <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM produk_foto pf
    WHERE pf.product_id = p.id AND pf.nama_foto = p.gambar
  );
