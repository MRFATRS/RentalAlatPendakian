<?php
function product_gallery_prepare_images(array $rows, $legacyImage, $fallbackImage){
  $images = [];
  foreach ($rows as $row) {
    $filename = basename((string)($row['nama_foto'] ?? ''));
    if ($filename === '' || !is_file(__DIR__ . '/../uploads/produk/' . $filename)) { continue; }
    $images[] = [
      'src' => 'uploads/produk/' . $filename,
      'warna' => (string)($row['warna'] ?? ''),
      'is_primary' => (int)($row['is_primary'] ?? 0),
    ];
  }
  if (!$images && $legacyImage) {
    $filename = basename((string)$legacyImage);
    if ($filename !== '' && is_file(__DIR__ . '/../uploads/produk/' . $filename)) {
      $images[] = ['src' => 'uploads/produk/' . $filename, 'warna' => '', 'is_primary' => 1];
    }
  }
  if (!$images) {
    $images[] = ['src' => $fallbackImage, 'warna' => '', 'is_primary' => 1];
  }
  return $images;
}

function product_gallery_images_for_products(PDO $pdo, array $products){
  if (!$products) { return []; }
  $ids = array_map(static function($product){ return (int)$product['id']; }, $products);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $query = $pdo->prepare(
    "SELECT product_id,nama_foto,warna,is_primary,urutan
     FROM produk_foto WHERE product_id IN ($placeholders)
     ORDER BY product_id,is_primary DESC,urutan ASC,id ASC"
  );
  $query->execute($ids);
  $rowsByProduct = [];
  foreach ($query->fetchAll() as $row) {
    $rowsByProduct[(int)$row['product_id']][] = $row;
  }

  $imagesByProduct = [];
  foreach ($products as $product) {
    $imagesByProduct[(int)$product['id']] = product_gallery_prepare_images(
      $rowsByProduct[(int)$product['id']] ?? [],
      $product['gambar'] ?? '',
      $product['fallback_image']
    );
  }
  return $imagesByProduct;
}

function product_gallery_images_for_product(PDO $pdo, array $product, $fallbackImage){
  $query = $pdo->prepare(
    'SELECT nama_foto,warna,is_primary,urutan FROM produk_foto
     WHERE product_id=? ORDER BY is_primary DESC,urutan ASC,id ASC'
  );
  $query->execute([$product['id']]);
  return product_gallery_prepare_images($query->fetchAll(), $product['gambar'] ?? '', $fallbackImage);
}

function product_gallery_render(array $images, $productName, $detail = false){
  if (!$images) { return; }
  $count = count($images);
  $firstColor = trim((string)($images[0]['warna'] ?? ''));
  $imageAlt = $productName . ($firstColor !== '' ? ' - ' . $firstColor : '');
  ?>
  <div class="product-gallery<?= $detail ? ' product-gallery--detail' : ' product-gallery--card' ?>" data-product-gallery>
    <img class="product-gallery-image" src="<?= e($images[0]['src']) ?>" alt="<?= e($imageAlt) ?>" loading="lazy" data-gallery-image>
    <?php if ($count > 1): ?>
      <button class="product-gallery-arrow product-gallery-prev" type="button" aria-label="Foto sebelumnya" data-gallery-prev><span aria-hidden="true">‹</span></button>
      <button class="product-gallery-arrow product-gallery-next" type="button" aria-label="Foto berikutnya" data-gallery-next><span aria-hidden="true">›</span></button>
      <div class="product-gallery-dots" role="group" aria-label="Pilih foto produk" data-gallery-dots>
        <?php foreach ($images as $index => $image): ?>
          <button type="button" class="product-gallery-dot<?= $index === 0 ? ' is-active' : '' ?>" aria-label="Lihat foto <?= $index + 1 ?> dari <?= $count ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" data-gallery-dot="<?= $index ?>"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <span class="product-gallery-color" data-gallery-color<?= $firstColor === '' ? ' hidden' : '' ?>><?= e($firstColor) ?></span>
    <?php if ($detail): ?>
      <div class="product-gallery-thumbnails" role="group" aria-label="Pilih foto berdasarkan warna" data-gallery-thumbnails>
        <?php foreach ($images as $index => $image):
          $color = trim((string)($image['warna'] ?? ''));
          $alt = $productName . ($color !== '' ? ' - ' . $color : ' - Foto ' . ($index + 1));
        ?>
          <button class="product-gallery-thumbnail<?= $index === 0 ? ' is-active' : '' ?>" type="button" aria-label="<?= e($color !== '' ? 'Pilih warna ' . $color : 'Pilih foto ' . ($index + 1)) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" data-gallery-thumbnail="<?= $index ?>">
            <img src="<?= e($image['src']) ?>" alt="<?= e($alt) ?>" loading="lazy">
            <?php if ($color !== ''): ?><span><?= e($color) ?></span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <script type="application/json" data-gallery-data><?= json_encode(
      array_map(static function($image) use ($productName){
        $color = trim((string)($image['warna'] ?? ''));
        return [
          'src' => $image['src'],
          'color' => $color,
          'alt' => $productName . ($color !== '' ? ' - ' . $color : ''),
        ];
      }, $images),
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>
  </div>
  <?php
}
