<?php
/**
 * Plugin Name: Better Recurring Coupons
 * Description: Allows the use of non-subscription WooCommerce coupons to be used both with WooCommerce subscriptions and non-subscription products.
 * Author: SierraPlugins
 * Author URI: https://sierraplugins.com/
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: better-recurring-coupons
 *
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Tested up to: 6.6.2
 * Requires Plugins: woocommerce
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('SPBRC_PLUGINS_URL', plugin_dir_url(__FILE__));
define('SPBRC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SPBRC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Add our autoloader for getting classes out of the 'includes' directory
 */
require_once SPBRC_PLUGIN_PATH . '/includes/class-better-recurring-coupons.php';


/**
 * Return a single instance of the Better_Recurring_Coupons class.
 *
 * @return SPBRC\Better_Recurring_Coupons
 */
function sierra_plugins_better_recurring_coupons(){
    return SPBRC\Better_Recurring_Coupons::instance(__FILE__);
}

/**
 * Kick off the plugin!
 */
function spbrc_initialize(){
    sierra_plugins_better_recurring_coupons()->initialize();
}

add_action('plugins_loaded', 'spbrc_initialize');