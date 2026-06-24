<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ITP_Woo {

    public function __construct() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        if ( itp_get_setting( 'track_woocommerce' ) !== '1' ) {
            return;
        }

        add_action( 'woocommerce_add_to_cart', [ $this, 'track_add_to_cart' ], 10, 6 );
        add_action( 'woocommerce_cart_item_removed', [ $this, 'track_remove_from_cart' ], 10, 2 );
        add_action( 'woocommerce_before_checkout_form', [ $this, 'track_checkout' ] );
        add_action( 'woocommerce_thankyou', [ $this, 'track_purchase' ] );
    }

    /* ─── Add to Cart ──────────────────────────────────────── */

    public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) {
            return;
        }

        $data = [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'name'         => $product->get_name(),
            'sku'          => $product->get_sku(),
            'price'        => $product->get_price(),
            'currency'     => get_woocommerce_currency(),
            'quantity'     => $quantity,
            'category'     => $this->get_product_category( $product_id ),
        ];

        add_action( 'wp_footer', function() use ( $data ) {
            echo '<script>if(window.__itp&&window.__itp.track){window.__itp.track("add_cart",' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . ');}</script>' . "\n";
        }, 99 );
    }

    /* ─── Remove from Cart ─────────────────────────────────── */

    public function track_remove_from_cart( $cart_item_key, $cart ) {
        $item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
        if ( ! $item ) {
            return;
        }

        $product_id = $item['product_id'] ?? 0;
        $product    = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        $data = [
            'product_id' => $product_id,
            'name'       => $product->get_name(),
            'sku'        => $product->get_sku(),
            'price'      => $product->get_price(),
            'currency'   => get_woocommerce_currency(),
            'quantity'   => $item['quantity'] ?? 1,
            'category'   => $this->get_product_category( $product_id ),
        ];

        add_action( 'wp_footer', function() use ( $data ) {
            echo '<script>if(window.__itp&&window.__itp.track){window.__itp.track("remove_cart",' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . ');}</script>' . "\n";
        }, 99 );
    }

    /* ─── Checkout ─────────────────────────────────────────── */

    public function track_checkout() {
        $cart = WC()->cart;
        if ( ! $cart ) {
            return;
        }

        $items = [];
        foreach ( $cart->get_cart() as $item ) {
            $product = $item['data'] ?? null;
            if ( ! $product ) {
                continue;
            }
            $items[] = [
                'product_id' => $item['product_id'],
                'name'       => $product->get_name(),
                'price'      => $product->get_price(),
                'quantity'   => $item['quantity'],
            ];
        }

        $data = [
            'total'      => $cart->get_total( 'edit' ),
            'subtotal'   => $cart->get_subtotal(),
            'tax'        => $cart->get_total_tax(),
            'currency'   => get_woocommerce_currency(),
            'item_count' => $cart->get_cart_contents_count(),
            'items'      => $items,
        ];

        echo '<script>if(window.__itp&&window.__itp.track){window.__itp.track("checkout",' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . ');}</script>' . "\n";
    }

    /* ─── Purchase ─────────────────────────────────────────── */

    public function track_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prevent duplicate tracking
        if ( $order->get_meta( '_itp_tracked' ) ) {
            return;
        }
        $order->update_meta_data( '_itp_tracked', '1' );
        $order->save();

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = [
                'product_id' => $item->get_product_id(),
                'name'       => $item->get_name(),
                'sku'        => $product ? $product->get_sku() : '',
                'price'      => $order->get_item_total( $item, false, false ),
                'quantity'   => $item->get_quantity(),
                'category'   => $this->get_product_category( $item->get_product_id() ),
            ];
        }

        // Coupon codes
        $coupons = [];
        foreach ( $order->get_coupon_codes() as $code ) {
            $coupons[] = $code;
        }

        // Is first order?
        $customer_id     = $order->get_customer_id();
        $is_first_order  = true;
        if ( $customer_id ) {
            $order_count    = wc_get_customer_order_count( $customer_id );
            $is_first_order = $order_count <= 1;
        }

        $data = [
            'order_id'       => $order_id,
            'total'          => $order->get_total(),
            'subtotal'       => $order->get_subtotal(),
            'tax'            => $order->get_total_tax(),
            'shipping'       => $order->get_shipping_total(),
            'discount'       => $order->get_total_discount(),
            'currency'       => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'coupon_codes'   => $coupons,
            'items'          => $items,
            'item_count'     => $order->get_item_count(),
            'is_first_order' => $is_first_order,
        ];

        echo '<script>if(window.__itp&&window.__itp.track){window.__itp.track("purchase",' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . ');}</script>' . "\n";
    }

    /* ─── Helper: get product primary category ─────────────── */

    private function get_product_category( $product_id ) {
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            return $terms[0]->name;
        }
        return '';
    }
}
