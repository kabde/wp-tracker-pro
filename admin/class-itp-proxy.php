<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX proxy — receives events from frontend JS and forwards to Worker.
 * The license key and endpoint are NEVER exposed to the browser.
 */
class ITP_Proxy {

    public function __construct() {
        add_action( 'wp_ajax_wtu_sync', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_wtu_sync', [ $this, 'handle' ] );
        add_action( 'wp_ajax_itp_session_detail', [ $this, 'handle_session_detail' ] );
    }

    public function handle() {
        // Verify nonce
        if ( ! check_ajax_referer( 'wtu_sync', 'nonce', false ) ) {
            wp_send_json_error( 'invalid_nonce', 403 );
        }

        // Read raw POST body
        $raw = file_get_contents( 'php://input' );
        if ( empty( $raw ) ) {
            wp_send_json_error( 'empty_body', 400 );
        }

        $events = json_decode( $raw, true );
        if ( ! is_array( $events ) ) {
            wp_send_json_error( 'invalid_json', 400 );
        }

        // Inject the license key server-side (never from client)
        $key      = get_option( 'itp_license_key', '' );
        $endpoint = ITP_API_URL . '/trk';

        if ( empty( $key ) || empty( $endpoint ) ) {
            wp_send_json_error( 'not_configured', 500 );
        }

        // Normalize: if single event, wrap in array
        if ( isset( $events['event_type'] ) ) {
            $events = [ $events ];
        }

        // Add key to each event
        foreach ( $events as &$event ) {
            $event['key'] = $key;
        }
        unset( $event );

        // Forward to Worker
        $response = wp_remote_post( $endpoint, [
            'timeout' => 10,
            'body'    => wp_json_encode( $events ),
            'headers' => [ 'Content-Type' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'upstream_error', 502 );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        wp_send_json( $body ?: [ 'ok' => $code === 200 ], $code );
    }

    /**
     * Proxy session detail requests from the admin Live Feed timeline.
     * Keeps the license key and API URL server-side.
     */
    public function handle_session_detail() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) {
            wp_send_json_error( 'forbidden', 403 );
        }
        if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'itp_nonce' ) ) {
            wp_send_json_error( 'invalid_nonce', 403 );
        }

        $sid = sanitize_text_field( $_GET['sid'] ?? '' );
        if ( empty( $sid ) ) {
            wp_send_json_error( 'missing_sid', 400 );
        }

        $key = get_option( 'itp_license_key', '' );
        if ( empty( $key ) ) {
            wp_send_json_error( 'not_configured', 500 );
        }

        $params = [
            'key'  => $key,
            'site' => wp_parse_url( home_url(), PHP_URL_HOST ),
            'view' => 'session_detail',
            'sid'  => $sid,
        ];

        $response = wp_remote_get( ITP_API_URL . '/trk?' . http_build_query( $params ), [
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'upstream_error', 502 );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        wp_send_json( $body ?: [ 'ok' => $code === 200 ], $code );
    }
}
