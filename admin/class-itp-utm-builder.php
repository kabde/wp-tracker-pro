<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITP_UTM_Builder {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 27 );
        add_action( 'wp_ajax_itp_utm_history', [ $this, 'ajax_history' ] );
    }

    public function add_menu() {
        if ( ! itp_is_licensed() ) return;
        add_submenu_page( 'itp-settings', __( 'UTM Builder', 'insight-tracker-pro' ), __( 'UTM Builder', 'insight-tracker-pro' ), ITP_CAPABILITY, 'itp-utm-builder', [ $this, 'render' ] );
    }

    public function ajax_history() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_send_json_error( 'forbidden', 403 );
        $history = get_option( 'itp_utm_history', [] );
        wp_send_json_success( $history );
    }

    private function save_to_history( $data ) {
        $history = get_option( 'itp_utm_history', [] );
        array_unshift( $history, $data );
        $history = array_slice( $history, 0, 50 ); // keep last 50
        update_option( 'itp_utm_history', $history, false );
    }

    public function render() {
        if ( ! current_user_can( ITP_CAPABILITY ) ) wp_die( 'Insufficient permissions.' );

        // Handle form submission
        $generated_url = '';
        if ( ! empty( $_POST['itp_utm_url'] ) && check_admin_referer( 'itp_utm_build' ) ) {
            $base_url    = esc_url_raw( wp_unslash( $_POST['itp_utm_url'] ) );
            $utm_source  = sanitize_text_field( wp_unslash( $_POST['itp_utm_source'] ?? '' ) );
            $utm_medium  = sanitize_text_field( wp_unslash( $_POST['itp_utm_medium'] ?? '' ) );
            $utm_campaign = sanitize_text_field( wp_unslash( $_POST['itp_utm_campaign'] ?? '' ) );
            $utm_content = sanitize_text_field( wp_unslash( $_POST['itp_utm_content'] ?? '' ) );
            $utm_term    = sanitize_text_field( wp_unslash( $_POST['itp_utm_term'] ?? '' ) );

            $args = [];
            if ( $utm_source )   $args['utm_source']   = $utm_source;
            if ( $utm_medium )   $args['utm_medium']    = $utm_medium;
            if ( $utm_campaign ) $args['utm_campaign']  = $utm_campaign;
            if ( $utm_content )  $args['utm_content']   = $utm_content;
            if ( $utm_term )     $args['utm_term']      = $utm_term;

            if ( $base_url && ! empty( $args ) ) {
                $generated_url = add_query_arg( $args, $base_url );
                $this->save_to_history( [
                    'url'       => $generated_url,
                    'base'      => $base_url,
                    'source'    => $utm_source,
                    'medium'    => $utm_medium,
                    'campaign'  => $utm_campaign,
                    'created'   => current_time( 'Y-m-d H:i' ),
                ] );
            }
        }

        $history = get_option( 'itp_utm_history', [] );

        // Get published posts/pages for dropdown
        $posts = get_posts( [
            'post_type'   => [ 'post', 'page', 'product' ],
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );
        ?>
        <style>
            .itp-utm-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
            .itp-utm-form{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px}
            .itp-utm-form h3{margin:0 0 18px;font-size:15px;font-weight:700;color:#1d2327}
            .itp-utm-field{margin-bottom:14px}
            .itp-utm-field label{display:block;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
            .itp-utm-field input,.itp-utm-field select{width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;color:#1d2327;background:#fff;transition:border-color .2s}
            .itp-utm-field input:focus,.itp-utm-field select:focus{border-color:#ffc45e;outline:none;box-shadow:0 0 0 3px rgba(255,196,94,.15)}
            .itp-utm-field small{display:block;font-size:11px;color:#9ca3af;margin-top:3px}
            .itp-utm-presets{display:flex;gap:4px;margin-top:4px;flex-wrap:wrap}
            .itp-utm-presets button{padding:3px 10px;border:1px solid #e5e7eb;border-radius:5px;background:#f9fafb;font-size:11px;color:#374151;cursor:pointer;transition:all .15s}
            .itp-utm-presets button:hover{border-color:#ffc45e;background:#fff}
            .itp-utm-submit{background:#1d2327;color:#ffc45e;border:none;padding:10px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;margin-top:6px}
            .itp-utm-submit:hover{background:#2d3339}
            .itp-utm-result{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px}
            .itp-utm-result h3{margin:0 0 18px;font-size:15px;font-weight:700;color:#1d2327}
            .itp-utm-output{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;font-family:monospace;font-size:13px;word-break:break-all;color:#1d2327;position:relative;min-height:44px}
            .itp-utm-copy{position:absolute;top:8px;right:8px;background:#1d2327;color:#ffc45e;border:none;padding:5px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}
            .itp-utm-copy:hover{background:#2d3339}
            .itp-utm-preview{margin-top:14px;padding:12px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px}
            .itp-utm-preview-h{font-size:11px;font-weight:600;color:#16a34a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
            .itp-utm-preview-row{display:flex;gap:8px;font-size:13px;padding:2px 0}
            .itp-utm-preview-k{color:#6b7280;min-width:100px}
            .itp-utm-preview-v{color:#1d2327;font-weight:600}
            .itp-utm-history{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .itp-utm-history h3{padding:16px 18px 0;margin:0;font-size:15px;font-weight:700;color:#1d2327}
            .itp-utm-ht{width:100%;border-collapse:collapse;min-width:600px}
            .itp-utm-ht th{background:#f9fafb;padding:8px 14px;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;border-bottom:1px solid #e5e7eb}
            .itp-utm-ht td{padding:8px 14px;font-size:.85rem;border-bottom:1px solid #f3f4f6}
            .itp-utm-ht tbody tr:hover{background:#f9fafb}
            .itp-utm-url-cell{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:.8rem}
            @media(max-width:960px){.itp-utm-grid{grid-template-columns:1fr}}
        </style>
        <div class="itp">
            <div class="itp-header">
                <div class="itp-header-top">
                    <div>
                        <h1 class="itp-title"><?php esc_html_e( 'UTM Builder', 'insight-tracker-pro' ); ?></h1>
                        <p class="itp-subtitle"><?php esc_html_e( 'Generate tagged URLs to track your campaigns', 'insight-tracker-pro' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="itp-utm-grid">
                <div class="itp-utm-form">
                    <h3><?php esc_html_e( 'Build Your Link', 'insight-tracker-pro' ); ?></h3>
                    <form method="post">
                        <?php wp_nonce_field( 'itp_utm_build' ); ?>

                        <div class="itp-utm-field">
                            <label><?php esc_html_e( 'Page URL', 'insight-tracker-pro' ); ?> *</label>
                            <select id="itp-utm-page-select" onchange="document.getElementById('itp-utm-url').value=this.value">
                                <option value=""><?php esc_html_e( '— Select a page —', 'insight-tracker-pro' ); ?></option>
                                <?php foreach ( $posts as $p ): ?>
                                    <option value="<?php echo esc_attr( get_permalink( $p ) ); ?>"><?php echo esc_html( $p->post_title . ' (' . $p->post_type . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="url" name="itp_utm_url" id="itp-utm-url" value="<?php echo esc_attr( $_POST['itp_utm_url'] ?? '' ); ?>" placeholder="https://yoursite.com/page" required style="margin-top:6px;">
                            <small><?php esc_html_e( 'Select a page or paste any URL', 'insight-tracker-pro' ); ?></small>
                        </div>

                        <div class="itp-utm-field">
                            <label><?php esc_html_e( 'Campaign Source', 'insight-tracker-pro' ); ?> (utm_source) *</label>
                            <input type="text" name="itp_utm_source" value="<?php echo esc_attr( $_POST['itp_utm_source'] ?? '' ); ?>" placeholder="facebook, google, newsletter" required>
                            <div class="itp-utm-presets">
                                <?php foreach ( ['facebook','google','instagram','twitter','linkedin','tiktok','youtube','newsletter','email'] as $s ): ?>
                                    <button type="button" onclick="this.closest('.itp-utm-field').querySelector('input').value='<?php echo esc_attr( $s ); ?>'"><?php echo esc_html( $s ); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="itp-utm-field">
                            <label><?php esc_html_e( 'Campaign Medium', 'insight-tracker-pro' ); ?> (utm_medium) *</label>
                            <input type="text" name="itp_utm_medium" value="<?php echo esc_attr( $_POST['itp_utm_medium'] ?? '' ); ?>" placeholder="cpc, social, email, banner" required>
                            <div class="itp-utm-presets">
                                <?php foreach ( ['cpc','social','email','banner','referral','organic','video','affiliate'] as $m ): ?>
                                    <button type="button" onclick="this.closest('.itp-utm-field').querySelector('input').value='<?php echo esc_attr( $m ); ?>'"><?php echo esc_html( $m ); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="itp-utm-field">
                            <label><?php esc_html_e( 'Campaign Name', 'insight-tracker-pro' ); ?> (utm_campaign)</label>
                            <input type="text" name="itp_utm_campaign" value="<?php echo esc_attr( $_POST['itp_utm_campaign'] ?? '' ); ?>" placeholder="spring_sale, product_launch">
                        </div>

                        <div class="itp-utm-field">
                            <label><?php esc_html_e( 'Campaign Content', 'insight-tracker-pro' ); ?> (utm_content)</label>
                            <input type="text" name="itp_utm_content" value="<?php echo esc_attr( $_POST['itp_utm_content'] ?? '' ); ?>" placeholder="header_banner, sidebar_cta">
                            <small><?php esc_html_e( 'Use to differentiate ads or links pointing to the same URL', 'insight-tracker-pro' ); ?></small>
                        </div>

                        <div class="itp-utm-field">
                            <label><?php esc_html_e( 'Campaign Term', 'insight-tracker-pro' ); ?> (utm_term)</label>
                            <input type="text" name="itp_utm_term" value="<?php echo esc_attr( $_POST['itp_utm_term'] ?? '' ); ?>" placeholder="running+shoes, coaching">
                            <small><?php esc_html_e( 'Keywords for paid search campaigns', 'insight-tracker-pro' ); ?></small>
                        </div>

                        <button type="submit" class="itp-utm-submit"><?php esc_html_e( 'Generate Link', 'insight-tracker-pro' ); ?></button>
                    </form>
                </div>

                <div class="itp-utm-result">
                    <h3><?php esc_html_e( 'Generated URL', 'insight-tracker-pro' ); ?></h3>
                    <?php if ( $generated_url ): ?>
                        <div class="itp-utm-output" id="itp-utm-output">
                            <?php echo esc_html( $generated_url ); ?>
                            <button class="itp-utm-copy" onclick="navigator.clipboard.writeText(document.getElementById('itp-utm-output').textContent.trim());this.textContent='Copied!';setTimeout(()=>{this.textContent='Copy'},1500)">Copy</button>
                        </div>

                        <div class="itp-utm-preview">
                            <div class="itp-utm-preview-h"><?php esc_html_e( 'Parameters', 'insight-tracker-pro' ); ?></div>
                            <?php
                            $parsed = wp_parse_url( $generated_url );
                            parse_str( $parsed['query'] ?? '', $params );
                            foreach ( $params as $k => $v ):
                                if ( strpos( $k, 'utm_' ) !== 0 ) continue;
                            ?>
                                <div class="itp-utm-preview-row">
                                    <span class="itp-utm-preview-k"><?php echo esc_html( $k ); ?></span>
                                    <span class="itp-utm-preview-v"><?php echo esc_html( $v ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px;color:#9ca3af;">
                            <div style="font-size:24px;margin-bottom:8px;">&#128279;</div>
                            <div style="font-size:13px;"><?php esc_html_e( 'Fill in the form and click Generate Link', 'insight-tracker-pro' ); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! empty( $history ) ): ?>
            <div class="itp-utm-history">
                <h3><?php esc_html_e( 'Recent Links', 'insight-tracker-pro' ); ?></h3>
                <table class="itp-utm-ht">
                    <thead><tr>
                        <th><?php esc_html_e( 'Date', 'insight-tracker-pro' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'insight-tracker-pro' ); ?></th>
                        <th><?php esc_html_e( 'Medium', 'insight-tracker-pro' ); ?></th>
                        <th><?php esc_html_e( 'Campaign', 'insight-tracker-pro' ); ?></th>
                        <th><?php esc_html_e( 'URL', 'insight-tracker-pro' ); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $history, 0, 20 ) as $h ): ?>
                        <tr>
                            <td style="white-space:nowrap;font-size:.82rem;color:#6b7280;"><?php echo esc_html( $h['created'] ?? '' ); ?></td>
                            <td style="font-weight:600;"><?php echo esc_html( $h['source'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $h['medium'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $h['campaign'] ?? '' ); ?></td>
                            <td class="itp-utm-url-cell" title="<?php echo esc_attr( $h['url'] ?? '' ); ?>"><?php echo esc_html( $h['url'] ?? '' ); ?></td>
                            <td><button class="itp-utm-copy" style="position:static" onclick="navigator.clipboard.writeText('<?php echo esc_js( $h['url'] ?? '' ); ?>');this.textContent='Copied!';setTimeout(()=>{this.textContent='Copy'},1500)">Copy</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
