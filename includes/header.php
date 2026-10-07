<?php require_once __DIR__ . '/functions.php'; ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rental Alat Pendakian</title><link rel="stylesheet" href="assets/css/style.css?v=user-navbar-profile-20261007-2"></head><body>
<?php
$userLoggedIn = !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'user';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<header class="user-navbar">
  <div class="user-navbar-container">
    <a class="user-navbar-brand" href="index.php" aria-label="Rental Pendakian, Beranda">
      <span class="user-navbar-brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 20 6-10 3 5 3-8 6 13H3Z"/><path d="m9 10 2 2m4-5 2 3"/></svg>
      </span>
      <span>Rental <span class="user-navbar-brand-sub">Pendakian</span></span>
    </a>
    <button class="user-navbar-toggle" type="button" aria-expanded="false" aria-controls="user-navbar-menu" aria-label="Buka menu navigasi">
      <svg class="user-navbar-toggle-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      <svg class="user-navbar-toggle-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <nav class="user-navbar-menu" id="user-navbar-menu" aria-label="Navigasi utama">
      <div class="user-navbar-links">
        <a class="user-navbar-link<?= $currentPage === 'index.php' ? ' is-active' : '' ?>" href="index.php"<?= $currentPage === 'index.php' ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg><span>Beranda</span>
        </a>
        <a class="user-navbar-link<?= $currentPage === 'produk.php' ? ' is-active' : '' ?>" href="index.php#produk"<?= $currentPage === 'produk.php' ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg><span>Peralatan</span>
        </a>
        <a class="user-navbar-link<?= $currentPage === 'riwayat.php' ? ' is-active' : '' ?>" href="riwayat.php"<?= $currentPage === 'riwayat.php' ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v15H4zM8 3v4m8-4v4M4 9h16"/><path d="M8 13h3m2 0h3m-8 4h3"/></svg><span>Sewa Saya</span>
        </a>
      </div>
      <div class="user-navbar-account">
        <?php if ($userLoggedIn): ?>
          <div class="user-navbar-profile">
            <button class="user-navbar-profile-trigger" type="button" aria-expanded="false" aria-haspopup="true" aria-controls="user-profile-menu">
              <span class="user-navbar-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
              <span class="user-navbar-username"><?= e($_SESSION['user_username']) ?></span>
              <svg class="user-navbar-profile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="user-navbar-profile-menu" id="user-profile-menu" hidden>
              <div class="user-navbar-profile-heading">
                <span class="user-navbar-profile-menu-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
                <span class="user-navbar-profile-menu-name"><?= e($_SESSION['user_username']) ?></span>
              </div>
              <a class="user-navbar-profile-logout" href="logout.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>
                <span>Logout</span>
              </a>
            </div>
          </div>
        <?php else: ?>
          <span class="user-navbar-guest">Mode Guest</span>
          <a class="user-navbar-login" href="login.php">Login</a>
          <a class="user-navbar-register" href="register.php">Daftar</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<script>
(() => {
  const header = document.querySelector('.user-navbar');
  const toggle = header.querySelector('.user-navbar-toggle');
  const menu = header.querySelector('.user-navbar-menu');
  const profile = header.querySelector('.user-navbar-profile');
  const profileTrigger = header.querySelector('.user-navbar-profile-trigger');
  const profileMenu = header.querySelector('.user-navbar-profile-menu');
  let profileCloseTimer;
  const closeMenu = () => {
    header.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Buka menu navigasi');
  };
  const closeProfileMenu = () => {
    if (!profileMenu) return;
    window.clearTimeout(profileCloseTimer);
    if (profileMenu.hidden) return;
    profileMenu.classList.remove('is-visible');
    profile.classList.remove('is-open');
    profileTrigger.setAttribute('aria-expanded', 'false');
    profileCloseTimer = window.setTimeout(() => {
      profileMenu.hidden = true;
    }, 180);
  };

  toggle.addEventListener('click', () => {
    const isOpen = header.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
  });
  menu.addEventListener('click', (event) => {
    if (event.target.closest('a')) closeMenu();
  });
  if (profile) {
    profileTrigger.addEventListener('click', () => {
      if (!profileMenu.hidden) {
        closeProfileMenu();
        return;
      }
      window.clearTimeout(profileCloseTimer);
      profileMenu.hidden = false;
      profile.classList.add('is-open');
      profileTrigger.setAttribute('aria-expanded', 'true');
      window.requestAnimationFrame(() => profileMenu.classList.add('is-visible'));
    });
    document.addEventListener('click', (event) => {
      if (!profile.contains(event.target)) closeProfileMenu();
    });
  }
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeProfileMenu();
      closeMenu();
    }
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) closeMenu();
    closeProfileMenu();
  });
})();
</script>
<main>
