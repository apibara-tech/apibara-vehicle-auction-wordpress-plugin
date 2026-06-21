<?php
if (!defined('ABSPATH')) exit;

class Apibara_Vehicles_Frontend {
    private Apibara_Vehicles_API $api;
    private Apibara_Vehicles_Form $form;

    public function __construct(Apibara_Vehicles_API $api, Apibara_Vehicles_Form $form) {
        $this->api = $api;
        $this->form = $form;
        add_shortcode('apibara_vehicles', [$this, 'vehicles_shortcode']);
        add_shortcode('apibara_vehicle', [$this, 'vehicle_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function assets(): void {
        $o = Apibara_Vehicles_Plugin::get_options();
        if (!empty($o['enable_plugin_styles'])) {
            wp_enqueue_style('apibara-vehicle-auction-listings', APIBARA_VEHICLES_URL . 'assets/css/frontend.css', [], APIBARA_VEHICLES_VERSION);
        }
        wp_enqueue_script('apibara-vehicle-auction-listings', APIBARA_VEHICLES_URL . 'assets/js/frontend.js', [], APIBARA_VEHICLES_VERSION, true);
        wp_localize_script('apibara-vehicle-auction-listings', 'ApibaraVehicles', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('apibara_vehicle_contact'),
            'defaultView' => $o['default_results_view'] ?? 'grid',
        ]);
        if (!empty($o['enable_plugin_styles'])) {
            wp_add_inline_style('apibara-vehicle-auction-listings', $this->inline_css($o));
        }
    }

    private function inline_css(array $o): string {
        $shadow = 'none';
        if (($o['card_shadow'] ?? '') === 'soft') $shadow = '0 12px 32px rgba(15,23,42,.07)';
        if (($o['card_shadow'] ?? '') === 'modern') $shadow = '0 20px 70px rgba(15,23,42,.10)';
        if (($o['card_shadow'] ?? '') === 'strong') $shadow = '0 26px 90px rgba(15,23,42,.18)';
        $button_radius = (($o['button_style'] ?? '') === 'pill') ? '999px' : ((($o['button_style'] ?? '') === 'square') ? '8px' : '14px');
        return ':root{--apibara-primary:' . esc_attr($o['primary_color']) . ';--apibara-secondary:' . esc_attr($o['secondary_color']) . ';--apibara-accent:' . esc_attr($o['accent_color']) . ';--apibara-bg:' . esc_attr($o['background_color']) . ';--apibara-panel-bg:' . esc_attr($o['panel_background']) . ';--apibara-card-bg:' . esc_attr($o['card_background']) . ';--apibara-text:' . esc_attr($o['text_color']) . ';--apibara-muted:' . esc_attr($o['muted_color']) . ';--apibara-border:' . esc_attr($o['border_color']) . ';--apibara-radius:' . absint($o['card_radius']) . 'px;--apibara-image-ratio:' . esc_attr($o['image_ratio']) . ';--apibara-shadow:' . esc_attr($shadow) . ';--apibara-button-radius:' . esc_attr($button_radius) . ';--apibara-grid-desktop:' . absint($o['grid_columns_desktop']) . ';--apibara-grid-tablet:' . absint($o['grid_columns_tablet']) . ';--apibara-grid-mobile:' . absint($o['grid_columns_mobile']) . ';}';
    }

    public function vehicles_shortcode($atts): string {
        $o = Apibara_Vehicles_Plugin::get_options();
        if (!$this->api->has_key()) return '<div class="apibara-alert">' . esc_html__('Apibara API key is not configured.', 'apibara-vehicle-auction-listings') . '</div>';
        $atts = shortcode_atts(['per_page' => $o['per_page']], $atts, 'apibara_vehicles');
        $params = $this->listing_query_params($o, (int)$atts['per_page']);
        $response = $this->api->list_vehicles($params);
        if (empty($response['ok']) && isset($response['error'])) return $this->api_error_html($response);
        $vehicles = Apibara_Vehicles_API::vehicles_from_response($response);
        $filter_data = $this->load_filter_data($o);
        $next_cursor = Apibara_Vehicles_API::next_cursor_from_response($response);
        $prev_cursor = $this->dg($response, 'meta.prev_cursor', '');
        $target = !empty($o['open_vehicle_new_tab']) ? ' target="_blank" rel="noopener"' : '';
        ob_start();
        ?>
        <section class="apibara-wrap apibara-marketplace" data-apibara-results-root data-default-view="<?php echo esc_attr($o['default_results_view']); ?>">
            <div class="apibara-container">
                <?php if (!empty($o['enable_template_header'])): ?>
                    <div class="apibara-hero-card">
                        <div>
                            <div class="apibara-hero-badge"><?php esc_html_e('Live vehicle data', 'apibara-vehicle-auction-listings'); ?></div>
                            <h1><?php esc_html_e('Vehicle auction listings', 'apibara-vehicle-auction-listings'); ?></h1>
                            <p><?php esc_html_e('Browse auction vehicles, inspect photos, compare prices and open full lot pages.', 'apibara-vehicle-auction-listings'); ?></p>
                        </div>
                        <div class="apibara-hero-actions">
                            <?php if (!empty($o['show_apibara_button']) && !empty($o['apibara_button_url'])): ?>
                                <a class="apibara-button" href="<?php echo esc_url('https://apibara.tech/en/products/vehicle-auction-data-api'); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get API key', 'apibara-vehicle-auction-listings'); ?></a>
                            <?php endif; ?>
                            <a class="apibara-soft-button" href="<?php echo esc_url(remove_query_arg($this->current_query_keys())); ?>"><?php esc_html_e('Reset filters', 'apibara-vehicle-auction-listings'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="apibara-layout <?php echo empty($o['enable_filters']) ? 'is-no-filters' : ''; ?>">
                    <?php if (!empty($o['enable_filters'])): ?>
                        <?php $this->render_filters($o, $filter_data); ?>
                    <?php endif; ?>
                    <div class="apibara-results-area">
                        <?php $this->render_results_header($vehicles, $o); ?>
                        <?php if (!$vehicles): ?>
                            <div class="apibara-empty"><?php esc_html_e('No vehicles returned. Try adjusting filters and submit again.', 'apibara-vehicle-auction-listings'); ?></div>
                        <?php else: ?>
                            <?php
                            $default_view = in_array((string)($o['default_results_view'] ?? 'grid'), ['grid','list'], true) ? (string)$o['default_results_view'] : 'grid';
                            $view_switcher_enabled = !empty($o['enable_view_switcher']);
                            ?>
                            <?php if ($view_switcher_enabled || $default_view === 'grid'): ?>
                                <div class="apibara-results-panel apibara-results-grid" data-results-panel="grid" <?php echo $default_view !== 'grid' ? 'hidden aria-hidden="true"' : ''; ?>>
                                    <?php foreach ($vehicles as $vehicle): $this->render_vehicle_card(Apibara_Vehicles_API::normalize_vehicle((array)$vehicle), 'grid', $target, $o); endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($view_switcher_enabled || $default_view === 'list'): ?>
                                <div class="apibara-results-panel apibara-results-list" data-results-panel="list" <?php echo $default_view !== 'list' ? 'hidden aria-hidden="true"' : ''; ?>>
                                    <?php foreach ($vehicles as $vehicle): $this->render_vehicle_card(Apibara_Vehicles_API::normalize_vehicle((array)$vehicle), 'list', $target, $o); endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($o['enable_pagination'])): ?>
                            <?php $this->render_pagination((string)$next_cursor, (string)$prev_cursor); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function listing_query_params(array $o, int $per_page): array {
        $map = [
            'apibara_q'=>'q','apibara_lot_status'=>'lot_status','apibara_lot_sub_status'=>'lot_sub_status','apibara_auction_type'=>'auction_type','apibara_make'=>'make','apibara_model'=>'model','apibara_type_id'=>'type_id','apibara_year_from'=>'year_from','apibara_year_to'=>'year_to','apibara_price_min'=>'price_min','apibara_price_max'=>'price_max','apibara_odometer_from'=>'odometer_from','apibara_odometer_to'=>'odometer_to','apibara_color'=>'color','apibara_fuel_type'=>'fuel_type','apibara_transmission'=>'transmission','apibara_drive_type'=>'drive_type','apibara_running_condition'=>'running_condition','apibara_damage'=>'damage','apibara_has_key'=>'has_key','apibara_cursor'=>'cursor'
        ];
        $params = ['per_page' => min(20, max(1, $per_page))];
        foreach ($map as $get => $api) {
            $value = $this->query_param($get);
            if ($value !== '') $params[$api] = $value;
        }
        return $params;
    }

    private function render_filters(array $o, array $filter_data = []): void {
        $lot_status_options = $this->filter_options($filter_data, 'lot.status', $this->parse_options((string)$o['filter_lot_status_options']));
        $sub_status_options = $this->filter_options($filter_data, 'lot.sub_status', $this->parse_options((string)$o['filter_sub_status_options']));
        $auction_type_options = $this->filter_options($filter_data, 'auction_type.options', $this->parse_options((string)$o['filter_auction_type_options']));
        $make_options = $this->filter_options_any($filter_data, ['make_model.makes', 'makes', 'make.options', 'make_model.options'], $this->line_options((string)$o['filter_make_options']));
        $type_options = $this->filter_options($filter_data, 'types', $this->line_options((string)$o['filter_vehicle_type_options']));
        $color_options = $this->filter_options($filter_data, 'color.options', $this->line_options((string)$o['filter_color_options']));
        $fuel_options = $this->filter_options($filter_data, 'fuel_type.options', $this->line_options((string)$o['filter_fuel_options']));
        $transmission_options = $this->filter_options($filter_data, 'transmission.options', $this->line_options((string)$o['filter_transmission_options']));
        $drive_options = $this->filter_options($filter_data, 'drive_type.options', $this->line_options((string)$o['filter_drive_options']));
        $damage_options = $this->filter_options($filter_data, 'damage.options', $this->line_options((string)$o['filter_damage_options']));
        $has_key_options = $this->filter_options($filter_data, 'has_key.options', $this->line_options((string)$o['filter_has_key_options']));
        $models_by_make = $this->filter_models_by_make($filter_data, (string)($o['filter_model_options_json'] ?? ''));
        if (!$make_options && $models_by_make) {
            $make_options = array_combine(array_keys($models_by_make), array_keys($models_by_make));
        }
        $current_make = $this->get('apibara_make');
        $model_options = [];
        if ($current_make !== '' && !empty($models_by_make[$current_make]) && is_array($models_by_make[$current_make])) {
            $model_options = array_combine($models_by_make[$current_make], $models_by_make[$current_make]);
        }
        $year_range = $this->filter_range($filter_data, 'year', (int)$o['year_min_default'], (int)$o['year_max_default']);
        $price_range = $this->filter_range($filter_data, 'price_usd', (int)$o['price_min_default'], (int)$o['price_max_default']);
        $odo_range = $this->filter_range($filter_data, 'odometer_mi', (int)$o['odometer_min_default'], (int)$o['odometer_max_default']);
        ?>
        <aside class="apibara-filter-card">
            <div class="apibara-filter-top">
                <div><span class="apibara-filter-icon">≡</span><strong><?php esc_html_e('Filters', 'apibara-vehicle-auction-listings'); ?></strong><small><?php esc_html_e('Search vehicles', 'apibara-vehicle-auction-listings'); ?></small></div>
                <a href="<?php echo esc_url(remove_query_arg($this->current_query_keys())); ?>"><?php esc_html_e('Reset', 'apibara-vehicle-auction-listings'); ?></a>
            </div>
            <form method="get" class="apibara-filter-form" data-models-by-make='<?php echo esc_attr(wp_json_encode($models_by_make)); ?>'>
                <?php if (!empty($o['filter_search'])): ?>
                    <label class="apibara-input-label"><?php esc_html_e('Search', 'apibara-vehicle-auction-listings'); ?><input type="search" name="apibara_q" value="<?php echo esc_attr($this->get('apibara_q')); ?>" placeholder="<?php esc_attr_e('VIN, lot, keyword', 'apibara-vehicle-auction-listings'); ?>"></label>
                <?php endif; ?>
                <?php if (!empty($o['filter_lot_status'])) $this->radio_pills('apibara_lot_status', __('Lot status', 'apibara-vehicle-auction-listings'), $lot_status_options, 'All'); ?>
                <?php if (!empty($o['filter_sub_status'])) $this->radio_pills('apibara_lot_sub_status', __('Sub status', 'apibara-vehicle-auction-listings'), $sub_status_options, 'Open'); ?>
                <?php if (!empty($o['filter_auction_type'])) $this->radio_pills('apibara_auction_type', __('Auction type', 'apibara-vehicle-auction-listings'), $auction_type_options, '0'); ?>
                <?php if (!empty($o['filter_make_model'])): ?>
                    <div class="apibara-filter-grid-2">
                        <?php $this->select_filter('apibara_make', __('Make', 'apibara-vehicle-auction-listings'), $make_options); ?>
                        <?php $this->select_filter('apibara_model', __('Model', 'apibara-vehicle-auction-listings'), $model_options); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($o['filter_year'])): ?>
                    <div class="apibara-filter-grid-2">
                        <label class="apibara-input-label"><?php esc_html_e('Year from', 'apibara-vehicle-auction-listings'); ?><input type="number" name="apibara_year_from" value="<?php echo esc_attr($this->get('apibara_year_from')); ?>" min="<?php echo esc_attr((string)$year_range['min']); ?>" max="<?php echo esc_attr((string)$year_range['max']); ?>"></label>
                        <label class="apibara-input-label"><?php esc_html_e('Year to', 'apibara-vehicle-auction-listings'); ?><input type="number" name="apibara_year_to" value="<?php echo esc_attr($this->get('apibara_year_to')); ?>" min="<?php echo esc_attr((string)$year_range['min']); ?>" max="<?php echo esc_attr((string)$year_range['max']); ?>"></label>
                    </div>
                <?php endif; ?>
                <?php if (!empty($o['filter_price'])) $this->range_filter('price', __('Price (USD)', 'apibara-vehicle-auction-listings'), 'apibara_price_min', 'apibara_price_max', (int)$price_range['min'], (int)$price_range['max'], 50, '$', ''); ?>
                <?php if (!empty($o['filter_odometer'])) $this->range_filter('odo', __('Odometer', 'apibara-vehicle-auction-listings'), 'apibara_odometer_from', 'apibara_odometer_to', (int)$odo_range['min'], (int)$odo_range['max'], 500, '', ' mi'); ?>
                <?php if (!empty($o['filter_specs'])): ?>
                    <div class="apibara-more-title"><?php esc_html_e('More filters', 'apibara-vehicle-auction-listings'); ?></div>
                    <div class="apibara-filter-grid-2">
                        <?php $this->select_filter('apibara_type_id', __('Vehicle type', 'apibara-vehicle-auction-listings'), $type_options); ?>
                        <?php $this->select_filter('apibara_color', __('Color', 'apibara-vehicle-auction-listings'), $color_options); ?>
                        <?php $this->select_filter('apibara_fuel_type', __('Fuel', 'apibara-vehicle-auction-listings'), $fuel_options); ?>
                        <?php $this->select_filter('apibara_transmission', __('Transmission', 'apibara-vehicle-auction-listings'), $transmission_options); ?>
                        <?php $this->select_filter('apibara_drive_type', __('Drive type', 'apibara-vehicle-auction-listings'), $drive_options); ?>
                        <?php $this->select_filter('apibara_damage', __('Damage', 'apibara-vehicle-auction-listings'), $damage_options); ?>
                        <?php $this->select_filter('apibara_has_key', __('Has key', 'apibara-vehicle-auction-listings'), $has_key_options); ?>
                    </div>
                <?php endif; ?>
                <div class="apibara-filter-actions"><button class="apibara-button" type="submit"><?php esc_html_e('Search', 'apibara-vehicle-auction-listings'); ?></button><a class="apibara-icon-button" href="<?php echo esc_url(remove_query_arg($this->current_query_keys())); ?>">↻</a></div>
            </form>
        </aside>
        <?php
    }

    private function render_results_header(array $vehicles, array $o): void { ?>
        <div class="apibara-results-header">
            <div><span><?php esc_html_e('Results', 'apibara-vehicle-auction-listings'); ?></span><h2><?php esc_html_e('Vehicle auction results', 'apibara-vehicle-auction-listings'); ?></h2><p><?php esc_html_e('Live vehicle data returned by the API using the current filter state.', 'apibara-vehicle-auction-listings'); ?></p></div>
            <div class="apibara-results-tools"><div class="apibara-count"><?php echo esc_html((string)count($vehicles)); ?> <?php esc_html_e('items', 'apibara-vehicle-auction-listings'); ?></div><?php if (!empty($o['enable_view_switcher'])): ?><div class="apibara-view-switch"><button type="button" data-results-view="grid">Grid</button><button type="button" data-results-view="list">List</button></div><?php endif; ?></div>
        </div>
    <?php }

    private function render_vehicle_card(array $v, string $view, string $target, array $o): void {
        $fields = (array)$o['list_fields'];
        $state = $this->vehicle_state($v);
        $platform = strtolower($this->value($v, 'platform'));
        $title = $this->vehicle_title($v);
        $url = $this->vehicle_url($v);
        $images = $this->vehicle_images($v);
        $gallery_id = 'apibara-g-' . substr(md5($this->value($v, 'lot_number') . $title . $view), 0, 10);
        ?>
        <article class="apibara-lot-card apibara-lot-<?php echo esc_attr($view); ?> is-<?php echo esc_attr($state['key']); ?>">
            <div class="apibara-card-soft"></div>
            <?php if (in_array('image', $fields, true)): ?>
                <div class="apibara-card-media" data-mini-slider>
                    <div class="apibara-mini-track" data-mini-track>
                        <?php foreach ($images as $img): ?>
                            <a href="<?php echo esc_url($img['large']); ?>" data-apibara-lightbox="1" data-apibara-fancybox="<?php echo esc_attr($gallery_id); ?>" class="apibara-mini-slide"><img loading="lazy" src="<?php echo esc_url($img['thumb']); ?>" alt="<?php echo esc_attr($title); ?>"></a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1 && !empty($o['enable_mini_slider'])): ?>
                        <button type="button" class="apibara-mini-prev" data-mini-prev>‹</button><button type="button" class="apibara-mini-next" data-mini-next>›</button>
                        <div class="apibara-mini-dots" data-mini-dots><?php foreach ($images as $i=>$img): ?><button type="button" data-mini-dot="<?php echo esc_attr((string)$i); ?>"></button><?php endforeach; ?></div>
                    <?php endif; ?>
                    <?php if (in_array('badges', $fields, true)) $this->render_badges($v, $state); ?>
                </div>
            <?php endif; ?>
            <div class="apibara-card-content">
                <?php if (in_array('badges', $fields, true) && !in_array('image', $fields, true)) $this->render_badges($v, $state); ?>
                <?php if (in_array('title', $fields, true)): ?><h3><a href="<?php echo esc_url($url); ?>"<?php echo $target; // phpcs:ignore ?>><?php echo esc_html($title); ?></a></h3><?php endif; ?>
				<div class="apibara-spec-grid">
					<?php
						if (in_array('location', $fields, true)) {
							$this->spec(
								'location',
								__('Location', 'apibara-vehicle-auction-listings'),
								$this->value($v, 'location'),
								$this->field_icon('location')
							);
						}

						if (in_array('odometer', $fields, true)) {
							$this->spec(
								'odometer',
								__('Odometer', 'apibara-vehicle-auction-listings'),
								$this->value($v, 'odometer'),
								$this->field_icon('odometer')
							);
						}

						if (in_array('fuel_transmission', $fields, true)) {
							$fuel = $this->value($v, 'fuel');
							$transmission = $this->value($v, 'transmission');

							$this->spec(
								'fuel',
								__('Fuel', 'apibara-vehicle-auction-listings'),
								trim($fuel . ($transmission ? ' · ' . $transmission : '')),
								$this->field_icon('fuel')
							);
						}

						if (in_array('engine_drive', $fields, true)) {
							$engine = $this->value($v, 'engine');
							$drive = $this->value($v, 'drive');

							$this->spec(
								'engine',
								__('Engine', 'apibara-vehicle-auction-listings'),
								trim($engine . ($drive ? ' · ' . $drive : '')),
								$this->field_icon('engine')
							);
						}
					?>
				</div>
                <?php if (in_array('price_panel', $fields, true)) $this->price_panel($v, $view); ?>
                <?php if (in_array('vin_lot', $fields, true)) $this->vin_lot_block($v); ?>
                <?php if (in_array('countdown', $fields, true) && !empty($o['enable_countdown'])) $this->countdown_block($v, $state); ?>
                <?php if (in_array('view_button', $fields, true)): ?><a class="apibara-button apibara-view-button" href="<?php echo esc_url($url); ?>"<?php echo $target; // phpcs:ignore ?>><?php esc_html_e('View details', 'apibara-vehicle-auction-listings'); ?></a><?php endif; ?>
            </div>
        </article>
        <?php
    }

