<?php
if (!defined('ABSPATH')) exit;

class Apibara_Vehicles_Plugin {
    private static $instance = null;
    public $api;
    public $admin;
    public $frontend;
    public $form;
    public $cpt;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init']);
        register_activation_hook(APIBARA_VEHICLES_FILE, [$this, 'activate']);
        register_deactivation_hook(APIBARA_VEHICLES_FILE, [$this, 'deactivate']);
    }

    public function init(): void {
        self::maybe_upgrade_options();
        $this->api = new Apibara_Vehicles_API();
        $this->form = new Apibara_Vehicles_Form($this->api);
        $this->cpt = new Apibara_Vehicles_CPT($this->api);
        $this->frontend = new Apibara_Vehicles_Frontend($this->api, $this->form);
        if (is_admin()) $this->admin = new Apibara_Vehicles_Admin($this->api);
    }

    public function activate(): void {
        $defaults = self::default_options();
        $existing = get_option('apibara_vehicles_options');
        if (!is_array($existing)) add_option('apibara_vehicles_options', $defaults, '', false);
        else update_option('apibara_vehicles_options', array_merge($defaults, $existing), false);
        if (class_exists('Apibara_Vehicles_CPT')) {
            $api = new Apibara_Vehicles_API();
            $cpt = new Apibara_Vehicles_CPT($api);
            $cpt->rewrite_rules();
        }
        flush_rewrite_rules();
    }

    public function deactivate(): void { flush_rewrite_rules(); }

    public static function maybe_upgrade_options(): void {
        $defaults = self::default_options();
        $options = get_option('apibara_vehicles_options', []);
        if (!is_array($options)) {
            update_option('apibara_vehicles_options', $defaults, false);
            return;
        }
        $changed = false;
        $previous_version = (string)($options['apibara_plugin_version'] ?? '');

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $options)) {
                $options[$key] = $value;
                $changed = true;
            }
        }

        /*
         * Repair options saved by v0.5.0.
         * That version used tabs in the admin page, but the sanitizer treated fields
         * absent from the current tab as empty values. As a result, saving one tab
         * could wipe listing/single field arrays and turn off important display flags.
         */
        if ($previous_version === '' || version_compare($previous_version, '0.5.1', '<')) {
            foreach (['list_fields', 'single_fields', 'single_detail_fields', 'form_fields', 'form_required_fields'] as $array_key) {
                if (empty($options[$array_key]) || !is_array($options[$array_key])) {
                    $options[$array_key] = $defaults[$array_key];
                    $changed = true;
                }
            }

            foreach (['enable_plugin_styles', 'enable_template_header', 'enable_view_switcher', 'enable_pagination', 'show_apibara_button', 'enable_filters', 'use_api_filters', 'filter_search', 'filter_lot_status', 'filter_sub_status', 'filter_auction_type', 'filter_make_model', 'filter_year', 'filter_price', 'filter_odometer', 'filter_specs', 'enable_countdown', 'enable_copy_buttons', 'enable_mini_slider', 'form_enabled'] as $enabled_key) {
                if (!array_key_exists($enabled_key, $options) || $options[$enabled_key] === '' || $options[$enabled_key] === null) {
                    $options[$enabled_key] = $defaults[$enabled_key];
                    $changed = true;
                }
            }
        }

        foreach (['api_base_url','api_list_path','api_single_path','single_page_url'] as $legacy_key) {
            if (array_key_exists($legacy_key, $options)) { unset($options[$legacy_key]); $changed = true; }
        }
        if (($options['apibara_plugin_version'] ?? '') !== APIBARA_VEHICLES_VERSION) {
            $options['apibara_plugin_version'] = APIBARA_VEHICLES_VERSION;
            $changed = true;
        }
        if ($changed) update_option('apibara_vehicles_options', array_merge($defaults, $options), false);
    }

    public static function default_options(): array {
        return [
            'api_key' => '',
            'per_page' => 12,
            'cache_minutes' => 10,
            'show_api_debug' => '',

            'auction_base_slug' => 'auctions',
            'auction_slug_pattern' => '{platform}/{lot_number}/{slug}/{vin}',
            'enable_plugin_styles' => '1',
            'enable_template_header' => '1',
            'template_badge_text' => 'Live vehicle data',
            'template_title' => 'Vehicle auction listings',
            'template_description' => 'Browse auction vehicles, inspect photos, compare prices and open full lot pages.',
            'default_results_view' => 'grid',
            'enable_view_switcher' => '1',
            'open_vehicle_new_tab' => '',
            'enable_pagination' => '1',
            'show_apibara_button' => '1',
            'apibara_button_text' => 'Get API key',
            'apibara_button_url' => 'https://apibara.tech/en/products/vehicle-auction-data-api',

            'enable_filters' => '1',
            'use_api_filters' => '1',
            'filter_search' => '1',
            'filter_lot_status' => '1',
            'filter_sub_status' => '1',
            'filter_auction_type' => '1',
            'filter_make_model' => '1',
            'filter_year' => '1',
            'filter_price' => '1',
            'filter_odometer' => '1',
            'filter_specs' => '1',
            'filter_lot_status_options' => "All|All\nBuy Now|Buy Now\nTimed|Timed",
            'filter_sub_status_options' => "Open|Open\nLive|Live\nEnded|Ended",
            'filter_auction_type_options' => "0|All\n1|Copart\n2|IAAI",
            'filter_make_options' => '',
            'filter_model_options_json' => '',
            'filter_vehicle_type_options' => '',
            'filter_color_options' => '',
            'filter_fuel_options' => '',
            'filter_transmission_options' => '',
            'filter_drive_options' => '',
            'filter_condition_options' => '',
            'filter_damage_options' => '',
            'filter_has_key_options' => "Yes\nNo",
            'price_min_default' => 0,
            'price_max_default' => 250000,
            'odometer_min_default' => 0,
            'odometer_max_default' => 500000,
            'year_min_default' => 1900,
            'year_max_default' => (int) gmdate('Y') + 1,

            'primary_color' => '#0891b2',
            'secondary_color' => '#2563eb',
            'accent_color' => '#10b981',
            'background_color' => '#f8fafc',
            'panel_background' => 'rgba(255,255,255,.90)',
            'card_background' => '#ffffff',
            'text_color' => '#0f172a',
            'muted_color' => '#64748b',
            'border_color' => '#e2e8f0',
            'card_radius' => 26,
            'image_ratio' => '16/10',
            'button_style' => 'rounded',
            'card_shadow' => 'modern',
            'grid_columns_desktop' => 2,
            'grid_columns_tablet' => 2,
            'grid_columns_mobile' => 1,
            'gallery_mode' => 'slider_lightbox',
            'enable_countdown' => '1',
            'enable_copy_buttons' => '1',
            'enable_mini_slider' => '1',

            'list_fields' => ['image','badges','title','location','odometer','fuel_transmission','engine_drive','price_panel','vin_lot','countdown','view_button'],
            'single_fields' => ['gallery','badges','title','price_panel','key_specs','vin_lot','countdown','details_table','contact_button'],
            'single_detail_fields' => ['lot_number','vin','platform','status','location','odometer','damage','sale_date','seller','keys','fuel','transmission','drive','engine','color','body_type','title_status'],

            'form_enabled' => '1',
            'form_email_to' => get_option('admin_email'),
            'form_subject' => 'New vehicle inquiry from website',
            'form_button_text' => 'Contact us',
            'form_title' => 'Contact us about this vehicle',
            'form_intro' => '',
            'form_footer' => '',
            'form_fields' => ['name','email','phone','message'],
            'form_required_fields' => ['name','email'],
            'form_custom_fields' => '',
            'success_message' => 'Thank you. We will contact you soon.',

            'translations_json' => '',
            'apibara_plugin_version' => APIBARA_VEHICLES_VERSION,
        ];
    }

    public static function get_options(): array {
        $options = get_option('apibara_vehicles_options', []);
        $options = array_merge(self::default_options(), is_array($options) ? $options : []);
        unset($options['api_base_url'], $options['api_list_path'], $options['api_single_path']);
        return $options;
    }

    public static function t(string $key, string $fallback): string {
        $options = self::get_options();
        $locale = determine_locale();
        $json = json_decode((string)($options['translations_json'] ?? ''), true);
        if (is_array($json) && isset($json[$locale][$key]) && $json[$locale][$key] !== '') {
            return esc_html((string)$json[$locale][$key]);
        }
        return esc_html($fallback);
    }
}
