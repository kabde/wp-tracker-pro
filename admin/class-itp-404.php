<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_404 {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 26 );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        $hook = add_submenu_page( 'itp-settings', __( '404 Errors', 'insight-tracker-pro' ), __( '404 Errors', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-404', [ $this, 'render' ] );

        // Add notification badge with 404 count
        add_action( 'admin_menu', function() {
            global $submenu;
            if ( empty( $submenu['itp-settings'] ) ) return;
            $count = $this->get_404_count();
            if ( $count > 0 ) {
                foreach ( $submenu['itp-settings'] as &$item ) {
                    if ( $item[2] === 'itp-404' ) {
                        $item[0] .= ' <span class="update-plugins count-' . $count . '"><span class="plugin-count">' . $count . '</span></span>';
                        break;
                    }
                }
                unset( $item );
            }
        }, 999 );
    }

    private function get_404_count() {
        $cached = get_transient( 'itp_404_count' );
        if ( $cached !== false ) return (int) $cached;
        $params = [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => 'errors_404', 'period' => '7d', 'limit' => 1 ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 5 ] );
        if ( is_wp_error( $r ) ) return 0;
        $data = json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
        $count = (int) ( $data['stats']['unique_pages'] ?? 0 );
        set_transient( 'itp_404_count', $count, 300 ); // cache 5 min
        return $count;
    }

    private function period() {
        $p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30d';
        return in_array( $p, [ '1d', '7d', '30d', '90d' ], true ) ? $p : '30d';
    }

    private function api() {
        $params = [ 'key' => get_option( 'itp_license_key', '' ), 'site' => wp_parse_url( home_url(), PHP_URL_HOST ), 'view' => 'errors_404', 'period' => $this->period(), 'limit' => 100 ];
        $r = wp_remote_get( ITP_TRK_URL . '/query?' . http_build_query( $params ), [ 'timeout' => 20 ] );
        if ( is_wp_error( $r ) ) return [];
        return json_decode( wp_remote_retrieve_body( $r ), true ) ?: [];
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );
        $period = $this->period();
        $data   = $this->api();
        $stats  = $data['stats'] ?? [];
        $rows   = $data['data'] ?? [];
        $trend  = $data['trend'] ?? [];
        $has_srp = class_exists( 'SRP_Settings' ) || is_plugin_active( 'smart-redirect-pro/smart-redirect-pro.php' );
        ?>
        <style>
            .itp-404-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
            .itp-404-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center}
            .itp-404-card-n{font-size:28px;font-weight:800;color:#1d2327}
            .itp-404-card-l{font-size:12px;color:#6b7280;margin-top:4px}
            .itp-404-card.alert .itp-404-card-n{color:#dc2626}
            .itp-404-trend{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:24px}
            .itp-404-trend-h{font-size:13px;font-weight:700;color:#1d2327;margin-bottom:12px}
            .itp-404-bars{display:flex;align-items:flex-end;gap:2px;height:60px}
            .itp-404-bar{flex:1;background:#fca5a5;border-radius:3px 3px 0 0;min-width:4px;position:relative;transition:height .3s}
            .itp-404-bar:hover{background:#ef4444}
            .itp-404-tip{display:none;position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1d2327;color:#fff;padding:2px 6px;border-radius:4px;font-size:10px;white-space:nowrap;margin-bottom:3px}
            .itp-404-bar:hover .itp-404-tip{display:block}
            .itp-404-labels{display:flex;gap:2px;margin-top:4px}
            .itp-404-labels span{flex:1;font-size:9px;color:#9ca3af;text-align:center}
            .itp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .itp-t{width:100%;border-collapse:collapse;min-width:900px}
            .itp-t th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
            .itp-t th.r,.itp-t td.r{text-align:right}
            .itp-t td{padding:10px 12px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
            .itp-t tbody tr:hover{background:#f9fafb}
            .itp-empty{text-align:center;padding:60px;color:#9ca3af}
            .itp-ref-list{margin:0;padding:0;list-style:none}
            .itp-ref-list li{font-size:.78rem;color:#6b7280;padding:2px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px}
            .itp-ref-list li a{color:#2563eb;text-decoration:none}
            .itp-action-btn{display:inline-block;padding:3px 10px;border-radius:5px;font-size:.75rem;font-weight:600;text-decoration:none;border:1px solid #e5e7eb;color:#374151;background:#fff;transition:all .15s;white-space:nowrap}
            .itp-action-btn:hover{border-color:#ffc45e;color:#1d2327}
            @media(max-width:768px){.itp-404-cards{grid-template-columns:1fr}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( '404 Errors', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Broken links and missing pages detected on your site', 'insight-tracker-pro' ); ?></p>
                    </div>
                    <div class="itp-period">
                        <?php foreach ( [ '1d'=>'Today', '7d'=>'7 Days', '30d'=>'30 Days', '90d'=>'90 Days' ] as $p=>$l ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=itp-404&period='.$p) ); ?>" class="<?php echo $period===$p?'on':''; ?>"><?php echo esc_html($l); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="itp-404-cards">
                <div class="itp-404-card<?php echo ( $stats['total_hits'] ?? 0 ) > 0 ? ' alert' : ''; ?>">
                    <div class="itp-404-card-n"><?php echo esc_html( number_format_i18n( $stats['total_hits'] ?? 0 ) ); ?></div>
                    <div class="itp-404-card-l"><?php esc_html_e( 'Total 404 Hits', 'insight-tracker-pro' ); ?></div>
                </div>
                <div class="itp-404-card<?php echo ( $stats['unique_pages'] ?? 0 ) > 0 ? ' alert' : ''; ?>">
                    <div class="itp-404-card-n"><?php echo esc_html( number_format_i18n( $stats['unique_pages'] ?? 0 ) ); ?></div>
                    <div class="itp-404-card-l"><?php esc_html_e( 'Unique Broken URLs', 'insight-tracker-pro' ); ?></div>
                </div>
                <div class="itp-404-card">
                    <div class="itp-404-card-n"><?php echo esc_html( number_format_i18n( $stats['unique_visitors'] ?? 0 ) ); ?></div>
                    <div class="itp-404-card-l"><?php esc_html_e( 'Affected Visitors', 'insight-tracker-pro' ); ?></div>
                </div>
            </div>

            <?php if ( ! empty( $trend ) ):
                $max_h = max( array_column( $trend, 'hits' ) ) ?: 1;
            ?>
            <div class="itp-404-trend">
                <div class="itp-404-trend-h"><?php esc_html_e( '404 Errors Over Time', 'insight-tracker-pro' ); ?></div>
                <div class="itp-404-bars">
                    <?php foreach ( $trend as $d ):
                        $h = round( ( $d['hits'] / $max_h ) * 100 );
                    ?>
                        <div class="itp-404-bar" style="height:<?php echo max( 4, $h ); ?>%;">
                            <div class="itp-404-tip"><?php echo esc_html( substr( $d['day'], 5 ) . ': ' . $d['hits'] . ' hits' ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="itp-404-labels">
                    <?php foreach ( $trend as $i => $d ):
                        $show = ( count( $trend ) <= 14 || $i % 3 === 0 );
                    ?>
                        <span><?php echo $show ? esc_html( substr( $d['day'], 5 ) ) : ''; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( empty( $rows ) ): ?>
                <div class="itp-wrap" style="padding:60px;text-align:center;">
                    <div style="font-size:32px;margin-bottom:8px;">&#9989;</div>
                    <div style="font-size:15px;font-weight:600;color:#16a34a;"><?php esc_html_e( 'No 404 errors detected', 'insight-tracker-pro' ); ?></div>
                    <div style="font-size:13px;color:#6b7280;margin-top:4px;"><?php esc_html_e( 'All links are working properly.', 'insight-tracker-pro' ); ?></div>
                </div>
            <?php else: ?>
            <div class="itp-wrap"><table class="itp-t itp-sortable">
                <thead><tr>
                    <th data-sort="text"><?php esc_html_e( 'URL', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Hits', 'insight-tracker-pro' ); ?></th>
                    <th class="r" data-sort="num"><?php esc_html_e( 'Visitors', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'First Seen', 'insight-tracker-pro' ); ?></th>
                    <th data-sort="text"><?php esc_html_e( 'Last Seen', 'insight-tracker-pro' ); ?></th>
                    <th><?php esc_html_e( 'Referrers', 'insight-tracker-pro' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'insight-tracker-pro' ); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $rows as $r ):
                    $referrers = $r['referrers'] ?? [];
                    if ( is_string( $referrers ) ) $referrers = json_decode( $referrers, true ) ?: [];
                    $referrers = array_filter( $referrers, function( $ref ) { return ! empty( $ref ) && $ref !== ''; } );
                    $path = $r['page_path'] ?? '';
                ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:#dc2626;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( $path ); ?></div>
                            <div style="font-size:.72rem;color:#9ca3af;"><?php echo esc_html( ( $r['last_device'] ?? '' ) ?: 'unknown' ); ?></div>
                        </td>
                        <td class="r" style="font-weight:700;" data-v="<?php echo esc_attr( $r['hits'] ); ?>"><?php echo esc_html( number_format_i18n( $r['hits'] ) ); ?></td>
                        <td class="r" data-v="<?php echo esc_attr( $r['visitors'] ); ?>"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></td>
                        <td style="font-size:.82rem;color:#6b7280;white-space:nowrap;"><?php echo esc_html( substr( $r['first_seen'] ?? '', 0, 16 ) ); ?></td>
                        <td style="font-size:.82rem;color:#6b7280;white-space:nowrap;"><?php echo esc_html( substr( $r['last_seen'] ?? '', 0, 16 ) ); ?></td>
                        <td>
                            <?php if ( empty( $referrers ) ): ?>
                                <span style="font-size:.82rem;color:#9ca3af;"><?php esc_html_e( 'Direct', 'insight-tracker-pro' ); ?></span>
                            <?php else: ?>
                                <ul class="itp-ref-list">
                                    <?php foreach ( array_slice( $referrers, 0, 3 ) as $ref ): ?>
                                        <li title="<?php echo esc_attr( $ref ); ?>"><a href="<?php echo esc_url( $ref ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $ref, PHP_URL_HOST ) ?: $ref ); ?></a></li>
                                    <?php endforeach; ?>
                                    <?php if ( count( $referrers ) > 3 ): ?>
                                        <li style="color:#9ca3af;">+<?php echo esc_html( count( $referrers ) - 3 ); ?> more</li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $has_srp ): ?>
                                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=itp_redirect&srp_from=' . urlencode( $path ) ) ); ?>" class="itp-action-btn" title="<?php esc_attr_e( 'Create redirect', 'insight-tracker-pro' ); ?>">&#8594; Redirect</a>
                            <?php else: ?>
                                <a href="<?php echo esc_url( home_url( $path ) ); ?>" class="itp-action-btn" target="_blank" title="<?php esc_attr_e( 'View page', 'insight-tracker-pro' ); ?>">&#128065; View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
