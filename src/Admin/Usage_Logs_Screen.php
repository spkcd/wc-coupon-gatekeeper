<?php
/**
 * Admin usage logs screen for WC Coupon Gatekeeper.
 *
 * Provides a comprehensive interface for viewing and managing coupon usage logs
 * with filtering, pagination, bulk actions, and export capabilities.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper\Admin;

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Usage_Logs_Screen
 *
 * Displays usage logs in the WordPress admin with filtering, actions, and export.
 */
class Usage_Logs_Screen {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * List table instance.
	 *
	 * @var Usage_Logs_List_Table
	 */
	private $list_table;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_item' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_wcgk_view_customer_history', array( $this, 'ajax_view_customer_history' ) );
		add_action( 'wp_ajax_wcgk_reset_usage', array( $this, 'ajax_reset_usage' ) );
	}

	/**
	 * Add menu item under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_item() {
		add_submenu_page(
			'woocommerce',
			__( 'Coupon Gatekeeper Logs', 'wc-coupon-gatekeeper' ),
			__( 'Gatekeeper Logs', 'wc-coupon-gatekeeper' ),
			'manage_woocommerce',
			'wc-coupon-gatekeeper-logs',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our page.
		if ( 'woocommerce_page_wc-coupon-gatekeeper-logs' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-coupon-gatekeeper-logs',
			plugins_url( 'assets/css/admin-logs.css', dirname( __DIR__, 2 ) . '/wc-coupon-gatekeeper.php' ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'wc-coupon-gatekeeper-logs',
			plugins_url( 'assets/js/admin-logs.js', dirname( __DIR__, 2 ) . '/wc-coupon-gatekeeper.php' ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'wc-coupon-gatekeeper-logs',
			'wcgkLogs',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wcgk_logs_action' ),
				'confirmReset'     => __( 'Are you sure you want to reset this usage count? This cannot be undone.', 'wc-coupon-gatekeeper' ),
				'confirmBulkReset' => __( 'Are you sure you want to reset the selected usage counts? This cannot be undone.', 'wc-coupon-gatekeeper' ),
				'confirmPurge'     => __( 'Are you sure you want to purge old logs? This will permanently delete records older than the configured retention period.', 'wc-coupon-gatekeeper' ),
			)
		);
	}

	/**
	 * Handle admin actions (export, purge).
	 *
	 * @return void
	 */
	public function handle_actions() {
		// Check if we're on our page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'wc-coupon-gatekeeper-logs' !== $_GET['page'] ) {
			return;
		}

		// Handle export.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'export_csv' === $_GET['action'] ) {
			$this->handle_export_csv();
			exit;
		}

		// Handle purge.
		if ( isset( $_POST['action'] ) && 'purge_old_logs' === $_POST['action'] ) {
			$this->handle_purge_old_logs();
		}
	}

	/**
	 * Handle export CSV action.
	 *
	 * @return void
	 */
	private function handle_export_csv() {
		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wcgk_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wc-coupon-gatekeeper' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export logs.', 'wc-coupon-gatekeeper' ) );
		}

		// Get filtered data.
		$filters = $this->get_current_filters();
		$data    = $this->get_logs_data( $filters, 0, 10000 ); // Export max 10k rows.

		// Generate CSV.
		$filename = 'coupon-usage-logs-' . gmdate( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Headers.
		fputcsv( $output, array( 'Coupon Code', 'Month', 'Customer Key', 'Count', 'Last Order ID', 'Updated At' ) );

		// Data rows.
		foreach ( $data as $row ) {
			fputcsv(
				$output,
				array(
					$row->coupon_code,
					$row->month,
					$row->customer_key,
					$row->count,
					$row->last_order_id,
					$row->updated_at,
				)
			);
		}

		fclose( $output );
	}

	/**
	 * Handle purge old logs action.
	 *
	 * @return void
	 */
	private function handle_purge_old_logs() {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wcgk_purge_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wc-coupon-gatekeeper' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to purge logs.', 'wc-coupon-gatekeeper' ) );
		}

		// Get retention months from settings.
		$retention_months = $this->settings->get( 'data_retention_months', 24 );

		// Purge old records.
		$deleted = Database::cleanup_old_records( $retention_months );

		// Redirect with success message.
		$redirect_url = add_query_arg(
			array(
				'page'    => 'wc-coupon-gatekeeper-logs',
				'purged'  => $deleted,
				'_wpnonce' => wp_create_nonce( 'wcgk_purge_success' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for viewing customer history.
	 *
	 * @return void
	 */
	public function ajax_view_customer_history() {
		// Verify nonce.
		check_ajax_referer( 'wcgk_logs_action', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-coupon-gatekeeper' ) ) );
		}

		// Get parameters.
		$coupon_code  = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$customer_key = isset( $_POST['customer_key'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_key'] ) ) : '';

		if ( empty( $coupon_code ) || empty( $customer_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wc-coupon-gatekeeper' ) ) );
		}

		// Get 12-month history.
		$history = $this->get_customer_history( $coupon_code, $customer_key, 12 );

		// Format for display.
		$formatted = array();
		foreach ( $history as $row ) {
			$formatted[] = array(
				'month'         => $row->month,
				'count'         => $row->count,
				'last_order_id' => $row->last_order_id,
				'updated_at'    => $row->updated_at,
			);
		}

		wp_send_json_success(
			array(
				'history'      => $formatted,
				'coupon_code'  => $coupon_code,
				'customer_key' => $this->mask_customer_key( $customer_key ),
			)
		);
	}

	/**
	 * AJAX handler for resetting usage count.
	 *
	 * @return void
	 */
	public function ajax_reset_usage() {
		// Verify nonce.
		check_ajax_referer( 'wcgk_logs_action', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-coupon-gatekeeper' ) ) );
		}

		// Get ID(s).
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No records selected.', 'wc-coupon-gatekeeper' ) ) );
		}

		// Reset usage counts.
		$reset_count = $this->reset_usage_by_ids( $ids );

		wp_send_json_success(
			array(
				'message' => sprintf(
					// translators: %d: number of records reset.
					_n( '%d usage record reset.', '%d usage records reset.', $reset_count, 'wc-coupon-gatekeeper' ),
					$reset_count
				),
			)
		);
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render_page() {
		// Verify user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-coupon-gatekeeper' ) );
		}

		// Show success messages.
		$this->show_admin_notices();

		// Create list table instance.
		$this->list_table = new Usage_Logs_List_Table( $this );

		// Prepare items.
		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Coupon Gatekeeper Logs', 'wc-coupon-gatekeeper' ); ?></h1>
			
			<?php $this->render_tools_section(); ?>

			<form method="get" id="wcgk-logs-filter">
				<input type="hidden" name="page" value="wc-coupon-gatekeeper-logs" />
				<?php
				$this->list_table->search_box( __( 'Search', 'wc-coupon-gatekeeper' ), 'wcgk-logs' );
				$this->list_table->display();
				?>
			</form>
		</div>

		<!-- Customer History Modal -->
		<div id="wcgk-history-modal" style="display:none;">
			<div class="wcgk-modal-overlay"></div>
			<div class="wcgk-modal-content">
				<span class="wcgk-modal-close">&times;</span>
				<h2><?php esc_html_e( '12-Month Usage History', 'wc-coupon-gatekeeper' ); ?></h2>
				<div id="wcgk-history-details"></div>
				<div id="wcgk-history-table"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show admin notices.
	 *
	 * @return void
	 */
	private function show_admin_notices() {
		// Check for purge success.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['purged'] ) && isset( $_GET['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wcgk_purge_success' ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$deleted = absint( $_GET['purged'] );
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							// translators: %d: number of records deleted.
							esc_html( _n( '%d old usage record deleted.', '%d old usage records deleted.', $deleted, 'wc-coupon-gatekeeper' ) ),
							esc_html( $deleted )
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Render tools section.
	 *
	 * @return void
	 */
	private function render_tools_section() {
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'wc-coupon-gatekeeper-logs',
					'action' => 'export_csv',
				),
				admin_url( 'admin.php' )
			),
			'wcgk_export_csv'
		);

		// Add current filters to export URL.
		$filters = $this->get_current_filters();
		foreach ( $filters as $key => $value ) {
			if ( ! empty( $value ) ) {
				$export_url = add_query_arg( $key, rawurlencode( $value ), $export_url );
			}
		}

		$retention_months = $this->settings->get( 'data_retention_months', 24 );
		?>
		<div class="wcgk-tools-section">
			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export Current View as CSV', 'wc-coupon-gatekeeper' ); ?>
			</a>

			<form method="post" style="display:inline;" onsubmit="return confirm(wcgkLogs.confirmPurge);">
				<?php wp_nonce_field( 'wcgk_purge_logs' ); ?>
				<input type="hidden" name="action" value="purge_old_logs" />
				<button type="submit" class="button button-secondary">
					<span class="dashicons dashicons-trash"></span>
					<?php
					printf(
						// translators: %d: number of months.
						esc_html__( 'Purge Logs Older Than %d Months', 'wc-coupon-gatekeeper' ),
						esc_html( $retention_months )
					);
					?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Get current filters from request.
	 *
	 * @return array Filters array.
	 */
	public function get_current_filters() {
		$filters = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['filter_month'] ) ) {
			$filters['month'] = sanitize_text_field( wp_unslash( $_GET['filter_month'] ) );
		}

		if ( ! empty( $_GET['filter_coupon'] ) ) {
			$filters['coupon'] = sanitize_text_field( wp_unslash( $_GET['filter_coupon'] ) );
		}

		if ( ! empty( $_GET['filter_customer'] ) ) {
			$filters['customer'] = sanitize_text_field( wp_unslash( $_GET['filter_customer'] ) );
		}

		if ( isset( $_GET['filter_min_count'] ) && '' !== $_GET['filter_min_count'] ) {
			$filters['min_count'] = absint( $_GET['filter_min_count'] );
		}

		if ( isset( $_GET['filter_max_count'] ) && '' !== $_GET['filter_max_count'] ) {
			$filters['max_count'] = absint( $_GET['filter_max_count'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $filters;
	}

	/**
	 * Get logs data with filters and pagination.
	 *
	 * @param array $filters Filters array.
	 * @param int   $offset  SQL offset.
	 * @param int   $limit   Number of records to return.
	 * @return array Log records.
	 */
	public function get_logs_data( $filters = array(), $offset = 0, $limit = 20 ) {
		global $wpdb;
		$table_name = Database::get_table_name();

		// Build WHERE clause.
		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( ! empty( $filters['month'] ) ) {
			$where_clauses[] = 'month = %s';
			$where_values[]  = $filters['month'];
		}

		if ( ! empty( $filters['coupon'] ) ) {
			$where_clauses[] = 'coupon_code LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( strtolower( $filters['coupon'] ) ) . '%';
		}

		if ( ! empty( $filters['customer'] ) ) {
			$where_clauses[] = 'customer_key LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( $filters['customer'] ) . '%';
		}

		if ( isset( $filters['min_count'] ) ) {
			$where_clauses[] = 'count >= %d';
			$where_values[]  = $filters['min_count'];
		}

		if ( isset( $filters['max_count'] ) ) {
			$where_clauses[] = 'count <= %d';
			$where_values[]  = $filters['max_count'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Build query.
		$query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY updated_at DESC, id DESC";

		// Add pagination.
		if ( $limit > 0 ) {
			$query          .= ' LIMIT %d OFFSET %d';
			$where_values[]  = $limit;
			$where_values[]  = $offset;
		}

		// Execute query.
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $query );
	}

	/**
	 * Get total count of logs with filters.
	 *
	 * @param array $filters Filters array.
	 * @return int Total count.
	 */
	public function get_logs_count( $filters = array() ) {
		global $wpdb;
		$table_name = Database::get_table_name();

		// Build WHERE clause.
		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( ! empty( $filters['month'] ) ) {
			$where_clauses[] = 'month = %s';
			$where_values[]  = $filters['month'];
		}

		if ( ! empty( $filters['coupon'] ) ) {
			$where_clauses[] = 'coupon_code LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( strtolower( $filters['coupon'] ) ) . '%';
		}

		if ( ! empty( $filters['customer'] ) ) {
			$where_clauses[] = 'customer_key LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( $filters['customer'] ) . '%';
		}

		if ( isset( $filters['min_count'] ) ) {
			$where_clauses[] = 'count >= %d';
			$where_values[]  = $filters['min_count'];
		}

		if ( isset( $filters['max_count'] ) ) {
			$where_clauses[] = 'count <= %d';
			$where_values[]  = $filters['max_count'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Build query.
		$query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";

		// Execute query.
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get customer history for past N months.
	 *
	 * @param string $coupon_code  Coupon code.
	 * @param string $customer_key Customer identifier.
	 * @param int    $months       Number of months to retrieve.
	 * @return array History records.
	 */
	private function get_customer_history( $coupon_code, $customer_key, $months = 12 ) {
		global $wpdb;
		$table_name = Database::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE coupon_code = %s 
				AND customer_key = %s 
				ORDER BY month DESC 
				LIMIT %d",
				$coupon_code,
				$customer_key,
				$months
			)
		);
	}

	/**
	 * Reset usage counts by record IDs.
	 *
	 * @param array $ids Record IDs.
	 * @return int Number of records reset.
	 */
	private function reset_usage_by_ids( $ids ) {
		global $wpdb;
		$table_name = Database::get_table_name();

		if ( empty( $ids ) ) {
			return 0;
		}

		// Sanitize IDs.
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return 0;
		}

		$ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table_name} SET count = 0, updated_at = NOW() WHERE id IN ({$ids_placeholder})",
				$ids
			)
		);
	}

	/**
	 * Mask customer key for display.
	 *
	 * @param string $customer_key Customer key.
	 * @return string Masked key.
	 */
	public function mask_customer_key( $customer_key ) {
		// If starts with "email:" and looks like hash, mask it.
		if ( preg_match( '/^email:([a-f0-9]{64})$/', $customer_key ) ) {
			return 'email:' . substr( $customer_key, 6, 8 ) . '...';
		}

		return $customer_key;
	}

	/**
	 * Get available months for filter dropdown (last 24 months).
	 *
	 * @return array Array of month strings (YYYY-MM).
	 */
	public function get_available_months() {
		$months       = array();
		$current_date = new \DateTime( 'now', wp_timezone() );

		for ( $i = 0; $i < 24; $i++ ) {
			$months[] = $current_date->format( 'Y-m' );
			$current_date->modify( '-1 month' );
		}

		return $months;
	}
}

/**
 * Class Usage_Logs_List_Table
 *
 * WP_List_Table implementation for usage logs.
 */
class Usage_Logs_List_Table extends \WP_List_Table {

	/**
	 * Parent screen instance.
	 *
	 * @var Usage_Logs_Screen
	 */
	private $parent_screen;

	/**
	 * Constructor.
	 *
	 * @param Usage_Logs_Screen $parent_screen Parent screen instance.
	 */
	public function __construct( $parent_screen ) {
		$this->parent_screen = $parent_screen;

		parent::__construct(
			array(
				'singular' => 'usage_log',
				'plural'   => 'usage_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array Columns array.
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'coupon_code'  => __( 'Coupon Code', 'wc-coupon-gatekeeper' ),
			'month'        => __( 'Month', 'wc-coupon-gatekeeper' ),
			'customer_key' => __( 'Customer Key', 'wc-coupon-gatekeeper' ),
			'count'        => __( 'Count', 'wc-coupon-gatekeeper' ),
			'last_order'   => __( 'Last Order', 'wc-coupon-gatekeeper' ),
			'updated_at'   => __( 'Updated At', 'wc-coupon-gatekeeper' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array Sortable columns.
	 */
	protected function get_sortable_columns() {
		return array(
			'coupon_code' => array( 'coupon_code', false ),
			'month'       => array( 'month', false ),
			'count'       => array( 'count', false ),
			'updated_at'  => array( 'updated_at', true ), // Default sort.
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array Bulk actions.
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk_reset' => __( 'Reset Selected', 'wc-coupon-gatekeeper' ),
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @param object $item Row data.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', $item->id );
	}

	/**
	 * Render coupon code column.
	 *
	 * @param object $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_coupon_code( $item ) {
		$actions = array(
			'reset'   => sprintf(
				'<a href="#" class="wcgk-reset-usage" data-id="%d">%s</a>',
				$item->id,
				__( 'Reset Count', 'wc-coupon-gatekeeper' )
			),
			'history' => sprintf(
				'<a href="#" class="wcgk-view-history" data-coupon="%s" data-customer="%s">%s</a>',
				esc_attr( $item->coupon_code ),
				esc_attr( $item->customer_key ),
				__( 'View 12-Month History', 'wc-coupon-gatekeeper' )
			),
		);

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( strtoupper( $item->coupon_code ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render month column.
	 *
	 * @param object $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_month( $item ) {
		return esc_html( $item->month );
	}

	/**
	 * Render customer key column.
	 *
	 * @param object $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_customer_key( $item ) {
		$masked_key = $this->parent_screen->mask_customer_key( $item->customer_key );
		return '<code>' . esc_html( $masked_key ) . '</code>';
	}

	/**
	 * Render count column.
	 *
	 * @param object $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_count( $item ) {
		return sprintf( '<strong>%d</strong>', $item->count );
	}

	/**
	 * Render last order column.
	 *
	 * @param object $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_last_order( $item ) {
		if ( empty( $item->last_order_id ) ) {
			return '—';
		}

		$order_url = admin_url( 'post.php?post=' . $item->last_order_id . '&action=edit' );
		return sprintf(
			'<a href="%s" target="_blank">#%d</a>',
			esc_url( $order_url ),
			$item->last_order_id
		);
	}

	/**
	 * Render updated at column.
	 *
	 * @param object $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_updated_at( $item ) {
		$timestamp = strtotime( $item->updated_at );
		$date_time = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		$time_diff = human_time_diff( $timestamp, time() );

		return sprintf(
			'<abbr title="%s">%s ago</abbr>',
			esc_attr( $date_time ),
			esc_html( $time_diff )
		);
	}

	/**
	 * Prepare table items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		// Get filters.
		$filters = $this->parent_screen->get_current_filters();

		// Get pagination.
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Get data.
		$this->items = $this->parent_screen->get_logs_data( $filters, $offset, $per_page );
		$total_items = $this->parent_screen->get_logs_count( $filters );

		// Set pagination.
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		// Set columns.
		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Display filters above table.
	 *
	 * @param string $which Position (top or bottom).
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$filters = $this->parent_screen->get_current_filters();
		$months  = $this->parent_screen->get_available_months();
		?>
		<div class="alignleft actions">
			<!-- Month Filter -->
			<select name="filter_month">
				<option value=""><?php esc_html_e( 'All Months', 'wc-coupon-gatekeeper' ); ?></option>
				<?php foreach ( $months as $month ) : ?>
					<option value="<?php echo esc_attr( $month ); ?>" <?php selected( isset( $filters['month'] ) ? $filters['month'] : '', $month ); ?>>
						<?php echo esc_html( $month ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- Coupon Code Filter -->
			<input 
				type="text" 
				name="filter_coupon" 
				placeholder="<?php esc_attr_e( 'Coupon code...', 'wc-coupon-gatekeeper' ); ?>" 
				value="<?php echo isset( $filters['coupon'] ) ? esc_attr( $filters['coupon'] ) : ''; ?>"
				style="width: 150px;"
			/>

			<!-- Customer Key Filter -->
			<input 
				type="text" 
				name="filter_customer" 
				placeholder="<?php esc_attr_e( 'Customer key...', 'wc-coupon-gatekeeper' ); ?>" 
				value="<?php echo isset( $filters['customer'] ) ? esc_attr( $filters['customer'] ) : ''; ?>"
				style="width: 150px;"
			/>

			<!-- Min Count -->
			<input 
				type="number" 
				name="filter_min_count" 
				placeholder="<?php esc_attr_e( 'Min count', 'wc-coupon-gatekeeper' ); ?>" 
				value="<?php echo isset( $filters['min_count'] ) ? esc_attr( $filters['min_count'] ) : ''; ?>"
				style="width: 80px;"
				min="0"
			/>

			<!-- Max Count -->
			<input 
				type="number" 
				name="filter_max_count" 
				placeholder="<?php esc_attr_e( 'Max count', 'wc-coupon-gatekeeper' ); ?>" 
				value="<?php echo isset( $filters['max_count'] ) ? esc_attr( $filters['max_count'] ) : ''; ?>"
				style="width: 80px;"
				min="0"
			/>

			<?php submit_button( __( 'Apply Filters', 'wc-coupon-gatekeeper' ), 'action', 'filter_action', false ); ?>
			
			<?php if ( ! empty( array_filter( $filters ) ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-coupon-gatekeeper-logs' ) ); ?>" class="button">
					<?php esc_html_e( 'Clear Filters', 'wc-coupon-gatekeeper' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Process bulk actions.
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		// Handled via AJAX in JavaScript.
	}
}