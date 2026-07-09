(function () {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  const data = window.plaqueItData || {};
  const fonts = data.fonts || [];
  const settings = data.settings || {};

  function mmToPx(mm) {
    return (Number(mm) / 25.4) * (data.dpi || 76);
  }

  function selectedVariationId(root) {
    const input = document.querySelector('input.variation_id');
    return input ? Number(input.value || 0) : 0;
  }

  function colours(root) {
    const id = selectedVariationId(root);
    return (data.variationData && (data.variationData[id] || data.variationData[0])) || { plaque: '#111111', engraving: '#ffffff' };
  }

  function fontFace(font) {
    if (document.getElementById('plaque-it-font-' + font.id)) return;
    const style = document.createElement('style');
    style.id = 'plaque-it-font-' + font.id;
    style.textContent = `@font-face{font-family:'${font.family || `PlaqueItFont${font.id}`}';src:url('${font.url}');font-weight:${font.weight};font-style:${font.style};font-display:swap;}`;
    document.head.appendChild(style);
  }

  fonts.forEach(fontFace);

  function lineRows(root, lines) {
    const wrap = root.querySelector('.plaque-it-lines');
    const current = Array.from(wrap.querySelectorAll('.plaque-it-line')).map((row) => ({
      font_id: Number(row.querySelector('.plaque-it-line-font').value || 0),
      size: Number(row.querySelector('.plaque-it-line-size').value || settings.min_font_size || 8),
    }));
    wrap.innerHTML = '';
    lines.forEach((text, index) => {
      const row = document.createElement('div');
      row.className = 'plaque-it-line';
      const fontOptions = fonts.map((font) => `<option value="${font.id}">${font.name}</option>`).join('');
      row.innerHTML = `<strong>Line ${index + 1}</strong><select class="plaque-it-line-font">${fontOptions}</select><input class="plaque-it-line-size" type="number" step="1" min="${settings.min_font_size || 8}" value="${current[index]?.size || settings.min_font_size || 8}" />`;
      wrap.appendChild(row);
      if (current[index]?.font_id) row.querySelector('.plaque-it-line-font').value = current[index].font_id;
      row.addEventListener('input', () => update(root));
      row.addEventListener('change', () => update(root));
    });
  }

  function shape(corner, width, height, fill) {
    if (corner === 'rounded') {
      const r = Math.min(width, height) * 0.08;
      return `<rect width="${width}" height="${height}" rx="${r}" fill="${fill}"/>`;
    }
    if (corner === 'straight') {
      const c = Math.min(width, height) * 0.1;
      return `<polygon points="${c},0 ${width - c},0 ${width},${c} ${width},${height - c} ${width - c},${height} ${c},${height} 0,${height - c} 0,${c}" fill="${fill}"/>`;
    }
    if (corner === 'scallop') {
      const r = Math.min(width, height) * 0.1;
      return `<path d="M ${r} 0 H ${width - r} Q ${width - r} ${r} ${width} ${r} V ${height - r} Q ${width - r} ${height - r} ${width - r} ${height} H ${r} Q ${r} ${height - r} 0 ${height - r} V ${r} Q ${r} ${r} ${r} 0 Z" fill="${fill}"/>`;
    }
    return `<rect width="${width}" height="${height}" fill="${fill}"/>`;
  }

  function getConfig(root) {
    const messageLines = root.querySelector('.plaque-it-message').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
    const rows = Array.from(root.querySelectorAll('.plaque-it-line'));
    const c = colours(root);
    return {
      width: Number(root.querySelector('.plaque-it-width').value || 0),
      height: Number(root.querySelector('.plaque-it-height').value || 0),
      corner_style: root.querySelector('.plaque-it-corner').value,
      variation_id: selectedVariationId(root),
      plaque_colour: c.plaque,
      engraving_colour: c.engraving,
      preview_approved: root.querySelector('.plaque-it-approval').checked,
      lines: messageLines.map((text, index) => ({
        text,
        font_id: Number(rows[index]?.querySelector('.plaque-it-line-font').value || fonts[0]?.id || 0),
        size: Number(rows[index]?.querySelector('.plaque-it-line-size').value || settings.min_font_size || 8),
      })),
    };
  }

  function clampNumberInputs(root) {
    root.querySelectorAll('input[type="number"]').forEach((input) => {
      if (input.value === '') return;
      const value = Number(input.value);
      if (!Number.isFinite(value)) return;
      const min = input.min === '' ? null : Number(input.min);
      const max = input.max === '' ? null : Number(input.max);
      if (max !== null && value > max) input.value = String(max);
      if (min !== null && value < min) input.value = String(min);
    });
  }

  function validate(config) {
    const errors = [];
    if (config.width < Number(settings.min_width) || config.width > Number(settings.max_width)) errors.push('Width is outside the allowed range.');
    if (config.height < Number(settings.min_height) || config.height > Number(settings.max_height)) errors.push('Height is outside the allowed range.');
    if (!config.lines.length) errors.push('Enter at least one message line.');
    if (config.lines.length > Number(settings.max_lines || 6)) errors.push(`Maximum ${settings.max_lines} lines allowed.`);
    const safeWidth = mmToPx(config.width) * (Number(settings.safe_width || 85) / 100);
    const safeHeight = mmToPx(config.height) * (Number(settings.safe_height || 80) / 100);
    let totalHeight = 0;
    config.lines.forEach((line) => {
      const font = fonts.find((item) => Number(item.id) === Number(line.font_id));
      const min = Math.max(Number(settings.min_font_size || 8), Number(font?.minSize || 0));
      const width = String(line.text).length * Number(line.size) * Number(font?.widthFactor || 0.56);
      totalHeight += Number(line.size) * 1.25;
      if (Number(line.size) < min) errors.push(`Line "${line.text}" is below the minimum readable size.`);
      if (width > safeWidth) errors.push(`Line "${line.text}" is too wide for the plaque.`);
    });
    if (totalHeight > safeHeight) errors.push('The selected text sizes are too tall for the plaque.');
    if (settings.require_approval && !config.preview_approved) errors.push('Approve the plaque preview before adding to cart.');
    return errors;
  }

  function render(root, config) {
    const w = Math.max(1, mmToPx(config.width));
    const h = Math.max(1, mmToPx(config.height));
    let y = h / 2;
    const total = config.lines.reduce((sum, line) => sum + Number(line.size) * 1.25, 0);
    y = (h - total) / 2;
    const text = config.lines.map((line) => {
      const font = fonts.find((item) => Number(item.id) === Number(line.font_id));
      y += Number(line.size);
      const out = `<text x="50%" y="${y}" text-anchor="middle" dominant-baseline="middle" fill="${config.engraving_colour}" font-family="PlaqueItFont${line.font_id}, Arial" font-size="${line.size}" font-weight="${font?.weight || 400}" font-style="${font?.style || 'normal'}">${String(line.text).replace(/[&<>]/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]))}</text>`;
      y += Number(line.size) * 0.25;
      return out;
    }).join('');
    const svg = `<svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg">${shape(config.corner_style, w, h, config.plaque_colour)}${text}</svg>`;
    const previews = document.querySelectorAll(`.plaque-it-preview[data-product-id="${root.dataset.productId}"]`);
    if (previews.length) {
      previews.forEach((preview) => { preview.innerHTML = svg; });
      return;
    }

    const preview = root.querySelector('.plaque-it-preview');
    if (preview) preview.innerHTML = svg;
  }

  function update(root) {
    clampNumberInputs(root);
    const messageLines = root.querySelector('.plaque-it-message').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
    if (messageLines.length !== root.querySelectorAll('.plaque-it-line').length) lineRows(root, messageLines);
    const config = getConfig(root);
    const errors = validate(config);
    const surcharge = Number(config.width) * Number(config.height) * Number(settings.area_rate || 0);
    root.querySelector('.plaque-it-price').textContent = `Plaque size surcharge: ${data.currency || ''}${surcharge.toFixed(2)}`;
    root.querySelector('.plaque-it-errors').textContent = errors.join(' ');
    root.querySelector('.plaque-it-config-input').value = JSON.stringify(config);
    const button = root.closest('form')?.querySelector('button.single_add_to_cart_button');
    if (button) button.disabled = errors.length > 0;
    render(root, config);
  }

  function init() {
    document.querySelectorAll('.plaque-it-configurator:not([data-plaque-it-ready])').forEach((root) => {
      root.dataset.plaqueItReady = '1';
      root.addEventListener('input', () => update(root));
      root.addEventListener('change', () => update(root));
      const variationInput = document.querySelector('input.variation_id');
      if (variationInput) variationInput.addEventListener('change', () => setTimeout(() => update(root), 0));
      if (window.jQuery) {
        window.jQuery(document.body).on('found_variation reset_data', () => setTimeout(() => update(root), 0));
      }
      update(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
