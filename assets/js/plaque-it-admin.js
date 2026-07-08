(function () {
  if (typeof window === 'undefined' || !window.jQuery) return;

  window.jQuery(function ($) {
    $('.plaque-it-colour-field').wpColorPicker();

    const $modal = $('.plaque-it-modal-overlay');
    const $fileInput = $modal.find('input[type="file"]');
    const $nameInput = $modal.find('input[name="name"]');
    const $preview = $modal.find('.plaque-it-upload-preview span');

    function openModal() {
      $modal.prop('hidden', false);
      $('body').addClass('plaque-it-modal-open');
    }

    function closeModal() {
      $modal.prop('hidden', true);
      $('body').removeClass('plaque-it-modal-open');
      $modal.find('form')[0]?.reset();
      $preview.css({ fontFamily: '', fontWeight: '', fontStyle: '' });
    }

    function filenameToTitle(name) {
      return String(name || '').replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();
    }

    $('.plaque-it-upload-font-btn').on('click', openModal);
    $('.plaque-it-modal-close, .plaque-it-modal-cancel').on('click', closeModal);
    $modal.on('click', function (event) {
      if (event.target === this) closeModal();
    });

    $fileInput.on('change', function () {
      const file = this.files && this.files[0];
      if (!file) return;
      if (!$nameInput.val()) $nameInput.val(filenameToTitle(file.name));
      if (!window.FontFace) return;
      const reader = new FileReader();
      reader.onload = function () {
        const family = 'PlaqueItUploadPreview';
        const face = new FontFace(family, reader.result);
        face.load().then(function (loadedFace) {
          document.fonts.add(loadedFace);
          $preview.css('fontFamily', family + ', sans-serif');
        }).catch(function () {});
      };
      reader.readAsArrayBuffer(file);
    });
  });
})();
