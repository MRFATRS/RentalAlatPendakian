<?php
function admin_layout_icon($name){
  $paths = [
    'dashboard' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    'products' => '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>',
    'bookings' => '<path d="M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z"/><path d="m8 14 2 2 5-5"/>',
    'payments' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-13 5h4"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.5.9l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1-.1 1.7Z"/>',
    'logout' => '<path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
    'mountain' => '<path d="m3 20 6-10 3 5 3-8 6 13H3Z"/><path d="m9 10 2 2m4-5 2 3"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
  ];
  return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function admin_layout_start($title, $active = 'dashboard'){
  $menuItems = [
    ['key' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'href' => 'index.php'],
    ['key' => 'products', 'icon' => 'products', 'label' => 'Kelola Produk', 'href' => 'index.php#products'],
    ['key' => 'bookings', 'icon' => 'bookings', 'label' => 'Kelola Booking', 'href' => 'index.php#bookings'],
    ['key' => 'payments', 'icon' => 'payments', 'label' => 'Pembayaran', 'href' => 'pembayaran.php'],
  ];
  ?>
  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Admin Rental Pendakian</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=admin-20261007-1">
  </head>
  <body class="admin-app">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-sidebar-brand" href="index.php">
        <span class="admin-sidebar-mark"><?= admin_layout_icon('mountain') ?></span>
        <span>RENTAL ALAT<small>PENDAKIAN</small></span>
      </a>
      <button class="admin-menu-toggle" type="button" aria-expanded="false" aria-controls="admin-sidebar-menu">
        <?= admin_layout_icon('menu') ?><span>Menu</span>
      </button>
      <div class="admin-sidebar-label">MENU UTAMA</div>
      <nav class="admin-sidebar-nav" id="admin-sidebar-menu" aria-label="Navigasi Admin">
        <?php foreach ($menuItems as $item): ?>
          <a class="admin-sidebar-link<?= $active === $item['key'] ? ' is-active' : '' ?>"
             href="<?= e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
            <span class="admin-sidebar-icon"><?= admin_layout_icon($item['icon']) ?></span>
            <span><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-sidebar-bottom">
        <div class="admin-sidebar-label">AKUN</div>
        <a class="admin-sidebar-link<?= $active === 'settings' ? ' is-active' : '' ?>"
           href="ubah_password.php"<?= $active === 'settings' ? ' aria-current="page"' : '' ?>>
          <span class="admin-sidebar-icon"><?= admin_layout_icon('settings') ?></span>
          <span>Ubah Password</span>
        </a>
        <a class="admin-sidebar-link admin-sidebar-logout" href="logout.php">
          <span class="admin-sidebar-icon"><?= admin_layout_icon('logout') ?></span>
          <span>Logout Admin</span>
        </a>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <div><span class="admin-topbar-eyebrow">RENTAL ALAT PENDAKIAN</span><h1><?= e($title) ?></h1></div>
        <div class="admin-user-badge"><span class="admin-user-avatar"><?= admin_layout_icon('user') ?></span><span><?= e($_SESSION['admin_username']) ?><small>Administrator</small></span></div>
      </header>
      <main class="admin-content">
        <div class="admin-dashboard">
  <?php
}

function admin_layout_end(){
  ?>
        </div>
      </main>
    </div>
  </div>
  <script>
  document.querySelectorAll('.admin-menu-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const sidebar = toggle.closest('.admin-sidebar');
      const isOpen = sidebar.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
  });
  </script>
  </body>
  </html>
  <?php
}
