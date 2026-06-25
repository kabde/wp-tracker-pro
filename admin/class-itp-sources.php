<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Sources {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 23 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Traffic Sources', 'insight-tracker-pro' ), __( 'Traffic Sources', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-sources', [ $this, 'render' ] );
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '30d';
    }

    private function api() {
        $params = [
            'key'    => get_option( 'itp_license_key', '' ),
            'site'   => wp_parse_url( home_url(), PHP_URL_HOST ),
            'view'   => 'sources',
            'period' => $this->period(),
            'limit'  => 50,
        ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    private function type_badge( $type ) {
        $colors = [
            'organic'  => '#16a34a',
            'paid'     => '#ea580c',
            'social'   => '#2563eb',
            'email'    => '#7c3aed',
            'referral' => '#0891b2',
            'direct'   => '#6b7280',
        ];
        $labels = [
            'organic'  => 'Organic',
            'paid'     => 'Paid',
            'social'   => 'Social',
            'email'    => 'Email',
            'referral' => 'Referral',
            'direct'   => 'Direct',
        ];
        $color = $colors[ $type ] ?? '#6b7280';
        $label = $labels[ $type ] ?? ucfirst( $type ?: 'Other' );
        return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:.7rem;font-weight:700;color:#fff;background:' . esc_attr( $color ) . ';">' . esc_html( $label ) . '</span>';
    }

    private function fmt_time( $seconds ) {
        $s = absint( $seconds );
        if ( $s < 60 ) return $s . 's';
        return floor( $s / 60 ) . 'm ' . str_pad( $s % 60, 2, '0', STR_PAD_LEFT ) . 's';
    }

    private function score_color( $bounce, $ctr, $pages_per ) {
        // Simple scoring: lower bounce + higher CTR + more pages = better
        $score = 0;
        if ( $bounce < 40 ) $score += 2;
        elseif ( $bounce < 60 ) $score += 1;
        if ( $ctr > 5 ) $score += 2;
        elseif ( $ctr > 2 ) $score += 1;
        if ( $pages_per > 2 ) $score += 1;
        if ( $score >= 4 ) return '#16a34a'; // great
        if ( $score >= 2 ) return '#ca8a04'; // ok
        return '#dc2626'; // needs work
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );
        $period = $this->period();
        $data   = $this->api();
        $rows   = $data['data'] ?? [];
        $total_visitors = array_sum( array_column( $rows, 'visitors' ) ) ?: 1;
        ?>
        <style>
            .itp-summary-section{margin-top:0}
            .itp-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
            .itp-sum-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center}
            .itp-sum-n{font-size:24px;font-weight:800;color:#1d2327}
            .itp-sum-l{font-size:12px;color:#6b7280;margin-top:4px}
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
            .itp-t{width:100%;border-collapse:collapse}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
            .itp-t th.right,.itp-t td.right{text-align:right}
            .itp-t td{padding:10px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr:hover{background:#f9fafb}
            .itp-src-name{font-weight:700;color:#1d2327;font-size:.9rem}
            .itp-bar{height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden;min-width:60px}
            .itp-bar-fill{height:100%;border-radius:3px}
            .itp-score{display:inline-block;width:8px;height:8px;border-radius:50%}
            .itp-empty{text-align:center;padding:60px;color:#9ca3af}
            .itp-medium{font-size:.72rem;color:#9ca3af;margin-top:2px}
            @media(max-width:960px){.itp-summary{grid-template-columns:repeat(2,1fr)}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'Traffic Sources', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Where your traffic is coming from', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d'=>'Today', '7d'=>'7 Days', '30d'=>'30 Days', '90d'=>'90 Days' ] as $p=>$l ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=itp-sources&period='.$p) ); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php
            // Summary by type
            $by_type = [];
            foreach ( $rows as $r ) {
                $t = ($r['type'] ?? '') ?: 'other';
                if ( ! isset( $by_type[$t] ) ) $by_type[$t] = 0;
                $by_type[$t] += $r['visitors'];
            }
            arsort( $by_type );
            ?>
            <div class="itp-summary">
                <?php foreach ( array_slice( $by_type, 0, 4, true ) as $type => $count ):
                    $pct = round( $count / $total_visitors * 100 );
                ?>
                    <div class="itp-sum-card">
                        <div class="itp-sum-n"><?php echo esc_html( number_format_i18n( $count ) ); ?> <span style="font-size:14px;color:#6b7280;">(<?php echo $pct; ?>%)</span></div>
                        <div class="itp-sum-l"><?php echo $this->type_badge( $type ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="itp-wrap"><table class="itp-t itp-sortable">
                <thead><tr>
                    <th></th>
                    <th data-sort="text"><?php esc_html_e( 'Source', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Type', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Visitors', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="num"><?php esc_html_e( 'Share', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Sessions', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Page Views', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Pages/Session', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Bounce Rate', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Avg Scroll', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'Avg Time', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'CTA Clicks', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'CTR', 'insight-tracker-pro' ); ?></th>
                    <th class="right" data-sort="num"><?php esc_html_e( 'New', 'insight-tracker-pro' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $rows ) ): ?>
                    <tr><td colspan="14" class="itp-empty"><?php esc_html_e( 'No traffic data yet.', 'insight-tracker-pro' ); ?></td></tr>
                <?php else: foreach ( $rows as $r ):
                    $pct = round( $r['visitors'] / $total_visitors * 100 );
                    $bounce = round( $r['bounce_rate'] ?? 0 );
                    $scroll = round( $r['avg_scroll'] ?? 0 );
                    $ctr = $r['ctr'] ?? 0;
                    $pps = $r['pages_per_session'] ?? 0;
                    $score_color = $this->score_color( $bounce, $ctr, $pps );
                ?>
                    <tr>
                        <td><span class="itp-score" style="background:<?php echo esc_attr( $score_color ); ?>;"></span></td>
                        <td>
                            <div class="itp-src-name"><?php echo esc_html( $r['source'] ); ?></div>
                            <?php if ( ! empty( $r['medium'] ) ): ?><div class="itp-medium"><?php echo esc_html( $r['medium'] ); ?></div><?php endif; ?>
                        </td>
                        <td data-v="<?php echo esc_attr( $r['type'] ); ?>"><?php echo $this->type_badge( $r['type'] ); ?></td>
                        <td class="right" style="font-weight:700;" data-v="<?php echo esc_attr( $r['visitors'] ); ?>"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></td>
                        <td data-v="<?php echo esc_attr( $pct ); ?>">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="itp-bar"><div class="itp-bar-fill" style="width:<?php echo max(3,$pct); ?>%;background:#2563eb;"></div></div>
                                <span style="font-size:.78rem;color:#6b7280;min-width:28px;"><?php echo $pct; ?>%</span>
                            </div>
                        </td>
                        <td class="right" data-v="<?php echo esc_attr( $r['sessions'] ); ?>"><?php echo esc_html( number_format_i18n( $r['sessions'] ) ); ?></td>
                        <td class="right" data-v="<?php echo esc_attr( $r['page_views'] ); ?>"><?php echo esc_html( number_format_i18n( $r['page_views'] ) ); ?></td>
                        <td class="right" style="font-weight:600;" data-v="<?php echo esc_attr( $pps ); ?>"><?php echo esc_html( $pps ); ?></td>
                        <td class="right" data-v="<?php echo esc_attr( $bounce ); ?>"><span style="color:<?php echo $bounce>60?'#dc2626':($bounce>40?'#ca8a04':'#16a34a'); ?>;font-weight:600;"><?php echo esc_html( $bounce ); ?>%</span></td>
                        <td class="right" data-v="<?php echo esc_attr( $scroll ); ?>"><?php echo $scroll > 0 ? esc_html( $scroll . '%' ) : '—'; ?></td>
                        <td class="right" data-v="<?php echo esc_attr( $r['avg_time_seconds'] ?? 0 ); ?>"><?php echo esc_html( $this->fmt_time( $r['avg_time_seconds'] ?? 0 ) ); ?></td>
                        <td class="right" data-v="<?php echo esc_attr( $r['cta_clicks'] ); ?>"><?php echo esc_html( number_format_i18n( $r['cta_clicks'] ) ); ?></td>
                        <td class="right" style="font-weight:600;color:<?php echo $ctr>5?'#16a34a':($ctr>2?'#ca8a04':'#6b7280'); ?>;" data-v="<?php echo esc_attr( $ctr ); ?>"><?php echo esc_html( $ctr ); ?>%</td>
                        <td class="right" style="font-size:.82rem;color:#6b7280;" data-v="<?php echo esc_attr( $r['new_visitors'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( $r['new_visitors'] ?? 0 ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>
        <?php
    }
}
