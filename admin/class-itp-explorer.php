<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_Explorer {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 22 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'Explorer', 'insight-tracker-pro' ), __( 'Explorer', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-explorer', [ $this, 'render' ] );
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '7d';
        return in_array( $p, ['1d','7d','30d','90d'], true ) ? $p : '7d';
    }

    private function api( $view, $extra = [] ) {
        $params = array_merge( [
            'key'    => get_option( 'itp_license_key', '' ),
            'site'   => wp_parse_url( home_url(), PHP_URL_HOST ),
            'view'   => $view,
            'period' => $this->period(),
        ], $extra );
        foreach ( ['segment','source','device','country'] as $f ) {
            if ( !empty( $_GET[$f] ) ) $params[$f] = sanitize_text_field( wp_unslash( $_GET[$f] ) );
        }
        $url = ITP_API_URL . '/trk' . '?' . http_build_query( $params );
        $r = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    private function tab_url( $tab ) {
        $args = [ 'page' => 'itp-explorer', 'tab' => $tab, 'period' => $this->period() ];
        foreach ( ['segment','source','device','country'] as $f ) {
            if ( !empty( $_GET[$f] ) ) $args[$f] = sanitize_text_field( wp_unslash( $_GET[$f] ) );
        }
        return admin_url( 'admin.php?' . http_build_query( $args ) );
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'landing';
        $period = $this->period();
        $seg = isset( $_GET['segment'] ) ? sanitize_text_field( wp_unslash( $_GET['segment'] ) ) : 'all';
        $tabs = [
            'landing'  => __( 'Landing Pages', 'insight-tracker-pro' ),
            'exit'     => __( 'Exit Pages', 'insight-tracker-pro' ),
            'journeys' => __( 'User Journeys', 'insight-tracker-pro' ),
            'funnel'   => __( 'Funnel Builder', 'insight-tracker-pro' ),
            'content'  => __( 'Content', 'insight-tracker-pro' ),
        ];
        if ( ! isset( $tabs[$tab] ) ) $tab = 'landing';
        ?>
        <style>
            .itp-explorer-content{margin-top:0}
            .itp-box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
            .itp-tbl{width:100%;border-collapse:collapse}
            .itp-tbl th{background:#f9fafb;padding:10px 14px;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;border-bottom:1px solid #e5e7eb}
            .itp-tbl td{padding:10px 14px;font-size:.88rem;border-bottom:1px solid #f3f4f6}
            .itp-tbl tbody tr:hover{background:#f9fafb}
            .itp-journey{padding:12px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:12px}
            .itp-journey:last-child{border-bottom:none}
            .itp-journey-path{flex:1;font-size:.85rem;color:#374151}
            .itp-journey-path span{display:inline-block;padding:2px 8px;background:#f3f4f6;border-radius:4px;margin:2px;font-size:.8rem}
            .itp-journey-path .arr{color:#9ca3af;margin:0 2px}
            .itp-journey-count{font-size:.85rem;font-weight:700;color:#1d2327;min-width:60px;text-align:right}
            .itp-funnel-step{display:flex;align-items:center;gap:12px;margin-bottom:10px}
            .itp-funnel-label{min-width:110px;font-size:.85rem;font-weight:600;color:#374151}
            .itp-funnel-bar{flex:1;height:32px;background:#f3f4f6;border-radius:8px;overflow:hidden}
            .itp-funnel-fill{height:100%;border-radius:8px;display:flex;align-items:center;padding-left:12px;font-size:.8rem;font-weight:700;color:#fff;min-width:2%}
            .itp-funnel-drop{font-size:.78rem;color:#dc2626;min-width:60px;text-align:right}
            .itp-empty{text-align:center;padding:50px;color:#9ca3af}
            @media(max-width:768px){.itp-tabs{overflow-x:auto}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'Explorer', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Deep dive into visitor behavior and journeys', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach (['7d'=>'7 Days','30d'=>'30 Days','90d'=>'90 Days'] as $p=>$l): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=itp-explorer&tab='.$tab.'&period='.$p)); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="itp-nav">
                    <?php foreach ($tabs as $k=>$l): ?>
                        <a href="<?php echo esc_url($this->tab_url($k)); ?>" class="<?php echo $tab===$k?'on':''; ?>"><?php echo esc_html($l); ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="itp-filter-bar">
                    <span class="itp-filter-label"><?php esc_html_e( 'Segment', 'insight-tracker-pro' ); ?></span>
                    <select onchange="location.href=this.value">
                        <?php foreach (['all'=>'All Users','new'=>'New Users','returning'=>'Returning','bouncers'=>'Bouncers'] as $k=>$l): ?>
                            <option value="<?php echo esc_url($this->tab_url($tab).'&segment='.$k); ?>" <?php selected($seg,$k); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php
            if ( $tab === 'landing' ) $this->render_landing();
            elseif ( $tab === 'exit' ) $this->render_exit();
            elseif ( $tab === 'journeys' ) $this->render_journeys();
            elseif ( $tab === 'funnel' ) $this->render_funnel();
            elseif ( $tab === 'content' ) $this->render_content();
            ?>
        </div>
        <?php
    }

    private function render_landing() {
        $data = $this->api( 'landing' );
        $rows = $data['data'] ?? [];
        ?>
        <div class="itp-box"><table class="itp-tbl itp-sortable">
            <thead><tr><th data-sort="text">Landing Page</th><th data-sort="num">Sessions</th><th data-sort="num">Visitors</th><th data-sort="num">Bounce Rate</th><th data-sort="num">Avg Time</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="5" class="itp-empty">No data yet</td></tr>
            <?php else: foreach ($rows as $r):
                $time = round($r['avg_time_seconds'] ?? $r['avg_time'] ?? 0);
                $mins = floor($time/60); $secs = $time%60;
            ?>
                <tr>
                    <td style="font-weight:600;"><?php echo esc_html($r['landing_page']); ?></td>
                    <td data-v="<?php echo esc_attr($r['sessions']); ?>"><?php echo esc_html(number_format_i18n($r['sessions'])); ?></td>
                    <td data-v="<?php echo esc_attr($r['visitors']); ?>"><?php echo esc_html(number_format_i18n($r['visitors'])); ?></td>
                    <td data-v="<?php echo esc_attr(round($r['bounce_rate']??0)); ?>"><span style="color:<?php echo ($r['bounce_rate']??0)>60?'#dc2626':(($r['bounce_rate']??0)>40?'#ca8a04':'#16a34a'); ?>;font-weight:600;"><?php echo esc_html(round($r['bounce_rate']??0)); ?>%</span></td>
                    <td data-v="<?php echo esc_attr($time); ?>"><?php echo esc_html($mins.'m '.$secs.'s'); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
        <?php
    }

    private function render_exit() {
        $data = $this->api( 'exit' );
        $rows = $data['data'] ?? [];
        ?>
        <div class="itp-box"><table class="itp-tbl itp-sortable">
            <thead><tr><th data-sort="text">Exit Page</th><th data-sort="num">Exits</th><th data-sort="num">Sessions</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="3" class="itp-empty">No data yet</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo esc_html($r['exit_page']); ?></td>
                    <td data-v="<?php echo esc_attr($r['exits']); ?>"><?php echo esc_html(number_format_i18n($r['exits'])); ?></td>
                    <td data-v="<?php echo esc_attr($r['sessions']); ?>"><?php echo esc_html(number_format_i18n($r['sessions'])); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
        <?php
    }

    private function render_journeys() {
        $data = $this->api( 'journeys' );
        $rows = $data['data'] ?? [];
        $total = array_sum( array_column( $rows, 'sessions' ) ) ?: 1;
        ?>
        <div class="itp-box">
            <?php if (empty($rows)): ?><div class="itp-empty">No journeys recorded yet. Need more page views to detect paths.</div>
            <?php else: foreach ($rows as $r):
                $pct = round($r['sessions'] / $total * 100);
                $journey = is_array($r['journey']) ? $r['journey'] : [];
            ?>
                <div class="itp-journey">
                    <div class="itp-journey-path">
                        <?php foreach ($journey as $j => $page): ?>
                            <?php if ($j > 0): ?><span class="arr">&rarr;</span><?php endif; ?>
                            <span><?php echo esc_html($page); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="itp-journey-count"><?php echo esc_html($r['sessions']); ?> <span style="font-weight:400;color:#9ca3af;font-size:.75rem;">(<?php echo $pct; ?>%)</span></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php
    }

    private function render_funnel() {
        $steps_param = isset( $_GET['steps'] ) ? sanitize_text_field( wp_unslash( $_GET['steps'] ) ) : 'pv,scroll,cta_click,purchase';
        $data = $this->api( 'funnel', [ 'steps' => $steps_param ] );
        $rows = $data['data'] ?? [];
        $max = !empty($rows) ? ($rows[0]['visitors'] ?? 1) : 1;
        $colors = ['#2563eb','#7c3aed','#16a34a','#059669','#ea580c','#0891b2'];
        $labels = ['pv'=>'Page View','scroll'=>'Scroll','time_on_page'=>'Time on Page','cta_click'=>'CTA Click','outbound_click'=>'Outbound','form_submit'=>'Form Submit','search'=>'Search','add_cart'=>'Add to Cart','checkout'=>'Checkout','purchase'=>'Purchase'];

        $all_events = array_keys($labels);
        $current_steps = explode(',', $steps_param);
        ?>
        <div style="margin-bottom:16px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;">
            <form method="get" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="page" value="itp-explorer">
                <input type="hidden" name="tab" value="funnel">
                <input type="hidden" name="period" value="<?php echo esc_attr($this->period()); ?>">
                <span style="font-size:13px;font-weight:600;color:#374151;">Steps:</span>
                <?php foreach ($all_events as $ev): ?>
                    <label style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:#374151;">
                        <input type="checkbox" name="step[]" value="<?php echo esc_attr($ev); ?>" <?php checked(in_array($ev, $current_steps)); ?>>
                        <?php echo esc_html($labels[$ev]); ?>
                    </label>
                <?php endforeach; ?>
                <button type="submit" class="button button-primary" style="margin-left:8px;" onclick="var c=document.querySelectorAll('input[name=\\'step[]\\'']:checked');var s=Array.from(c).map(function(i){return i.value}).join(',');var h=document.createElement('input');h.type='hidden';h.name='steps';h.value=s;this.form.appendChild(h);">Apply</button>
            </form>
        </div>

        <div class="itp-box" style="padding:20px;">
            <?php if (empty($rows)): ?><div class="itp-empty">Select steps and click Apply</div>
            <?php else: foreach ($rows as $i=>$r):
                $pct = $max > 0 ? round($r['visitors'] / $max * 100) : 0;
                $color = $colors[$i % count($colors)];
                $label = $labels[$r['step']] ?? $r['step'];
                $drop = $i > 0 && $rows[$i-1]['visitors'] > 0 ? round((1 - $r['visitors'] / $rows[$i-1]['visitors']) * 100) : 0;
            ?>
                <div class="itp-funnel-step">
                    <span class="itp-funnel-label"><?php echo esc_html($label); ?></span>
                    <div class="itp-funnel-bar">
                        <div class="itp-funnel-fill" style="width:<?php echo max(3,$pct); ?>%;background:<?php echo esc_attr($color); ?>;">
                            <?php echo esc_html(number_format_i18n($r['visitors'])); ?> (<?php echo $pct; ?>%)
                        </div>
                    </div>
                    <?php if ($i > 0): ?><span class="itp-funnel-drop">-<?php echo $drop; ?>%</span>
                    <?php else: ?><span style="min-width:60px;"></span><?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php
    }

    private function render_content() {
        $data = $this->api( 'content' );
        $rows = $data['data'] ?? [];
        ?>
        <div class="itp-box"><table class="itp-tbl itp-sortable">
            <thead><tr><th data-sort="text">Page</th><th data-sort="text">Type</th><th data-sort="num">Views</th><th data-sort="num">Visitors</th><th data-sort="num">Avg Scroll</th><th data-sort="num">Avg Time</th><th data-sort="num">CTA Clicks</th><th data-sort="num">CTR</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="8" class="itp-empty">No content data yet</td></tr>
            <?php else: foreach ($rows as $r):
                $time = round($r['avg_time_seconds'] ?? $r['avg_time'] ?? 0);
                $scroll = round($r['avg_scroll'] ?? 0);
            ?>
                <tr>
                    <td style="font-weight:600;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($r['page_path']); ?>"><?php echo esc_html($r['page_title'] ?: $r['page_path']); ?></td>
                    <td><span style="background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600;"><?php echo esc_html($r['post_type']); ?></span></td>
                    <td data-v="<?php echo esc_attr($r['views']); ?>"><?php echo esc_html(number_format_i18n($r['views'])); ?></td>
                    <td data-v="<?php echo esc_attr($r['visitors']); ?>"><?php echo esc_html(number_format_i18n($r['visitors'])); ?></td>
                    <td data-v="<?php echo esc_attr($scroll); ?>"><?php echo $scroll > 0 ? esc_html($scroll.'%') : '—'; ?></td>
                    <td data-v="<?php echo esc_attr($time); ?>"><?php echo $time > 0 ? esc_html(floor($time/60).'m '.($time%60).'s') : '—'; ?></td>
                    <td data-v="<?php echo esc_attr($r['cta_clicks']); ?>"><?php echo esc_html(number_format_i18n($r['cta_clicks'])); ?></td>
                    <td style="font-weight:600;color:<?php echo ($r['ctr']??0)>5?'#16a34a':'#6b7280'; ?>;" data-v="<?php echo esc_attr($r['ctr']??0); ?>"><?php echo esc_html(($r['ctr']??0).'%'); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
        <?php
    }
}
