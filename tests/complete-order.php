<?php
/** Real WordPress/WooCommerce checks: run with wp --user=admin eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    throw new RuntimeException( 'Run through WP-CLI with WordPress and WooCommerce active.' );
}
if ( getenv( 'ORDER_DESK_TEST_DATABASE' ) !== DB_NAME ) {
    throw new RuntimeException( 'Confirm the disposable test database with ORDER_DESK_TEST_DATABASE before creating fixtures.' );
}

$checks = 0;
$assert = static function ( $condition, string $message ) use ( &$checks ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$checks;
};
$admin_id = get_current_user_id();
$assert( current_user_can( 'manage_woocommerce' ), 'The test runner must start as a store manager.' );
$customer_id = wp_insert_user( [
    'user_login' => 'order_desk_ci_customer',
    'user_email' => 'order-desk-customer@example.invalid',
    'user_pass'  => wp_generate_password(),
    'role'      => 'customer',
] );
$assert( ! is_wp_error( $customer_id ), 'The customer fixture must be created.' );
$orders = [];
$request = static function ( int $id ) {
    return rest_do_request( new WP_REST_Request( 'POST', '/order-desk/v1/orders/' . $id . '/complete' ) );
};

try {
    $processing = wc_create_order( [ 'status' => 'processing' ] );
    $assert( $processing instanceof WC_Order, 'The processing order fixture must be created.' );
    $orders[] = $processing;
    $completed = $request( $processing->get_id() );
    $assert( 200 === $completed->get_status(), 'A manager can fulfill a processing order.' );
    $assert( [ 'id' => $processing->get_id(), 'status' => 'completed', 'status_label' => 'Completed' ] === $completed->get_data(), 'The response must describe the completed order exactly.' );
    $assert( 'completed' === wc_get_order( $processing->get_id() )->get_status(), 'Completion must persist, not only change the response.' );

    $protected = wc_create_order( [ 'status' => 'processing' ] );
    $assert( $protected instanceof WC_Order, 'The protected order fixture must be created.' );
    $orders[] = $protected;
    wp_set_current_user( $customer_id );
    $denied = $request( $protected->get_id() );
    $assert( 403 === $denied->get_status(), 'A customer must not fulfill an order.' );
    $assert( 'rest_forbidden' === $denied->get_data()['code'], 'Denial must come from the route permission check.' );
    $assert( 'processing' === wc_get_order( $protected->get_id() )->get_status(), 'Permission denial must leave the order unchanged.' );

    wp_set_current_user( $admin_id );
    $cancelled = wc_create_order( [ 'status' => 'cancelled' ] );
    $assert( $cancelled instanceof WC_Order, 'The cancelled order fixture must be created.' );
    $orders[] = $cancelled;
    $rejected = $request( $cancelled->get_id() );
    $assert( 400 === $rejected->get_status(), 'A non-processing order must be rejected.' );
    $assert( 'order_desk_not_fulfillable' === $rejected->get_data()['code'], 'The rejection must identify the status constraint.' );
    $assert( 'cancelled' === wc_get_order( $cancelled->get_id() )->get_status(), 'Status rejection must not complete the order.' );
    WP_CLI::success( $checks . ' real Order Desk REST and persistence checks passed.' );
} finally {
    wp_set_current_user( $admin_id );
    foreach ( $orders as $order ) {
        $order->delete( true );
    }
    if ( ! function_exists( 'wp_delete_user' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    wp_delete_user( $customer_id );
}
