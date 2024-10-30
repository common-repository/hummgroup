<?php
/**
 *
 * Plugin Name:       humm for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/hummgroup-woocommerce-plugin/
 * Description:       <b><a href="https://www.shophumm.com.au" target='_blank'>humm</a> - The Bigger Buy Now Pay Later.</b> 
 * Take advantage of our huge customer base, big buyer now pay later, easy integration.
 * Version:           3.1.4
 * Author:            humm
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Github URI:        https://github.com/shophumm/humm-au-woocommerce
 * WC requires at least: 3.2.6
 * WC tested up to: 7.6.0
 *
 * @version  3.1.4
 * @package  HummGroup
 * @author   humm
 */
if (! defined('ABSPATH')) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once 'includes/humm_config.php';

define('WC_HUMM_ASSETS', plugin_dir_url(__FILE__) . 'assets/');
define('WC_HUMM_PATH', plugin_dir_path(__FILE__));
define('WC_HUMM_PLUGIN_NAME', plugin_basename(__FILE__));

if (! is_plugin_active('woocommerce/woocommerce.php')) {
    return;
}
/**
 * Look for an ajax request that wants settings
 *
 * @param @query
 *
 * @return null
 */

function humm_link($links)
{
    $settings_link = array( '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=humm') . '">Settings</a>' );
    return array_merge($settings_link, $links);
}

add_action('plugins_loaded', function () {
    require_once 'includes/humm_gateway.php';
    if (class_exists('Humm_Gateway')) {
        $gateway = Humm_Gateway::get_instance();
        add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
            $methods[] = $gateway;
            return $methods;
        });
    }
});
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'humm_link');
