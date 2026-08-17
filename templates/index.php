<?php
/**
 * Order list: To fulfill / Completed / All, with search and a one-tap
 * "Mark Completed" action for orders that are ready to ship.
 */

if ( ! function_exists( 'wc_get_orders' ) ) {
	?>
	<!DOCTYPE html>
	<html <?php wp_app_language_attributes(); ?>>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
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

$app_path = wp_app_get_current_app_path();

$tab = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'processing';
if ( ! in_array( $tab, [ 'processing', 'completed', 'all' ], true ) ) {
	$tab = 'processing';
}

$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$query_args = [
	'status'  => \OrderDesk\App::statuses_for_tab( $tab ),
	// WooCommerce's own 's' arg only searches post title/content, which
	// doesn't cover order numbers or customer names, so search is done
	// in PHP below against a wider fetch instead.
	'limit'   => '' !== $search ? 200 : 50,
	'orderby' => 'date',
	'order'   => 'DESC',
];

$orders = wc_get_orders( $query_args );

if ( '' !== $search ) {
	$needle = mb_strtolower( $search );
	$orders = array_values(
		array_filter(
			$orders,
			static function ( $order ) use ( $needle ) {
				$haystack = mb_strtolower(
					implode(
						' ',
						[
							$order->get_order_number(),
							$order->get_formatted_billing_full_name(),
							$order->get_billing_email(),
						]
					)
				);
				return false !== mb_strpos( $haystack, $needle );
			}
		)
	);
}

$counts = [
	'processing' => (int) wc_orders_count( 'processing' ),
	'completed'  => (int) wc_orders_count( 'completed' ),
];
$counts['all'] = 0;
foreach ( \OrderDesk\App::statuses_for_tab( 'all' ) as $status ) {
	$counts['all'] += (int) wc_orders_count( $status );
}

$tabs = [
	'processing' => __( 'To Fulfill', 'order-desk' ),
	'completed'  => __( 'Completed', 'order-desk' ),
	'all'        => __( 'All', 'order-desk' ),
];
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( 'Order Desk' ); ?></title>
	<?php wp_app_head(); ?>
	<style>
		:root { color-scheme: light dark; }
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--wp-app-color-background); color: var(--wp-app-color-text); }
		main { max-width: 640px; margin: 0 auto; padding: 1rem 1rem 4rem; }
		h1 { font-size: 1.4rem; margin: 1rem 0; }
		.tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
		.tabs a { flex: 1; text-align: center; padding: 0.6rem 0.5rem; border-radius: 6px; background: var(--wp-app-color-surface-alt); color: var(--wp-app-color-text); text-decoration: none; font-size: 0.9rem; white-space: nowrap; }
		.tabs a.active { background: var(--wp-app-color-primary); color: var(--wp-app-color-on-primary); font-weight: 600; }
		.search-form { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; }
		.search-form input[type="search"] { flex: 1; padding: 0.6rem 0.75rem; border-radius: 6px; border: 1px solid var(--wp-app-color-border); background: var(--wp-app-color-surface); color: var(--wp-app-color-text); font-size: 1rem; }
		.search-form button { padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid var(--wp-app-color-border); background: var(--wp-app-color-surface-alt); color: var(--wp-app-color-text); font-size: 1rem; }
		.order-row { display: flex; flex-direction: column; gap: 0.4rem; background: var(--wp-app-color-surface); border-radius: 8px; padding: 0.9rem 1rem; margin-bottom: 0.6rem; text-decoration: none; color: inherit; transition: opacity 0.3s, transform 0.3s; }
		.order-row:hover { background: var(--wp-app-color-surface-alt); }
		.order-row.order-row-done { opacity: 0; transform: translateX(20px); }
		.order-row-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
		.order-number { font-weight: 600; min-width: 0; }
		.order-row-bottom { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem; }
		.order-meta { color: var(--wp-app-color-muted); font-size: 0.8rem; }
		.order-row-actions { display: flex; align-items: center; gap: 0.75rem; margin-left: auto; }
		.order-total { font-weight: 600; white-space: nowrap; }
		.status-badge { display: inline-block; flex-shrink: 0; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; background: var(--wp-app-color-surface-alt); color: var(--wp-app-color-muted); white-space: nowrap; }
		.status-badge.status-processing { background: var(--wp-app-color-primary); color: var(--wp-app-color-on-primary); }
		.status-badge.status-completed { background: #2e7d32; color: #fff; }
		.status-badge.status-on-hold { background: #b26a00; color: #fff; }
		.btn-complete { padding: 0.6rem 0.9rem; border-radius: 6px; border: none; background: var(--wp-app-color-primary); color: var(--wp-app-color-on-primary); font-size: 0.9rem; font-weight: 600; white-space: nowrap; min-height: 44px; cursor: pointer; }
		.btn-complete:disabled { opacity: 0.6; cursor: default; }
		.empty-state { text-align: center; color: var(--wp-app-color-muted); padding: 3rem 1rem; }
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main>
		<h1><?php esc_html_e( 'Order Desk', 'order-desk' ); ?></h1>

		<nav class="tabs">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a
					href="<?php echo esc_url( add_query_arg( [ 'status' => $key, 's' => $search ? $search : false ], home_url( '/' . $app_path . '/' ) ) ); ?>"
					class="<?php echo $tab === $key ? 'active' : ''; ?>"
				><?php echo esc_html( $label . ' (' . $counts[ $key ] . ')' ); ?></a>
			<?php endforeach; ?>
		</nav>

		<form method="get" class="search-form">
			<input type="hidden" name="status" value="<?php echo esc_attr( $tab ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search order # or customer', 'order-desk' ); ?>">
			<button type="submit"><?php esc_html_e( 'Search', 'order-desk' ); ?></button>
		</form>

		<div id="order-desk-list">
			<?php if ( empty( $orders ) ) : ?>
				<p class="empty-state">
					<?php
					if ( 'processing' === $tab ) {
						esc_html_e( 'Nothing to fulfill right now.', 'order-desk' );
					} else {
						esc_html_e( 'No orders here yet.', 'order-desk' );
					}
					?>
				</p>
			<?php else : ?>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$order_id     = $order->get_id();
					$status       = $order->get_status();
					$status_class = 'status-' . sanitize_html_class( $status );
					$name         = trim( $order->get_formatted_billing_full_name() );
					if ( '' === $name ) {
						$name = __( 'Guest', 'order-desk' );
					}
					$created = $order->get_date_created();
					?>
					<a class="order-row" href="<?php echo esc_url( home_url( '/' . $app_path . '/order/' . $order_id . '/' ) ); ?>" data-order-id="<?php echo esc_attr( $order_id ); ?>">
						<div class="order-row-top">
							<div class="order-number">#<?php echo esc_html( $order->get_order_number() ); ?> &middot; <?php echo esc_html( $name ); ?></div>
							<span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( wc_get_order_status_name( 'wc-' . $status ) ); ?></span>
						</div>
						<div class="order-row-bottom">
							<div class="order-meta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: item count, 2: relative date */
										_n( '%1$d item &middot; %2$s', '%1$d items &middot; %2$s', $order->get_item_count(), 'order-desk' ),
										$order->get_item_count(),
										$created ? human_time_diff( $created->getTimestamp() ) . ' ' . __( 'ago', 'order-desk' ) : ''
									)
								);
								?>
							</div>
							<div class="order-row-actions">
								<div class="order-total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></div>
								<?php if ( 'processing' === $status ) : ?>
									<button type="button" class="btn-complete" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Mark Completed', 'order-desk' ); ?></button>
								<?php endif; ?>
							</div>
						</div>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</main>

	<script>
	var orderDeskConfig = {
		restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( \OrderDesk\App::REST_NAMESPACE ) ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
	};
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.btn-complete' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();

		var id = button.dataset.orderId;
		var originalText = button.textContent;
		button.disabled = true;
		button.textContent = 'Completing…';

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
					button.disabled = false;
					button.textContent = originalText;
					return;
				}
				var row = button.closest( '.order-row' );
				row.classList.add( 'order-row-done' );
				setTimeout( function () {
					row.remove();
				}, 300 );
			} )
			.catch( function () {
				alert( 'Network error. Please try again.' );
				button.disabled = false;
				button.textContent = originalText;
			} );
	} );
	</script>

	<?php wp_app_body_close(); ?>
</body>
</html>
