<?php
defined('ABSPATH') || exit;

if (! class_exists('HummGroup_Gateway')) {
    require_once 'hummgroup_gateway.php';
}

if (! class_exists('Humm_Config')) {
    require_once 'humm_config.php';
}

if (!class_exists('humm_logger')) {
    require_once('humm_logger.php');
}

/**
 *
 */
class Humm_Gateway extends HummGroup_Gateway
{
    /**
     * @var null
     */
    protected static $ip4Pattern     = '/^103\.49\.19\.\d+$/';
    protected static $ip6Pattern     = '/^(?:[A-F0-9]{1,4}:){7}[A-F0-9]{1,4}$/i';
    private static $instance = null;
    private static $instance_count = 0;
    /**
     * @constant
     */
    const PLUGIN_NO_GATEWAY_LOG_MSG          = 'Transaction attempted with no gateway URL set. Please provide a gateway URL.';
    const PLUGIN_MISCONFIGURATION_CLIENT_MSG = 'There is an issue with the site configuration, which has been logged. We apologize for any inconvenience. Please try again later. ';
    const PLUGIN_NO_API_KEY_LOG_MSG          = 'Transaction attempted with no API key set. Please provide the API Key';
    const PLUGIN_NO_MERCHANT_ID_SET_LOG_MSG  = 'Transaction attempted with no Merchant Number. Please provide the Merchant Number.';
    const PLUGIN_NO_REGION_LOG_MSG           = 'Transaction attempted with no humm region set. Please provide the region.';
    public static $littleBigFlag             = 0;
    public static $big_small_flag            = array(
        'big'    => '&BigThings',
        //'little' => '&LittleThings',
    );
    public $shop_details;
    /**
     * WC_Humm_Gateway constructor.
     */
    public function __construct()
    {
        $config = new Humm_Config();
        parent::__construct($config);
        add_action('rest_api_init', array( $this, 'register_routes' ));
        $this->method_description = __('Easy to setup installment payment plans from ' . $config->getDisplayName());
        $this->title              = __($config->getDisplayName(), 'woocommerce');
        $this->shop_details       = __($config->getDisplayName() . ' Payment', 'woocommerce');
        $this->order_button_text  = __('Proceed to ' . $config->getDisplayName(), 'woocommerce');
        $this->description        = '<br>';
    }
    /***
     * @return self|null
     */
    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
            self::$instance_count++;
        }
        return self::$instance;
    }
    /***
     * @return int
     */
    public static function get_instance_count()
    {
        return self::$instance_count;
    }
    /***
     * @return void
     */
    public function register_routes()
    {
        $namespace = 'humm/v1';
        $route = '/callback-endpoint/';
        register_rest_route($namespace, $route.'(?P<order_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array( $this, 'humm_payment_callback' ),
            'permission_callback' => array( $this, 'humm_ip_permission_callback' ),
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => __('The ID of the order.', 'woo'),
                ),
            ),
        ));
        register_rest_route($namespace, $route.'ping', array(
            'methods' => 'GET',
            'callback' => array( $this, 'humm_payment_ping' ),
            'permission_callback' => '__return_true',
        ));
        add_filter('rest_pre_dispatch', array($this,'humm_rest_api_rate_limit'), 10, 3);
    }

    /***
     * @param WP_REST_Request $request
     * @return false|Humm_REST_Response|WP_Error
     */
    public function humm_payment_callback(WP_REST_Request $request)
    {
        Humm_Logger::log(sprintf("CallBack API start-----------:%s", json_encode($request->get_params())));
        foreach ($request->get_params() as $key => $value) {
            if (preg_match('/x_/', $key)) {
                $params[ $key ] = wp_kses_data($value);
            }
        }
        if (empty($params)) {
            return false;
        }
        if (isset($params['order_id'], $params['x_reference']) && $params['order_id'] !== $params['reference']) {
            return false;
        }
        $messageRetu = $this->payment_callback($params);
        $response = $this->send_response($messageRetu);
        Humm_Logger::log(sprintf("callback API finish-------: %s  %s ", json_encode($messageRetu), json_encode($response)));
        return $response;
    }
    /***
     * @param WP_REST_Request $request
     * @return void
     */
    public function humm_payment_ping(WP_REST_Request $request)
    {
        Humm_Logger::log(sprintf("CallBack ping for API --%s", json_encode($request)));
        $pingSuccess = array(
            'ping' => 'ok'
             );
        return $pingSuccess;
    }

    /**
     * @param $result
     * @param $server
     * @param $request
     * @return WP_Error
     */
    public function humm_rest_api_rate_limit($result, $server, $request)
    {
        if (!(!preg_match('/\/humm\/v1\/callback-endpoint\/\d+/', $request->get_route()) && $request->get_route() !== '/humm/v1/callback-endpoint/ping')) {
            $key = get_current_user_id() ?: $_SERVER['REMOTE_ADDR'];
            $requests = (array) get_transient("rest_api_rate_limit_{$key}");
            $requests[] = time();
            $requests = array_filter($requests, function ($time) {
                return $time > time() - 60;
            });
            Humm_Logger::log(sprintf("Rate for API%s--%s", $key, count($requests)));
            if (count($requests) > 15) {
                return new WP_Error('rest_rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
            }
            set_transient("rest_api_rate_limit_{$key}", $requests, 60);
        }
        return $result;
    }
    /***
     * @return bool
     */
    public function humm_ip_permission_callback()
    {
        $ipv4_pattern = self::$ip4Pattern;
        $ipv6_pattern = self::$ip6Pattern;
        $current_ip = $_SERVER['REMOTE_ADDR'];
        if (in_array($current_ip, array('127.0.0.1', '::1', 'localhost'))) {
            return true;
        }
        Humm_Logger::log(sprintf('CALLBACK- IP%s', $current_ip));
        if (preg_match($ipv4_pattern, $current_ip) || preg_match($ipv6_pattern, $current_ip)) {
            return true;
        } else {
            Humm_Logger::log(sprintf('Invalidate IP%s', $current_ip));
            return false;
        }
    }
    /**
     * @param $response_data
     * @return Humm_REST_Response|WP_Error
     */
    public function send_response($response_data)
    {
        $num_retries = 0;
        $max_retries = 5;
        $retry_delay = 1000; // in milliseconds
        while ($num_retries < $max_retries) {
            $response = new Humm_REST_Response($response_data, 200);
            if ($response->is_response_success()) {
                return $response;
            } else {
                $num_retries++;
                usleep($retry_delay * pow(2, $num_retries));
            }
        }
        return new WP_Error('api_error', __('API response error after multiple retries'), array('status' => 400));
    }
    /***
     * @return void
     */
    public function add_price_widget()
    {
        echo wp_kses('<div id="humm-price-info-anchor"></div>', $this->get_allow_html());
        echo wp_kses($this->get_widget_script(), $this->get_allow_html());
    }
    /***
     * @return string|void
     */
    public function get_widget_script()
    {
        global $product;
        $merchantId = sprintf('&merchantId=%s', $this->getMerchantId());

        if (is_product()) {
            $displayPrice = wc_get_price_to_display($product);
        }
        $script = '';
        if ($this->settings['country'] == 'AU') {
            if ($this->settings['enabled'] === 'yes' && ((isset($this->settings['price_widget']) && $this->settings['price_widget'] === 'yes') || $this->settings['page_builder'])) {
                $maximum  = $this->getMaxPrice();
                $name     = 'humm';
                $advanced = isset($this->settings['price_widget_advanced']) && $this->settings['price_widget_advanced'] === 'yes';
                $script   = '<script ';
                if ($maximum > 0) {
                    $script .= 'data-max="' . $maximum . '" ';
                }
               
                $script .= 'src="https://bpi.humm-au.com/au/content/scripts/price-info_sync.js?';
                
                if ($advanced && isset($this->settings['price_widget_dynamic_enabled']) && $this->settings['price_widget_dynamic_enabled'] === 'yes') {
                    if (isset($this->settings['price_widget_price_selector']) && $this->settings['price_widget_price_selector']) {
                        $selector1 = htmlspecialchars_decode($this->settings['price_widget_price_selector']);
                        $selector  = urldecode($selector1);
                    } else {
                        $selector = '.price .woocommerce-Price-amount.amount';
                    }
                    $script .= 'price-selector=' . $selector . '&delayLoad=1';
                } else {
                    $script .= 'productPrice=' . $displayPrice;
                }
                $script .= '&element=';
                $script .= $advanced && isset($this->settings['price_widget_element_selector']) && $this->settings['price_widget_element_selector'] !== '' ? urlencode($this->settings['price_widget_element_selector']) : '%23' . $name . '-price-info-anchor';

                // if ('humm' === $name) {
                //     $merchant_type = '&' . $this->settings['merchant_type'];
                //     if ($merchant_type !== '&both') {
                //         $script .= $merchant_type;
                //     }
                // }
                $script .= $merchantId;
                $script .= '"></script>';
            }
            return $script;
        }
    }
    /**
     * add_price_widget_anchor()
     */
    public function add_price_widget_anchor()
    {
        echo wp_kses('<div id="humm-price-info-anchor"></div>', $this->get_allow_html());
    }
    /**
     * add_top_banner_widget
     */
    public function add_top_banner_widget()
    {
        if (isset($this->settings['top_banner_widget']) && $this->settings['top_banner_widget'] === 'yes') {
            $country_domain = $this->settings['country'] === 'AU' ? 'com.au' : 'co.nz';
            if ((isset($this->settings['top_banner_widget_homepage_only']) && $this->settings['top_banner_widget_homepage_only'] === 'yes') && ! is_front_page()) {
                return;
            } else {
                echo wp_kses(sprintf('%s%s', '<div style="margin-bottom: 20px">', '<script defer id="humm-top-banner-script" src="https://widgets.shophumm.' . $country_domain . '/content/scripts/more-info-large.js"></script></div>'), $this->get_allow_html());
            }
        }
    }
    /**
     * @return array
     */
    public function get_settings()
    {
        // these are safe values to export via javascript
        $whitelist = array(
            'enabled'         => null,
            'display_details' => null,
            'title'           => null,
            'description'     => null,
            'shop_details'    => null,
            'shop_name'       => null,
            'country'         => null,
            'use_modal'       => null,
        );
        foreach ($whitelist as $k => $v) {
            if (isset($this->settings[ $k ])) {
                $whitelist[ $k ] = $this->settings[ $k ];
            }
        }
        if ($whitelist['use_modal'] === 'yes') {
            if ($this->currentConfig->getDisplayName() === 'Humm') {
                $whitelist['use_modal'] = 'no';
            }
        }
        return $whitelist;
    }
}
/**
 *
 */
class Humm_REST_Response extends WP_REST_Response
{
    /**
     * @return bool
     */
    public function is_response_success()
    {
        $status = $this->get_status();
        return $status >= 200 && $status < 300;
    }
}
