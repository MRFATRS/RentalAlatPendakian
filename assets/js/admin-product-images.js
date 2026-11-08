(() => {
  document.querySelectorAll('[data-product-photo-input]').forEach((input) => {
    const form = input.closest('form');
    const previewList = form.querySelector('[data-product-photo-list]');
    const existingList = form.querySelector('[data-existing-photo-list]');
    if (!previewList) return;

    input.addEventListener('change', () => {
      previewList.querySelectorAll('img[data-object-url]').forEach((image) => URL.revokeObjectURL(image.dataset.objectUrl));
      previewList.replaceChildren();
      Array.from(input.files || []).forEach((file, index) => {
        const row = document.createElement('article');
        row.className = 'admin-photo-row';
        const preview = document.createElement('img');
        preview.className = 'admin-photo-preview';
        preview.alt = `Pratinjau foto baru ${index + 1}`;
        preview.src = URL.createObjectURL(file);
        preview.dataset.objectUrl = preview.src;
        preview.addEventListener('load', () => URL.revokeObjectURL(preview.src), { once: true });

        const fields = document.createElement('div');
        fields.className = 'admin-photo-fields';
        const colorLabel = document.createElement('label');
        colorLabel.htmlFor = `warna-baru-${index}`;
        colorLabel.textContent = 'Label warna';
        const colorInput = document.createElement('input');
        colorInput.id = `warna-baru-${index}`;
        colorInput.name = 'warna_baru[]';
        colorInput.maxLength = 50;
        colorInput.placeholder = 'Contoh: Merah';
        fields.append(colorLabel, colorInput);

        const actions = document.createElement('div');
        actions.className = 'admin-photo-actions';
        const primaryLabel = document.createElement('label');
        primaryLabel.className = 'admin-photo-primary';
        const primaryInput = document.createElement('input');
        primaryInput.type = 'radio';
        primaryInput.name = 'foto_utama';
        primaryInput.value = `new:${index}`;
        primaryLabel.append(primaryInput, document.createTextNode(' Foto utama'));
        actions.append(primaryLabel);

        row.append(preview, fields, actions);
        previewList.append(row);
      });

      const primary = previewList.querySelector('input[type="radio"]');
      if (primary && !(existingList && existingList.querySelector('input[name="foto_utama"]:checked'))) {
        primary.checked = true;
      }
    });

    if (existingList) {
      existingList.addEventListener('change', (event) => {
        if (event.target.matches('[data-photo-delete]')) {
          event.target.closest('[data-existing-photo]').classList.toggle('is-marked-delete', event.target.checked);
          if (event.target.checked && event.target.closest('[data-existing-photo]').querySelector('input[name="foto_utama"]').checked) {
            const next = existingList.querySelector('[data-existing-photo]:not(.is-marked-delete) input[name="foto_utama"]')
              || previewList.querySelector('input[name="foto_utama"]');
            if (next) next.checked = true;
          }
        }
        if (event.target.matches('[data-photo-replacement]') && event.target.files[0]) {
          let preview = event.target.closest('[data-existing-photo]').querySelector('[data-photo-preview]');
          if (preview.tagName !== 'IMG') {
            const image = document.createElement('img');
            image.alt = 'Pratinjau foto pengganti';
            image.dataset.photoPreview = '';
            preview.replaceWith(image);
            preview = image;
          }
          if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
          preview.dataset.objectUrl = URL.createObjectURL(event.target.files[0]);
          preview.src = preview.dataset.objectUrl;
        }
      });
    }
  });
})();
