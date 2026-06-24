<?php
/**
 * Insight Tracker Pro — Uninstall
 *
 * Cleans up all plugin data when the plugin is deleted via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/* ─── Delete options ───────────────────────────────────────── */

$options = [
    'itp_settings',
    'itp_license_key',
    'itp_license_status',
    'itp_license_domain',
    'itp_license_expires_at',
    'itp_premium_files',
    'itp_last_update_check',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

/* ─── Delete transients ────────────────────────────────────── */

$transients = [
    'itp_license_valid',
    'itp_license_attempts',
    'itp_premium_fresh',
];

foreach ( $transients as $transient ) {
    delete_transient( $transient );
}

/* ─── Remove capabilities ──────────────────────────────────── */

$role = get_role( 'administrator' );
if ( $role ) {
    $role->remove_cap( 'manage_itp' );
}

/* ─── Clear scheduled hooks ────────────────────────────────── */

wp_clear_scheduled_hook( 'itp_validate_license_cron' );

/* ─── Multisite cleanup ────────────────────────────────────── */

if ( is_multisite() ) {
    $site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );

        foreach ( $options as $option ) {
            delete_option( $option );
        }
        foreach ( $transients as $transient ) {
            delete_transient( $transient );
        }

        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->remove_cap( 'manage_itp' );
        }

        wp_clear_scheduled_hook( 'itp_validate_license_cron' );

        restore_current_blog();
    }
}
