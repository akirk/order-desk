# Order Desk

A staff-facing order fulfillment queue for WooCommerce: see what needs packing, open an order for its items and shipping address, and mark it completed in one tap.

[Try Order Desk in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/order-desk/main/blueprint.json)

Order Desk is a secondary, easy-to-use frontend for a task WooCommerce staff already do in wp-admin — working through orders that are paid and waiting to ship — without the general-purpose order-list screen built for admins. It reads and writes through WooCommerce's own order objects; it keeps no data of its own.

- **To Fulfill / Completed / All** tabs, with a search field for order number or customer name.
- **Order detail**: line items with product thumbnails, shipping address, and the customer note, if any.
- **One-tap fulfillment**: a "Mark Completed" button moves an order from `processing` to `completed`. That's the only status change Order Desk makes; anything else (holds, cancellations, refunds, editing an order) stays in wp-admin.
- Requires the `manage_woocommerce` capability, so it's available to shop managers and administrators, not customers.

Built on [WpApp](https://github.com/akirk/wp-app).
