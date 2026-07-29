(function ($) {
  'use strict';

  $(function () {
    if ($.fn.wpColorPicker) {
      $('.ecc-color').wpColorPicker();
    }

    var $layout = $('#ecc_layout');
    var $cornerRow = $('.ecc-corner-side-row');
    function syncCornerRow() {
      if (!$layout.length || !$cornerRow.length) return;
      $cornerRow.prop('hidden', $layout.val() !== 'corner');
    }
    $layout.on('change', syncCornerRow);
    syncCornerRow();

    var $block = $('input[name="ecc_settings[block_scripts]"]');
    var $auto = $('input[name="ecc_settings[auto_block_known]"]');
    function syncAutoBlock() {
      if (!$block.length || !$auto.length) return;
      var on = $block.is(':checked');
      $auto.prop('disabled', !on);
    }
    $block.on('change', syncAutoBlock);
    syncAutoBlock();

    var $tabs = $('[data-ecc-lang-tabs]');
    if (!$tabs.length) return;

    $tabs.on('click', '[data-ecc-lang]', function (e) {
      e.preventDefault();
      var lang = $(this).data('ecc-lang');
      $tabs.find('[data-ecc-lang]').removeClass('button-primary').addClass('button');
      $(this).addClass('button-primary').removeClass('button');
      $('[data-ecc-lang-panel]').prop('hidden', true);
      $('[data-ecc-lang-panel="' + lang + '"]').prop('hidden', false);
    });
  });
})(jQuery);
