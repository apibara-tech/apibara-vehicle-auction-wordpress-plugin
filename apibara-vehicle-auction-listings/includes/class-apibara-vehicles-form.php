<?php
if (!defined('ABSPATH')) exit;

class Apibara_Vehicles_Form {
    private Apibara_Vehicles_API $api;

    public function __construct(Apibara_Vehicles_API $api) {
        $this->api = $api;
        add_action('wp_ajax_apibara_vehicle_contact', [$this, 'submit']);
        add_action('wp_ajax_nopriv_apibara_vehicle_contact', [$this, 'submit']);
    }

    public function modal_html(array $vehicle): string {
        $o = Apibara_Vehicles_Plugin::get_options();
        if (empty($o['form_enabled'])) return '';

        $fields = (array)$o['form_fields'];
        $required_fields = (array)($o['form_required_fields'] ?? []);
        ob_start();
        ?>
        <div class="apibara-modal" hidden>
            <div class="apibara-modal-backdrop" data-apibara-close></div>
            <div class="apibara-modal-box" role="dialog" aria-modal="true">
                <button type="button" class="apibara-modal-close" data-apibara-close aria-label="<?php esc_attr_e('Close', 'apibara-vehicle-auction-listings'); ?>">×</button>
                <h2><?php echo esc_html($o['form_title']); ?></h2>
                <?php if (!empty($o['form_intro'])): ?><div class="apibara-form-text"><?php echo wp_kses_post(wpautop($o['form_intro'])); ?></div><?php endif; ?>
                <form class="apibara-contact-form">
                    <input type="hidden" name="action" value="apibara_vehicle_contact">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('apibara_vehicle_contact')); ?>">
                    <input type="hidden" name="vehicle" value="<?php echo esc_attr(wp_json_encode($this->vehicle_data($vehicle))); ?>">

                    <?php if (in_array('name', $fields, true)): ?>
                        <label><?php esc_html_e('Name', 'apibara-vehicle-auction-listings'); ?><input <?php echo in_array('name', $required_fields, true) ? 'required' : ''; ?> type="text" name="name"></label>
                    <?php endif; ?>
                    <?php if (in_array('email', $fields, true)): ?>
                        <label><?php esc_html_e('Email', 'apibara-vehicle-auction-listings'); ?><input <?php echo in_array('email', $required_fields, true) ? 'required' : ''; ?> type="email" name="email"></label>
                    <?php endif; ?>
                    <?php if (in_array('phone', $fields, true)): ?>
                        <label><?php esc_html_e('Phone', 'apibara-vehicle-auction-listings'); ?><input <?php echo in_array('phone', $required_fields, true) ? 'required' : ''; ?> type="tel" name="phone"></label>
                    <?php endif; ?>
                    <?php foreach ($this->custom_fields() as $field): ?>
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- custom_field_html() escapes all dynamic attributes and labels internally. ?>
                        <?php echo $this->custom_field_html($field); ?>
                    <?php endforeach; ?>
                    <?php if (in_array('message', $fields, true)): ?>
                        <label><?php esc_html_e('Message', 'apibara-vehicle-auction-listings'); ?><textarea <?php echo in_array('message', $required_fields, true) ? 'required' : ''; ?> name="message" rows="4"></textarea></label>
                    <?php endif; ?>

