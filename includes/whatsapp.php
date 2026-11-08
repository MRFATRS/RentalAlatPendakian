<?php
$whatsappMessage = rawurlencode(
  'Halo Fatur, saya tertarik untuk menyewa alat pendakian di Rental Alat Pendakian. Boleh minta informasi mengenai ketersediaan alat, harga sewa, dan proses booking?'
);
?>
<div class="whatsapp-widget" data-whatsapp-widget>
  <div class="whatsapp-popup" id="whatsapp-admin-popup" role="dialog" aria-label="Kontak admin WhatsApp" aria-hidden="true" hidden>
    <div class="whatsapp-popup-heading">
      <span class="whatsapp-admin-avatar" aria-hidden="true">
        <svg viewBox="0 0 64 64" role="img">
          <circle cx="32" cy="32" r="32" fill="#e8f0e8"/>
          <path d="M12 62c1.8-12.1 9.1-18.2 20-18.2S50.2 49.9 52 62" fill="#365b46"/>
          <path d="M22 44.6 32 53l10-8.4-2.2-7.2H24.2z" fill="#d8a47f"/>
          <path d="M20.4 25.2c0-9 4.4-15.5 12-15.5 8.3 0 12 6.1 12 15.5v5.5c0 8.3-5.3 14.5-12.4 14.5S20.4 39 20.4 30.7z" fill="#e2b18e"/>
          <path d="M19.6 27.3c-2.1-12 3.7-19.8 13.2-19.8 9.4 0 14.5 7.3 12.1 19.7l-3.1-2.7-1.6-7.2c-4.1 3.3-10.4 5-18.1 4.6z" fill="#263b32"/>
          <path d="M25.5 30.6h.1m12.8 0h.1" stroke="#39443c" stroke-width="2.5" stroke-linecap="round"/>
          <path d="M28 37c2.4 1.9 5.6 1.9 8 0" fill="none" stroke="#9e624d" stroke-width="1.8" stroke-linecap="round"/>
          <path d="m25 46 7 6 7-6 7.7 5.6L51 62H13l4.3-10.4z" fill="#fff"/>
        </svg>
      </span>
      <span class="whatsapp-admin-copy">
        <span class="whatsapp-admin-name">Fatur</span>
        <span class="whatsapp-admin-role">Admin Rental Alat Pendakian</span>
      </span>
      <button class="whatsapp-close" type="button" aria-label="Tutup kontak WhatsApp">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <p class="whatsapp-intro">Ada yang ingin ditanyakan? Hubungi admin kami melalui WhatsApp.</p>
    <a class="whatsapp-contact" href="https://wa.me/6289504412715?text=<?= e($whatsappMessage) ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat Fatur di WhatsApp">
      <span class="whatsapp-contact-icon" aria-hidden="true">
        <svg viewBox="0 0 32 32" fill="currentColor">
          <path d="M16.04 3C8.87 3 3.04 8.78 3.04 15.9c0 2.26.6 4.46 1.75 6.4L3 29l6.88-1.79a13.1 13.1 0 0 0 6.16 1.53h.01c7.16 0 13-5.79 13-12.9 0-3.45-1.36-6.7-3.82-9.14A12.95 12.95 0 0 0 16.04 3Zm0 23.55h-.01c-1.88 0-3.73-.5-5.34-1.45l-.38-.23-4.08 1.06 1.09-3.96-.25-.4a10.7 10.7 0 0 1-1.65-5.67c0-5.88 4.8-10.66 10.7-10.66 2.85 0 5.53 1.1 7.55 3.12a10.57 10.57 0 0 1 3.13 7.54c0 5.88-4.8 10.65-10.76 10.65Zm5.88-7.98c-.32-.16-1.9-.94-2.2-1.05-.3-.1-.52-.16-.74.16-.22.33-.85 1.05-1.04 1.27-.2.22-.38.24-.7.08-.32-.16-1.36-.5-2.59-1.59-.96-.85-1.61-1.9-1.8-2.23-.2-.32-.02-.5.14-.65.14-.14.32-.38.48-.57.16-.19.22-.32.32-.54.1-.22.05-.4-.03-.57-.08-.16-.73-1.75-1-2.39-.27-.63-.53-.54-.73-.55h-.63c-.22 0-.57.08-.87.4-.3.33-1.14 1.11-1.14 2.7s1.17 3.13 1.33 3.35c.17.21 2.3 3.5 5.58 4.91.78.34 1.39.55 1.86.7.78.25 1.5.21 2.06.13.63-.1 1.9-.78 2.16-1.54.27-.76.27-1.4.19-1.54-.08-.14-.3-.22-.62-.38Z"/>
        </svg>
      </span>
      <span class="whatsapp-contact-copy">
        <strong>Chat dengan Fatur</strong>
        <small>Balasan langsung melalui WhatsApp</small>
      </span>
      <svg class="whatsapp-contact-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
    </a>
    <p class="whatsapp-phone">WhatsApp: 0895 0441 2715</p>
  </div>
  <button class="whatsapp-fab" type="button" aria-label="Buka kontak WhatsApp admin" aria-expanded="false" aria-controls="whatsapp-admin-popup">
    <svg viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
      <path d="M16.04 3C8.87 3 3.04 8.78 3.04 15.9c0 2.26.6 4.46 1.75 6.4L3 29l6.88-1.79a13.1 13.1 0 0 0 6.16 1.53h.01c7.16 0 13-5.79 13-12.9 0-3.45-1.36-6.7-3.82-9.14A12.95 12.95 0 0 0 16.04 3Zm0 23.55h-.01c-1.88 0-3.73-.5-5.34-1.45l-.38-.23-4.08 1.06 1.09-3.96-.25-.4a10.7 10.7 0 0 1-1.65-5.67c0-5.88 4.8-10.66 10.7-10.66 2.85 0 5.53 1.1 7.55 3.12a10.57 10.57 0 0 1 3.13 7.54c0 5.88-4.8 10.65-10.76 10.65Zm5.88-7.98c-.32-.16-1.9-.94-2.2-1.05-.3-.1-.52-.16-.74.16-.22.33-.85 1.05-1.04 1.27-.2.22-.38.24-.7.08-.32-.16-1.36-.5-2.59-1.59-.96-.85-1.61-1.9-1.8-2.23-.2-.32-.02-.5.14-.65.14-.14.32-.38.48-.57.16-.19.22-.32.32-.54.1-.22.05-.4-.03-.57-.08-.16-.73-1.75-1-2.39-.27-.63-.53-.54-.73-.55h-.63c-.22 0-.57.08-.87.4-.3.33-1.14 1.11-1.14 2.7s1.17 3.13 1.33 3.35c.17.21 2.3 3.5 5.58 4.91.78.34 1.39.55 1.86.7.78.25 1.5.21 2.06.13.63-.1 1.9-.78 2.16-1.54.27-.76.27-1.4.19-1.54-.08-.14-.3-.22-.62-.38Z"/>
    </svg>
  </button>
