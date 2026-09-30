/**
 * WC PTT Kargo settings page enhancements:
 *  - live label preview iframe that re-renders as the form changes
 *  - WP Media Library logo picker
 *  - "Test connection" button that works before saving
 */

(function ($) {
	'use strict';

	// Live Preview

	var $previewForm  = $('#wc-ptt-preview-form');
	var $mainForm     = $('#wc-ptt-settings-form');
	var $previewFrame = $('#wc-ptt-preview-iframe');
	var debounceTimer = null;

	function syncPreview() {
		if (!$previewForm.length || !$mainForm.length) return;

		$previewForm.find('input[name^="preview["]').remove();

		$mainForm.find('[data-preview-key]').each(function () {
			var $el = $(this);
			var key = $el.data('preview-key');
			var val;
			if ($el.attr('type') === 'checkbox') {
				val = $el.is(':checked') ? '1' : '0';
			} else {
				val = $el.val() || '';
			}
			$('<input type="hidden">').attr('name', 'preview[' + key + ']').val(val).appendTo($previewForm);
		});

		$previewForm[0].submit();
	}

	function debouncedSync() {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(syncPreview, 350);
	}

	if ($previewForm.length) {
		syncPreview();
		$mainForm.on('input change', '[data-preview-key]', debouncedSync);
	}

	// WP Media Library logo picker

	var mediaFrame = null;

	$(document).on('click', '#wc-ptt-logo-pick', function (e) {
		e.preventDefault();

		if (mediaFrame) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media({
			title: Barkoda.i18n.mediaTitle,
			button: { text: Barkoda.i18n.mediaButton },
			library: { type: 'image' },
			multiple: false
		});

		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
			$('#label_logo_url').val(url).trigger('input');
			$('#wc-ptt-logo-preview')
				.html('<img src="' + url + '" alt="">')
				.show();
		});

		mediaFrame.open();
	});

	$(document).on('click', '#wc-ptt-logo-clear', function (e) {
		e.preventDefault();
		$('#label_logo_url').val('').trigger('input');
		$('#wc-ptt-logo-preview').empty().hide();
	});

	// Test connection

	$(document).on('click', '#wc-ptt-test-conn-btn', function (e) {
		e.preventDefault();
		var $btn  = $(this);
		var $out  = $('#wc-ptt-test-result');
		var nonce = (window.Barkoda && window.Barkoda.nonce) || '';

		$out.removeClass('is-success is-error').addClass('is-loading')
			.html('<span class="dashicons dashicons-update spin"></span> ' +
				((window.Barkoda && window.Barkoda.i18n && window.Barkoda.i18n.testing) || 'Test ediliyor...'))
			.show();
		$btn.prop('disabled', true);

		var data = {
			action: 'barkoda_test_conn',
			nonce: nonce,
			environment: $('#environment').val(),
			musteri_id: $('#musteri_id').val(),
			sifre: $('#sifre').val()
		};

		$.post((window.Barkoda && window.Barkoda.ajaxUrl) || ajaxurl, data)
			.done(function (res) {
				$out.removeClass('is-loading');
				if (res && res.success) {
					$out.addClass('is-success').text(res.data.message);
				} else {
					$out.addClass('is-error').text((res && res.data && res.data.message) || Barkoda.i18n.genericErr);
				}
			})
			.fail(function (xhr) {
				$out.removeClass('is-loading').addClass('is-error')
					.text((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || Barkoda.i18n.serverErr);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

})(jQuery);

/* Product picker: mirror the enhanced-select values into the hidden field the form submits. */
(function ($) {
	$(function () {
		$('#urun_idler_picker').on('change', function () {
			var values = $(this).val() || [];
			$('#urun_idler').val(values.join(','));
		});
	});
})(jQuery);
