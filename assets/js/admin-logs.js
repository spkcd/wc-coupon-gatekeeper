/**
 * Admin Logs Screen JavaScript
 * WC Coupon Gatekeeper
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin logs functionality.
	 */
	function init() {
		// View customer history
		$('.wcgk-view-history').on('click', function(e) {
			e.preventDefault();
			
			const couponCode = $(this).data('coupon');
			const customerKey = $(this).data('customer');
			
			viewCustomerHistory(couponCode, customerKey);
		});

		// Reset single usage count
		$('.wcgk-reset-usage').on('click', function(e) {
			e.preventDefault();
			
			if (!confirm(wcgkLogs.confirmReset)) {
				return;
			}
			
			const id = $(this).data('id');
			resetUsage([id]);
		});

		// Bulk reset action
		$('#doaction, #doaction2').on('click', function(e) {
			const action = $(this).siblings('select').val();
			
			if (action !== 'bulk_reset') {
				return true;
			}
			
			e.preventDefault();
			
			if (!confirm(wcgkLogs.confirmBulkReset)) {
				return false;
			}
			
			const ids = [];
			$('input[name="ids[]"]:checked').each(function() {
				ids.push($(this).val());
			});
			
			if (ids.length === 0) {
				alert('Please select at least one record.');
				return false;
			}
			
			resetUsage(ids);
			return false;
		});

		// Close modal
		$('.wcgk-modal-close, .wcgk-modal-overlay').on('click', function() {
			$('#wcgk-history-modal').hide();
		});

		// Close modal on Escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#wcgk-history-modal').is(':visible')) {
				$('#wcgk-history-modal').hide();
			}
		});
	}

	/**
	 * View customer history via AJAX.
	 *
	 * @param {string} couponCode  Coupon code.
	 * @param {string} customerKey Customer key.
	 */
	function viewCustomerHistory(couponCode, customerKey) {
		// Show modal with loading state
		$('#wcgk-history-details').html('<p class="wcgk-loading">Loading history</p>');
		$('#wcgk-history-table').html('');
		$('#wcgk-history-modal').show();

		// Make AJAX request
		$.ajax({
			url: wcgkLogs.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wcgk_view_customer_history',
				nonce: wcgkLogs.nonce,
				coupon_code: couponCode,
				customer_key: customerKey
			},
			success: function(response) {
				if (response.success) {
					displayHistory(response.data);
				} else {
					showError(response.data.message || 'Failed to load history.');
				}
			},
			error: function() {
				showError('Network error. Please try again.');
			}
		});
	}

	/**
	 * Display customer history in modal.
	 *
	 * @param {Object} data History data.
	 */
	function displayHistory(data) {
		// Update details section
		const detailsHtml = `
			<p><strong>Coupon Code:</strong> ${escapeHtml(data.coupon_code.toUpperCase())}</p>
			<p><strong>Customer:</strong> <code>${escapeHtml(data.customer_key)}</code></p>
		`;
		$('#wcgk-history-details').html(detailsHtml);

		// Build history table
		if (data.history.length === 0) {
			$('#wcgk-history-table').html('<div class="no-history">No usage history found.</div>');
			return;
		}

		let tableHtml = '<table><thead><tr>';
		tableHtml += '<th>Month</th>';
		tableHtml += '<th>Count</th>';
		tableHtml += '<th>Last Order</th>';
		tableHtml += '<th>Updated At</th>';
		tableHtml += '</tr></thead><tbody>';

		data.history.forEach(function(row) {
			tableHtml += '<tr>';
			tableHtml += '<td><strong>' + escapeHtml(row.month) + '</strong></td>';
			tableHtml += '<td>' + escapeHtml(row.count) + '</td>';
			
			if (row.last_order_id) {
				const orderUrl = wcgkLogs.ajaxUrl.replace('admin-ajax.php', 'post.php?post=' + row.last_order_id + '&action=edit');
				tableHtml += '<td><a href="' + orderUrl + '" target="_blank">#' + row.last_order_id + '</a></td>';
			} else {
				tableHtml += '<td>—</td>';
			}
			
			tableHtml += '<td>' + escapeHtml(row.updated_at) + '</td>';
			tableHtml += '</tr>';
		});

		tableHtml += '</tbody></table>';
		$('#wcgk-history-table').html(tableHtml);
	}

	/**
	 * Reset usage counts via AJAX.
	 *
	 * @param {Array} ids Record IDs to reset.
	 */
	function resetUsage(ids) {
		// Show loading indicator
		const loadingNotice = $('<div class="notice notice-info"><p>Resetting usage counts...</p></div>');
		$('.wrap h1').after(loadingNotice);

		// Make AJAX request
		$.ajax({
			url: wcgkLogs.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wcgk_reset_usage',
				nonce: wcgkLogs.nonce,
				ids: ids
			},
			success: function(response) {
				loadingNotice.remove();
				
				if (response.success) {
					showSuccess(response.data.message);
					// Reload page after 1 second
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					showError(response.data.message || 'Failed to reset usage counts.');
				}
			},
			error: function() {
				loadingNotice.remove();
				showError('Network error. Please try again.');
			}
		});
	}

	/**
	 * Show success message.
	 *
	 * @param {string} message Message text.
	 */
	function showSuccess(message) {
		const notice = $('<div class="notice notice-success is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
		$('.wrap h1').after(notice);
		
		// Auto-dismiss after 3 seconds
		setTimeout(function() {
			notice.fadeOut(function() {
				$(this).remove();
			});
		}, 3000);
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message Error message.
	 */
	function showError(message) {
		const notice = $('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
		
		if ($('#wcgk-history-modal').is(':visible')) {
			$('#wcgk-history-details').html('<p style="color: #dc3232;">' + escapeHtml(message) + '</p>');
		} else {
			$('.wrap h1').after(notice);
		}
	}

	/**
	 * Escape HTML to prevent XSS.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);