</div>
<script>
(() => {
  const widget = document.querySelector('[data-whatsapp-widget]');
  if (!widget) return;

  const toggle = widget.querySelector('.whatsapp-fab');
  const popup = widget.querySelector('.whatsapp-popup');
  const closeButton = widget.querySelector('.whatsapp-close');
  let closeTimer;

  const openPopup = () => {
    window.clearTimeout(closeTimer);
    popup.hidden = false;
    popup.setAttribute('aria-hidden', 'false');
    toggle.setAttribute('aria-expanded', 'true');
    window.requestAnimationFrame(() => popup.classList.add('is-open'));
  };
  const closePopup = (restoreFocus = false) => {
    window.clearTimeout(closeTimer);
    if (popup.hidden) return;
    popup.classList.remove('is-open');
    popup.setAttribute('aria-hidden', 'true');
    toggle.setAttribute('aria-expanded', 'false');
    closeTimer = window.setTimeout(() => {
      if (!popup.classList.contains('is-open')) popup.hidden = true;
    }, 210);
    if (restoreFocus) toggle.focus();
  };

  toggle.addEventListener('click', () => {
    if (popup.hidden) openPopup();
    else closePopup();
  });
  closeButton.addEventListener('click', () => closePopup(true));
  document.addEventListener('click', (event) => {
    if (!widget.contains(event.target)) closePopup();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closePopup(true);
  });
})();
</script>
