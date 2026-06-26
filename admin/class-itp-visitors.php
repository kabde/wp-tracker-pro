<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Visitors {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 22 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Visitors', 'insight-tracker-pro' ), __( 'Visitors', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-visitors', [ $this, 'render' ] );
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '30d';
    }

    private function api( $view, $extra = [] ) {
        $params = array_merge( [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => $view, 'period' => $this->period(), 'limit' => 50 ], $extra );
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    private function fmt_dur( $s ) {
        $s = absint( $s );
        if ( $s < 60 ) return $s . 's';
        return floor( $s / 60 ) . 'm ' . str_pad( $s % 60, 2, '0', STR_PAD_LEFT ) . 's';
    }

    private function dev_icon( $t ) {
        if ( $t === 'mobile' ) return '&#128241;';
        if ( $t === 'tablet' ) return '&#128242;';
        return '&#128421;';
    }

    private function country_flag( $code ) {
        if ( strlen( $code ) !== 2 ) return '';
        $flag = '';
        foreach ( str_split( strtoupper( $code ) ) as $c ) {
            $flag .= mb_chr( 0x1F1E6 + ord( $c ) - 65 );
        }
        return $flag;
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );

        $vid = isset( $_GET['vid'] ) ? sanitize_text_field( wp_unslash( $_GET['vid'] ) ) : '';

        if ( $vid ) {
            $this->render_profile( $vid );
        } else {
            $this->render_listing();
        }
    }

    private function render_listing() {
        $period = $this->period();
        $data   = $this->api( 'visitors' );
        $rows   = $data['data'] ?? [];
        ?>
        <style>
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .itp-t{min-width:1100px;width:100%;border-collapse:collapse}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
            .itp-t th.r,.itp-t td.r{text-align:right}
            .itp-t td{padding:10px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr:hover{background:#f9fafb}
            .itp-vid-link{font-family:monospace;font-size:.82rem;color:#2563eb;text-decoration:none;font-weight:600}
            .itp-vid-link:hover{text-decoration:underline}
            .itp-role{display:inline-block;background:#dbeafe;color:#1d4ed8;font-size:.6rem;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:3px;text-transform:uppercase}
            .itp-anon{color:#9ca3af;font-size:.82rem}
            .itp-empty{text-align:center;padding:60px;color:#9ca3af}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'Visitors', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'All unique visitors on your site', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d'=>'Today', '7d'=>'7 Days', '30d'=>'30 Days', '90d'=>'90 Days' ] as $p=>$l ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=itp-visitors&period='.$p) ); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="itp-wrap"><table class="itp-t itp-sortable">
                <thead><tr>
                    <th data-sort="text"><?php esc_html_e( 'Visitor ID', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'WP User', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Sessions', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Page Views', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'First Seen', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Last Seen', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Device', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Country', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'CTA Clicks', 'insight-tracker-pro' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $rows ) ): ?>
                    <tr><td colspan="9" class="itp-empty"><?php esc_html_e( 'No visitors yet.', 'insight-tracker-pro' ); ?></td></tr>
                <?php else: foreach ( $rows as $r ):
                    $full_vid   = $r['visitor_id'] ?? '';
                    $short_vid  = substr( $full_vid, 0, 11 );
                    $wp_user_id = intval( $r['wp_user_id'] ?? 0 );
                    $sessions   = $r['total_sessions'] ?? 0;
                    $pvs        = $r['total_page_views'] ?? 0;
                    $first_seen = substr( $r['first_seen'] ?? '', 0, 10 );
                    $last_seen  = substr( $r['last_seen'] ?? '', 0, 10 );
                    $dev        = $r['device_type'] ?? '';
                    $co         = $r['geo_country'] ?? '';
                    $cta        = $r['cta_clicks'] ?? 0;

                    $wp_display = '';
                    $wp_role    = '';
                    if ( $wp_user_id > 0 ) {
                        $udata = get_userdata( $wp_user_id );
                        if ( $udata ) {
                            $wp_display = $udata->display_name;
                            $wp_role    = ! empty( $udata->roles ) ? $udata->roles[0] : '';
                        }
                    }
                ?>
                    <tr>
                        <td data-v="<?php echo esc_attr( $short_vid ); ?>">
                            <a class="itp-vid-link" href="<?php echo esc_url( admin_url( 'admin.php?page=itp-visitors&vid=' . $full_vid . '&period=' . $period ) ); ?>"><?php echo esc_html( $short_vid ); ?></a>
                        </td>
                        <td data-v="<?php echo esc_attr( $wp_display ?: 'Anonymous' ); ?>">
                            <?php if ( $wp_user_id > 0 && $wp_display ): ?>
                                <span style="font-weight:600;color:#1d2327;"><?php echo esc_html( $wp_display ); ?></span>
                                <?php if ( $wp_role ): ?><span class="itp-role"><?php echo esc_html( $wp_role ); ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="itp-anon"><?php esc_html_e( 'Anonymous', 'insight-tracker-pro' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="r" style="font-weight:700;" data-v="<?php echo esc_attr( $sessions ); ?>"><?php echo esc_html( number_format_i18n( $sessions ) ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $pvs ); ?>"><?php echo esc_html( number_format_i18n( $pvs ) ); ?></td>
                        <td data-v="<?php echo esc_attr( $first_seen ); ?>"><?php echo esc_html( $first_seen ); ?></td>
                        <td data-v="<?php echo esc_attr( $last_seen ); ?>"><?php echo esc_html( $last_seen ); ?></td>
                        <td data-v="<?php echo esc_attr( $dev ); ?>"><?php echo $this->dev_icon( $dev ); ?></td>
                        <td data-v="<?php echo esc_attr( $co ); ?>"><?php echo $this->country_flag( $co ); ?> <?php echo esc_html( $co ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $cta ); ?>"><?php echo esc_html( number_format_i18n( $cta ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>
        <?php
    }

    private function render_profile( $vid ) {
        $period    = $this->period();
        $data      = $this->api( 'visitor_profile', [ 'vid' => $vid ] );
        $profile   = $data['visitor'] ?? [];
        $sessions  = $data['sessions'] ?? [];
        $short_vid = substr( $vid, 0, 11 );

        $total_sessions = $profile['total_sessions'] ?? 0;
        $total_pages    = $profile['total_page_views'] ?? 0;
        $first_seen     = $profile['first_seen'] ?? '';
        $last_seen      = $profile['last_seen'] ?? '';
        $wp_user_id     = intval( $profile['wp_user_id'] ?? 0 );
        $dev            = $profile['device_type'] ?? '';
        $browser        = ( $profile['browser_name'] ?? '' ) . ' ' . ( $profile['browser_version'] ?? '' );
        $os             = ( $profile['os_name'] ?? '' ) . ' ' . ( $profile['os_version'] ?? '' );
        $screen         = $profile['screen_resolution'] ?? '';
        $language       = $profile['user_language'] ?? '';
        $timezone       = $profile['timezone'] ?? '';
        $co             = $profile['geo_country'] ?? '';
        $city           = $profile['geo_city'] ?? '';
        $cta_clicks     = $profile['cta_clicks'] ?? 0;
        $outbound       = $profile['outbound_clicks'] ?? 0;
        $form_submits   = $profile['form_submits'] ?? 0;
        $purchases      = $profile['purchases'] ?? 0;
        $revenue        = $profile['revenue'] ?? 0;
        $max_scroll     = round( $profile['max_scroll'] ?? 0 );

        $wp_display = '';
        $wp_role    = '';
        if ( $wp_user_id > 0 ) {
            $udata = get_userdata( $wp_user_id );
            if ( $udata ) {
                $wp_display = $udata->display_name;
                $wp_role    = ! empty( $udata->roles ) ? $udata->roles[0] : '';
            }
        }
        ?>
        <style>
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .itp-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
            .itp-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center}
            .itp-card-label{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;font-weight:700}
            .itp-card-val{font-size:22px;font-weight:800;color:#1d2327;margin-top:4px}
            .itp-detail-top{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border-bottom:1px solid #e5e7eb}
            .itp-detail-sec{padding:16px 20px;border-right:1px solid #e5e7eb}
            .itp-detail-sec:last-child{border-right:none}
            .itp-detail-sec h4{margin:0 0 10px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:700}
            .itp-dr{display:flex;gap:6px;padding:3px 0;font-size:.82rem}
            .itp-dk{color:#6b7280;min-width:100px;white-space:nowrap}
            .itp-dv{color:#1d2327;font-weight:500;word-break:break-all}
            .itp-dv code{background:#f3f4f6;padding:1px 6px;border-radius:3px;font-size:.78rem;font-family:monospace}
            .itp-role{display:inline-block;background:#dbeafe;color:#1d4ed8;font-size:.6rem;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:3px;text-transform:uppercase}
            .itp-t{min-width:900px;width:100%;border-collapse:collapse}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
            .itp-t th.r,.itp-t td.r{text-align:right}
            .itp-t td{padding:10px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr:hover{background:#f9fafb}
            .itp-back{display:inline-flex;align-items:center;gap:6px;margin-top:20px;padding:8px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-size:.85rem;font-weight:600;text-decoration:none;transition:border-color .2s}
            .itp-back:hover{border-color:#ffc45e;color:#1d2327}
            .itp-empty{text-align:center;padding:60px;color:#9ca3af}
            @media(max-width:960px){.itp-cards{grid-template-columns:repeat(2,1fr)}}
            @media(max-width:1100px){.itp-detail-top{grid-template-columns:1fr}.itp-detail-sec{border-right:none;border-bottom:1px solid #e5e7eb}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title" style="font-family:monospace;"><?php echo esc_html( $short_vid ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Visitor profile', 'insight-tracker-pro' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="itp-cards">
                <div class="itp-card">
                    <div class="itp-card-label"><?php esc_html_e( 'Total Sessions', 'insight-tracker-pro' ); ?></div>
                    <div class="itp-card-val"><?php echo esc_html( number_format_i18n( $total_sessions ) ); ?></div>
                </div>
                <div class="itp-card">
                    <div class="itp-card-label"><?php esc_html_e( 'Total Pages', 'insight-tracker-pro' ); ?></div>
                    <div class="itp-card-val"><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></div>
                </div>
                <div class="itp-card">
                    <div class="itp-card-label"><?php esc_html_e( 'First Seen', 'insight-tracker-pro' ); ?></div>
                    <div class="itp-card-val" style="font-size:16px;"><?php echo esc_html( $first_seen ); ?></div>
                </div>
                <div class="itp-card">
                    <div class="itp-card-label"><?php esc_html_e( 'Last Seen', 'insight-tracker-pro' ); ?></div>
                    <div class="itp-card-val" style="font-size:16px;"><?php echo esc_html( $last_seen ); ?></div>
                </div>
            </div>

            <div class="itp-wrap" style="margin-bottom:24px;">
                <div class="itp-detail-top">
                    <div class="itp-detail-sec">
                        <h4><?php esc_html_e( 'Identity', 'insight-tracker-pro' ); ?></h4>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Visitor ID', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><code><?php echo esc_html( $vid ); ?></code></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'WP User', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv">
                                <?php if ( $wp_user_id > 0 && $wp_display ): ?>
                                    <?php echo esc_html( $wp_display ); ?>
                                    <?php if ( $wp_role ): ?><span class="itp-role"><?php echo esc_html( $wp_role ); ?></span><?php endif; ?>
                                <?php else: ?>
                                    <?php esc_html_e( 'Anonymous', 'insight-tracker-pro' ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Device', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo $this->dev_icon( $dev ); ?> <?php echo esc_html( ucfirst( $dev ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Browser', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( trim( $browser ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'OS', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( trim( $os ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Screen', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( $screen ?: '—' ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Language', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( $language ?: '—' ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Timezone', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( $timezone ?: '—' ); ?></span>
                        </div>
                    </div>

                    <div class="itp-detail-sec">
                        <h4><?php esc_html_e( 'Location', 'insight-tracker-pro' ); ?></h4>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Country', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo $this->country_flag( $co ); ?> <?php echo esc_html( $co ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'City', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( $city ?: '—' ); ?></span>
                        </div>
                    </div>

                    <div class="itp-detail-sec">
                        <h4><?php esc_html_e( 'Engagement', 'insight-tracker-pro' ); ?></h4>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'CTA Clicks', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv" style="font-weight:700;"><?php echo esc_html( number_format_i18n( $cta_clicks ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Outbound Clicks', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( number_format_i18n( $outbound ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Form Submits', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( number_format_i18n( $form_submits ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Purchases', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo esc_html( number_format_i18n( $purchases ) ); ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Revenue', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo $revenue > 0 ? esc_html( '$' . number_format_i18n( $revenue, 2 ) ) : '—'; ?></span>
                        </div>
                        <div class="itp-dr">
                            <span class="itp-dk"><?php esc_html_e( 'Max Scroll', 'insight-tracker-pro' ); ?></span>
                            <span class="itp-dv"><?php echo $max_scroll > 0 ? esc_html( $max_scroll . '%' ) : '—'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <h3 style="font-size:14px;font-weight:700;color:#1d2327;margin:0 0 10px;"><?php esc_html_e( 'Sessions', 'insight-tracker-pro' ); ?></h3>
            <div class="itp-wrap"><table class="itp-t itp-sortable">
                <thead><tr>
                    <th data-sort="text"><?php esc_html_e( 'Session Start', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Landing Page', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Pages', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Scroll', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Duration', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Source', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Country', 'insight-tracker-pro' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $sessions ) ): ?>
                    <tr><td colspan="7" class="itp-empty"><?php esc_html_e( 'No sessions found.', 'insight-tracker-pro' ); ?></td></tr>
                <?php else: foreach ( $sessions as $s ):
                    $start   = $s['session_start'] ?? '';
                    $landing = $s['landing_page'] ?? '/';
                    $pages   = $s['pages'] ?? 0;
                    $scroll  = round( $s['max_scroll'] ?? 0 );
                    $dur     = $s['duration_seconds'] ?? 0;
                    $source  = $s['utm_source'] ?? '';
                    if ( ! $source ) $source = $s['referrer_domain'] ?? '';
                    if ( ! $source ) $source = 'direct';
                    $s_co    = $s['geo_country'] ?? '';
                ?>
                    <tr style="cursor:pointer;" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=itp-live&period=90d' ) ); ?>'">
                        <td style="font-family:monospace;font-size:.82rem;white-space:nowrap;" data-v="<?php echo esc_attr( $start ); ?>"><?php echo esc_html( $start ); ?></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $landing ); ?>"><?php echo esc_html( $landing ); ?></td>
                        <td class="r" style="font-weight:600;" data-v="<?php echo esc_attr( $pages ); ?>"><?php echo esc_html( $pages ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $scroll ); ?>"><?php echo $scroll > 0 ? esc_html( $scroll . '%' ) : '—'; ?></td>
                        <td class="r" style="white-space:nowrap;" data-v="<?php echo esc_attr( $dur ); ?>"><?php echo esc_html( $this->fmt_dur( $dur ) ); ?></td>
                        <td style="font-size:.85rem;"><?php echo esc_html( $source ); ?></td>
                        <td data-v="<?php echo esc_attr( $s_co ); ?>"><?php echo $this->country_flag( $s_co ); ?> <?php echo esc_html( $s_co ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>

            <a class="itp-back" href="<?php echo esc_url( admin_url( 'admin.php?page=itp-visitors&period=' . $period ) ); ?>">
                &larr; <?php esc_html_e( 'Back to all visitors', 'insight-tracker-pro' ); ?>
            </a>
        </div>
        <?php
    }
}
