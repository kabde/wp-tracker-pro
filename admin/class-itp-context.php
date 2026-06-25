<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ITP_Context {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_footer', [ $this, 'inject_context' ], 1 );
    }

    /* ─── Enqueue ──────────────────────────────────────────── */

    public function enqueue_scripts() {
        if ( itp_is_excluded() ) {
            return;
        }

        wp_enqueue_script(
            'wp-theme-utils',
            ITP_URL . 'public/js/wp-theme-utils.js',
            [],
            ITP_VERSION,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }

    /* ─── Inject context ───────────────────────────────────── */

    public function inject_context() {
        if ( itp_is_excluded() ) {
            return;
        }

        $ctx      = $this->collect_context();
        $settings = $this->collect_settings();

        $payload = [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wtu_sync' ),
            'cookieDays' => (int) itp_get_setting( 'cookie_duration' ),
            'ctx'        => $ctx,
            'settings'   => $settings,
        ];

        echo '<script>window.__wtu=' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) . ';</script>' . "\n";
    }

    /* ─── Collect WordPress context ────────────────────────── */

    private function collect_context() {
        global $post, $wp_query;

        $ctx = [];

        // Site
        $ctx['site_id']   = wp_parse_url( home_url(), PHP_URL_HOST );
        $ctx['site_name'] = get_bloginfo( 'name' );

        // WordPress context detection
        $ctx['wp_context'] = $this->detect_wp_context();

        // Post data
        if ( $post instanceof WP_Post ) {
            $ctx['post_id']         = $post->ID;
            $ctx['post_type']       = $post->post_type;
            $ctx['post_slug']       = $post->post_name;
            $ctx['post_author']     = (int) $post->post_author;
            $ctx['post_date']       = $post->post_date;
            $ctx['post_word_count'] = str_word_count( wp_strip_all_tags( $post->post_content ) );

            // Taxonomies — categories
            $categories = get_the_category( $post->ID );
            $ctx['categories'] = $categories ? array_map( function( $c ) { return $c->name; }, $categories ) : [];

            // Taxonomies — tags
            $tags = get_the_tags( $post->ID );
            $ctx['tags'] = $tags ? array_map( function( $t ) { return $t->name; }, $tags ) : [];

            // Custom taxonomies
            $custom_taxonomies = [];
            $post_taxonomies   = get_object_taxonomies( $post->post_type, 'objects' );
            foreach ( $post_taxonomies as $tax ) {
                if ( in_array( $tax->name, [ 'category', 'post_tag' ], true ) ) {
                    continue;
                }
                if ( ! $tax->public ) {
                    continue;
                }
                $terms = get_the_terms( $post->ID, $tax->name );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $custom_taxonomies[ $tax->name ] = array_map( function( $t ) { return $t->name; }, $terms );
                }
            }
            if ( ! empty( $custom_taxonomies ) ) {
                $ctx['custom_taxonomies'] = $custom_taxonomies;
            }
        }

        // Archive context
        if ( is_archive() ) {
            $ctx['archive_type'] = $this->detect_archive_type();
            $queried = get_queried_object();
            if ( $queried instanceof WP_Term ) {
                $ctx['archive_term']     = $queried->name;
                $ctx['archive_taxonomy'] = $queried->taxonomy;
            } elseif ( $queried instanceof WP_User ) {
                $ctx['archive_term']     = $queried->display_name;
                $ctx['archive_taxonomy'] = 'author';
            }
        }

        // Search
        if ( is_search() ) {
            $ctx['search_query']   = get_search_query();
            $ctx['search_results'] = $wp_query ? $wp_query->found_posts : 0;
        }

        // Template
        if ( is_page() || is_single() ) {
            $ctx['page_template'] = get_page_template_slug();
        }

        // User
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $ctx['wp_user_id']   = $user->ID;
            $ctx['wp_user_role'] = ! empty( $user->roles ) ? $user->roles[0] : '';
            $ctx['is_logged_in'] = true;
        } else {
            $ctx['wp_user_id']   = 0;
            $ctx['wp_user_role'] = '';
            $ctx['is_logged_in'] = false;
        }

        // WooCommerce product context
        if ( function_exists( 'is_product' ) && is_product() && $post instanceof WP_Post ) {
            $product = wc_get_product( $post->ID );
            if ( $product ) {
                $ctx['woo_product'] = [
                    'id'             => $product->get_id(),
                    'name'           => $product->get_name(),
                    'sku'            => $product->get_sku(),
                    'price'          => $product->get_price(),
                    'regular_price'  => $product->get_regular_price(),
                    'sale_price'     => $product->get_sale_price(),
                    'currency'       => get_woocommerce_currency(),
                    'stock_status'   => $product->get_stock_status(),
                    'type'           => $product->get_type(),
                    'categories'     => wp_list_pluck( wc_get_product_category_list( $product->get_id() ) ? get_the_terms( $product->get_id(), 'product_cat' ) : [], 'name' ),
                    'average_rating' => $product->get_average_rating(),
                    'review_count'   => $product->get_review_count(),
                    'on_sale'        => $product->is_on_sale(),
                ];
            }
        }

        return $ctx;
    }

    /* ─── Detect WP context ────────────────────────────────── */

    private function detect_wp_context() {
        // WooCommerce contexts first (more specific)
        if ( function_exists( 'is_product' ) && is_product() )                   return 'product';
        if ( function_exists( 'is_product_category' ) && is_product_category() ) return 'product_category';
        if ( function_exists( 'is_shop' ) && is_shop() )                         return 'shop';
        if ( function_exists( 'is_cart' ) && is_cart() )                          return 'cart';
        if ( function_exists( 'is_checkout' ) && is_checkout() )                  return 'checkout';
        if ( function_exists( 'is_account_page' ) && is_account_page() )         return 'account';

        // WordPress contexts
        if ( is_front_page() && is_home() ) return 'home';
        if ( is_front_page() )              return 'front_page';
        if ( is_home() )                    return 'blog';
        if ( is_single() )                  return 'single';
        if ( is_page() )                    return 'page';
        if ( is_category() )                return 'category';
        if ( is_tag() )                     return 'tag';
        if ( is_tax() )                     return 'taxonomy';
        if ( is_author() )                  return 'author';
        if ( is_date() )                    return 'date';
        if ( is_archive() )                 return 'archive';
        if ( is_search() )                  return 'search';
        if ( is_404() )                     return '404';

        return 'other';
    }

    /* ─── Detect archive type ──────────────────────────────── */

    private function detect_archive_type() {
        if ( is_category() ) return 'category';
        if ( is_tag() )      return 'tag';
        if ( is_tax() )      return 'taxonomy';
        if ( is_author() )   return 'author';
        if ( is_date() )     return 'date';
        if ( is_post_type_archive() ) return 'post_type';
        return 'archive';
    }

    /* ─── Collect tracking settings ────────────────────────── */

    private function collect_settings() {
        return [
            'pv'       => itp_get_setting( 'track_pageviews' ) === '1',
            'scroll'   => itp_get_setting( 'track_scroll' ) === '1',
            'time'     => itp_get_setting( 'track_time' ) === '1',
            'outbound' => itp_get_setting( 'track_outbound' ) === '1',
            'search'   => itp_get_setting( 'track_search' ) === '1',
            'e404'     => itp_get_setting( 'track_404' ) === '1',
            'woo'      => itp_get_setting( 'track_woocommerce' ) === '1',
        ];
    }
}
