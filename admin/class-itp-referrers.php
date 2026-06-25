<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Referrers {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 25 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Referrers', 'insight-tracker-pro' ), __( 'Referrers', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-referrers', [ $this, 'render' ] );
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '30d';
    }

    private function api() {
        $params = [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => 'referrers', 'period' => $this->period(), 'limit' => 50 ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    private function fmt_time( $s ) {
        $s = absint( $s );
        if ( $s < 60 ) return $s . 's';
        return floor( $s / 60 ) . 'm ' . str_pad( $s % 60, 2, '0', STR_PAD_LEFT ) . 's';
    }

    private function favicon_url( $domain ) {
        return 'https://www.google.com/s2/favicons?sz=16&domain=' . urlencode( $domain );
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );
        $period = $this->period();
        $data   = $this->api();
        $rows   = $data['data'] ?? [];
        $total  = array_sum( array_column( $rows, 'visitors' ) ) ?: 1;
        ?>
        <style>
            .itp-top5{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
            .itp-top5-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;text-align:center}
            .itp-top5-icon{margin-bottom:6px}
            .itp-top5-icon img{width:24px;height:24px;border-radius:4px}
            .itp-top5-name{font-size:13px;font-weight:700;color:#1d2327;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .itp-top5-num{font-size:20px;font-weight:800;color:#1d2327;margin-top:4px}
            .itp-top5-pct{font-size:12px;color:#6b7280}
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
            .itp-t{width:100%;border-collapse:collapse}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
            .itp-t th.r,.itp-t td.r{text-align:right}
            .itp-t td{padding:10px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr:hover{background:#f9fafb}
            .itp-ref-name{display:flex;align-items:center;gap:8px}
            .itp-ref-name img{width:16px;height:16px;border-radius:3px}
            .itp-ref-label{font-weight:700;color:#1d2327}
            .itp-bar{height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden;min-width:60px}
            .itp-bar-fill{height:100%;border-radius:3px}
            .itp-empty{text-align:center;padding:60px;color:#9ca3af}
            @media(max-width:960px){.itp-top5{grid-template-columns:repeat(3,1fr)}}
            @media(max-width:600px){.itp-top5{grid-template-columns:repeat(2,1fr)}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'Referrers', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Domains sending traffic to your site', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d'=>'Today', '7d'=>'7 Days', '30d'=>'30 Days', '90d'=>'90 Days' ] as $p=>$l ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=itp-referrers&period='.$p) ); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $rows ) ): ?>
            <div class="itp-top5">
                <?php foreach ( array_slice( $rows, 0, 5 ) as $r ):
                    $pct = round( $r['visitors'] / $total * 100 );
                    $domain = $r['referrer_domain'];
                ?>
                    <div class="itp-top5-card">
                        <div class="itp-top5-icon"><img src="<?php echo esc_url( $this->favicon_url( $domain ) ); ?>" alt="" loading="lazy"></div>
                        <div class="itp-top5-name" title="<?php echo esc_attr( $domain ); ?>"><?php echo esc_html( $domain ); ?></div>
                        <div class="itp-top5-num"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></div>
                        <div class="itp-top5-pct"><?php echo esc_html( $pct ); ?>% of referral traffic</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="itp-wrap"><table class="itp-t itp-sortable">
                <thead><tr>
                    <th data-sort="text"><?php esc_html_e( 'Domain', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Visitors', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="num"><?php esc_html_e( 'Share', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Sessions', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Page Views', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Pages/Sess', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Bounce', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Avg Scroll', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Avg Time', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'CTA Clicks', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'CTR', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'New', 'insight-tracker-pro' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $rows ) ): ?>
                    <tr><td colspan="12" class="itp-empty"><?php esc_html_e( 'No referrer data yet.', 'insight-tracker-pro' ); ?></td></tr>
                <?php else: foreach ( $rows as $r ):
                    $pct    = round( $r['visitors'] / $total * 100 );
                    $bounce = round( $r['bounce_rate'] ?? 0 );
                    $scroll = round( $r['avg_scroll'] ?? 0 );
                    $ctr    = $r['ctr'] ?? 0;
                    $domain = $r['referrer_domain'];
                ?>
                    <tr>
                        <td data-v="<?php echo esc_attr( $domain ); ?>">
                            <div class="itp-ref-name">
                                <img src="<?php echo esc_url( $this->favicon_url( $domain ) ); ?>" alt="" loading="lazy">
                                <span class="itp-ref-label"><?php echo esc_html( $domain ); ?></span>
                            </div>
                        </td>
                        <td class="r" style="font-weight:700;" data-v="<?php echo esc_attr( $r['visitors'] ); ?>"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></td>
                        <td data-v="<?php echo esc_attr( $pct ); ?>">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="itp-bar"><div class="itp-bar-fill" style="width:<?php echo max(3,$pct); ?>%;background:#0891b2;"></div></div>
                                <span style="font-size:.78rem;color:#6b7280;min-width:28px;"><?php echo esc_html( $pct ); ?>%</span>
                            </div>
                        </td>
                        <td class="r" data-v="<?php echo esc_attr( $r['sessions'] ); ?>"><?php echo esc_html( number_format_i18n( $r['sessions'] ) ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $r['page_views'] ); ?>"><?php echo esc_html( number_format_i18n( $r['page_views'] ) ); ?></td>
                        <td class="r" style="font-weight:600;" data-v="<?php echo esc_attr( $r['pages_per_session'] ?? 0 ); ?>"><?php echo esc_html( $r['pages_per_session'] ?? 0 ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $bounce ); ?>"><span style="color:<?php echo $bounce>60?'#dc2626':($bounce>40?'#ca8a04':'#16a34a'); ?>;font-weight:600;"><?php echo esc_html( $bounce ); ?>%</span></td>
                        <td class="r" data-v="<?php echo esc_attr( $scroll ); ?>"><?php echo $scroll > 0 ? esc_html( $scroll . '%' ) : '—'; ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $r['avg_time_seconds'] ?? 0 ); ?>"><?php echo esc_html( $this->fmt_time( $r['avg_time_seconds'] ?? 0 ) ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $r['cta_clicks'] ); ?>"><?php echo esc_html( number_format_i18n( $r['cta_clicks'] ) ); ?></td>
                        <td class="r" style="font-weight:600;color:<?php echo $ctr>5?'#16a34a':($ctr>2?'#ca8a04':'#6b7280'); ?>;" data-v="<?php echo esc_attr( $ctr ); ?>"><?php echo esc_html( $ctr ); ?>%</td>
                        <td class="r" style="font-size:.82rem;color:#6b7280;" data-v="<?php echo esc_attr( $r['new_visitors'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( $r['new_visitors'] ?? 0 ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>
        <?php
    }
}
