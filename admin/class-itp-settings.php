<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ITP_Settings {

    const OPTION_KEY = 'itp_settings';

    /** @var string Settings page hook suffix */
    private $hook = '';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 10 );
        add_action( 'admin_menu', [ $this, 'reorder_submenu' ], 99 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /* ─── Menu ─────────────────────────────────────────────── */

    public function add_menu() {
        $this->hook = add_menu_page(
            'WP Tracker Pro',
            'WP Tracker Pro',
            ITP_CAPABILITY,
            'itp-settings',
            [ $this, 'render' ],
            'dashicons-chart-area',
            20
        );
    }

    public function reorder_submenu() {
        global $submenu;
        if ( empty( $submenu['itp-settings'] ) ) return;
        // Find the auto-created first item and move it to end as "Settings"
        $found_key = null;
        foreach ( $submenu['itp-settings'] as $key => $item ) {
            if ( $item[2] === 'itp-settings' ) {
                $found_key = $key;
                break;
            }
        }
        if ( $found_key !== null ) {
            $settings_item = $submenu['itp-settings'][ $found_key ];
            $settings_item[0] = __( 'Settings', 'insight-tracker-pro' );
            unset( $submenu['itp-settings'][ $found_key ] );
            $submenu['itp-settings'][] = $settings_item;
        }
    }

    /* ─── Register ─────────────────────────────────────────── */

    public function register_settings() {
        register_setting( 'itp_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );

        add_filter( 'allowed_options', function ( $allowed ) {
            $allowed['itp_settings_group'] = [ 'itp_settings' ];
            return $allowed;
        } );
    }

    /* ─── Sanitize ─────────────────────────────────────────── */

    public function sanitize( $input ) {
        $input = is_array( $input ) ? $input : [];
        $clean = [];

        // General
        $clean['cookie_duration'] = absint( $input['cookie_duration'] ?? 365 );
        $clean['exclude_roles']   = isset( $input['exclude_roles'] ) && is_array( $input['exclude_roles'] )
            ? array_map( 'sanitize_text_field', $input['exclude_roles'] )
            : [];
        $clean['exclude_ips']     = sanitize_textarea_field( $input['exclude_ips'] ?? '' );

        // Tracking
        $clean['track_pageviews']   = empty( $input['track_pageviews'] ) ? '0' : '1';
        $clean['track_scroll']      = empty( $input['track_scroll'] ) ? '0' : '1';
        $clean['track_time']        = empty( $input['track_time'] ) ? '0' : '1';
        $clean['track_outbound']    = empty( $input['track_outbound'] ) ? '0' : '1';
        $clean['track_search']      = empty( $input['track_search'] ) ? '0' : '1';
        $clean['track_404']         = empty( $input['track_404'] ) ? '0' : '1';
        $clean['track_woocommerce'] = empty( $input['track_woocommerce'] ) ? '0' : '1';

        // UTM
        $clean['auto_utm']           = empty( $input['auto_utm'] ) ? '0' : '1';
        $clean['utm_source_pattern'] = sanitize_text_field( $input['utm_source_pattern'] ?? '' );
        $clean['utm_medium_pattern'] = sanitize_text_field( $input['utm_medium_pattern'] ?? '' );

        return $clean;
    }

    /* ─── Render ───────────────────────────────────────────── */

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'insight-tracker-pro' ) );
        }

        $licensed    = itp_is_licensed();
        $license_key = get_option( 'itp_license_key', '' );
        $settings    = get_option( self::OPTION_KEY, [] );
        $defaults    = itp_settings_defaults();
        $s           = wp_parse_args( $settings, $defaults );

        $tabs = [
            'license'  => [ 'label' => __( 'License', 'insight-tracker-pro' ),       'icon' => 'dashicons-lock' ],
            'general'  => [ 'label' => __( 'General', 'insight-tracker-pro' ),       'icon' => 'dashicons-admin-settings' ],
            'tracking' => [ 'label' => __( 'Tracking', 'insight-tracker-pro' ),      'icon' => 'dashicons-visibility' ],
            'utm'      => [ 'label' => __( 'UTM', 'insight-tracker-pro' ),           'icon' => 'dashicons-admin-links' ],
            'docs'     => [ 'label' => __( 'Documentation', 'insight-tracker-pro' ), 'icon' => 'dashicons-book' ],
        ];

        // Only show non-license tabs when licensed
        if ( ! $licensed ) {
            $tabs = [ 'license' => $tabs['license'] ];
        }

        $nonce = wp_create_nonce( 'itp_license_nonce' );
        ?>
        <style>
        /* ── Layout ── */
        #itp-settings-wrap { max-width: 1140px; margin-top: 20px; }
        .itp-settings-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .itp-settings-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; color: #1d2327; }
        .itp-settings-version { background: #f0f0f1; color: #787c82; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .itp-settings-layout { display: grid; grid-template-columns: 220px 1fr; gap: 0; min-height: 600px; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; background: #f6f7f7; }

        /* ── Sidebar ── */
        .itp-settings-sidebar { background: #1d2327; padding: 12px 0; display: flex; flex-direction: column; }
        .itp-sidebar-item { display: flex; align-items: center; gap: 10px; padding: 11px 20px; color: #bbc8d4; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 120ms; border-left: 3px solid transparent; cursor: pointer; }
        .itp-sidebar-item:hover { color: #fff; background: rgba(255,255,255,0.06); }
        .itp-sidebar-item:focus { color: #fff; box-shadow: none; outline: none; }
        .itp-sidebar-item.is-active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: #ffc45e; }
        .itp-sidebar-item .dashicons { font-size: 16px; width: 16px; height: 16px; opacity: 0.65; }
        .itp-sidebar-item.is-active .dashicons { opacity: 1; color: #ffc45e; }

        /* ── Panel ── */
        .itp-settings-panel { background: #fff; padding: 28px 32px; overflow-y: auto; }
        .itp-tab-content { display: none; }
        .itp-tab-content.is-active { display: block; animation: itpFadeIn 200ms ease; }
        @keyframes itpFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Sections ── */
        .itp-admin-section { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px 28px; margin: 0 0 20px; }
        .itp-admin-section h2 { margin: 0 0 16px; padding: 0 0 12px; border-bottom: 1px solid #e5e7eb; font-size: 1.05em; font-weight: 700; color: #1d2327; }
        .itp-admin-section .form-table th { font-weight: 600; color: #374151; padding-top: 16px; }
        .itp-admin-section .form-table td { padding-top: 12px; }

        /* ── Submit button ── */
        .itp-settings-panel .submit { margin-top: 8px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        .itp-settings-panel #submit { background: #1d2327; border-color: #1d2327; color: #fff; border-radius: 6px; padding: 6px 24px; font-weight: 600; transition: background 120ms; }
        .itp-settings-panel #submit:hover { background: #2c3338; }

        /* ── License card ── */
        .itp-license-card { max-width: 600px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 30px; }
        .itp-license-active { display: inline-block; background: #00a32a; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 600; }
        .itp-license-inactive { display: inline-block; background: #dba617; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 600; }

        /* ── Placeholder tag ── */
        .itp-placeholder-tag { display: inline-block; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px; padding: 2px 8px; font-family: monospace; font-size: 12px; margin: 2px 4px 2px 0; }

        /* ── Responsive ── */
        @media (max-width: 960px) {
            .itp-settings-layout { grid-template-columns: 1fr; }
            .itp-settings-sidebar { flex-direction: row; flex-wrap: wrap; padding: 8px; gap: 4px; }
            .itp-sidebar-item { padding: 8px 12px; border-left: none; border-bottom: 2px solid transparent; font-size: 12px; }
            .itp-sidebar-item.is-active { border-left: none; border-bottom-color: #ffc45e; }
            .itp-sidebar-item .dashicons { display: none; }
            .itp-settings-panel { padding: 20px 16px; }
        }
        </style>

        <div id="itp-settings-wrap" class="wrap">

            <!-- Header -->
            <div class="itp-settings-header">
                <h1>WP Tracker Pro</h1>
                <span class="itp-settings-version">v<?php echo esc_html( ITP_VERSION ); ?></span>
            </div>

            <div class="itp-settings-layout">

                <!-- Sidebar -->
                <nav class="itp-settings-sidebar">
                    <?php foreach ( $tabs as $slug => $tab ) : ?>
                        <a href="#<?php echo esc_attr( $slug ); ?>" class="itp-sidebar-item" data-tab="<?php echo esc_attr( $slug ); ?>">
                            <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                            <?php echo esc_html( $tab['label'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Panel -->
                <div class="itp-settings-panel">

                    <!-- ═══ License Tab ═══ -->
                    <div id="itp-tab-license" class="itp-tab-content">
                        <div class="itp-admin-section">
                            <h2><?php esc_html_e( 'License', 'insight-tracker-pro' ); ?></h2>
                            <div class="itp-license-card">
                                <?php if ( $licensed ) : ?>
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <span class="itp-license-active">&#10003; <?php esc_html_e( 'License Active', 'insight-tracker-pro' ); ?></span>
                                    </div>
                                    <table class="form-table" style="margin:0;">
                                        <tr>
                                            <th><?php esc_html_e( 'License Key', 'insight-tracker-pro' ); ?></th>
                                            <td><code style="font-size:14px;"><?php echo esc_html( $license_key ); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Domain', 'insight-tracker-pro' ); ?></th>
                                            <td><?php echo esc_html( home_url() ); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Expiration', 'insight-tracker-pro' ); ?></th>
                                            <td>
                                                <?php
                                                $expires = get_option( 'itp_license_expires_at', '' );
                                                if ( $expires ) {
                                                    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
                                                    $date_formatted = wp_date( 'd F Y', strtotime( $expires ) );
                                                    if ( $days <= 0 ) {
                                                        /* translators: %s: formatted expiration date */
                                                        echo '<span style="color:#dc2626;font-weight:600;">' . sprintf( esc_html__( 'Expired on %s', 'insight-tracker-pro' ), esc_html( $date_formatted ) ) . '</span>';
                                                    } elseif ( $days <= 30 ) {
                                                        /* translators: 1: formatted date, 2: number of days remaining */
                                                        echo '<span style="color:#d97706;font-weight:600;">' . esc_html( $date_formatted ) . ' (' . sprintf( _n( '%d day remaining', '%d days remaining', $days, 'insight-tracker-pro' ), $days ) . ')</span>';
                                                    } else {
                                                        /* translators: 1: formatted date, 2: number of days remaining */
                                                        echo '<span style="color:#16a34a;">' . esc_html( $date_formatted ) . ' (' . sprintf( _n( '%d day remaining', '%d days remaining', $days, 'insight-tracker-pro' ), $days ) . ')</span>';
                                                    }
                                                } else {
                                                    echo '<span style="color:#16a34a;">' . esc_html__( 'Lifetime', 'insight-tracker-pro' ) . '</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin-top:20px;">
                                        <button type="button" id="itp-deactivate-btn" class="button button-secondary" style="color:#d63638;"><?php esc_html_e( 'Deactivate License', 'insight-tracker-pro' ); ?></button>
                                    </p>
                                <?php else : ?>
                                    <h2 style="margin-top:0;"><?php esc_html_e( 'Activate Your License', 'insight-tracker-pro' ); ?></h2>
                                    <p><?php esc_html_e( 'Enter your license key to activate WP Tracker Pro.', 'insight-tracker-pro' ); ?></p>
                                    <p>
                                        <input type="text" id="itp-license-key" placeholder="ITP-XXXX-XXXX-XXXX" style="width:100%;font-size:16px;padding:8px 12px;font-family:monospace;text-transform:uppercase;" maxlength="19">
                                    </p>
                                    <p>
                                        <button type="button" id="itp-activate-btn" class="button button-primary button-hero" style="width:100%;"><?php esc_html_e( 'Activate License', 'insight-tracker-pro' ); ?></button>
                                    </p>
                                    <div id="itp-license-message" style="margin-top:15px;display:none;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ( $licensed ) : ?>

                    <!-- ═══ Form wraps General + Tracking + UTM ═══ -->
                    <form method="post" action="options.php" id="itp-settings-form">
                        <?php settings_fields( 'itp_settings_group' ); ?>
                        <input type="hidden" id="itp_active_tab" name="itp_active_tab" value="">

                        <!-- ═══ General Tab ═══ -->
                        <div id="itp-tab-general" class="itp-tab-content">
                            <div class="itp-admin-section">
                                <h2><?php esc_html_e( 'General Settings', 'insight-tracker-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Cookie Duration', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <input type="number" name="itp_settings[cookie_duration]" value="<?php echo esc_attr( $s['cookie_duration'] ); ?>" min="1" max="730" class="small-text">
                                            <?php esc_html_e( 'days', 'insight-tracker-pro' ); ?>
                                            <p class="description"><?php esc_html_e( 'How long visitor cookies are stored.', 'insight-tracker-pro' ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Excluded Roles', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <?php
                                            $excluded_roles = (array) $s['exclude_roles'];
                                            $check_roles    = [ 'administrator', 'editor', 'author' ];
                                            foreach ( $check_roles as $role ) :
                                            ?>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="checkbox" name="itp_settings[exclude_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $excluded_roles, true ) ); ?>>
                                                    <?php echo esc_html( ucfirst( $role ) ); ?>
                                                </label>
                                            <?php endforeach; ?>
                                            <p class="description"><?php esc_html_e( 'Logged-in users with these roles will not be tracked.', 'insight-tracker-pro' ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Excluded IPs', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <textarea name="itp_settings[exclude_ips]" rows="4" class="large-text" placeholder="192.168.1.1&#10;10.0.0.0/8"><?php echo esc_textarea( $s['exclude_ips'] ); ?></textarea>
                                            <p class="description"><?php esc_html_e( 'One IP address per line. CIDR notation supported.', 'insight-tracker-pro' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save Settings', 'insight-tracker-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- ═══ Tracking Tab ═══ -->
                        <div id="itp-tab-tracking" class="itp-tab-content">
                            <div class="itp-admin-section">
                                <h2><?php esc_html_e( 'Event Tracking', 'insight-tracker-pro' ); ?></h2>
                                <p style="color:#6b7280;margin:0 0 16px;"><?php esc_html_e( 'Choose which events to track automatically on the frontend.', 'insight-tracker-pro' ); ?></p>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Page Views', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_pageviews]" value="1" <?php checked( $s['track_pageviews'], '1' ); ?>>
                                                <?php esc_html_e( 'Track page views on every page load', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Scroll Depth', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_scroll]" value="1" <?php checked( $s['track_scroll'], '1' ); ?>>
                                                <?php esc_html_e( 'Track scroll depth at 25%, 50%, 75%, and 100%', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Time on Page', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_time]" value="1" <?php checked( $s['track_time'], '1' ); ?>>
                                                <?php esc_html_e( 'Track time spent on each page', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Outbound Clicks', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_outbound]" value="1" <?php checked( $s['track_outbound'], '1' ); ?>>
                                                <?php esc_html_e( 'Track clicks on external links', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Search', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_search]" value="1" <?php checked( $s['track_search'], '1' ); ?>>
                                                <?php esc_html_e( 'Track internal search queries and result counts', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( '404 Errors', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_404]" value="1" <?php checked( $s['track_404'], '1' ); ?>>
                                                <?php esc_html_e( 'Track 404 error pages and the URLs that caused them', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'WooCommerce Events', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[track_woocommerce]" value="1" <?php checked( $s['track_woocommerce'], '1' ); ?>>
                                                <?php esc_html_e( 'Track add to cart, remove from cart, checkout, and purchase events', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save Settings', 'insight-tracker-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- ═══ UTM Tab ═══ -->
                        <div id="itp-tab-utm" class="itp-tab-content">
                            <div class="itp-admin-section">
                                <h2><?php esc_html_e( 'UTM Auto-Injection', 'insight-tracker-pro' ); ?></h2>
                                <p style="color:#6b7280;margin:0 0 16px;"><?php esc_html_e( 'Automatically add UTM parameters to external links in your content.', 'insight-tracker-pro' ); ?></p>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Enable Auto-UTM', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="itp_settings[auto_utm]" value="1" <?php checked( $s['auto_utm'], '1' ); ?>>
                                                <?php esc_html_e( 'Add UTM parameters to all external links in post content', 'insight-tracker-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'utm_source Pattern', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <input type="text" name="itp_settings[utm_source_pattern]" value="<?php echo esc_attr( $s['utm_source_pattern'] ); ?>" class="regular-text" placeholder="{site_name}">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'utm_medium Pattern', 'insight-tracker-pro' ); ?></th>
                                        <td>
                                            <input type="text" name="itp_settings[utm_medium_pattern]" value="<?php echo esc_attr( $s['utm_medium_pattern'] ); ?>" class="regular-text" placeholder="{post_type}_cta">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="itp-admin-section">
                                <h2><?php esc_html_e( 'Available Placeholders', 'insight-tracker-pro' ); ?></h2>
                                <p style="color:#374151;">
                                    <span class="itp-placeholder-tag">{site_name}</span>
                                    <span class="itp-placeholder-tag">{post_type}</span>
                                    <span class="itp-placeholder-tag">{category}</span>
                                    <span class="itp-placeholder-tag">{post_id}</span>
                                    <span class="itp-placeholder-tag">{slug}</span>
                                </p>
                                <table class="widefat striped" style="max-width:600px;margin-top:12px;">
                                    <thead><tr><th><?php esc_html_e( 'Placeholder', 'insight-tracker-pro' ); ?></th><th><?php esc_html_e( 'Replaced With', 'insight-tracker-pro' ); ?></th></tr></thead>
                                    <tbody>
                                        <tr><td><code>{site_name}</code></td><td><?php esc_html_e( 'Sanitized site name (e.g. "my-blog")', 'insight-tracker-pro' ); ?></td></tr>
                                        <tr><td><code>{post_type}</code></td><td><?php esc_html_e( 'Current post type (post, page, product...)', 'insight-tracker-pro' ); ?></td></tr>
                                        <tr><td><code>{category}</code></td><td><?php esc_html_e( 'Primary category slug', 'insight-tracker-pro' ); ?></td></tr>
                                        <tr><td><code>{post_id}</code></td><td><?php esc_html_e( 'Numeric post ID', 'insight-tracker-pro' ); ?></td></tr>
                                        <tr><td><code>{slug}</code></td><td><?php esc_html_e( 'Post slug (URL name)', 'insight-tracker-pro' ); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save Settings', 'insight-tracker-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                    </form>

                    <!-- Documentation tab (outside the form) -->
                    <div id="itp-tab-docs" class="itp-tab-content">

                        <div class="itp-admin-section">
                            <h2><?php esc_html_e( 'Getting Started', 'insight-tracker-pro' ); ?></h2>
                            <ol style="line-height:2;font-size:14px;color:#374151;">
                                <li><?php echo __( 'Activate your <strong>license key</strong> in the License tab', 'insight-tracker-pro' ); ?></li>
                                <li><?php echo __( 'Configure tracking options in <strong>General</strong> settings', 'insight-tracker-pro' ); ?></li>
                                <li><?php echo __( 'Choose which <strong>events to track</strong> in the Tracking tab', 'insight-tracker-pro' ); ?></li>
                                <li><?php echo __( 'Optionally enable <strong>UTM auto-injection</strong> in the UTM tab', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'The tracking script loads automatically on all frontend pages', 'insight-tracker-pro' ); ?></li>
                            </ol>
                        </div>

                        <div class="itp-admin-section">
                            <h2><?php esc_html_e( 'Tracked Events', 'insight-tracker-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Event', 'insight-tracker-pro' ); ?></th><th><?php esc_html_e( 'Description', 'insight-tracker-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Page View', 'insight-tracker-pro' ); ?></strong></td><td><?php esc_html_e( 'Fired on every page load. Includes full WordPress context (post type, categories, tags, template).', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Scroll Depth', 'insight-tracker-pro' ); ?></strong></td><td><?php esc_html_e( 'Fired at 25%, 50%, 75%, and 100% scroll thresholds. Each fires only once per page.', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Time on Page', 'insight-tracker-pro' ); ?></strong></td><td><?php esc_html_e( 'Fired when the user leaves the page. Records total active time in seconds.', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Outbound Click', 'insight-tracker-pro' ); ?></strong></td><td><?php esc_html_e( 'Fired when a user clicks a link to an external domain.', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'CTA Click', 'insight-tracker-pro' ); ?></strong></td><td><?php echo __( 'Fired on elements with <code>data-itp="cta_click"</code> attribute.', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Form Submit', 'insight-tracker-pro' ); ?></strong></td><td><?php echo __( 'Fired on forms with <code>data-itp="form_submit"</code> attribute.', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Search', 'insight-tracker-pro' ); ?></strong></td><td><?php esc_html_e( 'Automatically fired on WordPress search results pages.', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( '404 Error', 'insight-tracker-pro' ); ?></strong></td><td><?php esc_html_e( 'Automatically fired on 404 pages with the requested URL.', 'insight-tracker-pro' ); ?></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="itp-admin-section">
                            <h2><?php esc_html_e( 'UTM Auto-Injection', 'insight-tracker-pro' ); ?></h2>
                            <p style="color:#374151;line-height:1.8;"><?php esc_html_e( 'When enabled, UTM parameters are automatically appended to all external links found in your post content. This allows you to track which content drives traffic to external sites.', 'insight-tracker-pro' ); ?></p>
                            <p style="color:#374151;line-height:1.8;"><?php esc_html_e( 'Patterns support dynamic placeholders that are replaced with values from the current post. For example, a utm_source of "{site_name}" becomes your sanitized site name, and a utm_medium of "{post_type}_cta" becomes "post_cta" on blog posts.', 'insight-tracker-pro' ); ?></p>
                            <p style="color:#374151;line-height:1.8;"><?php esc_html_e( 'Links that already have UTM parameters will not be modified.', 'insight-tracker-pro' ); ?></p>
                        </div>

                        <div class="itp-admin-section">
                            <h2><?php esc_html_e( 'WooCommerce Integration', 'insight-tracker-pro' ); ?></h2>
                            <p style="color:#374151;line-height:1.8;"><?php esc_html_e( 'When WooCommerce is active and WooCommerce tracking is enabled, the following events are tracked automatically:', 'insight-tracker-pro' ); ?></p>
                            <table class="widefat striped" style="max-width:700px;margin-top:12px;">
                                <thead><tr><th><?php esc_html_e( 'Event', 'insight-tracker-pro' ); ?></th><th><?php esc_html_e( 'Trigger', 'insight-tracker-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><code>add_cart</code></td><td><?php esc_html_e( 'Product added to cart', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><code>remove_cart</code></td><td><?php esc_html_e( 'Product removed from cart', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><code>checkout</code></td><td><?php esc_html_e( 'Checkout page loaded', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><code>purchase</code></td><td><?php esc_html_e( 'Order completed (thank you page)', 'insight-tracker-pro' ); ?></td></tr>
                                </tbody>
                            </table>
                            <p style="color:#374151;line-height:1.8;margin-top:12px;"><?php esc_html_e( 'Product pages also include rich product context: name, price, SKU, stock status, categories, and more.', 'insight-tracker-pro' ); ?></p>
                        </div>

                        <div class="itp-admin-section">
                            <h2><?php esc_html_e( 'Cookies', 'insight-tracker-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Cookie', 'insight-tracker-pro' ); ?></th><th><?php esc_html_e( 'Purpose', 'insight-tracker-pro' ); ?></th><th><?php esc_html_e( 'Duration', 'insight-tracker-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><code>_itp_vid</code></td><td><?php esc_html_e( 'Anonymous visitor ID (UUID)', 'insight-tracker-pro' ); ?></td><td><?php esc_html_e( 'Configurable (default 365 days)', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><code>_itp_vn</code></td><td><?php esc_html_e( 'Visit number counter', 'insight-tracker-pro' ); ?></td><td><?php esc_html_e( 'Same as visitor ID', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><code>_itp_ft</code></td><td><?php esc_html_e( 'First touch attribution data', 'insight-tracker-pro' ); ?></td><td><?php esc_html_e( 'Same as visitor ID', 'insight-tracker-pro' ); ?></td></tr>
                                    <tr><td><code>_itp_sid</code></td><td><?php esc_html_e( 'Session ID', 'insight-tracker-pro' ); ?></td><td><?php esc_html_e( 'Session (sessionStorage)', 'insight-tracker-pro' ); ?></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="itp-admin-section" style="background:#fefce8;border-color:#fde68a;">
                            <h2 style="border-color:#fde68a;"><?php esc_html_e( 'Privacy & GDPR', 'insight-tracker-pro' ); ?></h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php esc_html_e( 'No personally identifiable information (PII) is collected by default', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'Visitor IDs are anonymous UUIDs, not linked to personal data', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'IP addresses are not stored in tracking events', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'WordPress user IDs are only included for logged-in users and can be excluded by role', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'You should disclose analytics cookies in your privacy policy', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'If operating in the EU, integrate with a cookie consent solution to conditionally load the tracker', 'insight-tracker-pro' ); ?></li>
                                <li><?php esc_html_e( 'Data is securely sent to our analytics servers — no third-party services involved', 'insight-tracker-pro' ); ?></li>
                            </ul>
                        </div>

                        <div class="itp-admin-section" style="background:#fefce8;border-color:#fde68a;">
                            <h2 style="border-color:#fde68a;"><?php esc_html_e( 'Support', 'insight-tracker-pro' ); ?></h2>
                            <p style="color:#374151;"><?php esc_html_e( 'For any questions or issues:', 'insight-tracker-pro' ); ?></p>
                            <ul style="list-style:none;padding:0;line-height:2.2;">
                                <li><?php esc_html_e( 'Email:', 'insight-tracker-pro' ); ?> <a href="mailto:contact@khalid.digital">contact@khalid.digital</a></li>
                            </ul>
                        </div>

                    </div>

                    <?php endif; ?>

                </div><!-- .itp-settings-panel -->
            </div><!-- .itp-settings-layout -->
        </div><!-- #itp-settings-wrap -->

        <script>
        jQuery(function($) {
            /* ── Tab switching ── */
            var $items = $('.itp-sidebar-item');
            var $tabs  = $('.itp-tab-content');

            function activateTab(slug) {
                $items.removeClass('is-active');
                $tabs.removeClass('is-active');
                $items.filter('[data-tab="' + slug + '"]').addClass('is-active');
                $('#itp-tab-' + slug).addClass('is-active');
                $('#itp_active_tab').val(slug);
                if (history.replaceState) {
                    history.replaceState(null, null, '#' + slug);
                }
            }

            $items.on('click', function(e) {
                e.preventDefault();
                activateTab($(this).data('tab'));
            });

            // Determine initial tab
            var hash = window.location.hash.replace('#', '');
            var validTabs = [];
            $items.each(function() { validTabs.push($(this).data('tab')); });

            if (hash && validTabs.indexOf(hash) !== -1) {
                activateTab(hash);
            } else {
                activateTab(validTabs[0] || 'license');
            }

            /* ── License AJAX ── */
            var licenseNonce = '<?php echo esc_js( $nonce ); ?>';

            $('#itp-activate-btn').on('click', function() {
                var btn = $(this);
                var key = $('#itp-license-key').val().trim();
                if (!key) return;

                btn.prop('disabled', true).text('<?php echo esc_js( __( 'Activating...', 'insight-tracker-pro' ) ); ?>');

                $.post(ajaxurl, {
                    action: 'itp_activate_license',
                    nonce: licenseNonce,
                    license_key: key
                }, function(response) {
                    if (response.success) {
                        $('#itp-license-message').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>').show();
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $('#itp-license-message').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>').show();
                        btn.prop('disabled', false).text('<?php echo esc_js( __( 'Activate License', 'insight-tracker-pro' ) ); ?>');
                    }
                }).fail(function() {
                    $('#itp-license-message').html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Connection error.', 'insight-tracker-pro' ) ); ?></p></div>').show();
                    btn.prop('disabled', false).text('<?php echo esc_js( __( 'Activate License', 'insight-tracker-pro' ) ); ?>');
                });
            });

            $('#itp-deactivate-btn').on('click', function() {
                if (!confirm('<?php echo esc_js( __( 'Deactivate the license on this domain?', 'insight-tracker-pro' ) ); ?>')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js( __( 'Deactivating...', 'insight-tracker-pro' ) ); ?>');

                $.post(ajaxurl, {
                    action: 'itp_deactivate_license',
                    nonce: licenseNonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });

        });
        </script>
        <?php
    }
}
