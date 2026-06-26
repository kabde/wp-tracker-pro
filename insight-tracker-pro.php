<?php
/**
 * Plugin Name: WP Tracker Pro
 * Description: WordPress-native analytics with funnel tracking, UTM auto-injection, and WooCommerce integration.
 * Version:     1.0.0
 * Author:      Abderrahim KHALID
 * Author URI:  https://khalid.digital
 * Text Domain: insight-tracker-pro
 * Domain Path: /languages
 * Network:     true
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ITP_VERSION', '1.0.3' );
define( 'ITP_FILE', __FILE__ );
define( 'ITP_BASENAME', plugin_basename( __FILE__ ) );
define( 'ITP_PATH', plugin_dir_path( __FILE__ ) );
define( 'ITP_URL', plugin_dir_url( __FILE__ ) );
define( 'ITP_CAPABILITY', 'manage_itp' );
define( 'ITP_API_URL', 'https://dp-starter.khalid.digital' );
define( 'ITP_TRK_URL', 'https://analytics.visitormetric.com/trk' );

// Load translations
add_action( 'init', function() {
    load_plugin_textdomain( 'insight-tracker-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});

// License system
require_once ITP_PATH . 'inc/license.php';

// Settings page (always loaded — includes license tab)
require_once ITP_PATH . 'admin/class-itp-settings.php';
new ITP_Settings();

// Features loaded only when licensed
if ( itp_is_licensed() ) {
    require_once ITP_PATH . 'admin/class-itp-context.php';
    require_once ITP_PATH . 'admin/class-itp-proxy.php';
    new ITP_Context();
    new ITP_Proxy();

    require_once ITP_PATH . 'admin/class-itp-utm.php';
    new ITP_UTM();

    if ( class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
        require_once ITP_PATH . 'admin/class-itp-woo.php';
        new ITP_Woo();
    }

    if ( is_admin() ) {
        require_once ITP_PATH . 'admin/class-itp-dashboard.php';
        require_once ITP_PATH . 'admin/class-itp-live.php';
        require_once ITP_PATH . 'admin/class-itp-sources.php';
        require_once ITP_PATH . 'admin/class-itp-countries.php';
        require_once ITP_PATH . 'admin/class-itp-visitors.php';
        require_once ITP_PATH . 'admin/class-itp-referrers.php';
        require_once ITP_PATH . 'admin/class-itp-404.php';
        require_once ITP_PATH . 'admin/class-itp-explorer.php';
        new ITP_Dashboard();
        new ITP_Live();
        new ITP_Sources();
        new ITP_Countries();
        new ITP_Visitors();
        new ITP_Referrers();
        new ITP_404();
        new ITP_Explorer();

        // Shared header CSS + sortable table script on ITP pages
        add_action( 'admin_enqueue_scripts', function( $hook ) {
            if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'itp-' ) === 0 ) {
                wp_enqueue_style( 'itp-header', ITP_URL . 'admin/css/itp-header.css', [], ITP_VERSION );
                wp_enqueue_script( 'itp-sortable', ITP_URL . 'admin/js/itp-sortable.js', [], ITP_VERSION, true );
            }
        });
    }
}

// Capabilities
function itp_add_caps_for_blog() {
    $role = get_role( 'administrator' );
    if ( ! $role ) return;
    $role->add_cap( ITP_CAPABILITY );
}

function itp_activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        $site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            itp_add_caps_for_blog();
            restore_current_blog();
        }
    } else {
        itp_add_caps_for_blog();
    }
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'itp_activate' );

function itp_deactivate() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook( 'itp_validate_license_cron' );
    delete_transient( 'itp_license_valid' );
    delete_transient( 'itp_premium_fresh' );
}
register_deactivation_hook( __FILE__, 'itp_deactivate' );

function itp_maybe_add_caps() {
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( ITP_CAPABILITY ) ) {
        $role->add_cap( ITP_CAPABILITY );
    }
}
add_action( 'admin_init', 'itp_maybe_add_caps' );

// Multisite new blog
function itp_add_caps_on_new_blog( $blog_id ) {
    if ( ! is_multisite() ) return;
    switch_to_blog( $blog_id );
    itp_add_caps_for_blog();
    restore_current_blog();
}
add_action( 'wpmu_new_blog', 'itp_add_caps_on_new_blog' );

// Settings helpers
function itp_settings_defaults() {
    return [
        'cookie_duration'    => 365,
        'exclude_roles'      => [ 'administrator' ],
        'exclude_ips'        => '',
        'track_pageviews'    => '1',
        'track_scroll'       => '1',
        'track_time'         => '1',
        'track_outbound'     => '1',
        'track_search'       => '1',
        'track_404'          => '1',
        'track_woocommerce'  => '1',
        'auto_utm'           => '1',
        'utm_source_pattern' => '{site_name}',
        'utm_medium_pattern' => '{post_type}_cta',
    ];
}

function itp_get_setting( $key ) {
    static $settings = null;
    if ( $settings === null ) {
        $settings = wp_parse_args( get_option( 'itp_settings', [] ), itp_settings_defaults() );
    }
    return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

function itp_is_excluded() {
    if ( ! is_user_logged_in() ) return false;
    $user = wp_get_current_user();
    $excluded = (array) itp_get_setting( 'exclude_roles' );
    return ! empty( array_intersect( $user->roles, $excluded ) );
}
