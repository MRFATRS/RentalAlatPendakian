-- Run once on databases created before product variant management was added.
-- Existing "Stok" rows were generated for products without selectable variants.
ALTER TABLE product_variants
  ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER stok_total,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_default;

UPDATE product_variants
SET is_default = 1
WHERE nama_varian = 'Stok';

INSERT INTO product_variants (product_id,nama_varian,stok_total,is_default,is_active)
SELECT p.id,'Stok',0,1,1
FROM products p
LEFT JOIN product_variants v ON v.product_id=p.id
WHERE v.id IS NULL;

CREATE INDEX idx_product_variants_product_active
  ON product_variants (product_id, is_active, is_default);
