<?php

namespace OrderDesk;

use WpApp\BaseApp;
use WpApp\WpApp;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class App extends BaseApp {
	const REST_NAMESPACE = 'order-desk/v1';

	/**
	 * Order statuses (unprefixed) shown in the "To fulfill" tab.
	 */
	const TO_FULFILL_STATUSES = [ 'processing' ];

	/**
	 * The single status transition this app allows staff to make.
	 */
	const COMPLETE_STATUS = 'completed';

	public function __construct() {
		$this->app = new WpApp(
			$this->get_template_dir(),
			$this->get_url_path(),
			[
				'app_name'           => $this->get_plugin_name(),
				'app_name_textdomain' => 'order-desk',
				'require_capability' => 'manage_woocommerce',
				'my_apps'            => $this->get_plugin_name(),
			]
		);

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	protected function get_url_path(): string {
		return 'order-desk';
	}

	protected function get_template_dir(): string {
		return dirname( __DIR__ ) . '/templates';
	}

	protected function get_plugin_name(): string {
		if ( ! function_exists( 'get_file_data' ) ) {
			return 'Order Desk';
		}

		$plugin_data = get_file_data( dirname( __DIR__ ) . '/order-desk.php', [ 'name' => 'Plugin Name' ] );

		return $plugin_data['name'] ?: 'Order Desk';
	}

	protected function setup_database(): void {
		// No storage of our own: WooCommerce orders are the only data source.
	}

	protected function setup_routes(): void {
		$this->app->route( 'order/{id}', 'order.php' );
	}

	protected function setup_menu(): void {
		// The single list view already links to order detail; no extra menu items needed.
	}

	public function activate(): void {
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Get the unprefixed statuses to query for a given tab.
	 *
	 * @param string $tab One of 'processing', 'completed', 'all'.
	 * @return array Unprefixed WooCommerce order status slugs.
	 */
	public static function statuses_for_tab( string $tab ): array {
		if ( 'completed' === $tab ) {
			return [ 'completed' ];
		}

		if ( 'all' === $tab ) {
			return array_map(
				static function ( $status ) {
					return preg_replace( '/^wc-/', '', $status );
				},
				array_keys( wc_get_order_statuses() )
			);
		}

		return self::TO_FULFILL_STATUSES;
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)/complete',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_complete_order' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
					],
				],
			]
		);
	}

	/**
	 * REST callback: mark a "processing" order as "completed".
	 *
	 * This is the app's only write action, matching its single-purpose scope:
	 * fulfilling orders, not editing them.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_complete_order( WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error( 'order_desk_no_woocommerce', __( 'WooCommerce is not active.', 'order-desk' ), [ 'status' => 503 ] );
		}

		$order_id = (int) $request['id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'order_desk_not_found', __( 'Order not found.', 'order-desk' ), [ 'status' => 404 ] );
		}

		if ( 'processing' !== $order->get_status() ) {
			return new WP_Error(
				'order_desk_not_fulfillable',
				__( 'Only orders that are processing can be marked as completed.', 'order-desk' ),
				[ 'status' => 400 ]
			);
		}

		$order->update_status( self::COMPLETE_STATUS, __( 'Marked as fulfilled via Order Desk.', 'order-desk' ) );

		return rest_ensure_response(
			[
				'id'           => $order->get_id(),
				'status'       => $order->get_status(),
				'status_label' => wc_get_order_status_name( 'wc-' . $order->get_status() ),
			]
		);
	}
}
