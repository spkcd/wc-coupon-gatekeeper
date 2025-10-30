/**
 * Admin JavaScript for WC Coupon Gatekeeper.
 *
 * @package WC_Coupon_Gatekeeper
 */

(function($) {
	'use strict';

	/**
	 * Handle purge logs button click.
	 */
	$('#wcgk-purge-logs-btn').on('click', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $result = $('#wcgk-purge-logs-result');

		// Confirm action.
		if (!confirm(wcgkAdmin.i18n.confirm_purge)) {
			return;
		}

		// Disable button and show loading state.
		$button.prop('disabled', true).text(wcgkAdmin.i18n.purging);
		$result.html('');

		// Send AJAX request.
		$.ajax({
			url: wcgkAdmin.ajax_url,
			type: 'POST',
			data: {
				action: 'wcgk_purge_old_logs',
				nonce: wcgkAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html(
						'<div class="notice notice-success inline"><p>' +
						response.data.message +
						'</p></div>'
					);
				} else {
					$result.html(
						'<div class="notice notice-error inline"><p>' +
						(response.data.message || wcgkAdmin.i18n.purge_error) +
						'</p></div>'
					);
				}
			},
			error: function() {
				$result.html(
					'<div class="notice notice-error inline"><p>' +
					wcgkAdmin.i18n.purge_error +
					'</p></div>'
				);
			},
			complete: function() {
				// Re-enable button.
				$button.prop('disabled', false).text(wcgkAdmin.i18n.purge_button || 'Purge Old Logs Now');
			}
		});
	});

	/**
	 * Show/hide coupon list based on "Apply to ALL coupons" checkbox.
	 */
	$('#wcgk_apply_to_all_coupons').on('change', function() {
		var $textarea = $('#wcgk_restricted_coupons').closest('tr');
		if ($(this).is(':checked')) {
			$textarea.fadeTo(200, 0.4);
		} else {
			$textarea.fadeTo(200, 1);
		}
	}).trigger('change');

})(jQuery);