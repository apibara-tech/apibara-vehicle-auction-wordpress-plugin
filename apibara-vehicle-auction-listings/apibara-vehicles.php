<?php
/**
 * Plugin Name: Apibara Vehicle Auction Listings
 * Plugin URI: https://apibara.tech/en/products/vehicle-auction-data-api/plugins/vehicle-auction-wordpress-plugin
 * Description: Display live Copart and IAAI vehicle auction listings on WordPress using the Apibara Vehicle Auction API.
 * Version: 0.5.8
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * Author: Apibara
 * Author URI: https://apibara.tech
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: apibara-vehicle-auction-listings
 * Domain Path: /languages
 */
 
if (!defined('ABSPATH')) exit;

define('APIBARA_VEHICLES_VERSION', '0.5.8');
define('APIBARA_VEHICLES_FILE', __FILE__);
define('APIBARA_VEHICLES_DIR', plugin_dir_path(__FILE__));
define('APIBARA_VEHICLES_URL', plugin_dir_url(__FILE__));

require_once APIBARA_VEHICLES_DIR . 'includes/class-apibara-vehicles-plugin.php';
require_once APIBARA_VEHICLES_DIR . 'includes/class-apibara-vehicles-api.php';
require_once APIBARA_VEHICLES_DIR . 'includes/class-apibara-vehicles-admin.php';
require_once APIBARA_VEHICLES_DIR . 'includes/class-apibara-vehicles-cpt.php';
require_once APIBARA_VEHICLES_DIR . 'includes/class-apibara-vehicles-frontend.php';
require_once APIBARA_VEHICLES_DIR . 'includes/class-apibara-vehicles-form.php';

function apibara_vehicles_plugin(): Apibara_Vehicles_Plugin {
    return Apibara_Vehicles_Plugin::instance();
}

apibara_vehicles_plugin();
