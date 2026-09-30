(function ($) {
	'use strict';

	if (typeof Barkoda === 'undefined') return;

	var $modal = null;
	var currentOrder = null;

	function toast(message, type) {
		$('.wc-ptt-toast').remove();
		var $t = $('<div class="wc-ptt-toast"></div>').text(message);
		if (type === 'success') $t.addClass('is-success');
		if (type === 'error')   $t.addClass('is-error');
		$('body').append($t);
		setTimeout(function () { $t.fadeOut(400, function () { $(this).remove(); }); }, 4500);
	}

	function ajax(action, data) {
		return $.post(Barkoda.ajaxUrl, $.extend({ action: action, nonce: Barkoda.nonce }, data));
	}

	function refreshList() {
		var show = $('#wc-ptt-refresh').data('show') || 'pending';
		$('#wc-ptt-refresh').prop('disabled', true);
		ajax(Barkoda.actions.refresh, { show: show })
			.done(function (res) {
				if (res && res.success) {
					$('#wc-ptt-orders-container').html(res.data.html);
					$('.wc-ptt-count').text(res.data.count + ' ' + Barkoda.i18n.orderWord);
				}
			})
			.always(function () { $('#wc-ptt-refresh').prop('disabled', false); });
	}

	var FIELD_LABELS = {
		aliciAdi: Barkoda.i18n.fieldRecipient,
		aAdres: Barkoda.i18n.fieldAddress,
		aliciIlAdi: Barkoda.i18n.fieldProvince,
		aliciIlceAdi: Barkoda.i18n.fieldDistrict,
		aliciSms: Barkoda.i18n.fieldPhone,
		aliciEmail: Barkoda.i18n.fieldEmail
	};

	// Order-level override fields for shipping. If left blank, the auto-computed value is used.
	var SHIPPING_FIELDS = [
		{ key: 'agirlik',   label: Barkoda.i18n.fieldWeight,   type: 'number', step: '1',   min: '1' },
		{ key: 'desi',      label: Barkoda.i18n.fieldDesi,          type: 'number', step: '1',   min: '1' },
		{ key: 'en',        label: Barkoda.i18n.fieldWidth,       type: 'number', step: '1',   min: '0' },
		{ key: 'boy',       label: Barkoda.i18n.fieldLength,      type: 'number', step: '1',   min: '0' },
		{ key: 'yukseklik', label: Barkoda.i18n.fieldHeight, type: 'number', step: '1',   min: '0' }
	];

	function openSummaryModal(data) {
		currentOrder = data;
		$modal = $('#wc-ptt-modal');
		$modal.find('.wc-ptt-modal-title').text(Barkoda.i18n.summaryTitle + ' — #' + (data.order_no || ''));

		var $cust = $modal.find('.wc-ptt-customer').empty();
		var fields = data.fields || {};
		var missing = data.missing || {};

		$.each(FIELD_LABELS, function (key, label) {
			var val = fields[key] || '';
			var isMissing = !!missing[key];
			var $row = $('<div class="field-row"></div>');
			$row.append($('<label></label>').attr('for', 'field-' + key).text(label + (isMissing ? ' (eksik)' : '')));
			var $input = $('<input type="text" />')
				.attr({ id: 'field-' + key, 'data-key': key, value: val })
				.addClass('field-input' + (isMissing ? ' is-missing' : ''));
			$row.append($input);
			$cust.append($row);
		});

		// Auto-computed values appear as placeholders. A filled field overrides them;
		// a blank field falls back to the settings or the auto-computed value.
		var $ship = $modal.find('.wc-ptt-shipping').empty();
		SHIPPING_FIELDS.forEach(function (f) {
			var current = fields[f.key];
			var $row = $('<div class="field-row"></div>');
			$row.append($('<label></label>').attr('for', 'field-' + f.key).text(f.label));
			var $input = $('<input />')
				.attr({
					id: 'field-' + f.key,
					'data-key': f.key,
					'data-shipping': '1',
					type: f.type,
					step: f.step,
					min: f.min,
					placeholder: (current && Number(current) > 0) ? String(current) : '—'
				})
				.addClass('field-input shipping-input');
			$row.append($input);
			$ship.append($row);
		});

		// COD notice: for cash-on-delivery orders, UA and the handling fee go to PTT automatically.
		var $payInfo = $modal.find('.wc-ptt-payment-info').empty();
		if (data.is_cod) {
			$payInfo.show().html(
				'<div style="padding:8px 12px; background:#fff3cd; border-left:4px solid #856404; border-radius:3px; font-size:12px; margin:8px 0;">' +
				'<strong>' + Barkoda.i18n.codInfo + '</strong> ' +
				escapeHtml(data.payment_method || '') +
				' &middot; <code>odemesekli=UA</code> &middot; <code>odeme_sart_ucreti=' + escapeHtml(data.cod_amount || '0') + '</code>' +
				'</div>'
			);
		} else {
			$payInfo.hide();
		}

		// Insurance toggle: defaults to on/off based on settings; user can change it via the popup.
		var $ins = $modal.find('.wc-ptt-insurance').empty();
		var insDefault = !!data.insurance_default;
		var insAmount = data.insurance_amount || (fields.deger_ucreti || '0');
		var $insWrap = $('<div></div>');
		var $insLabel = $('<label style="display:block; margin-bottom:8px;"></label>');
		var $insChk = $('<input type="checkbox" data-key="__insurance" data-shipping="0" />').prop('checked', insDefault);
		$insLabel.append($insChk).append(' ').append($('<strong></strong>').text(Barkoda.i18n.insuranceLabel));
		$insWrap.append($insLabel);

		var $amountRow = $('<div class="field-row"></div>');
		$amountRow.append($('<label></label>').attr('for', 'field-deger_ucreti').text(Barkoda.i18n.insuranceAmount));
		var $amountInput = $('<input type="number" step="0.01" min="0" />')
			.attr({
				id: 'field-deger_ucreti',
				'data-key': 'deger_ucreti',
				'data-shipping': '1',
				placeholder: insAmount,
				value: insDefault ? insAmount : ''
			})
			.addClass('field-input shipping-input');
		$amountRow.append($amountInput);
		$insWrap.append($amountRow);

		// Enable or disable the amount input alongside the toggle.
		$insChk.on('change', function () {
			var on = $(this).is(':checked');
			$amountInput.prop('disabled', !on);
			if (on && !$amountInput.val()) $amountInput.val(insAmount);
			if (!on) $amountInput.val('');
		});
		$amountInput.prop('disabled', !insDefault);

		$ins.append($insWrap);

		// Retry notice: shown when a barcode was consumed by a previous failed attempt.
		var $pendingInfo = $modal.find('.wc-ptt-pending-info').empty();
		if (data.pending_barkod) {
			$pendingInfo.show().html(
				'<div style="padding:8px 12px; background:#cce5ff; border-left:4px solid #0c63e4; border-radius:3px; font-size:12px; margin:8px 0;">' +
				'🔁 <strong>' + escapeHtml(Barkoda.i18n.retryLabel) + '</strong> <code>' +
				escapeHtml(data.pending_barkod) +
				'</code> ' + escapeHtml(Barkoda.i18n.retryNotice) +
				'</div>'
			);
		} else {
			$pendingInfo.hide();
		}

		// Multi-package: package count + waybill number. 1 keeps the single-package flow.
		var $mp = $modal.find('.wc-ptt-multipackage').empty();
		var $mpRow = $('<div class="field-row"></div>');
		$mpRow.append($('<label></label>').attr('for', 'field-parca_adet').text(Barkoda.i18n.packageCount));
		var $mpInput = $('<input type="number" min="1" max="99" value="1" />')
			.attr({ id: 'field-parca_adet', 'data-key': 'parca_adet', 'data-multipackage': '1' })
			.addClass('field-input multi-input');
		$mpRow.append($mpInput);
		$mp.append($mpRow);

		var $irsRow = $('<div class="field-row"></div>').css('margin-top', '8px');
		$irsRow.append($('<label></label>').attr('for', 'field-irsaliye_no').text(Barkoda.i18n.waybillNo));
		var $irsInput = $('<input type="text" maxlength="30" />')
			.attr({ id: 'field-irsaliye_no', 'data-key': 'irsaliye_no', 'data-multipackage': '1' })
			.addClass('field-input multi-input');
		$irsRow.append($irsInput);
		$mp.append($irsRow);

		var $mpHint = $('<p style="font-size:11px; color:#646970; margin:4px 0 0;"></p>')
			.text(Barkoda.i18n.multiHint);
		$mp.append($mpHint);

		$modal.find('.missing-warn').toggle(Object.keys(missing).length > 0);
		$modal.show();
	}

	function closeModal() {
		if ($modal) $modal.hide();
		currentOrder = null;
	}

	function sendCurrent() {
		if (!currentOrder) return;
		var orderId = currentOrder.order_id;
		var $btn = $('tr[data-order-id="' + orderId + '"]').find('.js-ptt-send');
		$btn.addClass('is-loading').text(Barkoda.i18n.sending);

		var override = {};
		var multipack = { parca_adet: '1', irsaliye_no: '' };

		$modal.find('.field-input').each(function () {
			var $el = $(this);
			var key = $el.data('key');
			if ($el.prop('disabled')) return; // never override a disabled input
			var val = ($el.val() || '').trim();
			if ($el.data('multipackage')) {
				multipack[key] = val;
				return;
			}
			override[key] = val;
		});

		// Insurance checkbox; the backend whitelist accepts the on/off flag.
		var $insChk = $modal.find('input[data-key="__insurance"]');
		if ($insChk.length) {
			override['__insurance'] = $insChk.is(':checked') ? 'on' : 'off';
		}

		closeModal();

		ajax(Barkoda.actions.send, {
			order_id: orderId,
			override: override,
			parca_adet: multipack.parca_adet || '1',
			irsaliye_no: multipack.irsaliye_no || ''
		})
			.done(function (res) {
				if (res && res.success) {
					var msg = Barkoda.i18n.success + res.data.barkod;
					if (res.data.barkodlar && res.data.barkodlar.length > 1) {
						msg += ' (+' + (res.data.barkodlar.length - 1) + ' ' + Barkoda.i18n.extraPackages + ')';
					}
					toast(msg, 'success');
					if (res.data.label_url) window.open(res.data.label_url, '_blank');
					refreshList();
				} else {
					toast(Barkoda.i18n.error + ((res && res.data && res.data.message) || Barkoda.i18n.unknownErr), 'error');
					refreshList();
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || Barkoda.i18n.serverErr;
				toast(Barkoda.i18n.error + msg, 'error');
				refreshList();
			})
			.always(function () {
				$btn.removeClass('is-loading').text(Barkoda.i18n.shipBtn);
			});
	}

	function prepareAndOpen(orderId) {
		ajax(Barkoda.actions.prepare, { order_id: orderId })
			.done(function (res) {
				if (res && res.success) {
					openSummaryModal(res.data);
				} else {
					toast(Barkoda.i18n.error + ((res && res.data && res.data.message) || Barkoda.i18n.prepareErr), 'error');
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || Barkoda.i18n.serverErr;
				toast(Barkoda.i18n.error + msg, 'error');
			});
	}

	// Event delegation

	$(document).on('click', '#wc-ptt-refresh', function (e) {
		e.preventDefault();
		refreshList();
	});

	$(document).on('click', '.js-ptt-send', function (e) {
		e.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10);
		if (!orderId) return;
		prepareAndOpen(orderId);
	});

	$(document).on('click', '.js-ptt-takip', function (e) {
		e.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10);
		if (!orderId) return;
		ajax(Barkoda.actions.takip, { order_id: orderId }).done(function (res) {
			if (res && res.success) {
				openTakipModal(res.data);
			} else {
				toast(Barkoda.i18n.trackErr, 'error');
			}
		}).fail(function () {
			toast(Barkoda.i18n.trackErr, 'error');
		});
	});

	// Cancel a PTT shipment: barkodVeriSil with a referansVeriSil fallback.
	// Only succeeds while PTT has not accepted the shipment.
	$(document).on('click', '.js-ptt-cancel', function (e) {
		e.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10);
		if (!orderId) return;

		if (!window.confirm(Barkoda.i18n.cancelConfirm)) return;

		var $btn = $(this);
		var originalText = $btn.text();
		$btn.prop('disabled', true).text(Barkoda.i18n.canceling);

		ajax(Barkoda.actions.cancelKargo, { order_id: orderId })
			.done(function (res) {
				if (res && res.success) {
					var msg = Barkoda.i18n.cancelOk;
					if (res.data && res.data.message) msg += ' (' + res.data.message + ')';
					toast(msg, 'success');
					refreshList();
					if (window.location.href.indexOf('action=edit') !== -1 ||
						window.location.href.indexOf('post.php') !== -1) {
						setTimeout(function () { window.location.reload(); }, 800);
					}
				} else {
					var err = (res && res.data && res.data.message) || Barkoda.i18n.unknownErr;
					toast(Barkoda.i18n.cancelErr + err, 'error');
				}
			})
			.fail(function (xhr) {
				var err = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					|| Barkoda.i18n.serverErr;
				toast(Barkoda.i18n.cancelErr + err, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false).text(originalText);
			});
	});

	function openTakipModal(d) {
		$('#wc-ptt-takip-modal').remove();
		var pttSuccess = !!d.success;
		var hasEvents  = d.dongu && d.dongu.length > 0;

		var html = '<div id="wc-ptt-takip-modal" class="wc-ptt-modal">'
			+ '<div class="wc-ptt-modal-overlay"></div>'
			+ '<div class="wc-ptt-modal-box wc-ptt-takip-box">'
			+ '<h2>' + escapeHtml(Barkoda.i18n.trackTitle) + ' — <code>' + escapeHtml(d.barkod || '-') + '</code></h2>';

		if (d.ref_fallback) {
			html += '<div style="padding:6px 10px; background:#fff3cd; border-left:4px solid #856404; border-radius:3px; font-size:11px; margin-bottom:10px;">'
				+ '⚠ ' + escapeHtml(Barkoda.i18n.trackRefFound)
				+ '</div>';
		} else if (d.used_method === 'gonderiSorgu_referansNo') {
			html += '<div style="padding:6px 10px; background:#d1ecf1; border-left:4px solid #0c5460; border-radius:3px; font-size:11px; margin-bottom:10px;">'
				+ 'ℹ ' + escapeHtml(Barkoda.i18n.trackByRef)
				+ '</div>';
		}

		if (!pttSuccess) {
			html += '<div class="wc-ptt-takip-warn is-error">⚠ ' + escapeHtml(d.mesaj || Barkoda.i18n.trackErr) + '</div>';
		} else if (!hasEvents) {
			html += '<div class="wc-ptt-takip-warn is-info">ℹ ' + escapeHtml(d.mesaj || Barkoda.i18n.trackNoEvents) + '</div>';
		} else {
			html += '<div class="wc-ptt-takip-meta">';
			if (d.alici)    html += '<p><strong>' + escapeHtml(Barkoda.i18n.trackRecipient) + '</strong> ' + escapeHtml(d.alici) + '</p>';
			if (d.gonderen) html += '<p><strong>' + escapeHtml(Barkoda.i18n.trackSender) + '</strong> ' + escapeHtml(d.gonderen) + '</p>';
			if (d.mesaj)    html += '<p><strong>' + escapeHtml(Barkoda.i18n.trackStatus) + '</strong> ' + escapeHtml(d.mesaj) + '</p>';
			html += '</div>';
			html += '<h3>' + escapeHtml(Barkoda.i18n.trackEvents) + '</h3>';
			html += '<table class="wc-ptt-takip-events"><thead><tr><th>' + escapeHtml(Barkoda.i18n.trackColDate) + '</th><th>' + escapeHtml(Barkoda.i18n.trackColAction) + '</th><th>' + escapeHtml(Barkoda.i18n.trackColCenter) + '</th></tr></thead><tbody>';
			d.dongu.forEach(function (s) {
				var tarih = (s.ITARIH || '') + (s.ISAAT ? ' ' + s.ISAAT : '');
				html += '<tr>'
					+ '<td>' + escapeHtml(tarih) + '</td>'
					+ '<td>' + escapeHtml(s.ISLEM || '') + '</td>'
					+ '<td>' + escapeHtml(s.IMERK || '') + '</td>'
					+ '</tr>';
			});
			html += '</tbody></table>';
		}

		// Drop point info
		if (d.drop_point && d.drop_point.success) {
			var dp = d.drop_point;
			html += '<h3 style="margin-top:18px;">📍 ' + escapeHtml(Barkoda.i18n.dropPointTitle) + '</h3>';
			html += '<div class="wc-ptt-drop-point">';
			if (dp.dropPointName)        html += '<p><strong>' + escapeHtml(dp.dropPointName) + '</strong>';
			if (dp.dropPointCode)        html += ' <code>(' + escapeHtml(dp.dropPointCode) + ')</code>';
			html += '</p>';
			if (dp.dropPointFullAddress) html += '<p>' + escapeHtml(dp.dropPointFullAddress) + '</p>';
			if (dp.dropPointProvince)    html += '<p>' + escapeHtml(dp.dropPointProvince) + ' · ' + escapeHtml(dp.dropPointZipCode || '') + '</p>';
			if (dp.dropPointPhoneNumber) html += '<p>📞 ' + escapeHtml(dp.dropPointPhoneNumber) + '</p>';
			if (dp.dropPointEmail)       html += '<p>✉ ' + escapeHtml(dp.dropPointEmail) + '</p>';
			if (dp.dropPointWorkHours)   html += '<p>🕒 ' + escapeHtml(dp.dropPointWorkHours) + '</p>';
			if (dp.dropPointDeadLine)    html += '<p><strong>' + escapeHtml(Barkoda.i18n.dropDeadline) + '</strong> ' + escapeHtml(dp.dropPointDeadLine) + '</p>';
			if (dp.dropPointLatitude && dp.dropPointLongitude) {
				html += '<p><a href="https://www.google.com/maps/search/?api=1&query='
					+ encodeURIComponent(dp.dropPointLatitude + ',' + dp.dropPointLongitude)
					+ '" target="_blank">📍 ' + escapeHtml(Barkoda.i18n.showOnMap) + '</a></p>';
			}
			html += '</div>';
		} else if (d.drop_point_error) {
			html += '<p style="margin-top:14px; font-size:11px; color:#646970;">'
				+ '<em>' + escapeHtml(Barkoda.i18n.dropPointErr) + escapeHtml(d.drop_point_error) + '</em></p>';
		}

		if (d.raw) {
			html += '<details class="raw-toggle" style="margin-top:14px;"><summary>' + escapeHtml(Barkoda.i18n.rawResponse) + '</summary>'
				+ '<pre class="raw-dump">' + escapeHtml(d.raw) + '</pre></details>';
		}
		if (d.drop_point && d.drop_point.raw) {
			html += '<details class="raw-toggle" style="margin-top:6px;"><summary>' + escapeHtml(Barkoda.i18n.rawDropPoint) + '</summary>'
				+ '<pre class="raw-dump">' + escapeHtml(d.drop_point.raw) + '</pre></details>';
		}

		html += '<div class="wc-ptt-modal-actions">'
			+ '<button type="button" class="button" data-action="cancel">' + escapeHtml(Barkoda.i18n.close) + '</button>'
			+ '</div></div></div>';

		$('body').append(html);
	}

	// Courier pickup: package count + waybill number. 1 keeps the single-package flow.

	$(document).on('click', '#wc-ptt-courier-submit', function (e) {
		e.preventDefault();
		var $btn   = $(this);
		var $form  = $('#wc-ptt-courier-form');
		var $out   = $('#wc-ptt-courier-result');
		var origText = $btn.html();

		var data = { action: Barkoda.actions.courier, nonce: Barkoda.nonce };
		$form.find('input').each(function () {
			var name = $(this).attr('name');
			if (!name) return;
			data[name] = ($(this).val() || '').trim();
		});

		if (!data.adet || parseInt(data.adet, 10) <= 0) {
			$out.removeClass('is-success').addClass('is-error').show()
				.text(Barkoda.i18n.badPackageCount);
			return;
		}

		$btn.prop('disabled', true).text(Barkoda.i18n.courierSending);
		$out.removeClass('is-success is-error').show().text(Barkoda.i18n.courierSending);

		$.post(Barkoda.ajaxUrl, data)
			.done(function (res) {
				if (res && res.success) {
					var msg = Barkoda.i18n.courierOk;
					if (res.data && res.data.message) msg += ' — ' + res.data.message;
					if (res.data && res.data.siparis_id) msg += ' (' + Barkoda.i18n.courierOrderId + res.data.siparis_id + ')';
					$out.removeClass('is-error').addClass('is-success').text(msg);
				} else {
					var err = (res && res.data && res.data.message) || Barkoda.i18n.unknownErr;
					$out.removeClass('is-success').addClass('is-error').text(Barkoda.i18n.courierErr + err);
				}
			})
			.fail(function (xhr) {
				var err = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || Barkoda.i18n.serverErr;
				$out.removeClass('is-success').addClass('is-error').text(Barkoda.i18n.courierErr + err);
			})
			.always(function () {
				$btn.prop('disabled', false).html(origText);
			});
	});

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	$(document).on('click', '#wc-ptt-takip-modal .wc-ptt-modal-overlay, #wc-ptt-takip-modal [data-action="cancel"]', function () {
		$('#wc-ptt-takip-modal').remove();
	});

	$(document).on('click', '.wc-ptt-modal-overlay, [data-action="cancel"]', function () {
		closeModal();
	});

	$(document).on('click', '[data-action="confirm"]', function (e) {
		e.preventDefault();
		sendCurrent();
	});

})(jQuery);

/* Logs page: clear every stored request/response record. */
(function () {
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('#wc-ptt-clear-logs') : null;
		if (!btn) return;
		if (!window.confirm(Barkoda.i18n.clearLogsAsk)) return;
		var fd = new FormData();
		fd.append('action', Barkoda.actions.clearLogs);
		fd.append('nonce', btn.dataset.nonce);
		fetch(Barkoda.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function () { location.reload(); });
	});
})();