    public function vehicle_shortcode($atts): string {
        $o = Apibara_Vehicles_Plugin::get_options();
        if (!$this->api->has_key()) return '<div class="apibara-alert">' . esc_html__('Apibara API key is not configured.', 'apibara-vehicle-auction-listings') . '</div>';
        $atts = shortcode_atts(['id'=>'','platform'=>'','lot_number'=>'','slug'=>'','vin'=>''], $atts, 'apibara_vehicle');
        $get_vin = $this->query_param('vin');
        $vin = (string)($atts['vin'] ?: get_query_var('apibara_vin') ?: $atts['id'] ?: $get_vin);
        $vin = strtoupper(trim(sanitize_text_field($vin)));
        if ($vin === '') return '<div class="apibara-alert">' . esc_html__('VIN is missing.', 'apibara-vehicle-auction-listings') . '</div>';
        $response = $this->api->get_vehicle(['vin' => $vin]);
        if (empty($response['ok']) && isset($response['error'])) return $this->api_error_html($response);
        $v = Apibara_Vehicles_API::normalize_vehicle($response);
        if (!$v) return '<div class="apibara-empty">' . esc_html__('Vehicle not found.', 'apibara-vehicle-auction-listings') . '</div>';
        $fields = (array)$o['single_fields'];
        $state = $this->vehicle_state($v);
        ob_start();
        ?>
        <section class="apibara-wrap apibara-single-page" data-vehicle='<?php echo esc_attr(wp_json_encode($this->vehicle_email_data($v))); ?>'>
            <div class="apibara-container">
                <div class="apibara-single-shell">
                    <?php if (in_array('gallery', $fields, true)): ?><div class="apibara-single-gallery"><?php $this->gallery($v); ?></div><?php endif; ?>
						<div class="apibara-single-summary">
							<?php if (in_array('badges', $fields, true)) $this->render_badges($v, $state, true); ?>

							<?php if (in_array('title', $fields, true)): ?>
								<h1><?php echo esc_html($this->vehicle_title($v)); ?></h1>
							<?php endif; ?>

							<?php if (in_array('price_panel', $fields, true)) $this->price_panel($v, 'single'); ?>

							<?php if (in_array('key_specs', $fields, true)): ?>
								<div class="apibara-key-specs">
									<?php
										$key_specs = [
											'location' => __('Location', 'apibara-vehicle-auction-listings'),
											'odometer' => __('Odometer', 'apibara-vehicle-auction-listings'),
											'fuel'     => __('Fuel', 'apibara-vehicle-auction-listings'),
											'engine'   => __('Engine', 'apibara-vehicle-auction-listings'),
										];

										foreach ($key_specs as $spec_key => $spec_label) {
											$this->spec(
												$spec_key,
												$spec_label,
												$this->value($v, $spec_key),
												$this->field_icon($spec_key)
											);
										}
									?>
								</div>
							<?php endif; ?>

							<?php if (in_array('vin_lot', $fields, true)) $this->vin_lot_block($v); ?>

							<?php if (in_array('countdown', $fields, true) && !empty($o['enable_countdown'])) $this->countdown_block($v, $state); ?>

							<?php if (in_array('contact_button', $fields, true) && !empty($o['form_enabled'])): ?>
								<button type="button" class="apibara-button apibara-open-modal">
									<?php echo esc_html($o['form_button_text']); ?>
								</button>
							<?php endif; ?>
						</div>
                </div>
                <?php if (in_array('details_table', $fields, true)): ?><div class="apibara-details-panel"><h2><?php esc_html_e('Lot details', 'apibara-vehicle-auction-listings'); ?></h2><div class="apibara-details-grid"><?php $this->render_details_table($v, (array)$o['single_detail_fields']); ?></div></div><?php endif; ?>
            </div>
            <?php echo $this->form->modal_html($v); // phpcs:ignore ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_pagination(string $next_cursor, string $prev_cursor): void {
        if ($next_cursor === '' && $prev_cursor === '') return; ?>
        <div class="apibara-pagination-modern">
            <a class="<?php echo $prev_cursor === '' ? 'is-disabled' : ''; ?>" href="<?php echo esc_url($prev_cursor !== '' ? add_query_arg('apibara_cursor', rawurlencode($prev_cursor)) : '#'); ?>">‹ <?php esc_html_e('Prev', 'apibara-vehicle-auction-listings'); ?></a>
            <a class="<?php echo $next_cursor === '' ? 'is-disabled' : ''; ?>" href="<?php echo esc_url($next_cursor !== '' ? add_query_arg('apibara_cursor', rawurlencode($next_cursor)) : '#'); ?>"><?php esc_html_e('Next', 'apibara-vehicle-auction-listings'); ?> ›</a>
        </div><?php
    }

