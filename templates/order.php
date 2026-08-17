<?php
/**
 * Order detail: line items, shipping info, customer note, and a single
 * "Mark Completed" action when the order is ready to ship.
 */

$app_path = wp_app_get_current_app_path();
$back_url = home_url( '/' . $app_path . '/' );

if ( ! function_exists( 'wc_get_order' ) ) {
	?>
	<!DOCTYPE html>
	<html <?php wp_app_language_attributes(); ?>>
	<head>
		<meta charset="UTF-8">
		<title><?php wp_app_title( 'Order Desk' ); ?></title>
		<?php wp_app_head(); ?>
	</head>
	<body>
		<?php wp_app_body_open(); ?>
		<main style="max-width:480px;margin:3rem auto;padding:0 1rem;text-align:center;">
			<h1><?php esc_html_e( 'Order Desk needs WooCommerce', 'order-desk' ); ?></h1>
			<p><?php esc_html_e( 'Install and activate WooCommerce, then reload this page.', 'order-desk' ); ?></p>
		</main>
		<?php wp_app_body_close(); ?>
	</body>
	</html>
	<?php
	return;
}

$order_id = (int) wp_app_get_route_var( 'id' );
$order    = $order_id ? wc_get_order( $order_id ) : false;
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $order ? '#' . $order->get_order_number() : 'Order Desk' ); ?></title>
	<?php wp_app_head(); ?>
	<style>
		:root { color-scheme: light dark; }
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--wp-app-color-background); color: var(--wp-app-color-text); }
		main { max-width: 640px; margin: 0 auto; padding: 1rem 1rem 4rem; }
		.back-link { display: inline-block; margin-bottom: 1rem; color: var(--wp-app-color-link); text-decoration: none; }
		.order-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; }
		.order-header h1 { font-size: 1.3rem; margin: 0 0 0.2rem; }
		.order-header .order-customer { color: var(--wp-app-color-muted); }
		.status-badge { display: inline-block; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.85rem; background: var(--wp-app-color-surface-alt); color: var(--wp-app-color-muted); white-space: nowrap; }
		.status-badge.status-processing { background: var(--wp-app-color-primary); color: var(--wp-app-color-on-primary); }
		.status-badge.status-completed { background: #2e7d32; color: #fff; }
		.status-badge.status-on-hold { background: #b26a00; color: #fff; }
		.card { background: var(--wp-app-color-surface); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
		.card h2 { font-size: 1rem; margin: 0 0 0.75rem; color: var(--wp-app-color-muted); text-transform: uppercase; letter-spacing: 0.03em; }
		.line-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid var(--wp-app-color-border); }
		.line-item:last-child { border-bottom: none; }
		.line-item img { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
		.line-item-name { flex: 1; }
		.line-item-qty { color: var(--wp-app-color-muted); font-size: 0.9rem; }
		.line-item-total { font-weight: 600; white-space: nowrap; }
		.order-total-row { display: flex; justify-content: space-between; font-weight: 600; padding-top: 0.75rem; margin-top: 0.5rem; border-top: 1px solid var(--wp-app-color-border); }
		address { font-style: normal; line-height: 1.5; }
		.btn-complete { width: 100%; padding: 0.9rem; border-radius: 8px; border: none; background: var(--wp-app-color-primary); color: var(--wp-app-color-on-primary); font-size: 1.05rem; font-weight: 600; cursor: pointer; min-height: 44px; }
		.btn-complete:disabled { opacity: 0.6; cursor: default; }
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main>
		<a class="back-link" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Order Desk', 'order-desk' ); ?></a>

		<?php if ( ! $order ) : ?>
			<p><?php esc_html_e( 'Order not found.', 'order-desk' ); ?></p>
		<?php else : ?>
			<?php
			$status       = $order->get_status();
			$status_class = 'status-' . sanitize_html_class( $status );
			$name         = trim( $order->get_formatted_billing_full_name() );
			if ( '' === $name ) {
				$name = __( 'Guest', 'order-desk' );
			}
			?>
			<div class="order-header">
				<div>
					<h1>#<?php echo esc_html( $order->get_order_number() ); ?></h1>
					<div class="order-customer"><?php echo esc_html( $name ); ?></div>
				</div>
				<span id="order-desk-status" class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( wc_get_order_status_name( 'wc-' . $status ) ); ?></span>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Items', 'order-desk' ); ?></h2>
				<?php foreach ( $order->get_items() as $item ) : ?>
					<?php
					$product = $item->get_product();
					$image   = $product ? $product->get_image( 'thumbnail' ) : '';
					?>
					<div class="line-item">
						<?php if ( $image ) : ?>
							<?php echo wp_kses_post( $image ); ?>
						<?php endif; ?>
						<div class="line-item-name">
							<?php echo esc_html( $item->get_name() ); ?>
							<div class="line-item-qty">&times; <?php echo esc_html( $item->get_quantity() ); ?></div>
						</div>
						<div class="line-item-total"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></div>
					</div>
				<?php endforeach; ?>
				<div class="order-total-row">
					<span><?php esc_html_e( 'Total', 'order-desk' ); ?></span>
					<span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
				</div>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Ship to', 'order-desk' ); ?></h2>
				<address><?php echo wp_kses_post( $order->get_formatted_shipping_address( __( 'No shipping address on file.', 'order-desk' ) ) ); ?></address>
			</div>

			<?php if ( $order->get_customer_note() ) : ?>
				<div class="card">
					<h2><?php esc_html_e( 'Customer note', 'order-desk' ); ?></h2>
					<p><?php echo esc_html( $order->get_customer_note() ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'processing' === $status ) : ?>
				<button type="button" class="btn-complete" id="order-desk-complete" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"><?php esc_html_e( 'Mark Completed', 'order-desk' ); ?></button>
			<?php endif; ?>

			<script>
			var orderDeskConfig = {
				restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( \OrderDesk\App::REST_NAMESPACE ) ) ); ?>,
				nonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
				backUrl: <?php echo wp_json_encode( $back_url ); ?>
			};
			var completeButton = document.getElementById( 'order-desk-complete' );
			if ( completeButton ) {
				completeButton.addEventListener( 'click', function () {
					var id = completeButton.dataset.orderId;
					completeButton.disabled = true;
					completeButton.textContent = 'Completing…';

					fetch( orderDeskConfig.restUrl + '/orders/' + id + '/complete', {
						method: 'POST',
						headers: { 'X-WP-Nonce': orderDeskConfig.nonce }
					} )
						.then( function ( response ) {
							return response.json().then( function ( data ) {
								return { ok: response.ok, data: data };
							} );
						} )
						.then( function ( result ) {
							if ( ! result.ok ) {
								alert( result.data.message || 'Could not complete this order.' );
								completeButton.disabled = false;
								completeButton.textContent = 'Mark Completed';
								return;
							}
							var badge = document.getElementById( 'order-desk-status' );
							badge.textContent = result.data.status_label;
							badge.className = 'status-badge status-' + result.data.status;
							completeButton.remove();
						} )
						.catch( function () {
							alert( 'Network error. Please try again.' );
							completeButton.disabled = false;
							completeButton.textContent = 'Mark Completed';
						} );
				} );
			}
			</script>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
