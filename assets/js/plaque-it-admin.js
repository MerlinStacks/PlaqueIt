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

    const productSearch = document.querySelector('.plaque-it-live-product-search');
    const productResults = document.querySelector('.plaque-it-live-results');
    const config = window.plaqueItAdmin || {};

    function escapeHtml(value) {
      return String(value || '').replace(/[&<>'"]/g, function (char) {
        return {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          "'": '&#039;',
          '"': '&quot;',
        }[char];
      });
    }

    function statusLabel(product) {
      if (product.conflict) return 'PersonaliseIt conflict';
      if (product.enabled) return 'PlaqueIt enabled';
      return 'Not enabled';
    }

    function renderProductResults(products) {
      if (!productResults) return;
      if (!products.length) {
        productResults.innerHTML = '<div class="plaque-it-search-placeholder">' + escapeHtml(config.noResults || 'No products found.') + '</div>';
        return;
      }

      productResults.innerHTML = products.map(function (product) {
        const meta = ['#' + product.id, product.type, product.status].filter(Boolean).join(' - ');
        const sku = product.sku ? '<span>SKU: ' + escapeHtml(product.sku) + '</span>' : '';
        const badgeClass = product.conflict ? 'plaque-it-status-conflict' : (product.enabled ? 'plaque-it-status-enabled' : 'plaque-it-status-disabled');
        return '<a class="plaque-it-product-result" href="' + escapeHtml(product.url) + '">' +
          '<span class="plaque-it-product-result-main">' +
            '<strong>' + escapeHtml(product.name) + '</strong>' +
            '<small>' + escapeHtml(meta) + '</small>' +
          '</span>' +
          '<span class="plaque-it-product-result-meta">' + sku + '<span class="plaque-it-status-badge ' + badgeClass + '">' + escapeHtml(statusLabel(product)) + '</span></span>' +
        '</a>';
      }).join('');
    }

    if (productSearch && productResults && config.ajaxUrl) {
      let searchTimer = null;
      let controller = null;

      productSearch.addEventListener('input', function () {
        const term = productSearch.value.trim();
        window.clearTimeout(searchTimer);

        if (controller) controller.abort();
        if (!term) {
          productResults.innerHTML = '<div class="plaque-it-search-placeholder">' + escapeHtml(config.searchPrompt || 'Start typing to search.') + '</div>';
          return;
        }
        if (term.length < 2) {
          productResults.innerHTML = '<div class="plaque-it-search-placeholder">' + escapeHtml(config.searchMinimum || 'Type at least 2 characters to search.') + '</div>';
          return;
        }

        searchTimer = window.setTimeout(function () {
          controller = new AbortController();
          productResults.innerHTML = '<div class="plaque-it-search-placeholder">' + escapeHtml(config.searching || 'Searching products...') + '</div>';

          const params = new URLSearchParams({
            action: 'plaque_it_search_products',
            nonce: config.productNonce || '',
            term: term,
          });

          fetch(config.ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin',
            signal: controller.signal,
          }).then(function (response) {
            return response.json();
          }).then(function (payload) {
            renderProductResults(payload && payload.success && Array.isArray(payload.data) ? payload.data : []);
          }).catch(function (error) {
            if (error.name === 'AbortError') return;
            renderProductResults([]);
          });
        }, 250);
      });
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
