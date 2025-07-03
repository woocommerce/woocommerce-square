/**
 * WooCommerce Square Order Sync JavaScript.
 *
 * Handles admin interface interactions for order synchronization.
 */

(function($) {
	'use strict';

	/**
	 * Order Sync Admin Interface.
	 */
	var WCSquareOrderSync = {

		/**
		 * Initialize the order sync interface.
		 */
		init: function() {
			this.bindEvents();
			this.initializeTooltips();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Manual sync button clicks.
			$(document).on('click', '.wc-square-sync-order', this.handleManualSync);
			
			// Order source filter changes.
			$(document).on('change', '.wc-square-order-source-filter select', this.handleSourceFilter);
			
			// Bulk actions for order sync.
			$(document).on('change', '#bulk-action-selector-top', this.handleBulkActionChange);
			$(document).on('click', '#doaction, #doaction2', this.handleBulkAction);
			
			// Order source action clicks.
			$(document).on('click', '.square-order', this.handleSquareOrderAction);
		},

		/**
		 * Handle manual sync button click.
		 *
		 * @param {Event} e Click event.
		 */
		handleManualSync: function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var orderId = $button.data('order-id');
			
			if (!orderId) {
				return;
			}
			
			// Confirm sync action.
			if (!confirm(wcSquareOrderSync.i18n.confirmSync)) {
				return;
			}
			
			// Disable button and show loading state.
			$button.prop('disabled', true).text(wcSquareOrderSync.i18n.syncing || 'Syncing...');
			
			// Perform sync via AJAX.
			$.ajax({
				url: wcSquareOrderSync.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_square_manual_sync_order',
					order_id: orderId,
					nonce: wcSquareOrderSync.nonce
				},
				success: function(response) {
					if (response.success) {
						WCSquareOrderSync.showNotice(wcSquareOrderSync.i18n.syncSuccess, 'success');
						$button.text(wcSquareOrderSync.i18n.synced || 'Synced').addClass('synced');
					} else {
						WCSquareOrderSync.showNotice(response.data || wcSquareOrderSync.i18n.syncError, 'error');
						$button.prop('disabled', false).text(wcSquareOrderSync.i18n.sync || 'Sync to Square');
					}
				},
				error: function() {
					WCSquareOrderSync.showNotice(wcSquareOrderSync.i18n.syncError, 'error');
					$button.prop('disabled', false).text(wcSquareOrderSync.i18n.sync || 'Sync to Square');
				}
			});
		},

		/**
		 * Handle order source filter change.
		 *
		 * @param {Event} e Change event.
		 */
		handleSourceFilter: function(e) {
			var source = $(this).val();
			var $rows = $('.wp-list-table tbody tr');
			
			if (source === '') {
				$rows.show();
				return;
			}
			
			$rows.each(function() {
				var $row = $(this);
				var $sourceCell = $row.find('.column-order_source .wc-square-order-source');
				
				if ($sourceCell.length) {
					var rowSource = $sourceCell.hasClass('square') ? 'square' : 'woo';
					if (rowSource === source) {
						$row.show();
					} else {
						$row.hide();
					}
				}
			});
		},

		/**
		 * Handle bulk action selector change.
		 *
		 * @param {Event} e Change event.
		 */
		handleBulkActionChange: function(e) {
			var action = $(this).val();
			
			if (action === 'sync_to_square') {
				$('.bulk-edit-order-source').addClass('show');
			} else {
				$('.bulk-edit-order-source').removeClass('show');
			}
		},

		/**
		 * Handle bulk action execution.
		 *
		 * @param {Event} e Click event.
		 */
		handleBulkAction: function(e) {
			var action = $('#bulk-action-selector-top').val();
			
			if (action === 'sync_to_square') {
				e.preventDefault();
				
				var selectedOrders = $('.wp-list-table input[name="post[]"]:checked').map(function() {
					return $(this).val();
				}).get();
				
				if (selectedOrders.length === 0) {
					WCSquareOrderSync.showNotice('Please select orders to sync.', 'error');
					return;
				}
				
				if (confirm('Are you sure you want to sync ' + selectedOrders.length + ' orders to Square?')) {
					WCSquareOrderSync.bulkSyncOrders(selectedOrders);
				}
			}
		},

		/**
		 * Perform bulk sync of orders.
		 *
		 * @param {Array} orderIds Array of order IDs.
		 */
		bulkSyncOrders: function(orderIds) {
			var $progress = $('<div class="wc-square-bulk-sync-progress"><div class="progress-bar"><div class="progress-fill"></div></div><div class="progress-text">Syncing orders...</div></div>');
			$('body').append($progress);
			
			var total = orderIds.length;
			var completed = 0;
			var failed = 0;
			
			function syncNext() {
				if (orderIds.length === 0) {
					// All done.
					$progress.remove();
					var message = 'Bulk sync completed. ' + completed + ' orders synced successfully.';
					if (failed > 0) {
						message += ' ' + failed + ' orders failed.';
					}
					WCSquareOrderSync.showNotice(message, failed > 0 ? 'error' : 'success');
					return;
				}
				
				var orderId = orderIds.shift();
				
				$.ajax({
					url: wcSquareOrderSync.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wc_square_manual_sync_order',
						order_id: orderId,
						nonce: wcSquareOrderSync.nonce
					},
					success: function(response) {
						if (response.success) {
							completed++;
						} else {
							failed++;
						}
						
						// Update progress.
						var progress = ((total - orderIds.length) / total) * 100;
						$progress.find('.progress-fill').css('width', progress + '%');
						$progress.find('.progress-text').text('Syncing orders... ' + (total - orderIds.length) + '/' + total);
						
						// Continue with next order.
						setTimeout(syncNext, 500);
					},
					error: function() {
						failed++;
						setTimeout(syncNext, 500);
					}
				});
			}
			
			syncNext();
		},

		/**
		 * Handle Square order action click.
		 *
		 * @param {Event} e Click event.
		 */
		handleSquareOrderAction: function(e) {
			e.preventDefault();
			
			var squareOrderId = $(this).data('square-order-id');
			if (!squareOrderId) {
				return;
			}
			
			// Open Square dashboard in new window.
			var squareUrl = 'https://squareup.com/dashboard/orders/' + squareOrderId;
			window.open(squareUrl, '_blank');
		},

		/**
		 * Initialize tooltips for order source indicators.
		 */
		initializeTooltips: function() {
			$('.wc-square-order-source[title]').each(function() {
				var $element = $(this);
				var title = $element.attr('title');
				
				$element.removeAttr('title').attr('data-tooltip', title);
			});
		},

		/**
		 * Show admin notice.
		 *
		 * @param {string} message Notice message.
		 * @param {string} type Notice type (success, error, warning).
		 */
		showNotice: function(message, type) {
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			
			// Remove existing notices of the same type.
			$('.notice-' + type).remove();
			
			// Add new notice.
			$('#wpbody-content').prepend($notice);
			
			// Auto-dismiss after 5 seconds.
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Refresh order list table.
		 */
		refreshOrderList: function() {
			location.reload();
		},

		/**
		 * Get sync statistics.
		 */
		getSyncStats: function() {
			$.ajax({
				url: wcSquareOrderSync.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_square_get_sync_stats',
					nonce: wcSquareOrderSync.nonce
				},
				success: function(response) {
					if (response.success) {
						WCSquareOrderSync.updateStatsDisplay(response.data);
					}
				}
			});
		},

		/**
		 * Update statistics display.
		 *
		 * @param {Object} stats Sync statistics.
		 */
		updateStatsDisplay: function(stats) {
			$('.wc-square-sync-stats .total-square-orders').text(stats.total_square_orders);
			$('.wc-square-sync-stats .total-woo-orders').text(stats.total_woo_orders);
			$('.wc-square-sync-stats .sync-errors').text(stats.sync_errors);
			
			if (stats.last_sync) {
				$('.wc-square-sync-stats .last-sync').text(stats.last_sync);
			}
		}
	};

	// Initialize when document is ready.
	$(document).ready(function() {
		WCSquareOrderSync.init();
	});

	// Make available globally.
	window.WCSquareOrderSync = WCSquareOrderSync;

})(jQuery);
