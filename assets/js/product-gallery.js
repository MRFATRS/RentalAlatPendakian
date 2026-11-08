(() => {
  document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
    const dataElement = gallery.querySelector('[data-gallery-data]');
    const imageElement = gallery.querySelector('[data-gallery-image]');
    if (!dataElement || !imageElement) return;

    let images;
    try {
      images = JSON.parse(dataElement.textContent);
    } catch (error) {
      console.error('Data galeri produk tidak valid.', error);
      return;
    }
    if (!Array.isArray(images) || images.length < 2) return;

    const dots = Array.from(gallery.querySelectorAll('[data-gallery-dot]'));
    const thumbnails = Array.from(gallery.querySelectorAll('[data-gallery-thumbnail]'));
    const colorLabel = gallery.querySelector('[data-gallery-color]');
    let activeIndex = 0;

    const showImage = (index) => {
      activeIndex = (index + images.length) % images.length;
      const image = images[activeIndex];
      imageElement.src = image.src;
      imageElement.alt = image.alt;
      if (colorLabel) {
        colorLabel.textContent = image.color;
        colorLabel.hidden = !image.color;
      }
      dots.forEach((dot, dotIndex) => {
        const active = dotIndex === activeIndex;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-pressed', String(active));
      });
      thumbnails.forEach((thumbnail, thumbnailIndex) => {
        const active = thumbnailIndex === activeIndex;
        thumbnail.classList.toggle('is-active', active);
        thumbnail.setAttribute('aria-pressed', String(active));
      });
    };

    gallery.querySelector('[data-gallery-prev]')?.addEventListener('click', () => showImage(activeIndex - 1));
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click', () => showImage(activeIndex + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => showImage(Number(dot.dataset.galleryDot))));
    thumbnails.forEach((thumbnail) => thumbnail.addEventListener('click', () => {
      showImage(Number(thumbnail.dataset.galleryThumbnail));
    }));

    let pointerStart = null;
    gallery.addEventListener('pointerdown', (event) => {
      if (event.button !== 0 || event.target.closest('button')) return;
      pointerStart = { x: event.clientX, y: event.clientY };
      if (event.pointerType === 'touch') gallery.setPointerCapture(event.pointerId);
    });
    gallery.addEventListener('pointerup', (event) => {
      if (!pointerStart) return;
      const deltaX = event.clientX - pointerStart.x;
      const deltaY = event.clientY - pointerStart.y;
      pointerStart = null;
      if (Math.abs(deltaX) > 40 && Math.abs(deltaX) > Math.abs(deltaY)) {
        showImage(activeIndex + (deltaX < 0 ? 1 : -1));
      }
    });
    gallery.addEventListener('pointercancel', () => { pointerStart = null; });
  });
})();
