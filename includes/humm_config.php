<?php

defined('ABSPATH') || exit;

/**
 * Class Humm_Config
 */
class Humm_Config
{

    /**
     * @constant
     */
    const COUNTRY_AUSTRALIA   = 'AU';
    //const COUNTRY_NEW_ZEALAND = 'NZ';

    const PLATFORM_NAME       = 'woocommerce';
    const DISPLAY_NAME_BEFORE = 'humm';
    const DISPLAY_NAME_AFTER  = 'humm';
    const PLUGIN_FILE_NAME    = 'humm';
    const BUTTON_COLOR        = array(
        'humm' => 'FF6C00',
    );
    const URLS                = array(
        'AU'      => array(
            'sandboxURL'             => 'https://integration-cart.shophumm.com.au/Checkout/Process?platform=Default',
            'liveURL'                => 'https://cart.shophumm.com.au/Checkout/Process?platform=Default',
            'sandbox_refund_address' => 'https://integration-buyerapi.shophumm.com.au/api/ExternalRefund/v1/processrefund',
            'live_refund_address'    => 'https://buyerapi.shophumm.com.au/api/ExternalRefund/v1/processrefund',
        ),        
    );


    /**
     * @var array
     */
    public $countries = array(
        self::COUNTRY_AUSTRALIA => array(
            'name'            => 'Australia',
            'currency_code'   => 'AUD',
            'currency_symbol' => '$',
            'tld'             => '.com.au',
            'max_purchase'    => 2000,
            'min_purchase'    => 20,
        ),
    );

    /**
     * @return mixed
     */
    public function getButtonColor()
    {
            return self::BUTTON_COLOR['humm'];
    }

    /**
     * @return string
     */

    public function getDisplayName()
    {
        $name    = self::DISPLAY_NAME_BEFORE;
        $country = '';
        if (isset(get_option('woocommerce_humm_settings')['country'])) {
             $country = get_option('woocommerce_humm_settings')['country'];
        };
        if (! $country) {
            $wc_country = get_option('woocommerce_default_country');
            if ($wc_country) {
                $country = substr($wc_country, 0, 2);
            }
        }
        if ($country == 'AU') {
            $name = self::DISPLAY_NAME_AFTER;
        }

        return $name;
    }

    /**
     * GetLogger
     *
     * @return WC_Logger
     */
    public function getLogger()
    {
        if (function_exists('wc_get_logger')) {
            return wc_get_logger();
        }
    }

    /**
     * @param $countryCode
     * @return mixed
     */
    public function getUrlAddress($countryCode)
    {
            return self::URLS['AU'];
    }

    /**
     * @return string
     */

    public function getPluginFileName()
    {
        return self::PLUGIN_FILE_NAME;
    }

    /**
     * @return mixed
     */

    public function getPluginVersion()
    {
        return get_plugin_data(plugin_dir_path(__FILE__) . '../' . self::PLUGIN_FILE_NAME . '.php', false, false)['Version'];
    }
}
