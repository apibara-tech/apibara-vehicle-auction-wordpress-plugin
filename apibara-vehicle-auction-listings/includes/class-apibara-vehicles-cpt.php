<?php
if (!defined('ABSPATH')) exit;

/**
 * Virtual auction routes.
 *
 * This class intentionally does NOT save vehicles as WordPress posts.
 * It creates SEO-friendly virtual URLs and renders vehicle data live from the Apibara API.
 */
class Apibara_Vehicles_CPT {
    public const POST_TYPE = 'auctions';

    private Apibara_Vehicles_API $api;

    public function __construct(Apibara_Vehicles_API $api) {
        $this->api = $api;

        add_action('init', [$this, 'rewrite_rules'], 20);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'template_redirect'], 1);
    }

    public function register_post_type(): void {
        // Backward-compatible no-op. Vehicles are rendered live and are not stored as WP posts.
    }

    public function rewrite_rules(): void {
        $base = self::base_slug();
        add_rewrite_rule('^' . preg_quote($base, '#') . '/(.+?)/?$', 'index.php?apibara_auction_path=$matches[1]', 'top');
        add_rewrite_rule('^' . preg_quote($base, '#') . '/?$', 'index.php?apibara_auctions_archive=1', 'top');
    }

    public function query_vars(array $vars): array {
        $vars[] = 'apibara_auction_path';
        $vars[] = 'apibara_auctions_archive';
        return $vars;
    }

    public function template_redirect(): void {
        $archive = (string) get_query_var('apibara_auctions_archive');
        $path = (string) get_query_var('apibara_auction_path');

        if ($archive === '1') {
            $this->mark_as_valid_page();
            add_filter('document_title_parts', function(array $parts): array {
                $parts['title'] = __('Vehicle auctions', 'apibara-vehicle-auction-listings');
                return $parts;
            });
            get_header();
            echo '<main class="apibara-virtual-page apibara-virtual-archive">';
            echo do_shortcode('[apibara_vehicles]');
            echo '</main>';
            get_footer();
            exit;
        }

        if ($path === '') {
            return;
        }

        $route = self::parse_path($path);
        $id = self::lookup_id_from_route($route, $path);

        if ($id === '') {
            return;
        }

        $this->mark_as_valid_page();
        add_filter('document_title_parts', function(array $parts) use ($route): array {
            $title = self::title_from_route($route);
            if ($title !== '') $parts['title'] = $title;
            return $parts;
        });
        add_action('wp_head', function() use ($path): void {
            echo '<link rel="canonical" href="' . esc_url(home_url('/' . self::base_slug() . '/' . self::sanitize_path($path) . '/')) . '" />' . "\n";
        }, 1);

        $atts = [];
        foreach (['platform','lot_number','slug','vin'] as $key) {
            if (!empty($route[$key])) $atts[] = $key . '="' . esc_attr($route[$key]) . '"';
        }

        get_header();
        echo '<main class="apibara-virtual-page apibara-virtual-single">';
        echo do_shortcode('[apibara_vehicle id="' . esc_attr($id) . '" ' . implode(' ', $atts) . ']');
        echo '</main>';
        get_footer();
        exit;
    }

    private function mark_as_valid_page(): void {
        global $wp_query;
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
        }
        status_header(200);
        nocache_headers();
    }

    public static function base_slug(): string {
        $o = Apibara_Vehicles_Plugin::get_options();
        $base = sanitize_title((string)($o['auction_base_slug'] ?? 'auctions'));
        return $base ?: 'auctions';
    }

    public static function pattern(): string {
        $o = Apibara_Vehicles_Plugin::get_options();
        $pattern = trim((string)($o['auction_slug_pattern'] ?? '{platform}/{lot_number}/{slug}/{vin}'));
        return $pattern ?: '{platform}/{lot_number}/{slug}/{vin}';
    }

    public static function sanitize_path(string $path): string {
        $parts = array_filter(array_map(static function($part) {
            return sanitize_title((string)$part);
        }, explode('/', trim($path, '/'))));
        return implode('/', $parts);
    }

    public static function parse_path(string $path): array {
        $pattern_parts = array_values(array_filter(explode('/', trim(self::pattern(), '/'))));
        $path_parts = array_values(array_filter(explode('/', self::sanitize_path($path))));
        $route = [];

        foreach ($pattern_parts as $index => $pattern_part) {
            if (!isset($path_parts[$index])) continue;
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $pattern_part, $m)) {
                $route[$m[1]] = sanitize_text_field((string)$path_parts[$index]);
            }
        }

        return $route;
    }

    public static function lookup_id_from_route(array $route, string $path = ''): string {
        if (!empty($route['slug']) && !empty($route['vin'])) {
            return sanitize_text_field((string)$route['slug'] . '-' . (string)$route['vin']);
        }

        foreach (['slug_vin', 'slugVin', 'id', 'lot_number', 'vin', 'slug'] as $key) {
            if (!empty($route[$key])) return sanitize_text_field((string)$route[$key]);
        }

        $parts = array_values(array_filter(explode('/', self::sanitize_path($path))));
        return $parts ? sanitize_text_field((string)end($parts)) : '';
    }

    public static function title_from_route(array $route): string {
        if (!empty($route['slug'])) {
            return ucwords(str_replace('-', ' ', (string)$route['slug']));
        }
        if (!empty($route['lot_number'])) {
            /* translators: %s: vehicle lot number. */
            return sprintf(__('Vehicle lot %s', 'apibara-vehicle-auction-listings'), (string) $route['lot_number']);
        }
        if (!empty($route['vin'])) {
            /* translators: %s: vehicle VIN. */
            return sprintf(__('Vehicle VIN %s', 'apibara-vehicle-auction-listings'), (string) $route['vin']);
        }
        return '';
    }

    public static function vehicle_url(array $vehicle): string {
        $path = self::vehicle_path($vehicle);
        if ($path === '') {
            $id = $vehicle['id'] ?? $vehicle['lot_number'] ?? $vehicle['lot'] ?? $vehicle['vin'] ?? '';
            return add_query_arg('vehicle_id', rawurlencode((string)$id), home_url('/' . self::base_slug() . '/'));
        }
        return home_url('/' . self::base_slug() . '/' . $path . '/');
    }

    public static function vehicle_path(array $vehicle): string {
        $title = trim((string)($vehicle['title'] ?? (($vehicle['year'] ?? '') . ' ' . ($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''))));
        $slug = $vehicle['slug'] ?? ($title ? sanitize_title($title) : 'vehicle');

        $values = [
            '{platform}' => $vehicle['platform'] ?? $vehicle['auction'] ?? '',
            '{lot_number}' => $vehicle['lot_number'] ?? $vehicle['lot'] ?? '',
            '{slug}' => $slug,
            '{vin}' => $vehicle['vin'] ?? '',
            '{id}' => $vehicle['id'] ?? $vehicle['vehicle_id'] ?? '',
        ];

        $path = self::pattern();
        foreach ($values as $token => $value) {
            $path = str_replace($token, sanitize_title((string)$value), $path);
        }

        $parts = array_filter(array_map('sanitize_title', explode('/', $path)));
        return implode('/', $parts);
    }

    // Backward-compatible helpers. No posts are searched or created anymore.
    public static function find_post_for_vehicle(array $v): ?WP_Post { return null; }
    public static function find_by_path(string $path): ?WP_Post { return null; }
    public static function post_path(int $post_id): string { return ''; }
    public static function build_path(int $post_id): string { return ''; }
    public static function vehicle_lookup_id(int $post_id): string { return ''; }
    public function update_path_meta(int $post_id): string { return ''; }
    public function sync_from_api(int $page = 1, int $per_page = 50): array {
        return ['ok' => false, 'message' => __('Sync is disabled. Vehicles are rendered live from the API and are not stored as WordPress posts.', 'apibara-vehicle-auction-listings')];
    }
}