    private function radio_pills(string $name, string $label, array $options, string $default = ''): void { $current = $this->get($name, $default); echo '<div class="apibara-pills"><label>' . esc_html($label) . '</label><div>'; foreach ($options as $value=>$text) echo '<label><input class="screen-reader-text" type="radio" onchange="this.form.submit()" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . checked($current, (string)$value, false) . '><span>' . esc_html($text) . '</span></label>'; echo '</div></div>'; }
    private function select_filter(string $name, string $label, array $options): void { echo '<label class="apibara-input-label">' . esc_html($label) . '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '"><option value="">' . esc_html__('All', 'apibara-vehicle-auction-listings') . '</option>'; $current = $this->get($name); foreach ($options as $value=>$text) echo '<option value="' . esc_attr((string)$value) . '" ' . selected($current, (string)$value, false) . '>' . esc_html($text) . '</option>'; echo '</select></label>'; }
    private function range_filter(string $box, string $label, string $min_name, string $max_name, int $min, int $max, int $step, string $prefix, string $suffix): void { $cur_min = $this->get($min_name, (string)$min); $cur_max = $this->get($max_name, (string)$max); ?><div class="apibara-range-card" data-range-box="<?php echo esc_attr($box); ?>" data-prefix="<?php echo esc_attr($prefix); ?>" data-suffix="<?php echo esc_attr($suffix); ?>"><div class="apibara-range-top"><strong><?php echo esc_html($label); ?></strong><span><b data-range-out="min"></b> — <b data-range-out="max"></b></span></div><div class="apibara-range-shell"><i></i><em data-range-fill></em><input type="range" name="<?php echo esc_attr($min_name); ?>" data-range-input="min" min="<?php echo esc_attr((string)$min); ?>" max="<?php echo esc_attr((string)$max); ?>" step="<?php echo esc_attr((string)$step); ?>" value="<?php echo esc_attr($cur_min); ?>"><input type="range" name="<?php echo esc_attr($max_name); ?>" data-range-input="max" min="<?php echo esc_attr((string)$min); ?>" max="<?php echo esc_attr((string)$max); ?>" step="<?php echo esc_attr((string)$step); ?>" value="<?php echo esc_attr($cur_max); ?>"></div><div class="apibara-range-limits"><span><?php echo esc_html($prefix . number_format($min) . $suffix); ?></span><span><?php echo esc_html($prefix . number_format($max) . $suffix); ?></span></div></div><?php }

    private function render_badges(array $v, array $state, bool $static = false): void { $platform = strtoupper($this->value($v, 'platform') ?: $this->value($v, 'auction') ?: 'Auction'); $damage = $this->value($v, 'damage'); echo '<div class="apibara-badges-modern ' . ($static ? 'is-static' : '') . '"><span class="is-state is-' . esc_attr($state['key']) . '"><i></i>' . esc_html($state['label']) . '</span><span class="is-platform">' . esc_html($platform) . '</span>'; if ($damage !== '') echo '<span class="is-damage">' . esc_html($damage) . '</span>'; if ($this->truthy($this->dg($v, 'auction.is_timed'))) echo '<span class="is-timed">Timed</span>'; if ($this->truthy($this->dg($v, 'auction.is_buy_now'))) echo '<span class="is-buy-now">Buy now</span>'; echo '</div>'; }
    private function spec(string $class, string $label, string $value, string $icon): void { if ($value === '') $value = '—'; echo '<div class="apibara-spec apibara-spec-' . esc_attr($class) . '"><i>' . esc_html($icon) . '</i><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>'; }
    private function price_panel(array $v, string $view): void { $bid=$this->money($this->first($v,['pricing.current_bid_usd','current_bid','price'])); $bin=$this->money($this->first($v,['pricing.buy_now_usd','buy_now_price','bnp'])); $est=(string)$this->first($v,['pricing.estimated_cost.text','estimated_cost','est_cost']); echo '<div class="apibara-price-panel is-' . esc_attr($view) . '"><div><span>Current bid</span><strong>' . esc_html($bid ?: '—') . '</strong></div><div><span>Buy now</span><strong class="is-buy">' . esc_html($bin ?: '—') . '</strong></div><div><span>Est. cost</span><strong>' . esc_html($est ?: '—') . '</strong></div></div>'; }
    private function vin_lot_block(array $v): void { $vin=$this->value($v,'vin'); $lot=$this->value($v,'lot_number'); echo '<div class="apibara-vin-lot"><button type="button" data-copy="' . esc_attr($vin) . '"><span>VIN</span><strong>' . esc_html($vin ?: '—') . '</strong></button><button type="button" data-copy="' . esc_attr($lot) . '"><span>Lot</span><strong>' . esc_html($lot ?: '—') . '</strong></button></div>'; }
    private function countdown_block(array $v, array $state): void { $date = (string)$this->first($v,['ad','auction.date','sale_date','auction_date']); echo '<div class="apibara-countdown-card"><span>Time left</span><strong data-countdown-to="' . esc_attr($date) . '" data-countdown-status="' . esc_attr($state['key']) . '">' . esc_html($date ? 'Calculating...' : 'No date') . '</strong></div>'; }
    private function render_details_table(array $v, array $fields): void { foreach ($fields as $field) { $value = $this->value($v, (string)$field); if ($value === '') continue; echo '<div><span>' . esc_html($this->label((string)$field)) . '</span><strong>' . esc_html($value) . '</strong></div>'; } }

	private function gallery(array $v): void
	{
		$o = Apibara_Vehicles_Plugin::get_options();
		$mode = (string) ($o['gallery_mode'] ?? 'slider_lightbox');
		$images = $this->vehicle_images($v, 30);
		$title = $this->vehicle_title($v);

		if (!$images) {
			return;
		}

		if ($mode === 'slider' || $mode === 'slider_lightbox') {
			echo '<div class="apibara-single-slider apibara-single-slider-with-thumbs" data-mini-slider data-single-gallery>';

			echo '<div class="apibara-mini-track" data-mini-track>';

			foreach ($images as $i => $img) {
				$large = (string) ($img['large'] ?? $img['thumb'] ?? '');
				$thumb = (string) ($img['thumb'] ?? $img['large'] ?? '');

				if ($large === '' && $thumb === '') {
					continue;
				}

				$href = $large !== '' ? $large : $thumb;
				$src = $large !== '' ? $large : $thumb;

				echo '<a class="apibara-mini-slide" href="' . esc_url($href) . '" data-mini-index="' . esc_attr((string) $i) . '" ' . ($mode === 'slider_lightbox' ? 'data-apibara-lightbox="1" data-apibara-fancybox="single-gallery"' : '') . '>';
				echo '<img loading="lazy" src="' . esc_url($src) . '" alt="' . esc_attr($title) . '">';
				echo '</a>';
			}

			echo '</div>';

			if (count($images) > 1) {
				echo '<button type="button" class="apibara-mini-prev" data-mini-prev aria-label="' . esc_attr__('Previous image', 'apibara-vehicle-auction-listings') . '">‹</button>';
				echo '<button type="button" class="apibara-mini-next" data-mini-next aria-label="' . esc_attr__('Next image', 'apibara-vehicle-auction-listings') . '">›</button>';

				echo '<div class="apibara-single-thumbs" data-single-thumbs>';

				foreach ($images as $i => $img) {
					$thumb = (string) ($img['thumb'] ?? $img['large'] ?? '');

					if ($thumb === '') {
						continue;
					}

					echo '<button type="button" class="apibara-single-thumb ' . ($i === 0 ? 'is-active' : '') . '" data-single-thumb="' . esc_attr((string) $i) . '" aria-label="' . esc_attr(sprintf(
						/* translators: %d: image number. */
						__('Show image %d', 'apibara-vehicle-auction-listings'),
						$i + 1
					)) . '">';
					echo '<img loading="lazy" src="' . esc_url($thumb) . '" alt="' . esc_attr($title) . '">';
					echo '</button>';
				}

				echo '</div>';
			}

			echo '</div>';

			return;
		}

		echo '<div class="apibara-gallery-grid">';

		foreach ($images as $img) {
			$large = (string) ($img['large'] ?? $img['thumb'] ?? '');
			$thumb = (string) ($img['thumb'] ?? $img['large'] ?? '');

			if ($large === '' && $thumb === '') {
				continue;
			}

			echo '<a href="' . esc_url($large !== '' ? $large : $thumb) . '" ' . ($mode === 'lightbox' ? 'data-apibara-lightbox="1" data-apibara-fancybox="single-gallery"' : '') . '>';
			echo '<img loading="lazy" src="' . esc_url($thumb !== '' ? $thumb : $large) . '" alt="' . esc_attr($title) . '">';
			echo '</a>';
		}

		echo '</div>';
	}


	private function vehicle_images(array $v, int $limit = 12): array
	{
		$images = [];

		$items = $this->dg($v, 'media.items', []);

		if (is_array($items)) {
			foreach ($items as $it) {
				if (is_array($it) && (($it['type'] ?? 'image') === 'image')) {
					$images[] = [
						'thumb' => (string) ($it['thumb'] ?? $it['large'] ?? ''),
						'large' => (string) ($it['large'] ?? $it['thumb'] ?? ''),
					];
				}
			}
		}

		$thumbs = $this->dg($v, 'media.thumbs', []);

		if (!$images && is_array($thumbs)) {
			foreach ($thumbs as $thumb) {
				$images[] = [
					'thumb' => (string) $thumb,
					'large' => (string) $thumb,
				];
			}
		}

		foreach (['images', 'image', 'photo', 'thumbnail'] as $key) {
			$val = $v[$key] ?? null;

			if (!$images && is_array($val)) {
				foreach ($val as $img) {
					$images[] = [
						'thumb' => (string) $img,
						'large' => (string) $img,
					];
				}
			} elseif (!$images && is_scalar($val) && (string) $val !== '') {
				$images[] = [
					'thumb' => (string) $val,
					'large' => (string) $val,
				];
			}
		}

		$images = array_values(array_filter($images, static function ($image): bool {
			return is_array($image) && (!empty($image['thumb']) || !empty($image['large']));
		}));

		if (!$images) {
			$placeholder = APIBARA_VEHICLES_PLUGIN_URL . 'assets/images/no-image.webp';

			$images[] = [
				'thumb' => $placeholder,
				'large' => $placeholder,
			];
		}

		return array_slice($images, 0, $limit);
	}

    private function vehicle_state(array $v): array { $raw=strtolower((string)$this->first($v,['auction.state','status','lot_status'])); if (in_array($raw,['ended','finished','sold'],true)) return ['key'=>'ended','label'=>'Ended']; if ($raw==='live') return ['key'=>'live','label'=>'Live']; return ['key'=>'open','label'=>'Open']; }
    private function vehicle_url(array $v): string { if (class_exists('Apibara_Vehicles_CPT')) return Apibara_Vehicles_CPT::vehicle_url($v); $vin=$this->value($v,'vin'); return add_query_arg('vin', rawurlencode($vin), home_url('/auctions/')); }
    private function vehicle_title(array $v): string { return trim((string)($v['title'] ?? (($v['year'] ?? '') . ' ' . ($v['make'] ?? '') . ' ' . ($v['model'] ?? '')))) ?: __('Vehicle lot', 'apibara-vehicle-auction-listings'); }
    private function value(array $v, string $field): string { $map=['lot_number'=>['lot_number','lot'],'platform'=>['platform','auction'],'status'=>['auction.state','status','lot_status'],'location'=>['location.display','location','branch'],'odometer'=>['odometer.mi','odometer','mileage'],'damage'=>['condition.primary_damage','damage','primary_damage'],'sale_date'=>['ad','sale_date','auction_date'],'seller'=>['seller','seller_name'],'keys'=>['condition.keys','keys','has_key'],'fuel'=>['vehicle_specs.fuel_type','fuel_type','fuel'],'transmission'=>['vehicle_specs.transmission','transmission'],'drive'=>['vehicle_specs.drive_type','drive_type','drive'],'engine'=>['vehicle_specs.engine.raw','engine','engine_size'],'color'=>['vehicle_specs.color','color'],'body_type'=>['vehicle_specs.body_type','body_type'],'title_status'=>['vehicle_specs.title_status','title_status'],'price'=>['pricing.current_bid_usd','price','current_bid'],'vin'=>['vin'],'year'=>['year'],'make'=>['make'],'model'=>['model'],'auction'=>['auction','platform']]; $val=$this->first($v,$map[$field]??[$field]); if ($field==='odometer' && is_numeric($val)) return number_format((int)$val) . ' mi'; if ($field==='price') return $this->money($val); return is_scalar($val) ? (string)$val : (is_array($val) ? wp_json_encode($val) : ''); }
    private function label(string $field): string {
        switch ($field) {
            case 'image': return __('Image', 'apibara-vehicle-auction-listings');
            case 'gallery': return __('Gallery', 'apibara-vehicle-auction-listings');
            case 'badges': return __('Badges', 'apibara-vehicle-auction-listings');
            case 'title': return __('Title', 'apibara-vehicle-auction-listings');
            case 'lot_number': return __('Lot number', 'apibara-vehicle-auction-listings');
            case 'vin': return __('VIN', 'apibara-vehicle-auction-listings');
            case 'platform': return __('Platform', 'apibara-vehicle-auction-listings');
            case 'year': return __('Year', 'apibara-vehicle-auction-listings');
            case 'make': return __('Make', 'apibara-vehicle-auction-listings');
            case 'model': return __('Model', 'apibara-vehicle-auction-listings');
            case 'price': return __('Price', 'apibara-vehicle-auction-listings');
            case 'auction': return __('Auction', 'apibara-vehicle-auction-listings');
            case 'status': return __('Status', 'apibara-vehicle-auction-listings');
            case 'location': return __('Location', 'apibara-vehicle-auction-listings');
            case 'odometer': return __('Odometer', 'apibara-vehicle-auction-listings');
            case 'damage': return __('Damage', 'apibara-vehicle-auction-listings');
            case 'sale_date': return __('Sale date', 'apibara-vehicle-auction-listings');
            case 'seller': return __('Seller', 'apibara-vehicle-auction-listings');
            case 'keys': return __('Keys', 'apibara-vehicle-auction-listings');
            case 'fuel': return __('Fuel', 'apibara-vehicle-auction-listings');
            case 'transmission': return __('Transmission', 'apibara-vehicle-auction-listings');
            case 'drive': return __('Drive', 'apibara-vehicle-auction-listings');
            case 'engine': return __('Engine', 'apibara-vehicle-auction-listings');
            case 'color': return __('Color', 'apibara-vehicle-auction-listings');
            case 'body_type': return __('Body type', 'apibara-vehicle-auction-listings');
            case 'title_status': return __('Title status', 'apibara-vehicle-auction-listings');
            default: return ucwords(str_replace('_', ' ', sanitize_key($field)));
        }
    }
    private function vehicle_email_data(array $v): array { return ['title'=>$this->vehicle_title($v),'lot_number'=>$this->value($v,'lot_number'),'vin'=>$this->value($v,'vin'),'auction'=>$this->value($v,'platform'),'status'=>$this->value($v,'status'),'price'=>$this->value($v,'price'),'url'=>esc_url_raw(home_url(add_query_arg([])))]; }


    private function load_filter_data(array $o): array {
        if (empty($o['use_api_filters']) || !$this->api->has_key()) {
            return [];
        }

        $response = $this->api->filters();
        if (empty($response['ok']) && isset($response['error'])) {
            return [];
        }

        return Apibara_Vehicles_API::filters_from_response($response);
    }

    private function filter_options_any(array $filters, array $paths, array $fallback = []): array {
        foreach ($paths as $path) {
            $options = $this->filter_options($filters, (string)$path, []);
            if ($options) {
                return $options;
            }
        }
        return $fallback;
    }

    private function filter_options(array $filters, string $path, array $fallback = []): array {
        $raw = $this->dg($filters, $path, []);
        $out = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $item) {
                if (is_array($item)) {
                    $value = $item['value'] ?? $item['id'] ?? $item['slug'] ?? $item['code'] ?? $item['name'] ?? $item['label'] ?? $key;
                    $label = $item['label'] ?? $item['name'] ?? $item['title'] ?? $item['text'] ?? $value;
                    if ($value !== '' && $value !== null) {
                        $out[(string)$value] = (string)$label;
                    }
                } elseif (is_scalar($item)) {
                    if (is_string($key) && !is_numeric($key)) {
                        $out[(string)$key] = (string)$item;
                    } else {
                        $out[(string)$item] = (string)$item;
                    }
                }
            }
        }

        return $out ?: $fallback;
    }

