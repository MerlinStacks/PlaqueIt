(function () {
  if (typeof window === 'undefined' || !window.jQuery) return;

  window.jQuery(function ($) {
    $('.plaque-it-colour-field').wpColorPicker();
  });
})();
