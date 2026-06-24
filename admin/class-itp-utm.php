<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ITP_UTM {

    public function __construct() {
        if ( itp_get_setting( 'auto_utm' ) !== '1' ) {
            return;
        }

        add_filter( 'the_content', [ $this, 'inject_utms' ], 999 );
    }

    /* ─── Inject UTMs into external links ──────────────────── */

    public function inject_utms( $content ) {
        if ( empty( $content ) || is_admin() || wp_doing_ajax() ) {
            return $content;
        }

        // Only process if there are links
        if ( stripos( $content, '<a ' ) === false ) {
            return $content;
        }

        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );

        $content = preg_replace_callback(
            '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function( $matches ) use ( $site_host ) {
                $url  = $matches[1];
                $full = $matches[0];

                // Skip non-HTTP links
                if ( strpos( $url, 'http' ) !== 0 ) {
                    return $full;
                }

                // Skip internal links
                $link_host = wp_parse_url( $url, PHP_URL_HOST );
                if ( ! $link_host || $link_host === $site_host ) {
                    return $full;
                }

                // Skip if already has UTM params
                if ( stripos( $url, 'utm_source' ) !== false ) {
                    return $full;
                }

                // Build UTM params
                $utm_source = $this->replace_placeholders( itp_get_setting( 'utm_source_pattern' ) );
                $utm_medium = $this->replace_placeholders( itp_get_setting( 'utm_medium_pattern' ) );

                $args = [];
                if ( $utm_source ) {
                    $args['utm_source'] = $utm_source;
                }
                if ( $utm_medium ) {
                    $args['utm_medium'] = $utm_medium;
                }

                if ( empty( $args ) ) {
                    return $full;
                }

                $new_url = add_query_arg( $args, $url );

                return str_replace( $url, $new_url, $full );
            },
            $content
        );

        return $content;
    }

    /* ─── Replace placeholders ─────────────────────────────── */

    private function replace_placeholders( $pattern ) {
        if ( empty( $pattern ) ) {
            return '';
        }

        global $post;

        $site_name = sanitize_title( get_bloginfo( 'name' ) );
        $post_type = '';
        $category  = '';
        $post_id   = '';
        $slug      = '';

        if ( $post instanceof WP_Post ) {
            $post_type = $post->post_type;
            $post_id   = (string) $post->ID;
            $slug      = $post->post_name;

            $categories = get_the_category( $post->ID );
            if ( ! empty( $categories ) ) {
                $category = $categories[0]->slug;
            }
        }

        $replacements = [
            '{site_name}' => $site_name,
            '{post_type}' => $post_type,
            '{category}'  => $category,
            '{post_id}'   => $post_id,
            '{slug}'      => $slug,
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $pattern );
    }
}
