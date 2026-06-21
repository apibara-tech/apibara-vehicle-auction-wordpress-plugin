<?php
if (!defined('ABSPATH')) exit;

class Apibara_Vehicles_Admin {
    private Apibara_Vehicles_API $api;
    private array $field_labels = [];

    public function __construct(Apibara_Vehicles_API $api) {
        $this->api = $api;
        $this->field_labels = self::field_labels();
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }

    public function menu(): void {
        add_menu_page(__('Apibara Vehicles', 'apibara-vehicle-auction-listings'), __('Apibara Vehicles', 'apibara-vehicle-auction-listings'), 'manage_options', 'apibara-vehicle-auction-listings', [$this, 'page'], 'dashicons-car', 56);
    }

    public function admin_assets(string $hook): void {
        if ($hook !== 'toplevel_page_apibara-vehicle-auction-listings') return;
        wp_add_inline_style('common', $this->admin_css());
    }

    public function settings(): void {
        register_setting('apibara_vehicles_group', 'apibara_vehicles_options', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => Apibara_Vehicles_Plugin::default_options(),
        ]);
    }

    public function sanitize($input): array {
        $defaults = Apibara_Vehicles_Plugin::default_options();
        $old = Apibara_Vehicles_Plugin::get_options();
        $input = is_array($input) ? wp_unslash($input) : [];

        /*
         * IMPORTANT:
         * The settings page is split into tabs. WordPress posts only fields from
         * the currently opened tab, so missing keys must NOT be treated as empty.
         * Start with old values and update only submitted fields.
         */
        $out = array_merge($defaults, $old);

        unset($input['apibara_current_tab']);

        $text_keys = [
            'api_key','auction_base_slug','auction_slug_pattern',
            'primary_color','secondary_color','accent_color','background_color','panel_background','card_background','text_color','muted_color','border_color',
            'button_style','card_shadow','image_ratio','gallery_mode','default_results_view',
            'filter_lot_status_options','filter_sub_status_options','filter_auction_type_options','filter_make_options','filter_model_options_json','filter_vehicle_type_options','filter_color_options','filter_fuel_options','filter_transmission_options','filter_drive_options','filter_condition_options','filter_damage_options','filter_has_key_options',
            'form_email_to','form_subject','form_button_text','form_title','form_intro','form_footer','success_message','form_custom_fields','translations_json'
        ];

        foreach ($text_keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            if ($key === 'api_key') {
                $new_api_key = trim(sanitize_text_field((string) $input[$key]));

                // Do not expose the saved key in the password field. If the field is left blank, keep the old key.
                if ($new_api_key !== '') {
                    $out[$key] = $new_api_key;
                }

                continue;
            }

            if ($key === 'translations_json' || $key === 'filter_model_options_json') {
                $out[$key] = (string) $input[$key];
            } else {
                $out[$key] = sanitize_textarea_field((string) $input[$key]);
            }
        }

        foreach (['primary_color','secondary_color','accent_color','background_color','card_background','text_color','muted_color','border_color'] as $color_key) {
            if (!array_key_exists($color_key, $input)) {
                continue;
            }
            $color = sanitize_hex_color((string) $input[$color_key]);
            $out[$color_key] = $color ?: $defaults[$color_key];
        }

        $out['api_key'] = trim((string)($out['api_key'] ?? ''));
        $out['form_email_to'] = sanitize_email((string)($out['form_email_to'] ?? '')) ?: get_option('admin_email');
        $out['auction_base_slug'] = sanitize_title((string)($out['auction_base_slug'] ?? 'auctions')) ?: 'auctions';
        $out['auction_slug_pattern'] = trim((string)($out['auction_slug_pattern'] ?? '{platform}/{lot_number}/{slug}/{vin}'));

        foreach (['per_page'=>[1,20], 'cache_minutes'=>[0,1440], 'card_radius'=>[0,60], 'grid_columns_desktop'=>[1,6], 'grid_columns_tablet'=>[1,4], 'grid_columns_mobile'=>[1,2], 'price_min_default'=>[0,10000000], 'price_max_default'=>[0,10000000], 'odometer_min_default'=>[0,5000000], 'odometer_max_default'=>[0,5000000], 'year_min_default'=>[1900,2100], 'year_max_default'=>[1900,2100]] as $key => $range) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $out[$key] = max($range[0], min($range[1], (int)$input[$key]));
        }

        foreach ($this->checkbox_keys() as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = !empty($input[$key]) && (string)$input[$key] !== '0' ? '1' : '';
            }
        }

        $out['default_results_view'] = in_array((string)($out['default_results_view'] ?? 'grid'), ['grid','list'], true) ? $out['default_results_view'] : 'grid';
        $out['gallery_mode'] = in_array((string)($out['gallery_mode'] ?? 'slider_lightbox'), ['simple','slider','lightbox','slider_lightbox'], true) ? $out['gallery_mode'] : 'slider_lightbox';
        $out['card_shadow'] = in_array((string)($out['card_shadow'] ?? 'modern'), ['none','soft','modern','strong'], true) ? $out['card_shadow'] : 'modern';
        $out['button_style'] = in_array((string)($out['button_style'] ?? 'rounded'), ['rounded','pill','square'], true) ? $out['button_style'] : 'rounded';

        $allowed_fields = array_keys($this->field_labels);
        foreach (['list_fields','single_fields','single_detail_fields','form_fields','form_required_fields'] as $arr_key) {
            if (!array_key_exists($arr_key, $input)) {
                continue;
            }

            $values = is_array($input[$arr_key]) ? array_map('sanitize_key', $input[$arr_key]) : [];
            $values = array_values(array_filter($values, static function($value) {
                return $value !== '';
            }));

            if (in_array($arr_key, ['list_fields','single_fields','single_detail_fields'], true)) {
                $values = array_values(array_intersect($values, $allowed_fields));
            } else {
                $values = array_values(array_intersect($values, ['name','email','phone','message']));
            }

            /*
             * Keep templates usable. Users can hide individual blocks, but an empty
             * submitted field group usually means the browser posted no checked
             * values. Do not leave the frontend with completely blank cards.
             */
            if (empty($values) && in_array($arr_key, ['list_fields','single_fields','single_detail_fields'], true)) {
                $values = $defaults[$arr_key];
            }

            $out[$arr_key] = $values;
        }

        $out['apibara_plugin_version'] = APIBARA_VEHICLES_VERSION;

        delete_transient('apibara_vehicles_test_connection');
        flush_rewrite_rules(false);

        return array_merge($defaults, $out);
    }

    private function checkbox_keys(): array {
        return ['show_api_debug','enable_plugin_styles','enable_template_header','enable_view_switcher','open_vehicle_new_tab','enable_pagination','show_apibara_button','enable_filters','use_api_filters','filter_search','filter_lot_status','filter_sub_status','filter_auction_type','filter_make_model','filter_year','filter_price','filter_odometer','filter_specs','enable_countdown','enable_copy_buttons','enable_mini_slider','form_enabled'];
    }

    public function page(): void {
        if (!current_user_can('manage_options')) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin tab navigation uses a safe GET parameter and does not change state.
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'api';
        $tabs = [
            'api' => __('API Key', 'apibara-vehicle-auction-listings'),
            'general' => __('General', 'apibara-vehicle-auction-listings'),
            'filters' => __('Filters', 'apibara-vehicle-auction-listings'),
            'design' => __('Design', 'apibara-vehicle-auction-listings'),
            'fields' => __('Fields & Template', 'apibara-vehicle-auction-listings'),
            'form' => __('Contact Form', 'apibara-vehicle-auction-listings'),
            'docs' => __('Documentation', 'apibara-vehicle-auction-listings'),
        ];
        if (!isset($tabs[$tab])) $tab = 'api';
        $o = Apibara_Vehicles_Plugin::get_options();
        ?>
        <div class="wrap apibara-admin">
            <div class="apibara-admin-hero">
                <div>
                    <p class="apibara-kicker">Apibara WordPress Connector</p>
                    <h1><?php esc_html_e('Vehicle Auction Listings', 'apibara-vehicle-auction-listings'); ?></h1>
                    <p><?php esc_html_e('Modern live templates for Copart and IAAI vehicle auction data. No vehicles are stored in WordPress.', 'apibara-vehicle-auction-listings'); ?></p>
                </div>
                <div class="apibara-admin-status <?php echo $this->api->has_key() ? 'is-ok' : 'is-warn'; ?>">
                    <?php echo $this->api->has_key() ? esc_html__('API key configured', 'apibara-vehicle-auction-listings') : esc_html__('API key missing', 'apibara-vehicle-auction-listings'); ?>
                </div>
            </div>
            <nav class="nav-tab-wrapper apibara-tabs">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=apibara-vehicle-auction-listings&tab=' . $key)); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($tab === 'docs'): ?>
                <?php $this->render_docs_tab($o); ?>
            <?php else: ?>
                <form method="post" action="options.php" class="apibara-settings-card">
                    <?php settings_fields('apibara_vehicles_group'); ?>
                    <input type="hidden" name="apibara_vehicles_options[apibara_current_tab]" value="<?php echo esc_attr($tab); ?>">
                    <?php $this->render_tab($tab, $o); ?>
                    <?php submit_button(__('Save settings', 'apibara-vehicle-auction-listings')); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_tab(string $tab, array $o): void {
        switch ($tab) {
            case 'general': $this->render_general_tab($o); break;
            case 'filters': $this->render_filters_tab($o); break;
            case 'design': $this->render_design_tab($o); break;
            case 'fields': $this->render_fields_tab($o); break;
            case 'form': $this->render_form_tab($o); break;
            case 'api': default: $this->render_api_tab($o); break;
        }
    }

    private function render_api_tab(array $o): void { ?>
        <h2><?php esc_html_e('API Key', 'apibara-vehicle-auction-listings'); ?></h2>
        <p class="description"><?php esc_html_e('Connect this WordPress site to Apibara Vehicle Auction API. Vehicles are loaded live from Apibara and are not stored as WordPress posts.', 'apibara-vehicle-auction-listings'); ?></p>
        <?php $this->render_apibara_links(); ?>
        <?php $this->password('api_key', __('Apibara API key', 'apibara-vehicle-auction-listings'), $o); ?>
        <p class="description"><?php esc_html_e('For security, the saved API key is never printed into the page HTML. Leave this field blank to keep the existing key, or enter a new key to replace it.', 'apibara-vehicle-auction-listings'); ?></p>
        <?php $this->number('cache_minutes', __('Cache API responses for minutes', 'apibara-vehicle-auction-listings'), $o, 0, 1440); ?>
        <?php $this->checkbox('show_api_debug', __('Show API debug URL to administrators', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->render_usage_stats(); ?>
		<?php $this->render_integration_help_box(); ?>
    <?php }

    private function render_general_tab(array $o): void { ?>
        <h2><?php esc_html_e('General settings', 'apibara-vehicle-auction-listings'); ?></h2>
        <div class="apibara-grid-admin two">
            <?php $this->text('auction_base_slug', __('Base slug', 'apibara-vehicle-auction-listings'), $o, 'auctions'); ?>
            <?php $this->text('auction_slug_pattern', __('Vehicle URL structure', 'apibara-vehicle-auction-listings'), $o, '{platform}/{lot_number}/{slug}/{vin}'); ?>
            <?php $this->number('per_page', __('Vehicles per page', 'apibara-vehicle-auction-listings'), $o, 1, 20); ?>
            <?php $this->select('default_results_view', __('Default results view', 'apibara-vehicle-auction-listings'), $o, ['grid'=>'Grid','list'=>'List']); ?>
        </div>
        <?php $this->checkbox('enable_template_header', __('Show modern header above listings', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('enable_view_switcher', __('Show Grid/List switcher', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('enable_pagination', __('Enable cursor pagination', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('open_vehicle_new_tab', __('Open vehicle pages in a new tab', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('show_apibara_button', __('Show Apibara website button in listing header', 'apibara-vehicle-auction-listings'), $o); ?>
        <p class="description">
            <?php esc_html_e('Header text and Apibara button destination are built into the plugin, so regular site owners only configure functional options here.', 'apibara-vehicle-auction-listings'); ?>
        </p>
    <?php }

    private function render_filters_tab(array $o): void { ?>
        <h2><?php esc_html_e('Filter panel', 'apibara-vehicle-auction-listings'); ?></h2>
        <p class="description"><?php esc_html_e('Filter values are loaded from the Apibara filters endpoint. The first checkbox disables or enables the whole filter sidebar. The options below control individual filter blocks. Manual values are used only as fallback if the API filters endpoint is unavailable.', 'apibara-vehicle-auction-listings'); ?></p>
        <?php $this->checkbox('enable_filters', __('Enable filter sidebar completely', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('use_api_filters', __('Load filter options, makes, models and ranges from Apibara API', 'apibara-vehicle-auction-listings'), $o); ?>
        <h3><?php esc_html_e('Visible filter blocks', 'apibara-vehicle-auction-listings'); ?></h3>
        <div class="apibara-grid-admin three">
            <?php
            $filter_block_labels = [
                'filter_search' => __('Search input', 'apibara-vehicle-auction-listings'),
                'filter_lot_status' => __('Lot status buttons', 'apibara-vehicle-auction-listings'),
                'filter_sub_status' => __('Sub status buttons', 'apibara-vehicle-auction-listings'),
                'filter_auction_type' => __('Auction type buttons', 'apibara-vehicle-auction-listings'),
                'filter_make_model' => __('Make / model', 'apibara-vehicle-auction-listings'),
                'filter_year' => __('Year range', 'apibara-vehicle-auction-listings'),
                'filter_price' => __('Price range', 'apibara-vehicle-auction-listings'),
                'filter_odometer' => __('Odometer range', 'apibara-vehicle-auction-listings'),
                'filter_specs' => __('More specs filters', 'apibara-vehicle-auction-listings'),
            ];
            foreach ($filter_block_labels as $key => $label) {
                $this->checkbox($key, $label, $o);
            }
            ?>
        </div>
        <h3><?php esc_html_e('Manual fallback options', 'apibara-vehicle-auction-listings'); ?></h3>
        <p class="description"><?php esc_html_e('These fields are not required when API filters are enabled. They are used only as fallback.', 'apibara-vehicle-auction-listings'); ?></p>
        <details class="apibara-advanced" style="margin-top:12px;">
            <summary><?php esc_html_e('Open manual fallback settings', 'apibara-vehicle-auction-listings'); ?></summary>
            <div class="apibara-grid-admin two" style="margin-top:16px;">
                <?php $this->textarea('filter_lot_status_options', __('Lot status options: value|label', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_sub_status_options', __('Sub status options: value|label', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_auction_type_options', __('Auction type options: value|label', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_make_options', __('Makes', 'apibara-vehicle-auction-listings'), $o, 5); ?>
                <?php $this->textarea('filter_model_options_json', __('Models by make JSON', 'apibara-vehicle-auction-listings'), $o, 5); ?>
                <?php $this->textarea('filter_vehicle_type_options', __('Vehicle types', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_color_options', __('Colors', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_fuel_options', __('Fuel types', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_transmission_options', __('Transmissions', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_drive_options', __('Drive types', 'apibara-vehicle-auction-listings'), $o, 4); ?>
                <?php $this->textarea('filter_damage_options', __('Damages', 'apibara-vehicle-auction-listings'), $o, 4); ?>
            </div>
        </details>
        <h3><?php esc_html_e('Fallback range defaults', 'apibara-vehicle-auction-listings'); ?></h3>
        <div class="apibara-grid-admin four">
            <?php $this->number('price_min_default', __('Price min', 'apibara-vehicle-auction-listings'), $o, 0, 10000000); ?>
            <?php $this->number('price_max_default', __('Price max', 'apibara-vehicle-auction-listings'), $o, 0, 10000000); ?>
            <?php $this->number('odometer_min_default', __('Odometer min', 'apibara-vehicle-auction-listings'), $o, 0, 5000000); ?>
            <?php $this->number('odometer_max_default', __('Odometer max', 'apibara-vehicle-auction-listings'), $o, 0, 5000000); ?>
            <?php $this->number('year_min_default', __('Year min', 'apibara-vehicle-auction-listings'), $o, 1900, 2100); ?>
            <?php $this->number('year_max_default', __('Year max', 'apibara-vehicle-auction-listings'), $o, 1900, 2100); ?>
        </div>
    <?php }

    private function render_design_tab(array $o): void { ?>
        <h2><?php esc_html_e('Design', 'apibara-vehicle-auction-listings'); ?></h2>
        <?php $this->checkbox('enable_plugin_styles', __('Use built-in modern template styles', 'apibara-vehicle-auction-listings'), $o); ?>
        <div class="apibara-grid-admin three">
            <?php
            $color_labels = [
                'primary_color' => __('Primary color', 'apibara-vehicle-auction-listings'),
                'secondary_color' => __('Secondary color', 'apibara-vehicle-auction-listings'),
                'accent_color' => __('Accent color', 'apibara-vehicle-auction-listings'),
                'background_color' => __('Page background', 'apibara-vehicle-auction-listings'),
                'card_background' => __('Card background', 'apibara-vehicle-auction-listings'),
                'text_color' => __('Text color', 'apibara-vehicle-auction-listings'),
                'muted_color' => __('Muted text', 'apibara-vehicle-auction-listings'),
                'border_color' => __('Border color', 'apibara-vehicle-auction-listings'),
            ];
            foreach ($color_labels as $key => $label) {
                $this->color($key, $label, $o);
            }
            ?>
            <?php $this->text('panel_background', __('Panel background CSS', 'apibara-vehicle-auction-listings'), $o, 'rgba(255,255,255,.90)'); ?>
            <?php $this->number('card_radius', __('Border radius', 'apibara-vehicle-auction-listings'), $o, 0, 60); ?>
            <?php $this->text('image_ratio', __('Image ratio', 'apibara-vehicle-auction-listings'), $o, '16/10'); ?>
            <?php $this->select('card_shadow', __('Card shadow', 'apibara-vehicle-auction-listings'), $o, ['none'=>'None','soft'=>'Soft','modern'=>'Modern','strong'=>'Strong']); ?>
            <?php $this->select('button_style', __('Button style', 'apibara-vehicle-auction-listings'), $o, ['rounded'=>'Rounded','pill'=>'Pill','square'=>'Square']); ?>
            <?php $this->select('gallery_mode', __('Gallery mode', 'apibara-vehicle-auction-listings'), $o, ['simple'=>'Simple grid','slider'=>'Slider','lightbox'=>'Lightbox','slider_lightbox'=>'Slider + lightbox']); ?>
            <?php $this->number('grid_columns_desktop', __('Grid columns desktop', 'apibara-vehicle-auction-listings'), $o, 1, 6); ?>
            <?php $this->number('grid_columns_tablet', __('Grid columns tablet', 'apibara-vehicle-auction-listings'), $o, 1, 4); ?>
            <?php $this->number('grid_columns_mobile', __('Grid columns mobile', 'apibara-vehicle-auction-listings'), $o, 1, 2); ?>
        </div>
        <h3><?php esc_html_e('Interactive UI', 'apibara-vehicle-auction-listings'); ?></h3>
        <?php $this->checkbox('enable_countdown', __('Enable live countdown timers', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('enable_copy_buttons', __('Enable click-to-copy VIN and lot', 'apibara-vehicle-auction-listings'), $o); ?>
        <?php $this->checkbox('enable_mini_slider', __('Enable mini image sliders in listing cards', 'apibara-vehicle-auction-listings'), $o); ?>
    <?php }

    private function render_fields_tab(array $o): void { ?>
        <h2><?php esc_html_e('Fields and template blocks', 'apibara-vehicle-auction-listings'); ?></h2>
        <div class="apibara-admin-columns">
            <div><?php $this->checkbox_group('list_fields', __('Listing page blocks', 'apibara-vehicle-auction-listings'), (array)$o['list_fields'], $this->list_field_options()); ?></div>
            <div><?php $this->checkbox_group('single_fields', __('Single lot page blocks', 'apibara-vehicle-auction-listings'), (array)$o['single_fields'], $this->single_field_options()); ?></div>
        </div>
        <h3><?php esc_html_e('Single lot details table', 'apibara-vehicle-auction-listings'); ?></h3>
        <?php $this->checkbox_group('single_detail_fields', __('Fields in the details table', 'apibara-vehicle-auction-listings'), (array)$o['single_detail_fields'], $this->detail_field_options()); ?>
    <?php }

    private function render_form_tab(array $o): void { ?>
        <h2><?php esc_html_e('Contact form', 'apibara-vehicle-auction-listings'); ?></h2>
        <?php $this->checkbox('form_enabled', __('Enable contact form on lot pages', 'apibara-vehicle-auction-listings'), $o); ?>
        <div class="apibara-grid-admin two">
            <?php $this->text('form_email_to', __('Recipient email', 'apibara-vehicle-auction-listings'), $o, get_option('admin_email')); ?>
            <?php $this->text('form_subject', __('Email subject', 'apibara-vehicle-auction-listings'), $o, 'New vehicle inquiry from website'); ?>
            <?php $this->text('form_button_text', __('Button text', 'apibara-vehicle-auction-listings'), $o, 'Contact us'); ?>
            <?php $this->text('form_title', __('Form title', 'apibara-vehicle-auction-listings'), $o, 'Contact us about this vehicle'); ?>
        </div>
        <?php $this->textarea('form_intro', __('Text above form', 'apibara-vehicle-auction-listings'), $o, 4); ?>
        <?php $this->textarea('form_footer', __('Text below form', 'apibara-vehicle-auction-listings'), $o, 4); ?>
        <div class="apibara-admin-columns">
            <div><?php $this->checkbox_group('form_fields', __('Enabled fields', 'apibara-vehicle-auction-listings'), (array)$o['form_fields'], ['name'=>'Name','email'=>'Email','phone'=>'Phone','message'=>'Message']); ?></div>
            <div><?php $this->checkbox_group('form_required_fields', __('Required fields', 'apibara-vehicle-auction-listings'), (array)$o['form_required_fields'], ['name'=>'Name','email'=>'Email','phone'=>'Phone','message'=>'Message']); ?></div>
        </div>
        <?php $this->textarea('form_custom_fields', __('Custom fields', 'apibara-vehicle-auction-listings'), $o, 5); ?>
        <p class="description"><?php esc_html_e('Format: key|Label|type|required|option1,option2. Types: text, email, tel, textarea, select.', 'apibara-vehicle-auction-listings'); ?></p>
        <?php $this->text('success_message', __('Success message', 'apibara-vehicle-auction-listings'), $o, 'Thank you. We will contact you soon.'); ?>
    <?php }

    private function render_docs_tab(array $o): void { ?>
        <div class="apibara-settings-card">
            <h2><?php esc_html_e('Documentation', 'apibara-vehicle-auction-listings'); ?></h2>
            <p><strong><?php esc_html_e('Listing URL:', 'apibara-vehicle-auction-listings'); ?></strong> <code><?php echo esc_html(home_url('/' . $o['auction_base_slug'] . '/')); ?></code></p>
            <p><strong><?php esc_html_e('Example lot URL:', 'apibara-vehicle-auction-listings'); ?></strong> <code><?php echo esc_html(home_url('/' . $o['auction_base_slug'] . '/copart/46205636/2020-chevrolet-equinox-lt/2gnaxkev5l6257738/')); ?></code></p>
            <p><strong><?php esc_html_e('Shortcodes:', 'apibara-vehicle-auction-listings'); ?></strong> <code>[apibara_vehicles]</code> <code>[apibara_vehicle vin="2GNAXKEV5L6257738"]</code></p>
            <ol>
                <li><?php esc_html_e('Enter your Apibara API key.', 'apibara-vehicle-auction-listings'); ?></li>
                <li><?php esc_html_e('Configure filters, design, listing blocks and single lot blocks.', 'apibara-vehicle-auction-listings'); ?></li>
                <li><?php esc_html_e('Go to Settings > Permalinks and click Save Changes after changing the base slug.', 'apibara-vehicle-auction-listings'); ?></li>
            </ol>
            <p><?php esc_html_e('Vehicles are rendered live from the Apibara API. They are not stored as WordPress posts.', 'apibara-vehicle-auction-listings'); ?></p>
        </div>
    <?php }

	private function render_integration_help_box(): void {
		echo '<div class="apibara-integration-help">';
		echo '<h3>' . esc_html__('Need help with integration or design?', 'apibara-vehicle-auction-listings') . '</h3>';
		echo '<p>' . esc_html__('If you want to connect Apibara Vehicle Auction API to your own CRM, marketplace, dealer website, importer platform or custom design, contact us. We can help with API integration, plugin setup, custom templates and adapting the design to your system.', 'apibara-vehicle-auction-listings') . '</p>';

		echo '<div class="apibara-integration-help-actions">';
		echo '<a class="button button-primary" href="mailto:admin@apibara.tech?subject=Apibara%20integration%20help" target="_blank" rel="noopener noreferrer">';
		echo esc_html__('Contact us', 'apibara-vehicle-auction-listings');
		echo '</a>';

		echo '<a class="button" href="https://apibara.tech/en/products/vehicle-auction-data-api/docs" target="_blank" rel="noopener noreferrer">';
		echo esc_html__('View documentation', 'apibara-vehicle-auction-listings');
		echo '</a>';
		echo '</div>';

		echo '</div>';
	}

    private function render_apibara_links(): void {
        $links = [
            [
                'label' => __('Get API key', 'apibara-vehicle-auction-listings'),
                'url' => 'https://apibara.tech/en/account/api-keys',
                'primary' => true,
            ],
            [
                'label' => __('Product page', 'apibara-vehicle-auction-listings'),
                'url' => 'https://apibara.tech/en/products/vehicle-auction-data-api',
                'primary' => false,
            ],
            [
                'label' => __('Documentation', 'apibara-vehicle-auction-listings'),
                'url' => 'https://apibara.tech/en/products/vehicle-auction-data-api/docs',
                'primary' => false,
            ],
            [
                'label' => __('API endpoints', 'apibara-vehicle-auction-listings'),
                'url' => 'https://apibara.tech/en/products/vehicle-auction-data-api/endpoints',
                'primary' => false,
            ],
            [
                'label' => __('Live demo', 'apibara-vehicle-auction-listings'),
                'url' => 'https://apibara.tech/en/products/vehicle-auction-data-api/demo',
                'primary' => false,
            ],
            [
                'label' => __('Pricing', 'apibara-vehicle-auction-listings'),
                'url' => 'https://apibara.tech/en/products/vehicle-auction-data-api#pricing',
                'primary' => false,
            ],
        ];

        echo '<div class="apibara-quick-links">';
        echo '<div>';
        echo '<h3>' . esc_html__('Need an API key?', 'apibara-vehicle-auction-listings') . '</h3>';
        echo '<p>' . esc_html__('Create an Apibara account, choose a Vehicle Auction API plan, then copy your API key into this plugin.', 'apibara-vehicle-auction-listings') . '</p>';
        echo '</div>';
        echo '<div class="apibara-quick-links-actions">';

        foreach ($links as $link) {
            $class = !empty($link['primary']) ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($link['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link['label']) . '</a>';
        }

        echo '</div>';
        echo '</div>';
    }


    private function render_usage_stats(): void {
        echo '<hr style="margin:24px 0;">';
        echo '<h2>' . esc_html__('Plan usage', 'apibara-vehicle-auction-listings') . '</h2>';

        if (!$this->api->has_key()) {
            echo '<p class="description">' . esc_html__('Enter and save your Apibara API key to see plan usage statistics.', 'apibara-vehicle-auction-listings') . '</p>';
            return;
        }

        $usage = $this->api->usage();

        if (!is_array($usage)) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Usage statistics are not available.', 'apibara-vehicle-auction-listings') . '</p></div>';
            return;
        }

        if (empty($usage['ok'])) {
            $message = $usage['error'] ?? $usage['message'] ?? __('Usage statistics are not available.', 'apibara-vehicle-auction-listings');

            if (is_array($message)) {
                $message = wp_json_encode($message);
            }

            echo '<div class="notice notice-warning inline"><p>' . esc_html((string) $message) . '</p></div>';
            echo '<p class="description">' . esc_html__('This block is visible only to administrators.', 'apibara-vehicle-auction-listings') . '</p>';
            return;
        }

        $data = (!empty($usage['data']) && is_array($usage['data'])) ? $usage['data'] : $usage;

        if (empty($data) || !is_array($data)) {
            echo '<p class="description">' . esc_html__('Usage response is empty.', 'apibara-vehicle-auction-listings') . '</p>';
            return;
        }

        $product = (!empty($data['product']) && is_array($data['product'])) ? $data['product'] : [];
        $plan = (!empty($data['plan']) && is_array($data['plan'])) ? $data['plan'] : [];
        $quota = (!empty($data['quota']) && is_array($data['quota'])) ? $data['quota'] : [];
        $period = (!empty($data['period']) && is_array($data['period'])) ? $data['period'] : [];
        $summary = (!empty($data['summary']) && is_array($data['summary'])) ? $data['summary'] : [];

        $product_name = $this->usage_text($product['name'] ?? $data['product_name'] ?? $data['api_name'] ?? '');
        $plan_name = $this->usage_text($plan['name'] ?? $plan['title'] ?? $data['plan_name'] ?? $data['name'] ?? '');

        if ($plan_name === '') {
            $plan_name = __('Unknown', 'apibara-vehicle-auction-listings');
        }

        $used = $this->usage_number($quota['used'] ?? $data['requests_used'] ?? $data['used'] ?? $summary['period_requests'] ?? $data['period_requests'] ?? null);
        $limit = $this->usage_number($quota['limit'] ?? $data['requests_limit'] ?? $data['monthly_limit'] ?? $data['limit'] ?? null);
        $remaining = $this->usage_number($quota['left'] ?? $quota['remaining'] ?? $data['remaining'] ?? $data['requests_remaining'] ?? null);

        if ($remaining === null && $used !== null && $limit !== null && $limit > 0) {
            $remaining = max(0, $limit - $used);
        }

        $percent = $this->usage_number($quota['percent'] ?? $data['quota_percent'] ?? null);

        if ($percent === null && $used !== null && $limit !== null && $limit > 0) {
            $percent = min(100, max(0, round(($used / $limit) * 100)));
        }

        $period_from = $this->usage_text($period['from'] ?? $data['period_from'] ?? '');
        $period_to = $this->usage_text($period['to'] ?? $period['reset_at'] ?? $data['period_to'] ?? $data['reset_at'] ?? '');
        $period_text = '';

        if ($period_from !== '' && $period_to !== '') {
            $period_text = $period_from . ' — ' . $period_to;
        } elseif ($period_from !== '') {
            $period_text = $period_from;
        } elseif ($period_to !== '') {
            $period_text = $period_to;
        }

        $today_requests = $this->usage_number($summary['today_requests'] ?? $data['today_requests'] ?? null);
        $today_errors = $this->usage_number($summary['today_errors'] ?? $data['today_errors'] ?? null);
        $period_errors = $this->usage_number($summary['period_errors'] ?? $data['period_errors'] ?? null);
        $avg_latency = $this->usage_number($summary['avg_latency_ms'] ?? $data['avg_latency_ms'] ?? $data['avg_latency'] ?? null);

        echo '<div class="apibara-usage-grid">';

        if ($product_name !== '') {
            $this->usage_card(__('Product', 'apibara-vehicle-auction-listings'), $product_name);
        }

        $this->usage_card(__('Plan', 'apibara-vehicle-auction-listings'), $plan_name);

        if ($used !== null && $limit !== null && $limit > 0) {
            $this->usage_card(__('Used', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $used) . ' / ' . number_format_i18n((int) $limit));
        } elseif ($used !== null) {
            $this->usage_card(__('Used', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $used));
        }

        if ($limit !== null) {
            $this->usage_card(__('Limit', 'apibara-vehicle-auction-listings'), $limit > 0 ? number_format_i18n((int) $limit) : __('Unlimited', 'apibara-vehicle-auction-listings'));
        }

        if ($remaining !== null) {
            $this->usage_card(__('Remaining', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $remaining));
        }

        if ($today_requests !== null) {
            $this->usage_card(__('Today', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $today_requests));
        }

        if ($today_errors !== null) {
            $this->usage_card(__('Today errors', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $today_errors));
        }

        if ($period_errors !== null) {
            $this->usage_card(__('Period errors', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $period_errors));
        }

        if ($avg_latency !== null) {
            $this->usage_card(__('Avg latency', 'apibara-vehicle-auction-listings'), number_format_i18n((int) $avg_latency) . ' ms');
        }

        if ($period_text !== '') {
            $this->usage_card(__('Period / reset', 'apibara-vehicle-auction-listings'), $period_text);
        }

        echo '</div>';

        if ($percent !== null) {
            $percent = max(0, min(100, (float) $percent));
            echo '<div class="apibara-usage-bar" title="' . esc_attr(round($percent) . '%') . '"><span style="width:' . esc_attr((string) $percent) . '%"></span></div>';
            /* translators: %s: percentage of the API quota used. */
            echo '<p class="description" style="margin-top:8px;">' . esc_html(sprintf(__('Quota used: %s%%', 'apibara-vehicle-auction-listings'), number_format_i18n((float) $percent, 0))) . '</p>';
        }
    }

    private function usage_card(string $label, string $value): void {
        if ($value === '') {
            $value = '—';
        }

        echo '<div class="apibara-usage-card"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }

    private function usage_text($value): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            foreach (['name', 'title', 'label', 'value', 'text', 'from', 'to'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    return trim((string) $value[$key]);
                }
            }
        }

        return '';
    }

    private function usage_number($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            foreach (['value', 'count', 'total', 'used', 'limit', 'left', 'percent'] as $key) {
                if (isset($value[$key]) && is_numeric($value[$key])) {
                    return (float) $value[$key];
                }
            }
        }

        return null;
    }

    private function text(string $key, string $label, array $o, string $placeholder = ''): void { echo '<label class="apibara-field"><span>' . esc_html($label) . '</span><input type="text" name="apibara_vehicles_options[' . esc_attr($key) . ']" value="' . esc_attr((string)($o[$key] ?? '')) . '" placeholder="' . esc_attr($placeholder) . '"></label>'; }
    private function password(string $key, string $label, array $o): void {
        $has_value = trim((string)($o[$key] ?? '')) !== '';
        $placeholder = $has_value ? __('API key is saved. Enter a new key to replace it.', 'apibara-vehicle-auction-listings') : '';

        echo '<label class="apibara-field"><span>' . esc_html($label) . '</span><input type="password" autocomplete="off" name="apibara_vehicles_options[' . esc_attr($key) . ']" value="" placeholder="' . esc_attr($placeholder) . '"></label>';

        if ($has_value) {
            echo '<p class="description">' . esc_html__('An API key is currently saved. It is hidden for security.', 'apibara-vehicle-auction-listings') . '</p>';
        }
    }
    private function color(string $key, string $label, array $o): void { echo '<label class="apibara-field"><span>' . esc_html($label) . '</span><input type="color" name="apibara_vehicles_options[' . esc_attr($key) . ']" value="' . esc_attr((string)($o[$key] ?? '#000000')) . '"></label>'; }
    private function number(string $key, string $label, array $o, int $min, int $max): void { echo '<label class="apibara-field"><span>' . esc_html($label) . '</span><input type="number" min="' . esc_attr((string)$min) . '" max="' . esc_attr((string)$max) . '" name="apibara_vehicles_options[' . esc_attr($key) . ']" value="' . esc_attr((string)($o[$key] ?? '')) . '"></label>'; }
    private function textarea(string $key, string $label, array $o, int $rows = 4): void { echo '<label class="apibara-field"><span>' . esc_html($label) . '</span><textarea rows="' . esc_attr((string)$rows) . '" name="apibara_vehicles_options[' . esc_attr($key) . ']">' . esc_textarea((string)($o[$key] ?? '')) . '</textarea></label>'; }
    private function select(string $key, string $label, array $o, array $choices): void { echo '<label class="apibara-field"><span>' . esc_html($label) . '</span><select name="apibara_vehicles_options[' . esc_attr($key) . ']">'; foreach ($choices as $value=>$choice_label) echo '<option value="' . esc_attr((string)$value) . '" ' . selected((string)($o[$key] ?? ''), (string)$value, false) . '>' . esc_html($choice_label) . '</option>'; echo '</select></label>'; }
    private function checkbox(string $key, string $label, array $o): void { echo '<input type="hidden" name="apibara_vehicles_options[' . esc_attr($key) . ']" value="0"><label class="apibara-check"><input type="checkbox" name="apibara_vehicles_options[' . esc_attr($key) . ']" value="1" ' . checked(!empty($o[$key]), true, false) . '> <span>' . esc_html($label) . '</span></label>'; }
    private function checkbox_group(string $key, string $label, array $selected, array $choices): void { echo '<div class="apibara-checkgroup"><strong>' . esc_html($label) . '</strong><input type="hidden" name="apibara_vehicles_options[' . esc_attr($key) . '][]" value=""><div>'; foreach ($choices as $value=>$choice_label) echo '<label><input type="checkbox" name="apibara_vehicles_options[' . esc_attr($key) . '][]" value="' . esc_attr((string)$value) . '" ' . checked(in_array((string)$value, array_map('strval',$selected), true), true, false) . '> ' . esc_html($choice_label) . '</label>'; echo '</div></div>'; }

    private function list_field_options(): array { return ['image'=>'Image / mini slider','badges'=>'Status and platform badges','title'=>'Vehicle title','location'=>'Location','odometer'=>'Odometer','fuel_transmission'=>'Fuel + transmission','engine_drive'=>'Engine + drive','price_panel'=>'Current bid / buy now / estimated cost','vin_lot'=>'VIN and lot copy block','countdown'=>'Countdown / time left','view_button'=>'View details button']; }
    private function single_field_options(): array { return ['gallery'=>'Gallery','badges'=>'Badges','title'=>'Title','price_panel'=>'Price panel','key_specs'=>'Key specs','vin_lot'=>'VIN and lot copy block','countdown'=>'Countdown','details_table'=>'Details table','contact_button'=>'Contact button']; }
    private function detail_field_options(): array { return array_intersect_key($this->field_labels, array_flip(['lot_number','vin','platform','status','location','odometer','damage','sale_date','seller','keys','fuel','transmission','drive','engine','color','body_type','title_status'])); }
    public static function field_labels(): array { return ['image'=>'Image','gallery'=>'Gallery','badges'=>'Badges','title'=>'Title','lot_number'=>'Lot number','vin'=>'VIN','platform'=>'Platform','year'=>'Year','make'=>'Make','model'=>'Model','price'=>'Price','auction'=>'Auction','status'=>'Status','location'=>'Location','odometer'=>'Odometer','damage'=>'Damage','sale_date'=>'Sale date','seller'=>'Seller','keys'=>'Keys','fuel'=>'Fuel','transmission'=>'Transmission','drive'=>'Drive','engine'=>'Engine','color'=>'Color','body_type'=>'Body type','title_status'=>'Title status','price_panel'=>'Price panel','vin_lot'=>'VIN/Lot block','countdown'=>'Countdown','view_button'=>'View button','key_specs'=>'Key specs','details_table'=>'Details table','contact_button'=>'Contact button','fuel_transmission'=>'Fuel + transmission','engine_drive'=>'Engine + drive']; }

    private function admin_css(): string { return '.apibara-admin-hero{margin:18px 0 16px;padding:24px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#0891b2);color:#fff;display:flex;justify-content:space-between;gap:20px;align-items:center}.apibara-admin-hero h1{color:#fff;margin:6px 0;font-size:30px}.apibara-kicker{text-transform:uppercase;letter-spacing:.16em;font-weight:800;font-size:11px;opacity:.8;margin:0}.apibara-admin-status{border-radius:999px;padding:9px 13px;font-weight:800;background:rgba(255,255,255,.14);white-space:nowrap}.apibara-admin-status.is-ok{box-shadow:inset 0 0 0 1px rgba(16,185,129,.55)}.apibara-admin-status.is-warn{box-shadow:inset 0 0 0 1px rgba(251,191,36,.65)}.apibara-tabs{margin-bottom:0}.apibara-settings-card{background:#fff;border:1px solid #dcdcde;border-top:0;padding:24px;max-width:1180px}.apibara-field{display:block;margin:0 0 16px}.apibara-field span{display:block;font-weight:700;margin-bottom:6px}.apibara-field input[type=text],.apibara-field input[type=password],.apibara-field input[type=number],.apibara-field textarea,.apibara-field select{width:100%;max-width:100%;border-radius:10px}.apibara-field input[type=color]{width:80px;height:38px;padding:2px;border-radius:10px}.apibara-check{display:flex;gap:8px;align-items:center;margin:10px 0;font-weight:600}.apibara-grid-admin{display:grid;gap:16px;margin:14px 0}.apibara-grid-admin.two{grid-template-columns:repeat(2,minmax(0,1fr))}.apibara-grid-admin.three{grid-template-columns:repeat(3,minmax(0,1fr))}.apibara-grid-admin.four{grid-template-columns:repeat(4,minmax(0,1fr))}.apibara-admin-columns{display:grid;grid-template-columns:1fr 1fr;gap:28px}.apibara-checkgroup{background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:16px;margin:14px 0}.apibara-checkgroup strong{display:block;margin-bottom:12px}.apibara-checkgroup div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.apibara-checkgroup label{display:block;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:9px 10px}.apibara-usage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0}.apibara-quick-links{margin:18px 0 22px;padding:18px;border:1px solid #dbeafe;background:linear-gradient(135deg,#eff6ff,#ecfeff);border-radius:18px;display:flex;justify-content:space-between;gap:18px;align-items:center}.apibara-quick-links h3{margin:0 0 6px;color:#0f172a}.apibara-quick-links p{margin:0;color:#475569;max-width:620px}.apibara-quick-links-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}.apibara-usage-card{border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px;padding:14px}.apibara-usage-card span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#64748b;font-weight:800}.apibara-usage-card strong{display:block;margin-top:6px;font-size:18px;color:#0f172a}.apibara-usage-bar{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden}.apibara-usage-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#0891b2,#2563eb)}.apibara-integration-help{    margin:18px 0 22px;    padding:18px;    border:1px solid #bae6fd;    background:linear-gradient(135deg,#f0f9ff,#ecfeff);    border-radius:18px;}.apibara-integration-help h3{
    margin:0 0 6px;    color:#0f172a;}.apibara-integration-help p{margin:0;color:#475569;max-width:820px;line-height:1.6;}.apibara-integration-help-actions{    display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}@media(max-width:900px){.apibara-grid-admin.two,.apibara-grid-admin.three,.apibara-grid-admin.four,.apibara-admin-columns{grid-template-columns:1fr}.apibara-admin-hero{display:block}.apibara-quick-links{display:block}.apibara-quick-links-actions{justify-content:flex-start;margin-top:14px}}'; }
}