    private function filter_models_by_make(array $filters, string $fallback_json): array {
        $raw = [];
        foreach (['make_model.models_by_make', 'models_by_make', 'models.by_make', 'make_model.models'] as $path) {
            $candidate = $this->dg($filters, $path, []);
            if (is_array($candidate) && $candidate) {
                $raw = $candidate;
                break;
            }
        }

        if (is_array($raw) && $raw) {
            $out = [];
            foreach ($raw as $make => $models) {
                $make = (string)$make;
                if ($make === '') {
                    continue;
                }

                $normalized = [];
                if (is_array($models)) {
                    foreach ($models as $model_key => $model_item) {
                        if (is_array($model_item)) {
                            $value = $model_item['value'] ?? $model_item['id'] ?? $model_item['slug'] ?? $model_item['name'] ?? $model_item['label'] ?? $model_key;
                            if ($value !== '' && $value !== null) {
                                $normalized[] = (string)$value;
                            }
                        } elseif (is_scalar($model_item)) {
                            $normalized[] = (string)$model_item;
                        }
                    }
                }

                $normalized = array_values(array_unique(array_filter($normalized, static function($value) { return $value !== ''; })));
                if ($normalized) {
                    $out[$make] = $normalized;
                }
            }
            if ($out) {
                return $out;
            }
        }

        $fallback = json_decode($fallback_json, true);
        return is_array($fallback) ? $fallback : [];
    }

