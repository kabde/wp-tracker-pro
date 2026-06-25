<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Countries {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 24 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Countries', 'insight-tracker-pro' ), __( 'Countries', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-countries', [ $this, 'render' ] );
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '30d';
    }

    private function api() {
        $params = [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => 'countries', 'period' => $this->period(), 'limit' => 50 ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    private function country_name( $code ) {
        $names = [
            'US'=>'United States','FR'=>'France','GB'=>'United Kingdom','DE'=>'Germany','CA'=>'Canada',
            'ES'=>'Spain','IT'=>'Italy','BR'=>'Brazil','IN'=>'India','AU'=>'Australia','JP'=>'Japan',
            'MA'=>'Morocco','NL'=>'Netherlands','BE'=>'Belgium','CH'=>'Switzerland','PT'=>'Portugal',
            'MX'=>'Mexico','AR'=>'Argentina','CO'=>'Colombia','CL'=>'Chile','PL'=>'Poland',
            'SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark','FI'=>'Finland','IE'=>'Ireland',
            'AT'=>'Austria','NZ'=>'New Zealand','SG'=>'Singapore','AE'=>'UAE','SA'=>'Saudi Arabia',
            'EG'=>'Egypt','NG'=>'Nigeria','KE'=>'Kenya','ZA'=>'South Africa','TH'=>'Thailand',
            'VN'=>'Vietnam','ID'=>'Indonesia','PH'=>'Philippines','KR'=>'South Korea','TW'=>'Taiwan',
            'HK'=>'Hong Kong','MY'=>'Malaysia','TR'=>'Turkey','RU'=>'Russia','UA'=>'Ukraine',
            'RO'=>'Romania','CZ'=>'Czech Republic','HU'=>'Hungary','GR'=>'Greece','IL'=>'Israel',
        ];
        return $names[ $code ] ?? $code;
    }

    private function country_flag( $code ) {
        if ( strlen( $code ) !== 2 ) return '';
        $flag = '';
        foreach ( str_split( strtoupper( $code ) ) as $c ) {
            $flag .= mb_chr( 0x1F1E6 + ord( $c ) - 65 );
        }
        return $flag;
    }

    private function fmt_time( $s ) {
        $s = absint( $s );
        if ( $s < 60 ) return $s . 's';
        return floor( $s / 60 ) . 'm ' . str_pad( $s % 60, 2, '0', STR_PAD_LEFT ) . 's';
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );
        $period = $this->period();
        $data   = $this->api();
        $rows   = $data['data'] ?? [];
        $total  = array_sum( array_column( $rows, 'visitors' ) ) ?: 1;
        ?>
        <style>
            .itp-top5-section{margin-top:0}
            .itp-top5{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
            .itp-top5-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;text-align:center}
            .itp-top5-flag{font-size:28px;line-height:1}
            .itp-top5-name{font-size:13px;font-weight:700;color:#1d2327;margin-top:6px}
            .itp-top5-num{font-size:20px;font-weight:800;color:#1d2327;margin-top:4px}
            .itp-top5-pct{font-size:12px;color:#6b7280}
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
            .itp-t{width:100%;border-collapse:collapse}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
            .itp-t th.r,.itp-t td.r{text-align:right}
            .itp-t td{padding:10px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr:hover{background:#f9fafb}
            .itp-co-name{display:flex;align-items:center;gap:8px}
            .itp-co-flag{font-size:18px}
            .itp-co-label{font-weight:700;color:#1d2327}
            .itp-co-code{font-size:.72rem;color:#9ca3af;margin-left:4px}
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
                        <h1 class="itp-title"><?php esc_html_e( 'Countries', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Geographic distribution of your visitors', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d'=>'Today', '7d'=>'7 Days', '30d'=>'30 Days', '90d'=>'90 Days' ] as $p=>$l ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=itp-countries&period='.$p) ); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $rows ) ): ?>
            <div class="itp-top5">
                <?php foreach ( array_slice( $rows, 0, 5 ) as $r ):
                    $pct = round( $r['visitors'] / $total * 100 );
                ?>
                    <div class="itp-top5-card">
                        <div class="itp-top5-flag"><?php echo $this->country_flag( $r['geo_country'] ); ?></div>
                        <div class="itp-top5-name"><?php echo esc_html( $this->country_name( $r['geo_country'] ) ); ?></div>
                        <div class="itp-top5-num"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></div>
                        <div class="itp-top5-pct"><?php echo $pct; ?>% of traffic</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="itp-wrap"><table class="itp-t itp-sortable">
                <thead><tr>
                    <th data-sort="text"><?php esc_html_e( 'Country', 'insight-tracker-pro' ); ?></th>
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
                    <tr><td colspan="12" class="itp-empty"><?php esc_html_e( 'No geo data yet.', 'insight-tracker-pro' ); ?></td></tr>
                <?php else: foreach ( $rows as $r ):
                    $pct    = round( $r['visitors'] / $total * 100 );
                    $bounce = round( $r['bounce_rate'] ?? 0 );
                    $scroll = round( $r['avg_scroll'] ?? 0 );
                    $ctr    = $r['ctr'] ?? 0;
                ?>
                    <tr>
                        <td data-v="<?php echo esc_attr( $this->country_name( $r['geo_country'] ) ); ?>">
                            <div class="itp-co-name">
                                <span class="itp-co-flag"><?php echo $this->country_flag( $r['geo_country'] ); ?></span>
                                <span class="itp-co-label"><?php echo esc_html( $this->country_name( $r['geo_country'] ) ); ?></span>
                                <span class="itp-co-code"><?php echo esc_html( $r['geo_country'] ); ?></span>
                            </div>
                        </td>
                        <td class="r" style="font-weight:700;" data-v="<?php echo esc_attr( $r['visitors'] ); ?>"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></td>
                        <td data-v="<?php echo esc_attr( $pct ); ?>">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="itp-bar"><div class="itp-bar-fill" style="width:<?php echo max(3,$pct); ?>%;background:#2563eb;"></div></div>
                                <span style="font-size:.78rem;color:#6b7280;min-width:28px;"><?php echo $pct; ?>%</span>
                            </div>
                        </td>
                        <td class="r" data-v="<?php echo esc_attr( $r['sessions'] ); ?>"><?php echo esc_html( number_format_i18n( $r['sessions'] ) ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $r['page_views'] ); ?>"><?php echo esc_html( number_format_i18n( $r['page_views'] ) ); ?></td>
                        <td class="r" style="font-weight:600;" data-v="<?php echo esc_attr( $r['pages_per_session'] ?? 0 ); ?>"><?php echo esc_html( $r['pages_per_session'] ?? 0 ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $bounce ); ?>"><span style="color:<?php echo $bounce>60?'#dc2626':($bounce>40?'#ca8a04':'#16a34a'); ?>;font-weight:600;"><?php echo $bounce; ?>%</span></td>
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
