<?php
if (!defined('ABSPATH')) exit;

class Apibara_Vehicles_API {
    private const API_BASE_URL = 'https://apibara.tech';
    private const VEHICLE_LIST_PATH = '/api/v1/vehicle-auction/vehicles';
    private const VEHICLE_SINGLE_PATH = '/api/v1/vehicle-auction/vehicles/{vin}';
    private const VEHICLE_FILTERS_PATH = '/api/v1/vehicle-auction/vehicles/filters';
    private const VEHICLE_USAGE_PATH = '/api/v1/vehicle-auction/usage';

    public function has_key(): bool {
        $options = Apibara_Vehicles_Plugin::get_options();
        return trim((string)($options['api_key'] ?? '')) !== '';
    }

    public function list_vehicles(array $params = []): array {
        $options = Apibara_Vehicles_Plugin::get_options();
        $url = self::API_BASE_URL . self::VEHICLE_LIST_PATH;

        $params = array_merge([
            'per_page' => min(20, max(1, (int)($options['per_page'] ?? 12))),
        ], $params);

        // The Apibara API uses cursor pagination. Keep the plugin frontend simple:
        // remove legacy page=1 and pass cursor only when present.
        if (isset($params['page'])) {
            unset($params['page']);
        }

        return $this->request($url, $params);
    }


    public function filters(): array {
        $url = self::API_BASE_URL . self::VEHICLE_FILTERS_PATH;
        return $this->request($url, []);
    }

    public function usage(): array {
        return $this->request(self::API_BASE_URL . self::VEHICLE_USAGE_PATH, []);
    }

    public function get_vehicle(array $params): array {
        $vin = strtoupper(trim((string)($params['vin'] ?? '')));

        if ($vin === '') {
            return [
                'ok' => false,
                'error' => __('VIN is missing.', 'apibara-vehicle-auction-listings'),
            ];
        }

        $url = self::API_BASE_URL . str_replace('{vin}', rawurlencode($vin), self::VEHICLE_SINGLE_PATH);
        return $this->request($url, []);
    }

    private function request(string $url, array $query): array {
        $options = Apibara_Vehicles_Plugin::get_options();
        $api_key = trim((string)($options['api_key'] ?? ''));

        if ($api_key === '') {
            return [
                'ok' => false,
                'error' => __('API key is missing.', 'apibara-vehicle-auction-listings'),
            ];
        }

        $query = array_filter($query, static function($value) {
            return $value !== '' && $value !== null;
        });

        $request_url = add_query_arg($query, $url);
        $cache_minutes = max(0, min(1440, (int)($options['cache_minutes'] ?? 10)));
        $cache_key = 'apibara_vehicles_' . md5($request_url);

        if ($cache_minutes > 0) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get($request_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $api_key,
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => 'ApibaraVehiclesWordPress/' . APIBARA_VEHICLES_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'error' => $response->get_error_message(),
                'debug_url' => $this->redact_url($request_url),
            ];
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        $raw_body = (string)wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code < 200 || $code >= 300) {
            $message = 'API error: HTTP ' . $code;
            if (is_array($body) && !empty($body['message'])) {
                $message .= ' — ' . sanitize_text_field((string)$body['message']);
            }

            return [
                'ok' => false,
                'error' => $message,
                'response_body' => is_array($body) ? $body : $raw_body,
                'debug_url' => $this->redact_url($request_url),
            ];
        }

        $result = is_array($body)
            ? $body
            : [
                'ok' => false,
                'error' => __('Invalid API response.', 'apibara-vehicle-auction-listings'),
                'debug_url' => $this->redact_url($request_url),
                'raw_body' => $raw_body,
            ];

        if ($cache_minutes > 0) {
            set_transient($cache_key, $result, $cache_minutes * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    private function redact_url(string $url): string {
        return remove_query_arg(['api_key', 'key', 'token'], $url);
    }

    public static function normalize_vehicle(array $vehicle): array {
        if (isset($vehicle['vehicle']) && is_array($vehicle['vehicle'])) return $vehicle['vehicle'];
        if (isset($vehicle['data']) && is_array($vehicle['data']) && !isset($vehicle['data'][0])) return $vehicle['data'];
        return $vehicle;
    }

    public static function vehicles_from_response(array $response): array {
        $items = $response['data'] ?? $response['items'] ?? $response['vehicles'] ?? [];

        if (is_array($items) && isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }

        if (is_array($items) && isset($items['items']) && is_array($items['items'])) {
            $items = $items['items'];
        }

        return is_array($items) ? array_values($items) : [];
    }


    public static function filters_from_response(array $response): array {
        $candidates = [
            $response['filters'] ?? null,
            $response['data']['filters'] ?? null,
            $response['data'] ?? null,
            $response['result']['filters'] ?? null,
            $response['result'] ?? null,
            $response,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    public static function next_cursor_from_response(array $response): string {
        foreach (['next_cursor', 'cursor', 'next'] as $key) {
            if (!empty($response[$key]) && is_scalar($response[$key])) {
                return (string)$response[$key];
            }
        }

        if (!empty($response['meta']['next_cursor']) && is_scalar($response['meta']['next_cursor'])) {
            return (string)$response['meta']['next_cursor'];
        }

        return '';
    }
}
