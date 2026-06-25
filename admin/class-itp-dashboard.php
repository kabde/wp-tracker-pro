<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Dashboard {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Dashboard', 'insight-tracker-pro' ), __( 'Dashboard', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-dashboard', [ $this, 'render' ] );
    }

    private function api( $view, $extra = [] ) {
        $params = array_merge( [
            'key'    => get_option( 'itp_license_key', '' ),
            'site'   => wp_parse_url( home_url(), PHP_URL_HOST ),
            'view'   => $view,
            'period' => $this->period(),
        ], $extra );
        // Pass through segment/source/device/country filters from URL
        foreach ( [ 'segment', 'source', 'device', 'country' ] as $f ) {
            if ( ! empty( $_GET[ $f ] ) ) $params[ $f ] = sanitize_text_field( wp_unslash( $_GET[ $f ] ) );
        }
        $url = ITP_API_URL . '/trk' . '?' . http_build_query( $params );
        $r = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '7d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '7d';
    }

    private function filter_url( $overrides = [] ) {
        $base = [ 'page' => 'itp-dashboard', 'period' => $this->period() ];
        foreach ( [ 'segment', 'source', 'device', 'country' ] as $f ) {
            if ( ! empty( $_GET[ $f ] ) ) $base[ $f ] = sanitize_text_field( wp_unslash( $_GET[ $f ] ) );
        }
        return admin_url( 'admin.php?' . http_build_query( array_merge( $base, $overrides ) ) );
    }

    private function pct_bar( $value, $max, $color = '#2563eb' ) {
        $pct = $max > 0 ? round( $value / $max * 100 ) : 0;
        return '<div style="display:flex;align-items:center;gap:8px;"><div style="flex:1;height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden;"><div style="height:100%;width:' . $pct . '%;background:' . esc_attr( $color ) . ';border-radius:4px;"></div></div><span style="font-size:.8rem;color:#6b7280;min-width:36px;text-align:right;">' . esc_html( number_format_i18n( $value ) ) . '</span></div>';
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );

        $data = $this->api( 'overview' );
        $stats = $data['stats'] ?? [];
        $trend = $data['trend'] ?? [];
        $pages = $data['top_pages'] ?? [];
        $sources = $data['sources'] ?? [];
        $countries = $data['countries'] ?? [];
        $period = $this->period();
        $seg = isset( $_GET['segment'] ) ? sanitize_text_field( wp_unslash( $_GET['segment'] ) ) : 'all';
        ?>
        <style>
            .itp{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
            .itp-cards{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:24px}
            .itp-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px 16px;text-align:center}
            .itp-card-n{font-size:28px;font-weight:800;color:#1d2327;line-height:1}
            .itp-card-l{font-size:12px;color:#6b7280;margin-top:6px}
            .itp-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px}
            .itp-box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
            .itp-box-h{padding:14px 18px;font-size:13px;font-weight:700;color:#1d2327;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between}
            .itp-box-h a{font-size:12px;color:#2563eb;text-decoration:none;font-weight:600}
            .itp-box-body{padding:12px 18px}
            .itp-list-row{display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #f9fafb}
            .itp-list-row:last-child{border-bottom:none}
            .itp-list-name{flex:1;font-size:.88rem;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .itp-list-val{font-size:.85rem;font-weight:700;color:#1d2327;min-width:40px;text-align:right}
            .itp-list-pct{font-size:.75rem;color:#9ca3af;min-width:36px;text-align:right}
            .itp-chart{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:24px}
            .itp-chart-h{font-size:13px;font-weight:700;color:#1d2327;margin-bottom:14px}
            .itp-bars{display:flex;align-items:flex-end;gap:2px;height:120px}
            .itp-bar{flex:1;background:#2563eb;border-radius:3px 3px 0 0;min-width:4px;transition:height .3s;position:relative}
            .itp-bar:hover{background:#1d4ed8}
            .itp-bar-tip{display:none;position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1d2327;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;white-space:nowrap;margin-bottom:4px}
            .itp-bar:hover .itp-bar-tip{display:block}
            .itp-bar-labels{display:flex;gap:2px;margin-top:6px}
            .itp-bar-labels span{flex:1;font-size:10px;color:#9ca3af;text-align:center;overflow:hidden;text-overflow:ellipsis}
            .itp-funnel{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:24px}
            .itp-funnel-h{font-size:13px;font-weight:700;color:#1d2327;margin-bottom:14px}
            .itp-funnel-step{display:flex;align-items:center;gap:12px;margin-bottom:8px}
            .itp-funnel-label{min-width:100px;font-size:.85rem;font-weight:600;color:#374151}
            .itp-funnel-bar{flex:1;height:28px;background:#f3f4f6;border-radius:6px;overflow:hidden;position:relative}
            .itp-funnel-fill{height:100%;border-radius:6px;display:flex;align-items:center;padding-left:10px;font-size:.78rem;font-weight:700;color:#fff}
            .itp-funnel-drop{font-size:.75rem;color:#dc2626;min-width:60px;text-align:right}
            .itp-empty{text-align:center;padding:40px;color:#9ca3af;font-size:.9rem}
            @media(max-width:1100px){.itp-cards{grid-template-columns:repeat(3,1fr)}.itp-row{grid-template-columns:1fr}}
            @media(max-width:768px){.itp-cards{grid-template-columns:repeat(2,1fr)}}
        </style>

        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'Analytics Dashboard', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Track your website performance and conversions', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d' => 'Today', '7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days' ] as $p => $l ) : ?>
                            <a href="<?php echo esc_url( $this->filter_url( [ 'period' => $p ] ) ); ?>" class="<?php echo $period === $p ? 'on' : ''; ?>"><?php echo esc_html( $l ); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="itp-filter-bar">
                    <span class="itp-filter-label"><?php esc_html_e( 'Segment', 'insight-tracker-pro' ); ?></span>
                    <select onchange="location.href=this.value">
                        <?php foreach ( [ 'all' => 'All Users', 'new' => 'New Users', 'returning' => 'Returning', 'bouncers' => 'Bouncers' ] as $k => $l ) : ?>
                            <option value="<?php echo esc_url( $this->filter_url( [ 'segment' => $k ] ) ); ?>" <?php selected( $seg, $k ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php /* ── STATS CARDS ── */ ?>
            <div class="itp-cards">
                <?php
                $cards = [
                    [ 'n' => $stats['unique_visitors'] ?? 0, 'l' => 'Visitors', 'fmt' => 'num' ],
                    [ 'n' => $stats['sessions'] ?? 0, 'l' => 'Sessions', 'fmt' => 'num' ],
                    [ 'n' => $stats['page_views'] ?? 0, 'l' => 'Page Views', 'fmt' => 'num' ],
                    [ 'n' => $stats['bounce_rate'] ?? 0, 'l' => 'Bounce Rate', 'fmt' => 'pct' ],
                    [ 'n' => $stats['cta_clicks'] ?? 0, 'l' => 'CTA Clicks', 'fmt' => 'num' ],
                    [ 'n' => $stats['revenue'] ?? 0, 'l' => 'Revenue', 'fmt' => 'money' ],
                ];
                foreach ( $cards as $c ) :
                    if ( $c['fmt'] === 'pct' ) $display = $c['n'] . '%';
                    elseif ( $c['fmt'] === 'money' ) $display = '$' . number_format_i18n( $c['n'] );
                    else $display = number_format_i18n( $c['n'] );
                ?>
                    <div class="itp-card">
                        <div class="itp-card-n"><?php echo esc_html( $display ); ?></div>
                        <div class="itp-card-l"><?php echo esc_html( $c['l'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php /* ── TREND CHART ── */ ?>
            <?php if ( ! empty( $trend ) ) :
                $max_v = max( array_column( $trend, 'visitors' ) ) ?: 1;
            ?>
            <div class="itp-chart">
                <div class="itp-chart-h"><?php esc_html_e( 'Visitors Trend', 'insight-tracker-pro' ); ?></div>
                <div class="itp-bars">
                    <?php foreach ( $trend as $d ) :
                        $h = round( ( $d['visitors'] / $max_v ) * 100 );
                        $day = substr( $d['day'], 5 ); // MM-DD
                    ?>
                        <div class="itp-bar" style="height:<?php echo max( 4, $h ); ?>%;">
                            <div class="itp-bar-tip"><?php echo esc_html( $day . ': ' . $d['visitors'] . ' visitors' ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="itp-bar-labels">
                    <?php foreach ( $trend as $i => $d ) :
                        $show = ( count( $trend ) <= 14 || $i % 3 === 0 );
                    ?>
                        <span><?php echo $show ? esc_html( substr( $d['day'], 5 ) ) : ''; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php /* ── TOP PAGES / SOURCES / COUNTRIES ── */ ?>
            <div class="itp-row">
                <div class="itp-box">
                    <div class="itp-box-h"><?php esc_html_e( 'Top Pages', 'insight-tracker-pro' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=itp-explorer&tab=content&period=' . $period ) ); ?>"><?php esc_html_e( 'View all', 'insight-tracker-pro' ); ?></a></div>
                    <div class="itp-box-body">
                        <?php if ( empty( $pages ) ) : ?>
                            <div class="itp-empty"><?php esc_html_e( 'No data yet', 'insight-tracker-pro' ); ?></div>
                        <?php else :
                            $max_pv = $pages[0]['views'] ?? 1;
                            foreach ( $pages as $pg ) : ?>
                                <div class="itp-list-row">
                                    <span class="itp-list-name" title="<?php echo esc_attr( $pg['page_path'] ); ?>"><?php echo esc_html( $pg['page_path'] ); ?></span>
                                    <span class="itp-list-val"><?php echo esc_html( number_format_i18n( $pg['views'] ) ); ?></span>
                                </div>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>

                <div class="itp-box">
                    <div class="itp-box-h"><?php esc_html_e( 'Traffic Sources', 'insight-tracker-pro' ); ?></div>
                    <div class="itp-box-body">
                        <?php if ( empty( $sources ) ) : ?>
                            <div class="itp-empty"><?php esc_html_e( 'No data yet', 'insight-tracker-pro' ); ?></div>
                        <?php else :
                            $total_src = array_sum( array_column( $sources, 'visitors' ) ) ?: 1;
                            $src_colors = [ '#2563eb', '#16a34a', '#ea580c', '#7c3aed', '#ca8a04', '#0891b2', '#dc2626', '#6366f1' ];
                            foreach ( $sources as $i => $s ) :
                                $pct = round( $s['visitors'] / $total_src * 100 );
                                $color = $src_colors[ $i % count( $src_colors ) ];
                            ?>
                                <div class="itp-list-row">
                                    <span style="width:8px;height:8px;border-radius:2px;background:<?php echo esc_attr( $color ); ?>;flex-shrink:0;"></span>
                                    <span class="itp-list-name"><?php echo esc_html( $s['source'] ); ?></span>
                                    <span class="itp-list-pct"><?php echo esc_html( $pct ); ?>%</span>
                                    <span class="itp-list-val"><?php echo esc_html( number_format_i18n( $s['visitors'] ) ); ?></span>
                                </div>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>

                <div class="itp-box">
                    <div class="itp-box-h"><?php esc_html_e( 'Countries', 'insight-tracker-pro' ); ?></div>
                    <div class="itp-box-body">
                        <?php if ( empty( $countries ) ) : ?>
                            <div class="itp-empty"><?php esc_html_e( 'No data yet', 'insight-tracker-pro' ); ?></div>
                        <?php else :
                            $total_co = array_sum( array_column( $countries, 'visitors' ) ) ?: 1;
                            foreach ( $countries as $co ) :
                                $pct = round( $co['visitors'] / $total_co * 100 );
                            ?>
                                <div class="itp-list-row">
                                    <span class="itp-list-name"><?php echo esc_html( $co['geo_country'] ); ?></span>
                                    <span class="itp-list-pct"><?php echo esc_html( $pct ); ?>%</span>
                                    <span class="itp-list-val"><?php echo esc_html( number_format_i18n( $co['visitors'] ) ); ?></span>
                                </div>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>
            </div>

            <?php /* ── CONVERSION FUNNEL ── */ ?>
            <?php
            $funnel = $this->api( 'funnel', [ 'steps' => 'pv,scroll,cta_click,purchase' ] );
            $funnel_data = $funnel['data'] ?? [];
            if ( ! empty( $funnel_data ) && ( $funnel_data[0]['visitors'] ?? 0 ) > 0 ) :
                $funnel_max = $funnel_data[0]['visitors'];
                $funnel_colors = [ '#2563eb', '#7c3aed', '#16a34a', '#059669', '#ea580c' ];
                $step_labels = [ 'pv' => 'Page View', 'scroll' => 'Scroll', 'cta_click' => 'CTA Click', 'add_cart' => 'Add to Cart', 'checkout' => 'Checkout', 'purchase' => 'Purchase', 'form_submit' => 'Form Submit' ];
            ?>
            <div class="itp-funnel">
                <div class="itp-funnel-h"><?php esc_html_e( 'Conversion Funnel', 'insight-tracker-pro' ); ?></div>
                <?php foreach ( $funnel_data as $i => $step ) :
                    $pct = round( $step['visitors'] / $funnel_max * 100 );
                    $label = $step_labels[ $step['step'] ] ?? $step['step'];
                    $color = $funnel_colors[ $i % count( $funnel_colors ) ];
                    $drop = $i > 0 && $funnel_data[ $i - 1 ]['visitors'] > 0 ? round( ( 1 - $step['visitors'] / $funnel_data[ $i - 1 ]['visitors'] ) * 100 ) : 0;
                ?>
                    <div class="itp-funnel-step">
                        <span class="itp-funnel-label"><?php echo esc_html( $label ); ?></span>
                        <div class="itp-funnel-bar">
                            <div class="itp-funnel-fill" style="width:<?php echo max( 2, $pct ); ?>%;background:<?php echo esc_attr( $color ); ?>;">
                                <?php echo esc_html( number_format_i18n( $step['visitors'] ) ); ?> (<?php echo esc_html( $pct ); ?>%)
                            </div>
                        </div>
                        <?php if ( $i > 0 ) : ?>
                            <span class="itp-funnel-drop">-<?php echo esc_html( $drop ); ?>%</span>
                        <?php else : ?>
                            <span style="min-width:60px;"></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