    private function filter_range(array $filters, string $key, int $default_min, int $default_max): array {
        $range = $this->dg($filters, 'ranges.' . $key, []);
        if (is_array($range)) {
            return [
                'min' => isset($range['min']) ? (int)$range['min'] : $default_min,
                'max' => isset($range['max']) ? (int)$range['max'] : $default_max,
                'step' => isset($range['step']) ? (float)$range['step'] : 1,
            ];
        }
        return ['min' => $default_min, 'max' => $default_max, 'step' => 1];
    }

    private function query_params(): array {
        return isset($_GET) && is_array($_GET) ? (array) wp_unslash($_GET) : [];
    }

    private function query_param(string $key, string $default = ''): string {
        $query = $this->query_params();
        return isset($query[$key]) ? sanitize_text_field((string) $query[$key]) : $default;
    }

    private function current_query_keys(): array {
        return array_values(array_filter(array_map('sanitize_key', array_keys($this->query_params()))));
    }

    private function api_error_html(array $response): string { $out='<div class="apibara-alert">' . esc_html((string)($response['error'] ?? __('API error.', 'apibara-vehicle-auction-listings'))) . '</div>'; $options=Apibara_Vehicles_Plugin::get_options(); if (!empty($options['show_api_debug']) && current_user_can('manage_options') && !empty($response['debug_url'])) $out.='<div class="apibara-alert apibara-debug"><strong>Debug URL:</strong><br><code>' . esc_html((string)$response['debug_url']) . '</code></div>'; return $out; }
    private function parse_options(string $text): array { $out=[]; foreach (preg_split('/\r\n|\r|\n/', $text) as $line) { $line=trim($line); if($line==='') continue; $parts=array_map('trim', explode('|',$line,2)); $out[$parts[0]]=$parts[1] ?? $parts[0]; } return $out; }
    private function line_options(string $text): array { $out=[]; foreach (preg_split('/\r\n|\r|\n/', $text) as $line) { $line=trim($line); if($line==='') continue; $parts=array_map('trim', explode('|',$line,2)); $out[$parts[0]]=$parts[1] ?? $parts[0]; } return $out; }
    private function get(string $key, string $default = ''): string { return $this->query_param($key, $default); }
    private function dg($array, string $path, $default = null) { $cur=$array; foreach (explode('.',$path) as $part) { if (is_array($cur) && array_key_exists($part,$cur)) $cur=$cur[$part]; else return $default; } return $cur; }
    private function first(array $v, array $paths) { foreach ($paths as $path) { $val=$this->dg($v,$path,null); if ($val !== null && $val !== '') return $val; } return ''; }
    private function money($value): string { if ($value === '' || $value === null) return ''; if (is_numeric($value)) return '$' . number_format((float)$value, 0); return (string)$value; }
    private function truthy($value): bool { return in_array($value, [true,1,'1','yes','true'], true); }
	
	
	private function field_icon(string $field): string
	{
		$icons = [
			'lot_number' => '🏷️',
			'vin' => '🔢',
			'platform' => '🏛️',
			'year' => '📅',
			'make' => '🏭',
			'model' => '🚘',
			'title' => '🚗',
			'price' => '💵',
			'auction' => '🔨',
			'status' => '📌',
			'location' => '📍',
			'odometer' => '🛣️',
			'damage' => '⚠️',
			'sale_date' => '⏰',
			'seller' => '👤',
			'keys' => '🔑',
			'fuel' => '⛽',
			'transmission' => '⚙️',
			'drive' => '🛞',
			'engine' => '🔧',
			'color' => '🎨',
			'body_type' => '🚙',
			'title_status' => '📄',
		];

		return $icons[$field] ?? '•';
	}
		
}
