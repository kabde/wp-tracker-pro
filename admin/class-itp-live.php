<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Live {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 21 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Live Feed', 'insight-tracker-pro' ), __( 'Live Feed', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-live', [ $this, 'render' ] );
    }

    private function tab() {
        $t = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'sessions';
        return in_array( $t, [ 'sessions', 'pages', 'outbound' ], true ) ? $t : 'sessions';
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '1d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '1d';
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

    private function fmt( $v ) {
        if ( $v === '' || $v === '0' || $v === 0 || $v === '{}' || $v === '[]' || $v === '1970-01-01 00:00:00.000' ) return '';
        if ( is_array( $v ) ) return implode( ', ', $v );
        return (string) $v;
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );
        $period   = $this->period();
        $tab      = $this->tab();
        if ( $tab === 'pages' ) {
            $data  = $this->api( 'events', [ 'event' => 'pv' ] );
            $pages = $data['events'] ?? [];
        } elseif ( $tab === 'outbound' ) {
            $data     = $this->api( 'events', [ 'event' => 'outbound_click' ] );
            $outbound = $data['events'] ?? [];
        } else {
            $data     = $this->api( 'sessions' );
            $sessions = $data['data'] ?? [];
        }
        ?>
        <style>
            .itp-wrap{margin-top:0}
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .itp-t{border-collapse:collapse;min-width:900px;width:100%}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb}
            .itp-t td{padding:9px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr.itp-sr:hover{background:#f9fafb}
            .itp-sr{cursor:pointer}
            .itp-dot{width:8px;height:8px;border-radius:50%;display:inline-block}
            .itp-new{display:inline-block;background:#dbeafe;color:#1d4ed8;font-size:.6rem;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:3px;text-transform:uppercase}
            .itp-det{display:none}
            .itp-det.open{display:table-row}
            .itp-det>td{padding:0!important;border-bottom:none!important}
            .itp-detail-panel{background:#fafbfc;border-top:2px solid #e5e7eb;padding:0}
            .itp-detail-top{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border-bottom:1px solid #e5e7eb}
            .itp-detail-sec{padding:16px 20px;border-right:1px solid #e5e7eb}
            .itp-detail-sec:last-child{border-right:none}
            .itp-detail-sec h4{margin:0 0 10px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:700}
            .itp-dr{display:flex;gap:6px;padding:3px 0;font-size:.82rem}
            .itp-dk{color:#6b7280;min-width:90px;white-space:nowrap}
            .itp-dv{color:#1d2327;font-weight:500;word-break:break-all}
            .itp-dv code{background:#f3f4f6;padding:1px 6px;border-radius:3px;font-size:.78rem;font-family:monospace}
            .itp-timeline-box{padding:16px 20px}
            .itp-timeline-box h4{margin:0 0 12px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:700}
            .itp-tl{border-left:2px solid #e5e7eb;margin-left:8px}
            .itp-tl-i{display:flex;align-items:flex-start;gap:8px;padding:5px 0 5px 14px;position:relative}
            .itp-tl-i::before{content:'';position:absolute;left:-5px;top:9px;width:8px;height:8px;border-radius:50%;background:#d1d5db}
            .itp-tl-i.pv::before{background:#2563eb}
            .itp-tl-i.scroll::before{background:#7c3aed}
            .itp-tl-i.cta_click::before{background:#16a34a}
            .itp-tl-i.purchase::before{background:#059669}
            .itp-tl-i.time_on_page::before{background:#0891b2}
            .itp-tl-i.error_404::before{background:#dc2626}
            .itp-tl-time{font-size:.75rem;color:#9ca3af;min-width:50px}
            .itp-tl-ev{font-size:.8rem;font-weight:600;color:#374151;min-width:80px}
            .itp-tl-pg{font-size:.8rem;color:#6b7280;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .itp-tl-val{font-size:.75rem;color:#9ca3af;margin-left:auto}
            .itp-col-cfg{position:relative;display:inline-block}
            .itp-col-btn{background:none;border:1px solid #e5e7eb;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:13px;color:#6b7280;display:flex;align-items:center;gap:5px;transition:border-color .2s}
            .itp-col-btn:hover{border-color:#ffc45e;color:#1d2327}
            .itp-col-btn svg{width:14px;height:14px}
            .itp-col-drop{display:none;position:absolute;top:100%;right:0;margin-top:6px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px 0;z-index:100;min-width:200px;max-height:340px;overflow-y:auto}
            .itp-col-drop.open{display:block}
            .itp-col-drop label{display:flex;align-items:center;gap:8px;padding:6px 16px;font-size:13px;color:#374151;cursor:pointer;white-space:nowrap}
            .itp-col-drop label:hover{background:#f9fafb}
            .itp-col-drop label input{accent-color:#ffc45e}
            .itp-col-drop .itp-col-sep{border-top:1px solid #f3f4f6;margin:6px 0}
            .itp-col-drop .itp-col-title{padding:4px 16px;font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;font-weight:700}
            .itp-empty{text-align:center;padding:60px;color:#9ca3af}
            @media(max-width:1100px){.itp-detail-top{grid-template-columns:1fr}.itp-detail-sec{border-right:none;border-bottom:1px solid #e5e7eb}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'Live Feed', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Real-time visitor sessions on your site', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d'=>'Today', '7d'=>'7 Days', '30d'=>'30 Days', '90d'=>'90 Days' ] as $p=>$l ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=itp-live&period='.$p.'&tab='.$tab) ); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="itp-stat-badge">
                    <span class="itp-pulse"></span>
                    <strong><?php
                        if ( $tab === 'pages' ) echo esc_html( number_format_i18n( count( $pages ) ) );
                        elseif ( $tab === 'outbound' ) echo esc_html( number_format_i18n( count( $outbound ) ) );
                        else echo esc_html( number_format_i18n( count( $sessions ) ) );
                    ?></strong>
                    <?php
                        if ( $tab === 'pages' ) esc_html_e( 'page views', 'insight-tracker-pro' );
                        elseif ( $tab === 'outbound' ) esc_html_e( 'outbound clicks', 'insight-tracker-pro' );
                        else esc_html_e( 'sessions', 'insight-tracker-pro' );
                    ?>
                </div>
                <div class="itp-nav">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=itp-live&period=' . $period . '&tab=sessions' ) ); ?>" class="<?php echo $tab === 'sessions' ? 'on' : ''; ?>">Sessions</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=itp-live&period=' . $period . '&tab=pages' ) ); ?>" class="<?php echo $tab === 'pages' ? 'on' : ''; ?>">Recent Pages</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=itp-live&period=' . $period . '&tab=outbound' ) ); ?>" class="<?php echo $tab === 'outbound' ? 'on' : ''; ?>">Outbound Clicks</a>
                </div>
            </div>
            <?php if ( $tab === 'pages' ) { $this->render_pages( $pages ); } elseif ( $tab === 'outbound' ) { $this->render_outbound( $outbound ); } else { ?>
            <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
                <div class="itp-col-cfg">
                    <button class="itp-col-btn" onclick="this.nextElementSibling.classList.toggle('open')" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        Columns
                    </button>
                    <div class="itp-col-drop" id="itp-col-drop">
                        <div class="itp-col-title">Default columns</div>
                        <label><input type="checkbox" data-col="visitor" checked disabled> Visitor</label>
                        <label><input type="checkbox" data-col="landing" checked disabled> Landing Page</label>
                        <label><input type="checkbox" data-col="pages" checked> Pages</label>
                        <label><input type="checkbox" data-col="scroll" checked> Scroll</label>
                        <label><input type="checkbox" data-col="duration" checked> Duration</label>
                        <label><input type="checkbox" data-col="source" checked> Source</label>
                        <label><input type="checkbox" data-col="device" checked> Device</label>
                        <label><input type="checkbox" data-col="country" checked> Country</label>
                        <div class="itp-col-sep"></div>
                        <div class="itp-col-title">Additional columns</div>
                        <label><input type="checkbox" data-col="medium"> UTM Medium</label>
                        <label><input type="checkbox" data-col="campaign"> UTM Campaign</label>
                        <label><input type="checkbox" data-col="browser"> Browser</label>
                        <label><input type="checkbox" data-col="os"> OS</label>
                        <label><input type="checkbox" data-col="region"> Region</label>
                        <label><input type="checkbox" data-col="city"> City</label>
                        <label><input type="checkbox" data-col="cta"> CTA Clicks</label>
                        <label><input type="checkbox" data-col="referrer"> Referrer Domain</label>
                    </div>
                </div>
            </div>
            <div class="itp-wrap"><table class="itp-t itp-sortable" id="itp-sessions-tbl">
                <thead><tr>
                    <th style="width:24px" data-col="status"></th>
                    <th data-sort="text" data-col="visitor">Visitor</th>
                    <th data-sort="text" data-col="landing">Landing Page</th>
                    <th data-sort="num" data-col="pages">Pages</th>
                    <th data-sort="num" data-col="scroll">Scroll</th>
                    <th data-sort="num" data-col="duration">Duration</th>
                    <th data-sort="text" data-col="source">Source</th>
                    <th data-sort="text" data-col="device">Device</th>
                    <th data-sort="text" data-col="country">Country</th>
                    <th data-sort="text" data-col="medium" style="display:none">Medium</th>
                    <th data-sort="text" data-col="campaign" style="display:none">Campaign</th>
                    <th data-sort="text" data-col="browser" style="display:none">Browser</th>
                    <th data-sort="text" data-col="os" style="display:none">OS</th>
                    <th data-sort="text" data-col="region" style="display:none">Region</th>
                    <th data-sort="text" data-col="city" style="display:none">City</th>
                    <th data-sort="num" data-col="cta" style="display:none">CTA</th>
                    <th data-sort="text" data-col="referrer" style="display:none">Referrer</th>
                    <th style="width:30px" data-col="eye"></th>
                </tr></thead>
                <tbody>
                <?php if ( empty($sessions) ): ?>
                    <tr><td colspan="18" class="itp-empty">No sessions yet. Visit your site to start tracking.</td></tr>
                <?php else: foreach ( $sessions as $i => $s ):
                    $vid     = substr( $s['visitor_id'] ?? '', 0, 8 );
                    $sid     = $s['session_id'] ?? '';
                    $landing = $s['landing_page'] ?? '/';
                    $pg_count = $s['pages'] ?? 0;
                    $scroll  = round( $s['max_scroll'] ?? 0 );
                    $dur     = $s['duration_seconds'] ?? 0;
                    $src     = $s['utm_source'] ?? $s['referrer_domain'] ?? 'direct';
                    $dev     = $s['device_type'] ?? '';
                    $br      = $s['browser_name'] ?? '';
                    $co      = $s['geo_country'] ?? '';
                    $is_new  = ! empty( $s['is_first_visit'] );
                    $vn      = $s['visit_number'] ?? 1;
                    $end_ts  = strtotime( $s['session_end'] ?? '' );
                    $active  = $end_ts && ( time() - $end_ts ) < 300;
                    $did     = 'itp-sd-' . $i;
                ?>
                    <?php
                        $medium   = $s['utm_medium'] ?? '';
                        $campaign = $s['utm_campaign'] ?? '';
                        $os       = ($s['os_name'] ?? '') . ' ' . ($s['os_version'] ?? '');
                        $region   = $s['geo_region'] ?? '';
                        $city     = $s['geo_city'] ?? '';
                        $cta      = $s['cta_clicks'] ?? 0;
                        $ref_dom  = $s['referrer_domain'] ?? '';
                    ?>
                    <tr class="itp-sr" onclick="document.getElementById('<?php echo esc_attr($did); ?>').classList.toggle('open')">
                        <td data-col="status"><span class="itp-dot" style="background:<?php echo $active?'#22c55e':'#d1d5db'; ?>"></span></td>
                        <td data-col="visitor">
                            <span style="font-family:monospace;font-size:.82rem;"><?php echo esc_html($vid); ?></span>
                            <?php if ($is_new): ?><span class="itp-new">New</span><?php endif; ?>
                            <?php if ($vn > 1): ?><span style="font-size:.68rem;color:#9ca3af;margin-left:2px;">#<?php echo esc_html($vn); ?></span><?php endif; ?>
                        </td>
                        <td data-col="landing" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($landing); ?>"><?php echo esc_html($landing); ?></td>
                        <td data-col="pages" style="font-weight:600;" data-v="<?php echo esc_attr($pg_count); ?>"><?php echo esc_html($pg_count); ?></td>
                        <td data-col="scroll" data-v="<?php echo esc_attr($scroll); ?>"><?php if ($scroll > 0): ?>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <div style="width:36px;height:5px;background:#f3f4f6;border-radius:3px;overflow:hidden;"><div style="height:100%;width:<?php echo min(100,$scroll); ?>%;background:<?php echo $scroll>=75?'#16a34a':($scroll>=50?'#ca8a04':'#ef4444'); ?>;border-radius:3px;"></div></div>
                                <span style="font-size:.75rem;color:#6b7280;"><?php echo $scroll; ?>%</span>
                            </div>
                        <?php else: ?><span style="color:#d1d5db;">—</span><?php endif; ?></td>
                        <td data-col="duration" style="font-size:.85rem;white-space:nowrap;" data-v="<?php echo esc_attr($dur); ?>"><?php echo esc_html($this->fmt_dur($dur)); ?></td>
                        <td data-col="source" style="font-size:.85rem;"><?php echo esc_html($src); ?></td>
                        <td data-col="device" style="white-space:nowrap;"><?php echo $this->dev_icon($dev); ?> <span style="font-size:.78rem;color:#6b7280;"><?php echo esc_html($br); ?></span></td>
                        <td data-col="country"><?php echo esc_html($co); ?></td>
                        <td data-col="medium" style="display:none;font-size:.85rem;"><?php echo esc_html($medium ?: '—'); ?></td>
                        <td data-col="campaign" style="display:none;font-size:.85rem;"><?php echo esc_html($campaign ?: '—'); ?></td>
                        <td data-col="browser" style="display:none;font-size:.85rem;"><?php echo esc_html(($s['browser_name'] ?? '') . ' ' . ($s['browser_version'] ?? '')); ?></td>
                        <td data-col="os" style="display:none;font-size:.85rem;"><?php echo esc_html(trim($os)); ?></td>
                        <td data-col="region" style="display:none;font-size:.85rem;"><?php echo esc_html($region ?: '—'); ?></td>
                        <td data-col="city" style="display:none;font-size:.85rem;"><?php echo esc_html($city ?: '—'); ?></td>
                        <td data-col="cta" style="display:none;font-weight:600;" data-v="<?php echo esc_attr($cta); ?>"><?php echo esc_html($cta); ?></td>
                        <td data-col="referrer" style="display:none;font-size:.85rem;"><?php echo esc_html($ref_dom ?: '—'); ?></td>
                        <td data-col="eye"><span style="font-size:14px;cursor:pointer;">&#128065;</span></td>
                    </tr>
                    <tr id="<?php echo esc_attr($did); ?>" class="itp-det">
                        <td colspan="18">
                            <div class="itp-detail-panel">
                                <div class="itp-detail-top">
                                    <?php
                                    // Build sections with ALL available data
                                    $sections = [
                                        'Visitor' => array_filter([
                                            'Visitor ID'     => $s['visitor_id'] ?? '',
                                            'Session ID'     => $sid,
                                            'Visit #'        => $vn > 0 ? '#' . $vn : '',
                                            'First Visit'    => $is_new ? 'Yes' : 'No',
                                            'WP User'        => ($s['wp_user_id'] ?? 0) ? '#' . $s['wp_user_id'] . ' (' . ($s['wp_user_role'] ?? '') . ')' : '',
                                            'First Seen'     => $this->fmt($s['first_visit_ts'] ?? ''),
                                            'First Page'     => $this->fmt($s['first_landing_page'] ?? ''),
                                            'First Source'   => $this->fmt($s['first_utm_source'] ?? '') ?: $this->fmt($s['first_referrer_domain'] ?? ''),
                                        ], function($v) { return $v !== '' && $v !== 'No'; }),

                                        'Source & UTM' => array_filter([
                                            'Referrer'       => $this->fmt($s['referrer_url'] ?? ''),
                                            'Domain'         => $this->fmt($s['referrer_domain'] ?? ''),
                                            'Type'           => $this->fmt($s['referrer_type'] ?? ''),
                                            'UTM Source'     => $this->fmt($s['utm_source'] ?? ''),
                                            'UTM Medium'     => $this->fmt($s['utm_medium'] ?? ''),
                                            'UTM Campaign'   => $this->fmt($s['utm_campaign'] ?? ''),
                                            'UTM Content'    => $this->fmt($s['utm_content'] ?? ''),
                                            'UTM Term'       => $this->fmt($s['utm_term'] ?? ''),
                                            'fbclid'         => $this->fmt($s['fbclid'] ?? ''),
                                            'gclid'          => $this->fmt($s['gclid'] ?? ''),
                                            'msclkid'        => $this->fmt($s['msclkid'] ?? ''),
                                            'ttclid'         => $this->fmt($s['ttclid'] ?? ''),
                                            'click_id'       => $this->fmt($s['click_id'] ?? ''),
                                            'URL Params'     => $this->fmt($s['url_params'] ?? ''),
                                        ], function($v) { return $v !== ''; }),

                                        'Device & Geo' => array_filter([
                                            'Device'         => $this->fmt($s['device_type'] ?? ''),
                                            'Browser'        => ($s['browser_name'] ?? '') . ' ' . ($s['browser_version'] ?? ''),
                                            'OS'             => ($s['os_name'] ?? '') . ' ' . ($s['os_version'] ?? ''),
                                            'Screen'         => $this->fmt($s['screen_resolution'] ?? ''),
                                            'Language'       => $this->fmt($s['user_language'] ?? ''),
                                            'Timezone'       => $this->fmt($s['timezone'] ?? ''),
                                            'Country'        => $this->fmt($s['geo_country'] ?? ''),
                                            'Region'         => $this->fmt($s['geo_region'] ?? ''),
                                            'City'           => $this->fmt($s['geo_city'] ?? ''),
                                        ], function($v) { return $v !== '' && trim($v) !== ''; }),
                                    ];

                                    foreach ( $sections as $sec_name => $fields ):
                                        if ( empty($fields) ) continue;
                                    ?>
                                        <div class="itp-detail-sec">
                                            <h4><?php echo esc_html($sec_name); ?></h4>
                                            <?php foreach ($fields as $k => $v): ?>
                                                <div class="itp-dr">
                                                    <span class="itp-dk"><?php echo esc_html($k); ?></span>
                                                    <span class="itp-dv"><?php
                                                        if (in_array($k, ['fbclid','gclid','msclkid','ttclid','click_id','URL Params','Visitor ID','Session ID'], true)) {
                                                            echo '<code>' . esc_html($v) . '</code>';
                                                        } else {
                                                            echo esc_html($v);
                                                        }
                                                    ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="itp-timeline-box">
                                    <h4>Session Timeline</h4>
                                    <div class="itp-tl" id="<?php echo esc_attr('itp-tl-'.$i); ?>">
                                        <div style="padding:15px;color:#9ca3af;font-size:.82rem;">Loading timeline...</div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        <script>
        (function(){
            var itpNonce=<?php echo wp_json_encode( wp_create_nonce( 'itp_nonce' ) ); ?>;
            var loaded={};
            document.querySelectorAll('.itp-sr').forEach(function(row,idx){
                row.addEventListener('click',function(){
                    if(loaded[idx])return;loaded[idx]=true;
                    var det=document.getElementById('itp-sd-'+idx);
                    if(!det)return;
                    var panel=det.querySelector('.itp-detail-panel');
                    if(!panel)return;
                    var sid=<?php echo wp_json_encode(array_column($sessions,'session_id')); ?>[idx];
                    if(!sid)return;
                    var container=document.getElementById('itp-tl-'+idx);
                    fetch(ajaxurl+'?action=itp_session_detail&nonce='+encodeURIComponent(itpNonce)+'&sid='+encodeURIComponent(sid))
                    .then(function(r){return r.json()}).then(function(d){
                        var events=d.data||[];
                        if(!events.length){container.innerHTML='<div style="padding:15px;color:#9ca3af;">No events</div>';return;}
                        var labels={pv:'Page View',scroll:'Scroll',time_on_page:'Time on Page',cta_click:'CTA Click',outbound_click:'Outbound',form_submit:'Form Submit',search:'Search',add_cart:'Add to Cart',purchase:'Purchase',error_404:'404 Error'};
                        var html='';
                        events.forEach(function(e){
                            var ts=(e.event_ts||'').substring(11,19);
                            var et=e.event_type||'';
                            var label=labels[et]||et;
                            var page=e.page_path||'';
                            var title=e.page_title||'';
                            var val='';
                            if(et==='scroll')val=Math.round(e.event_value||0)+'%';
                            else if(et==='time_on_page')val=Math.round(e.event_value||0)+'s';
                            else if(et==='purchase')val='$'+e.event_value;
                            else if(e.event_label)val=e.event_label;
                            html+='<div class="itp-tl-i '+et+'">';
                            html+='<span class="itp-tl-time">'+ts+'</span>';
                            html+='<span class="itp-tl-ev">'+label+'</span>';
                            html+='<span class="itp-tl-pg" title="'+page+'">'+(title||page)+'</span>';
                            if(val)html+='<span class="itp-tl-val">'+val+'</span>';
                            html+='</div>';
                        });
                        container.innerHTML=html;
                    }).catch(function(){
                        container.innerHTML='<div style="padding:15px;color:#dc2626;">Failed to load timeline</div>';
                    });
                });
            });
        })();
        // Column toggle
        (function(){
            var KEY='itp_live_cols';
            var drop=document.getElementById('itp-col-drop');
            if(!drop)return;
            var saved=JSON.parse(localStorage.getItem(KEY)||'{}');
            var checks=drop.querySelectorAll('input[data-col]');
            // Apply saved state
            checks.forEach(function(cb){
                var col=cb.dataset.col;
                if(col in saved&&!cb.disabled)cb.checked=saved[col];
            });
            function applyCols(){
                var state={};
                checks.forEach(function(cb){
                    var col=cb.dataset.col;
                    if(cb.disabled)return;
                    state[col]=cb.checked;
                    var show=cb.checked?'':'none';
                    document.querySelectorAll('[data-col="'+col+'"]').forEach(function(el){el.style.display=show;});
                });
                localStorage.setItem(KEY,JSON.stringify(state));
            }
            applyCols();
            checks.forEach(function(cb){cb.addEventListener('change',applyCols);});
            // Close dropdown on outside click
            document.addEventListener('click',function(e){
                if(!e.target.closest('.itp-col-cfg'))drop.classList.remove('open');
            });
        })();
        </script>
            <?php } ?>
        </div>
        <?php
    }

    private function render_pages( $pages ) {
        ?>
        <div class="itp-wrap"><table class="itp-t itp-sortable">
            <thead><tr>
                <th data-sort="text">Time</th>
                <th data-sort="text">Page</th>
                <th data-sort="text">Title</th>
                <th data-sort="text">Visitor</th>
                <th data-sort="text">Source</th>
                <th data-sort="text">Device</th>
                <th data-sort="text">Country</th>
            </tr></thead>
            <tbody>
            <?php if ( empty( $pages ) ): ?>
                <tr><td colspan="7" class="itp-empty">No page views yet.</td></tr>
            <?php else: foreach ( $pages as $e ):
                $ts   = substr( $e['event_ts'] ?? '', 11, 8 );
                $path = $e['page_path'] ?? '/';
                $title = $e['page_title'] ?? '';
                $vid  = substr( $e['visitor_id'] ?? '', 0, 8 );
                $src  = $e['utm_source'] ?? '';
                if ( ! $src ) $src = $e['referrer_domain'] ?? '';
                if ( ! $src ) $src = 'direct';
                $dev  = $e['device_type'] ?? '';
                $co   = $e['geo_country'] ?? '';
            ?>
                <tr>
                    <td style="font-family:monospace;font-size:.82rem;white-space:nowrap;"><?php echo esc_html( $ts ); ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( $path ); ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></td>
                    <td><span style="font-family:monospace;font-size:.82rem;"><?php echo esc_html( $vid ); ?></span></td>
                    <td style="font-size:.85rem;"><?php echo esc_html( $src ); ?></td>
                    <td style="white-space:nowrap;"><?php echo $this->dev_icon( $dev ); ?></td>
                    <td><?php echo esc_html( $co ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
        <?php
    }

    private function render_outbound( $clicks ) {
        ?>
        <div class="itp-wrap"><table class="itp-t itp-sortable">
            <thead><tr>
                <th data-sort="text"><?php esc_html_e( 'Time', 'insight-tracker-pro' ); ?></th>
                <th data-sort="text"><?php esc_html_e( 'Page', 'insight-tracker-pro' ); ?></th>
                <th data-sort="text"><?php esc_html_e( 'Clicked URL', 'insight-tracker-pro' ); ?></th>
                <th data-sort="text"><?php esc_html_e( 'Link Text', 'insight-tracker-pro' ); ?></th>
                <th data-sort="text"><?php esc_html_e( 'Domain', 'insight-tracker-pro' ); ?></th>
                <th data-sort="text"><?php esc_html_e( 'Visitor', 'insight-tracker-pro' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( empty( $clicks ) ): ?>
                <tr><td colspan="6" class="itp-empty"><?php esc_html_e( 'No outbound clicks yet.', 'insight-tracker-pro' ); ?></td></tr>
            <?php else: foreach ( $clicks as $c ):
                $ts   = substr( $c['event_ts'] ?? '', 11, 8 );
                $page = $c['page_path'] ?? '/';
                $url  = $c['event_label'] ?? '';
                $edata = json_decode( $c['event_data'] ?? '{}', true );
                $text = $edata['text'] ?? '';
                $domain = $edata['domain'] ?? '';
                $vid  = substr( $c['visitor_id'] ?? '', 0, 11 );
            ?>
                <tr>
                    <td style="font-family:monospace;font-size:.82rem;white-space:nowrap;"><?php echo esc_html( $ts ); ?></td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $page ); ?>"><?php echo esc_html( $page ); ?></td>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $url ); ?>"><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:none;"><?php echo esc_html( $url ); ?></a></td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6b7280;" title="<?php echo esc_attr( $text ); ?>"><?php echo esc_html( $text ?: '—' ); ?></td>
                    <td><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600;background:#f3f4f6;color:#374151;"><?php echo esc_html( $domain ); ?></span></td>
                    <td style="font-family:monospace;font-size:.82rem;"><?php echo esc_html( $vid ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
        <?php
    }
}