                    <button class="apibara-button" type="submit"><?php echo esc_html($o['form_button_text']); ?></button>
                    <div class="apibara-form-result" aria-live="polite"></div>
                </form>
                <?php if (!empty($o['form_footer'])): ?><div class="apibara-form-text"><?php echo wp_kses_post(wpautop($o['form_footer'])); ?></div><?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function submit(): void {
        check_ajax_referer('apibara_vehicle_contact', 'nonce');

        $o = Apibara_Vehicles_Plugin::get_options();
        $to = sanitize_email((string)$o['form_email_to']);
        if (!$to) wp_send_json_error(['message' => __('Recipient email is not configured.', 'apibara-vehicle-auction-listings')], 400);

		$vehicle_raw = '';

		if (isset($_POST['vehicle'])) {
			$vehicle_raw = (string) wp_unslash($_POST['vehicle']);
			$vehicle_raw = wp_check_invalid_utf8($vehicle_raw);
		}

		$vehicle = json_decode($vehicle_raw, true);
		$vehicle = is_array($vehicle) ? $vehicle : [];

		$vehicle = [
			'title' => sanitize_text_field((string)($vehicle['title'] ?? '')),
			'vin' => sanitize_text_field((string)($vehicle['vin'] ?? '')),
			'lot_number' => sanitize_text_field((string)($vehicle['lot_number'] ?? '')),
			'platform' => sanitize_text_field((string)($vehicle['platform'] ?? '')),
			'url' => esc_url_raw((string)($vehicle['url'] ?? '')),
		];
		
        $fields = [];
        $enabled_fields = (array)($o['form_fields'] ?? []);
        $required_fields = (array)($o['form_required_fields'] ?? []);
        foreach (['name','email','phone','message'] as $key) {
            $posted_value = isset($_POST[$key]) ? sanitize_textarea_field((string) wp_unslash($_POST[$key])) : '';
            if (in_array($key, $required_fields, true) && $posted_value === '') {
                /* translators: %s: contact form field label. */
                wp_send_json_error(['message' => sprintf(__('Field %s is required.', 'apibara-vehicle-auction-listings'), ucfirst($key))], 422);
            }
            if (in_array($key, $enabled_fields, true) && $posted_value !== '') $fields[$key] = $posted_value;
        }
        foreach ($this->custom_fields() as $field) {
            $key = $field['key'];
            $posted_value = isset($_POST[$key]) ? sanitize_textarea_field((string) wp_unslash($_POST[$key])) : '';
            if (!empty($field['required']) && $posted_value === '') {
                /* translators: %s: custom contact form field label. */
                wp_send_json_error(['message' => sprintf(__('Field %s is required.', 'apibara-vehicle-auction-listings'), $field['label'])], 422);
            }
            if ($posted_value !== '') $fields[$key] = $posted_value;
        }

        $subject = sanitize_text_field((string)$o['form_subject']);
        $body = "New vehicle inquiry\n\n";
        $body .= "Customer data:\n";
        foreach ($fields as $key => $value) $body .= ucfirst(str_replace('_', ' ', $key)) . ': ' . $value . "\n";
        $body .= "\nVehicle data:\n";
        foreach ($vehicle as $key => $value) $body .= ucfirst(str_replace('_', ' ', $key)) . ': ' . (is_scalar($value) ? $value : wp_json_encode($value)) . "\n";

        $headers = [];
        if (!empty($fields['email']) && is_email($fields['email'])) {
            $headers[] = 'Reply-To: ' . sanitize_email($fields['email']);
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        if (!$sent) wp_send_json_error(['message' => __('Email could not be sent.', 'apibara-vehicle-auction-listings')], 500);
        wp_send_json_success(['message' => sanitize_text_field((string) $o['success_message'])]);
    }

    private function vehicle_data(array $v): array {
        return [
            'title' => trim(($v['year'] ?? '') . ' ' . ($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?: ($v['title'] ?? ''),
            'lot_number' => $v['lot_number'] ?? $v['lot'] ?? '',
            'vin' => $v['vin'] ?? '',
            'auction' => $v['auction'] ?? '',
            'status' => $v['status'] ?? '',
            'price' => $v['price'] ?? $v['current_bid'] ?? $v['buy_now_price'] ?? $v['last_sold_price'] ?? '',
            'url' => home_url(add_query_arg([])),
        ];
    }

    private function custom_fields(): array {
        $o = Apibara_Vehicles_Plugin::get_options();
        $lines = preg_split('/\r\n|\r|\n/', (string)$o['form_custom_fields']);
        $fields = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = array_map('trim', explode('|', $line));
            $fields[] = [
                'key' => sanitize_key($parts[0] ?? ''),
                'label' => sanitize_text_field($parts[1] ?? ($parts[0] ?? 'Field')),
                'type' => in_array(($parts[2] ?? 'text'), ['text','email','tel','textarea','select'], true) ? $parts[2] : 'text',
                'required' => in_array(strtolower($parts[3] ?? ''), ['1','yes','required','true'], true),
                'options' => array_filter(array_map('trim', explode(',', $parts[4] ?? ''))),
            ];
        }
        return array_filter($fields, static fn($f) => !empty($f['key']));
    }

    private function custom_field_html(array $field): string {
        $name = esc_attr($field['key']);
        $label = esc_html($field['label']);
        $required = $field['required'] ? ' required' : '';
        if ($field['type'] === 'textarea') return "<label>{$label}<textarea name=\"{$name}\" rows=\"3\"{$required}></textarea></label>";
        if ($field['type'] === 'select') {
            $html = "<label>{$label}<select name=\"{$name}\"{$required}>";
            $html .= '<option value="">' . esc_html__('Select', 'apibara-vehicle-auction-listings') . '</option>';
            foreach ($field['options'] as $option) $html .= '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
            return $html . '</select></label>';
        }
        return "<label>{$label}<input type=\"" . esc_attr($field['type']) . "\" name=\"{$name}\"{$required}></label>";
    }
}
