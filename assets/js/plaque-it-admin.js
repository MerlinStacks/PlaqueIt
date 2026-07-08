(function () {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }
    callback();
  }

  ready(function () {
    if (window.jQuery && window.jQuery.fn.wpColorPicker) {
      window.jQuery('.plaque-it-colour-field').wpColorPicker();
    }

    const modal = document.querySelector('.plaque-it-modal-overlay');
    const uploadButton = document.querySelector('.plaque-it-upload-font-btn');
    if (!modal || !uploadButton) return;

    const form = modal.querySelector('form');
    const fileInput = modal.querySelector('input[type="file"]');
    const nameInput = modal.querySelector('input[name="name"]');
    const weightInput = modal.querySelector('select[name="weight"]');
    const styleInput = modal.querySelector('select[name="style"]');
    const preview = modal.querySelector('.plaque-it-upload-preview span');
    const closeButtons = modal.querySelectorAll('.plaque-it-modal-close, .plaque-it-modal-cancel');

    function openModal() {
      modal.hidden = false;
      modal.removeAttribute('hidden');
      document.body.classList.add('plaque-it-modal-open');
      if (fileInput) fileInput.focus();
    }

    function closeModal() {
      modal.hidden = true;
      modal.setAttribute('hidden', 'hidden');
      document.body.classList.remove('plaque-it-modal-open');
      if (form) form.reset();
      if (preview) {
        preview.style.fontFamily = '';
        preview.style.fontWeight = '';
        preview.style.fontStyle = '';
      }
    }

    function filenameToTitle(name) {
      return String(name || '').replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();
    }

    uploadButton.addEventListener('click', openModal);
    closeButtons.forEach(function (button) {
      button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (event) {
      if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    if (!fileInput) return;
    fileInput.addEventListener('change', function () {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (nameInput && !nameInput.value) nameInput.value = filenameToTitle(file.name);
      if (!window.FontFace || !window.FileReader || !preview) return;

      const reader = new FileReader();
      reader.onload = function () {
        const family = 'PlaqueItUploadPreview';
        const face = new FontFace(family, reader.result, {
          weight: weightInput ? weightInput.value : '400',
          style: styleInput ? styleInput.value : 'normal',
        });
        face.load().then(function (loadedFace) {
          document.fonts.add(loadedFace);
          preview.style.fontFamily = family + ', sans-serif';
          preview.style.fontWeight = weightInput ? weightInput.value : '400';
          preview.style.fontStyle = styleInput ? styleInput.value : 'normal';
        }).catch(function () {});
      };
      reader.readAsArrayBuffer(file);
    });
  });
})();
