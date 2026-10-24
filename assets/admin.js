(function () {
  'use strict';

  var config = window.swnewspaper2Admin || {};

  document.querySelectorAll('.swnp-drop input[type="file"]').forEach(function (input) {
    var label = input.closest('.swnp-drop').querySelector('[data-swnp-filename]');
    input.addEventListener('change', function () {
      label.textContent = input.files.length ? input.files[0].name : config.noFile;
    });
  });

  document.querySelectorAll('form[data-swnp-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(config.confirmDelete)) {
        event.preventDefault();
      }
    });
  });
})();
