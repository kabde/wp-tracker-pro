<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Widget {

    public function __construct() {
        add_action( 'wp_dashboard_setup', [ $this, 'add_widget' ] );
        add_action( 'admin_bar_menu', [ $this, 'admin_bar_realtime' ], 999 );
        add_action( 'admin_head', [ $this, 'admin_bar_css' ] );
        add_action( 'wp_head', [ $this, 'admin_bar_css' ] );
    }

    /* ─── Dashboard Widget ────────────────────────────────── */

    public function add_widget() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) return;
        wp_add_dashboard_widget( 'itp_widget', 'WP Tracker Pro', [ $this, 'render_widget' ] );
    }

    private function api_widget() {
        $cached = get_transient( 'itp_widget_data' );
        if ( $cached !== false ) return $cached;
        $params = [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => 'widget' ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 5 ] );
        if ( is_wp_error( $r ) ) return [];
        $data = json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
        set_transient( 'itp_widget_data', $data, 120 ); // cache 2 min
        return $data;
    }

    public function render_widget() {
        $data      = $this->api_widget();
        $today     = $data['today'] ?? [];
        $yesterday = $data['yesterday'] ?? [];
        $top       = $data['top_page'] ?? [];

        $vis_today = (int) ( $today['visitors'] ?? 0 );
        $vis_yest  = (int) ( $yesterday['visitors'] ?? 0 );
        $pv_today  = (int) ( $today['page_views'] ?? 0 );
        $bounce    = (int) ( $today['bounce_rate'] ?? 0 );
        $cta       = (int) ( $today['cta_clicks'] ?? 0 );

        $delta = $vis_yest > 0 ? round( ( $vis_today - $vis_yest ) / $vis_yest * 100 ) : 0;
        $delta_sign = $delta > 0 ? '+' : '';
        $delta_color = $delta >= 0 ? '#16a34a' : '#dc2626';
        ?>
        <style>
            #itp_widget .itp-w-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
            #itp_widget .itp-w-card{text-align:center;padding:10px 6px;background:#f9fafb;border-radius:8px}
            #itp_widget .itp-w-n{font-size:22px;font-weight:800;color:#1d2327;line-height:1}
            #itp_widget .itp-w-l{font-size:10px;color:#6b7280;margin-top:3px;text-transform:uppercase;letter-spacing:.3px}
            #itp_widget .itp-w-delta{font-size:11px;font-weight:600;margin-top:2px}
            #itp_widget .itp-w-top{background:#f9fafb;border-radius:8px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
            #itp_widget .itp-w-top-path{font-size:13px;font-weight:600;color:#1d2327;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
            #itp_widget .itp-w-top-views{font-size:13px;font-weight:700;color:#6b7280;flex-shrink:0;margin-left:10px}
            #itp_widget .itp-w-link{display:block;text-align:center;padding:8px;font-size:12px;font-weight:600;color:#1d2327;text-decoration:none;background:#f9fafb;border-radius:8px;transition:all .15s}
            #itp_widget .itp-w-link:hover{background:#1d2327;color:#ffc45e}
        </style>
        <div class="itp-w-cards">
            <div class="itp-w-card">
                <div class="itp-w-n"><?php echo esc_html( $vis_today ); ?></div>
                <div class="itp-w-l"><?php esc_html_e( 'Visitors', 'insight-tracker-pro' ); ?></div>
                <?php if ( $delta !== 0 ): ?>
                    <div class="itp-w-delta" style="color:<?php echo esc_attr( $delta_color ); ?>"><?php echo esc_html( $delta_sign . $delta . '%' ); ?></div>
                <?php endif; ?>
            </div>
            <div class="itp-w-card">
                <div class="itp-w-n"><?php echo esc_html( $pv_today ); ?></div>
                <div class="itp-w-l"><?php esc_html_e( 'Page Views', 'insight-tracker-pro' ); ?></div>
            </div>
            <div class="itp-w-card">
                <div class="itp-w-n"><?php echo esc_html( $bounce ); ?>%</div>
                <div class="itp-w-l"><?php esc_html_e( 'Bounce', 'insight-tracker-pro' ); ?></div>
            </div>
            <div class="itp-w-card">
                <div class="itp-w-n"><?php echo esc_html( $cta ); ?></div>
                <div class="itp-w-l"><?php esc_html_e( 'CTA', 'insight-tracker-pro' ); ?></div>
            </div>
        </div>

        <?php if ( ! empty( $top['page_path'] ) ): ?>
        <div class="itp-w-top">
            <span class="itp-w-top-path" title="<?php echo esc_attr( $top['page_path'] ); ?>"><?php echo esc_html( $top['page_path'] ); ?></span>
            <span class="itp-w-top-views"><?php echo esc_html( $top['views'] ?? 0 ); ?> views</span>
        </div>
        <?php endif; ?>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=itp-dashboard' ) ); ?>" class="itp-w-link"><?php esc_html_e( 'View full dashboard', 'insight-tracker-pro' ); ?> &rarr;</a>
        <?php
    }

    /* ─── Admin Bar Real-time Counter ─────────────────────── */

    private function get_active_count() {
        $cached = get_transient( 'itp_realtime_count' );
        if ( $cached !== false ) return (int) $cached;
        $params = [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => 'realtime' ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 3 ] );
        if ( is_wp_error( $r ) ) return 0;
        $data = json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
        $count = (int) ( $data['active']['active_visitors'] ?? 0 );
        set_transient( 'itp_realtime_count', $count, 60 ); // cache 1 min
        return $count;
    }

    public function admin_bar_realtime( $wp_admin_bar ) {
        if ( ! current_user_can( ITP_CAPABILITY ) ) return;
        $count = $this->get_active_count();
        $wp_admin_bar->add_node( [
            'id'    => 'itp-realtime',
            'title' => '<span class="itp-ab-dot"></span> <span class="itp-ab-count">' . esc_html( $count ) . '</span> <span class="itp-ab-label">online</span>',
            'href'  => admin_url( 'admin.php?page=itp-live' ),
            'meta'  => [ 'title' => sprintf( __( '%d visitors in the last 5 minutes', 'insight-tracker-pro' ), $count ) ],
        ] );
    }

    public function admin_bar_css() {
        if ( ! is_admin_bar_showing() || ! current_user_can( ITP_CAPABILITY ) ) return;
        ?>
        <style>
            #wp-admin-bar-itp-realtime .itp-ab-dot{display:inline-block;width:7px;height:7px;background:#22c55e;border-radius:50%;margin-right:3px;animation:itp-ab-pulse 2s infinite}
            #wp-admin-bar-itp-realtime .itp-ab-count{font-weight:700;color:#fff}
            #wp-admin-bar-itp-realtime .itp-ab-label{color:#a0a0a0;font-size:12px}
            @keyframes itp-ab-pulse{0%,100%{opacity:1}50%{opacity:.4}}
        </style>
        <?php
    }
}
