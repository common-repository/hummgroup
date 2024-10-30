<?php

defined('ABSPATH') || exit;


/**
 * Class HummGroup_Gateway
 */
abstract class HummGroup_Gateway extends WC_Payment_Gateway
{

    /**
     * @var
     */
    public $plugin_current_version;
    public $logger               = null;
    protected $currentConfig     = null;
    protected $pluginDisplayName = null;

    protected $pluginFileName = null;

    protected $flexi_payment_preselected = false;
    private $logContext;

    protected static $hummJs       = 'https://bpi.humm-au.com/au/content/scripts/price-info_sync.js?productPrice=';
    protected static $cartWidgetSelector     = '.humm-cart-widget';
    protected static $checkoutWidgetSelector = '.woocommerce-checkout-review-order-table';

    /**
     * WC_Flexi_Gateway_Humm constructor.
     *
     * @param $config
     */
    public function __construct($config)
    {
        $this->currentConfig     = $config;
        $this->pluginDisplayName = $config->getDisplayName();
        $this->pluginFileName    = strtolower($config->getPluginFileName());
        if (function_exists('wc_get_logger')) {
            $this->logger     = wc_get_logger();
            $this->logContext = array( 'source' => $this->pluginDisplayName );
        }

        $this->id                     = $this->pluginFileName;
        $this->has_fields             = false;
        $this->method_title           = __($this->pluginDisplayName, 'woocommerce');
        $this->plugin_current_version = $config->getPluginVersion();

        $this->init_form_fields();
        $this->init_settings();
        if (is_admin()) {
            $this->init_upgrade_process();
        }
        if (is_admin() && ($this->settings['enabled'] == 'yes')) {
            add_action('admin_enqueue_scripts', array( $this, 'admin_scripts' ));
        }
        if ($this->settings['enabled'] == 'yes') {
            add_action('wp_enqueue_scripts', array( $this, 'front_scripts' ));
        }

        $wigHook = 'woocommerce_single_product_summary';
        if ($this->getWidgetHook()) {
            $wigHook = $this->getWidgetHook();
        }
        if ($this->settings['enabled'] == 'yes') {
            if (isset($this->settings['price_widget']) && $this->settings['price_widget'] == 'yes') {
                add_action($wigHook, array($this, 'add_price_widget'), 11);
            }
        }
        if ($this->settings['enabled'] == 'yes') {
            if (isset($this->settings['page_builder']) && $this->settings['page_builder'] == 'yes') {
                add_shortcode('woocommerce_humm_elementor', array($this, 'add_price_widget'));
            }
        }

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options',
            )
        );

        add_action('wp_body_open', array($this, 'add_top_banner_widget' ));
        add_filter('woocommerce_thankyou_order_id', array( $this, 'payment_finalisation' ));
       // add_filter('the_title', array( $this, 'order_received_title' ), 11);
        add_action('woocommerce_review_order_before_payment', array( $this, 'display_min_max_notice' ));
        add_action('woocommerce_before_cart', array( $this, 'display_min_max_notice' ));
        add_filter('woocommerce_available_payment_gateways', array( $this, 'display_min_max_filter' ));
        add_filter('woocommerce_available_payment_gateways', array( $this, 'preselect_flexi' ));
        add_filter('woocommerce_thankyou_order_received_text', array( $this, 'thankyou_page_message' ));
        add_action('wp_enqueue_scripts', array( $this, 'add_payment_field_script' ));
        add_filter('script_loader_tag', array( $this, 'add_hummjs_defer' ), 20, 2);
        add_action('admin_footer', array($this, 'humm_button_js_function' ));
        add_action( 'woocommerce_cart_totals_after_order_total', array($this, 'humm_custom_after_cart_totals'), 10 );
        add_action("wp_footer", array($this, "add_humm_cart_widget"));
        

        if ('yes' === $this->settings['enabled']) {
            if (isset($this->settings['status_humm']) && 'yes' === $this->settings['status_humm']) {
                add_filter('manage_edit-shop_order_columns', array($this, 'humm_order_payment_note_column'));
                add_action('manage_shop_order_posts_custom_column', array($this, 'humm_order_payment_note_column_content'));
            }
        }
        $preselect_button_order = $this->settings['preselect_button_order'] ? $this->settings['preselect_button_order'] : '10';
        add_action(
            'woocommerce_proceed_to_checkout',
            array(
                $this,
                'flexi_checkout_button',
            ),
            $preselect_button_order
        );
    }


    /**
     * WC override to display the administration property page
     */
    public function init_form_fields()
    {
        /** @var TYPE_NAME $countryOptions */
        //$countryOptions = array( '' => __('Please select', 'woocommerce') );
        $countryOptions = array();
        //$callping_url = $this->humm_get_ping_url();
        foreach ($this->currentConfig->countries as $countryCode => $country) {
            $countryOptions[ $countryCode ] = __($country['name'], 'woocommerce');
        }

        $merchantTypes = array(
            
            'BigThings'    => __('BigThings', 'woocommerce'),            
        );

        $callbackTypes = array(
            '2'         => __('V2', 'woocommerce')
        );

        $this->form_fields = array(            
            'Payment method configuration'                         => array(
                'title' => __('Payment method configuration', 'woocommerce'),
                'type'  => 'title',
                'css'   => WC_HUMM_ASSETS . 'css/humm.css',
                'class' => 'humm-general',
            ),
            'enabled'                                  => array(
                'title'       => __('Enable humm payment gateway', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Yes', 'woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => false,
            ),
            'country'                                  => array(
                'title'             => __('Region', 'woocommerce'),
                'type'              => 'select',
                'class'             => 'wc-enhanced-select',
                'css'   => WC_HUMM_ASSETS . 'css/humm.css',
                'description'       => 'Select the region that matches your retailer agreement.',
                'options'           => $countryOptions,
                'desc_tip'          => true,
                'custom_attributes' => array( 'required' => 'required' ),
            ),
            "{$this->pluginFileName}_merchant_id"      => array(
                'id'                => $this->pluginFileName . '_merchant_id',
                'title'             => __('Merchant Number', 'woocommerce'),
                'type'              => 'text',
                'default'           => '',
                'description'       => $this->pluginDisplayName . ' will have supplied you with your ' . $this->pluginDisplayName . ' Merchant Number. Contact us if you cannot find it.',
                'desc_tip'          => true,
                'custom_attributes' => array( 'required' => 'required' ),
            ),
            $this->pluginFileName . '_api_key' => array(
                'id'                => $this->pluginFileName . '_api_key',
                'title'             => __('API Key', 'woocommerce'),
                'type'              => 'password',
                'default'           => '',
                'description'       => 'Please use the correct Merchant Number and API key to avoid error on checkout',
                'desc_tip'          => false,
                'custom_attributes' => array( 'required' => 'required' ),
            ),
            $this->pluginFileName . '_minimum'         => array(
                'id'          => $this->pluginFileName . '_minimum',
                'title'       => __('Minimum Order Amount (AUD)', 'woocommerce'),
                'type'        => 'number',
                'default'     => '',
                'description' => 'Minimum value must be atleast $80 to use humm at checkout',
                'desc_tip'    => true,
                'custom_attributes' => array('min' => '80', 'max' => '30000'),
            ),
            $this->pluginFileName . '_maximum'         => array(
                'id'          => $this->pluginFileName . '_maximum',
                'title'       => __('Maximum Order Amount (AUD)', 'woocommerce'),
                'type'        => 'number',
                'default'     => '',
                'description' => 'Enter the maximum checkout value configured at humm' ,
                'desc_tip'    => true,
                'custom_attributes' => array('min' => '80', 'max' => '30000'),
            ),
            'use_test'                                 => array(
                'title'       => __('Test Mode', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Yes', 'woocommerce'),
                'default'     => 'no',
                'description' => __('Enable test mode to test the humm plugin before going live. All transactions will be simulated and cards will not be charged', 'woocommerce'),
            ),            
            
            'shop_name'                                => array(
                'title'       => __('Shop Name', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Name of the shop that will be displayed in humm', 'woocommerce'),
                'default'     => __('', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'status_humm'                              => array(
                'title'       => __('Show Payment Status', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Add payment status column in the order listing', 'woocommerce'),
                'default'     => 'yes',
                'description' => __('You can review payment status on order listing', 'woocommerce'),
                'desc_tip'    => true,
            ),
            // 'ping_button' => array(
            //     'title' => __('Check API', 'woocommerce'),
            //     'type' => 'button',
            //     'button_type' => 'submit',
            //     'class' => 'button-primary humm-callback-check',
            //     'id' => 'ping-button-id',
            //     'desc' => __('Click this button to ping a CallBack API.', 'woocommerce'),
            //     'custom_attributes' => array( 'onclick' => 'hummButtonHandler("' . $callping_url . '")' ),
            // ),
            
            'display_settings'                        => array(
                'title' => __('Banners and Widgets', 'woocommerce'),
                'type'  => 'title',
                'css'   => WC_HUMM_ASSETS . 'css/humm.css',
                'class' => 'humm-general',
            ),
            'price_widget'                             => array(
                'title' => __('Price Widget', 'woocommerce'),
                'type'  => 'checkbox',
                'label' => __('Enable humm Price Widget', 'woocommerce'),
            ),
            'hook_widget_selector'                     => array(
                'title'       => __('Product page hook', 'woocommerce'),
                'type'        => 'text',
                'default'     => 'woocommerce_single_product_summary',
                'description' => '<i><p>WooCommerce Hook to control the position of the humm widget on the product page</p> </p></i>',
            ),            

            'price_widget_advanced'                    => array(
                'title'       => __('Price Widget Advanced Settings', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable advanced options for the Price Widget', 'woocommerce'),
                'default'     => 'no',
                'description' => '<strong>Leave disabled if unsure.</strong>',
            ),
            'price_widget_element_selector'            => array(
                'type'        => 'text',
                'default'     => '',
                'description' => 'CSS selector for the element to insert the price widget after.<br>Leave empty for default location.',
            ),
            'price_widget_dynamic_enabled'             => array(
                'type'        => 'checkbox',
                'label'       => __('Use Dynamic Version of the Price Widget'),
                'default'     => 'no',
                'description' => 'Price widget will automatically update the breakdown if the product price changes. <br>Leave this disabled if unsure. <br><strong>Uses the CSS selector below to track changes.</strong>',
            ),
            'price_widget_price_selector'              => array(
                'label'       => __('Price Widget CSS Selector', 'woocommerce'),
                'type'        => 'text',
                'default'     => '.price .woocommerce-Price-amount.amount',
                'description' => 'CSS selector for the element containing the product price',
                'desc_tip'    => true,
            ),
            'preselect_button_enabled'                 => array(
                'title'       => __('Add humm Checkout Button', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Add a "Checkout with ' . $this->pluginDisplayName . '" button on Cart page', 'woocommerce'),
                'default'     => 'yes',
                'description' => __('Add a "Checkout with ' . $this->pluginDisplayName . '" button on Cart page that takes customer to Checkout page with ' . $this->pluginDisplayName . ' as a pre-selected option', 'woocommerce'),
            ),
            'preselect_button_order'                   => array(
                'title'       => __('Checkout Button Order', 'woocommerce'),
                'type'        => 'text',
                'label'       => __('Checkout Button Order', 'woocommerce'),
                'default'     => '10',
                'description' => __('Sort order of the humm button on Cart page. Use smaller number to move the humm button up in the order.', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'cart_widget'                              => array(
                'title'       => __('Cart Widget', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable ' . $this->pluginDisplayName . ' Cart Widget', 'woocommerce'),
                'default'     => 'yes',
                'description' => 'Display humm widget on cart page',
            ),

            'checkout_widget'                          => array(
                'title'       => __('Checkout Widget', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable ' . $this->pluginDisplayName . ' Checkout Widget', 'woocommerce'),
                'default'     => 'yes',
                'description' => 'Display humm widget on checkout page',
            ),
            'top_banner_widget'                        => array(
                'title'           => __('Top Banner Widget', 'woocommerce'),
                'label'           => __('Enable ' . $this->pluginDisplayName . ' Top Banner Widget', 'woocommerce'),
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => 'start',
                'show_if_checked' => 'option',
                'description'     => 'Display humm widget as a top banner',
            ),
            'top_banner_widget_homepage_only'          => array(
                'label'           => __('Top Banner Widget Shows on FrontPage Only', 'woocommerce'),
                'default'         => 'yes',
                'type'            => 'checkbox',
                'checkboxgroup'   => 'end',
                'show_if_checked' => 'yes',
                'description'     => 'If enabled, humm banner will be displayed on homepage only',
                'autoload'        => false,
            ),

            'page_builder'                             => array(
                'title'           => __('Elementor Page Builder', 'woocommerce'),
                'label'           => __('humm Widget ShortCode in Builder Page', 'woocommerce'),
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => 'start',
                'show_if_checked' => 'option',
                'description'     => 'In the Elementor Page, Insert ShortCode <span><strong>woocommerce_humm_elementor</span></strong>,compatible with a product page using hook widget',
            ),

            'au_settings'                              => array(
                'title' => __('', 'woocommerce'),
                'type'  => 'title',
            ),
            'merchant_type'                            => array(
                'title'             => __('Merchant Type', 'woocommerce'),
                'type'              => 'select',
                'class'             => 'wc-enhanced-select',
                'options'           => $merchantTypes,
                'custom_attributes' => array( 'required' => 'required' ),
            ),
                       
            'enable_logging'                           => array(
                'title'       => __('Enable Logging', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Yes', 'woocommerce'),
                'default'     => 'yes',
                'description' => __('humm logs are available at the <a href="' . admin_url('admin.php?page=wc-status&tab=logs') . '">WooCommerce status page</a>', 'woocommerce'),
            ),
            'callback_type'                            => array(
                'title'             => __('Callback API version', 'woocommerce'),
                'type'              => 'select',
                'class'             => 'wc-enhanced-select',
                'options'           => $callbackTypes,               
            ),
            
        );
    }

    /***
     * @return void
     */
    public function humm_button_js_function()
    {
        ?>
        <script>
            function hummButtonHandler(url) {
                fetch(url)
                    .then(response => {
                        if (response.ok) {
                            return response.json();
                        }
                        throw new Error('Network response was not ok.');
                    })
                    .then(json => {
                        document.getElementsByClassName('humm-callback-check')[0].value = 'API call succeeded with response: ' + JSON.stringify(json);
                    }).catch(error => {
                        alert(error)
                        document.getElementById('humm-callback-check')[0].value = 'API call failed with error: ' + error.message;
                    });
            }
        </script>
        <?php
    }

    /**
     * Check to see if we need to run upgrades.
     */
    public function init_upgrade_process()
    {
        // get the current upgrade version. This will default to 0 before version 0.4.5 of the plugin
        $currentDbVersion = isset($this->settings['db_plugin_version']) ? $this->settings['db_plugin_version'] : 0;
        // see if the current upgrade version is lower than the latest version
        if (version_compare($currentDbVersion, $this->plugin_current_version) < 0) {
            // run the upgrade process
            if ($this->upgrade($currentDbVersion)) {
                // update the stored upgrade version if the upgrade process was successful
                $this->updateSetting('db_plugin_version', $this->plugin_current_version);
            }
        }
    }
    /**
     * @param $currentDbVersion
     * @return bool
     */
    private function upgrade($currentDbVersion)
    {
        if (is_admin() && ($this->settings['enabled'] == 'yes')) {
            add_action('admin_enqueue_scripts', array( $this, 'admin_scripts' ));
        }
        if (version_compare($currentDbVersion, '1.2.0') < 0) {
            if (! isset($this->settings['use_modal'])) {
                // default to the redirect for existing merchants
                // so we don't break the existing behaviour
                $this->settings['use_modal'] = false;
                $this->updateSetting('use_modal', $this->settings['use_modal']);
            }
            $minField       = sprintf('%s_minimum', $this->pluginFileName);
            $maxField       = sprintf('%s_maximum', $this->pluginFileName);
            
            if (! isset($this->settings[ $minField ])) {
                $this->updateSetting('use_modal', $this->settings[ $minField ]);
            }
            if (! isset($this->settings[ $maxField ])) {
                $this->updateSetting('use_modal', $this->settings[ $maxField ]);
            }
            
        } elseif (version_compare($currentDbVersion, '1.3.5') < 0) {
            if (! isset($this->settings['preselect_button_enabled'])) {
                $this->settings['preselect_button_enabled'] = 'no';
                $this->updateSetting('preselect_button_enabled', $this->settings['preselect_button_enabled']);
            }
            if (! isset($this->settings['preselect_button_order'])) {
                // set default to 20 for pre-select button sequence
                $this->settings['preselect_button_order'] = '10';
                $this->updateSetting('preselect_button_order', $this->settings['preselect_button_order']);
            }
        } elseif (version_compare($currentDbVersion, '1.3.14') < 0) {
            if (! isset($this->settings['top_banner_widget'])) {

                $this->settings['top_banner_widget'] = 'no';
                $this->updateSetting('top_banner_widget', $this->settings['top_banner_widget']);
            }
            if (! isset($this->settings['top_banner_widget_homepage_only'])) {
                // set default to 20 for pre-select button sequence
                $this->settings['top_banner_widget_homepage_only'] = '20';
                $this->updateSetting('top_banner_widget_homepage_only', $this->settings['top_banner_widget_homepage_only']);
            }
        } elseif (version_compare($currentDbVersion, '1.6.0') < 0) {
            if (! isset($this->settings['merchant_type'])) {
                // default to BigThings
                $this->settings['merchant_type'] = 'BigThings';
                $this->updateSetting('merchant_type', $this->settings['merchant_type']);
            }
        } elseif (version_compare($currentDbVersion, '1.7.4') < 0) {
            if (! isset($this->settings['enable_logging'])) {
                // default to yes
                $this->settings['enable_logging'] = 'yes';
                $this->updateSetting('enable_logging', $this->settings['enable_logging']);
            }
        }

        return true;
    }

    /**
     * Update a plugin setting stored in the database
     */
    private function updateSetting($key, $value)
    {
        $this->settings[ $key ] = $value;
        update_option($this->get_option_key(), $this->settings);
    }

    /**
     * @return array
     */
    public function get_allow_html()
    {
        return $allowed_html = array(
            'script' => array(
                'src'   => array(),
                'title' => array(),
                'defer' => array(),
                'id'    => array(),
            ),
            'div'    => array(
                'id'    => array(),
                'style' => array(),
                'class' => array(),
            ),
            'em'     => array(),
            'strong' => array(),
            'h3'     => array(),
            'h5'     => array(
                'class' => array(),
            ),
            'a'      => array(
                'href'  => array(),
                'id'    => array(),
                'style' => array(),
                'class' => array(),

            ),
            'img'    => array(
                'alt'   => array(),
                'src'   => array(),
                'class' => array(),
                'width' => array(),
            ),
            'span'   => array(
                'class' => array(),
            ),
        );
    }


    /**
     * Abstract classs
     *
     * @return mixed
     */
    abstract public function add_top_banner_widget();

    /**
     * Abstract classs
     *
     * @return mixed
     */

    abstract public function add_price_widget();

    abstract public function add_price_widget_anchor();

    /**
     * flexi_checkout_button
     */

    public function flexi_checkout_button()
    {

        $minimum = $this->getMinPrice();
        $maximum = $this->getMaxPrice();
        if (($minimum != 0 && WC()->cart->total > $minimum) && ($maximum != 0 && WC()->cart->total < $maximum)) {
            if ($this->settings['preselect_button_enabled'] == 'yes' && $this->settings['enabled'] == 'yes') {
                echo wp_kses(
                    '<div><a href="' . esc_url(wc_get_checkout_url()) . '?' . $this->pluginDisplayName . '_preselected=true" class="checkout-button button humm-express">Check out with ' . $this->pluginDisplayName . '</a></div>',
                    $this->get_allow_html()
                );
            }
        }
    }

    // Adding humm widget placeholder
    public function humm_custom_after_cart_totals()
    {        
        echo wp_kses(
            '<div class="humm-cart-widget"></div>',
            $this->get_allow_html()
        );
    }

    /**
     * @param $columns
     * @return mixed
     */

    public function humm_order_payment_note_column($columns)
    {
        $columns['Payment-Status'] = 'Payment-Status';
        return $columns;
    }

    /**
     * @param $column
     */
    public function humm_order_payment_note_column_content($column)
    {
        global $post;
        if ('Payment-Status' === $column) {
            $order     = wc_get_order($post->ID);
            $orderNote = $this->get_humm_order_notes($order->get_id());
            if ($order->get_data()['payment_method'] == $this->pluginFileName) {
                $showNote = ' <div class="humm-status"><span>' . (isset($orderNote[0]) ? $orderNote[0] : ' ') . '</span></div>';
            } else {
                $showNote = ' <div class="payment-status"><span>' . $order->get_data()['payment_method'] . '</span></div>';
            }
            echo wp_kses($showNote, $this->get_allow_html());
        }
    }

    /**
     * @param $orderId
     * @return array
     */
    public function get_humm_order_notes($orderId)
    {
        global $wpdb;
        $query   = $wpdb->prepare(
            "SELECT *
        FROM  {$wpdb->prefix}comments
        WHERE  `comment_post_ID` = %s  AND  `comment_type` LIKE  'order_note'",
            $orderId
        );
        $results = $wpdb->get_results(
            $query
        );

        $orderNote = array();
        foreach ($results as $note) {
            $orderNote[] = sprintf('%s <br/>', $note->comment_content);
        }
        return $orderNote;
    }

    /**
     * display_min_max_notice
     */

    public function display_min_max_notice()
    {
        $minimum = $this->getMinPrice();
        $maximum = $this->getMaxPrice();

        if ($minimum != 0 && WC()->cart->total < $minimum) {
            if (is_checkout()) {
                wc_print_notice(
                    sprintf(
                        'Orders under %s are not supported by %s',
                        wc_price($minimum),
                        $this->pluginDisplayName                        
                    ),
                    'error'
                );
            }
        }
        else if ($minimum != 0 && WC()->cart->total < 80) {
            if (is_checkout()) {
                wc_print_notice(
                    sprintf(
                        'Orders under $80 are not supported by %s',                        
                        $this->pluginDisplayName                        
                    ),
                    'error'
                );
            }
        } else if ($maximum != 0 && WC()->cart->total > $maximum) {
            if (is_checkout()) {
                wc_print_notice(
                    sprintf(
                        'Orders above than %s are not supported by %s',
                        wc_price($maximum),
                        $this->pluginDisplayName
                    ),
                    'error'
                );
            }
        }
    }

    /**
     * @return int
     */

    protected function getMinPrice()
    {
        $field = sprintf('%s_minimum', $this->pluginFileName);
        return !isset($this->settings[$field]) ? 0 : $this->settings[ $field ];
    }

    /**
     * @return int
     */
    protected function getMaxPrice()
    {
        $field = sprintf('%s_maximum', $this->pluginFileName);

        return isset($this->settings[ $field ]) && (intval($this->settings[ $field ]) <> 0) ? $this->settings[ $field ] : 10000;
    }

    /**
     * @param $available_gateways
     * @return mixed
     */

    public function display_min_max_filter($available_gateways)
    {

        if (isset(WC()->cart)) {
            $minimum = $this->getMinPrice();
            $maximum = $this->getMaxPrice();
            if (($minimum != 0 && WC()->cart->total < $minimum) || ($maximum != 0 && WC()->cart->total > $maximum)) {
                if (isset($available_gateways[ $this->pluginFileName ])) {
                    unset($available_gateways[ $this->pluginFileName ]);
                }
            }
        }
        return $available_gateways;
    }

    /**
     * @param $available_gateways
     * @return mixed
     */

    public function preselect_flexi($available_gateways)
    {
        if (isset($_GET[ $this->pluginDisplayName . '_preselected' ])) {
            $this->flexi_payment_preselected = $_GET[ $this->pluginDisplayName . '_preselected' ];
        }

        if (! empty($available_gateways)) {
            if ($this->flexi_payment_preselected == 'true') {
                foreach ($available_gateways as $gateway) {
                    if (strtolower($gateway->id) == $this->pluginFileName) {
                        WC()->session->set('chosen_payment_method', $gateway->id);
                    }
                }
            }
        }

        return $available_gateways;
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

        return $whitelist;
    }

    /**
     * payment_fields
     */
    public function payment_fields()
    {
        if (($this->currentConfig->getDisplayName() == 'humm') && ($this->isTesting() != 'no')) {
            $sandbox_enabled = '<div> <span class="humm-sandbox-enabled">humm is in Test Mode. You won\'t be able to make real purchases at this time.</span></div>';
            echo wp_kses($sandbox_enabled, $this->get_allow_html());
        }

        if (isset($this->settings['checkout_widget']) && ($this->settings['checkout_widget'] == 'yes')) {
            if (($this->currentConfig->getDisplayName() == 'humm')) {
                $merchant_type = '&' . $this->settings['merchant_type'];
                if ($merchant_type == '&both') {
                    $merchant_type = '';
                }
                $hummLogo          = plugins_url('../assets/images/humm-logo.png', __FILE__);
                $this->description = '<div> <span class="humm-checkout">The Bigger Buy Now Pay Later</span><img width="120" alt="humm logo" class="humm-image" src=' . $hummLogo . '></div>';
                echo wp_kses($this->description, $this->get_allow_html());
            }
        }
    }
    public function add_payment_field_script()
    {

        if (isset($this->settings['checkout_widget']) && ($this->settings['checkout_widget'] == 'yes')) {
            $checkout_total = (WC()->cart) ? WC()->cart->get_totals()['total'] : '0';
            $merchantId     = sprintf('&merchantId=%s', $this->getMerchantId());
            if (($this->currentConfig->getDisplayName() == 'humm')) {
                $merchant_type = '&' . $this->settings['merchant_type'];
                if ($merchant_type == '&both') {
                    $merchant_type = '';
                }
                if (is_checkout()) {
                    wp_enqueue_script('wc-humm-js-checkout', self::$hummJs . $checkout_total . '&price-selector=' . self::$checkoutWidgetSelector . $merchantId, '', '', true);
                }
            }
        }
        if ($this->settings['enabled'] == 'yes' && isset($this->settings['cart_widget']) && $this->settings['cart_widget'] == 'yes') {
            if (is_cart()) {
                global $woocommerce;
                $cart_total = $woocommerce->cart->total ? $woocommerce->cart->total : '0';
                if ($this->settings['country'] == 'AU') {
                    $merchant_type = '&' . $this->settings['merchant_type'];
                    $merchantId    = sprintf('&merchantId=%s', $this->getMerchantId());
                    wp_enqueue_script('wc-humm-js-cart', self::$hummJs . $cart_total . '&element=' . self::$cartWidgetSelector . $merchantId, '', '', true);
                }
            }
        }
    }

    public function add_hummjs_defer($tag, $handle)
    {
        if ($handle !== 'wc-humm-js-checkout' && $handle != 'wc-humm-js-cart') {
            return $tag;
        }

        return str_replace(' src=', ' defer src=', $tag);
    }

    /**
     * @param $order
     * @param bool  $re
     * @return bool
     */

    public function validateHummOrder($order, $re = true)
    {
        $order_id = trim(str_replace('#', '', $order->get_order_number()));
        if ($order->get_data()['payment_method'] !== $this->pluginFileName) {
            WC()->session->set('flexi_result_note', '');
            $this->log(sprintf('No humm Payment. orderId: %s is not a %s order ', $order_id, $this->pluginDisplayName));
            $re = false;
        }
        return $re;
    }

    /**
     * Generates the payment gateway request parameters and signature and redirects to the
     * payment gateway through the invisible processing.php form
     *
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {

        $this->log('start process_payment...');
        $order      = new WC_Order($order_id);
        $gatewayUrl = $this->getGatewayUrl();
        $isValid    = true;
        $isValid    = $isValid && $this->verifyConfiguration($order);
        $isValid    = $isValid && $this->checkCustomerLocation($order);
        $isValid    = $isValid && $this->checkOrderAmount($order);
        $isValid    = $isValid && ! is_null($gatewayUrl) && $gatewayUrl != '';

        if (! $isValid) {
            return array();
        }
        if (isset($this->settings['callback_type']) && (2 === intval($this->settings['callback_type']))) {
            $callbackURL = $this->humm_get_callback_url($order_id);
        } else {
            $callbackURL = $this->get_return_url($order);
        }
        $completeURL = $this->get_return_url($order);
        $transaction_details                = array(
            'x_reference'                  => $order_id,
            'x_account_id'                 => $this->settings[ $this->pluginFileName . '_merchant_id' ],
            'x_amount'                     => $order->get_total(),
            'x_currency'                   => $this->getCurrencyCode(),
            'x_url_callback'               => $callbackURL,
            'x_url_complete'               => $completeURL,
            'x_url_cancel'                 => $order->get_checkout_payment_url(),
            'x_test'                       => 'false',
            'x_shop_country'               => $this->getCountryCode(),
            'x_shop_name'                  => $this->settings['shop_name'],
            'x_transaction_timeout'        => 30,
            'version_info'                 => 'humm_' . $this->currentConfig->getPluginVersion() . '_on_wc' . substr(WC()->version, 0, 3),
            'gateway_url'                  => $gatewayUrl,
        );
        $signature                          = $this->flexi_sign($transaction_details, $this->settings[ $this->pluginFileName . '_api_key' ]);
        $transaction_details['x_signature'] = $signature;
        $this->log(json_encode($transaction_details));

        // use RFC 3986 so that we can decode it correctly in js
        $qs          = http_build_query($transaction_details, null, '&', PHP_QUERY_RFC1738);
        $redirectURL = sprintf('%s&parameters=%s', $gatewayUrl, urlencode($qs));
        return array(
            'result'   => 'success',
            'redirect' => $redirectURL,
        );
    }

    /**
     * returns the gateway URL
     *
     * @param $countryCode
     *
     * @return string
     */
    private function getGatewayUrl($countryCode = '')
    {
        // if no countryCode passed in
        if ($this->is_null_or_empty($countryCode)) {
            if (isset($this->settings['country'])) {
                $countryCode = $this->settings['country'];
            } else {
                $countryCode = 'AU';
            }
        }
        $environment = ($this->isTesting() == 'no') ? 'live' : 'sandbox';
        return $this->currentConfig->getUrlAddress($countryCode)[ $environment . 'URL' ];
    }

    /**
     * @param $str
     *
     * @return bool
     */
    private function is_null_or_empty($str)
    {
        return is_null($str) || $str == '';
    }

    /**
     * @return string
     */
    public function isTesting()
    {
        return $this->settings['use_test'] ?? 'no';
    }

    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    private function verifyConfiguration($order)
    {

        $apiKey     = $this->settings[ $this->pluginFileName . '_api_key' ];
        $merchantId = $this->settings[ $this->pluginFileName . '_merchant_id' ];
        $region     = $this->settings['country'];

        $isValid   = true;
        $clientMsg = static::PLUGIN_MISCONFIGURATION_CLIENT_MSG;
        $logMsg    = '';

        if ($this->is_null_or_empty($region)) {
            $logMsg  = static::PLUGIN_NO_REGION_LOG_MSG;
            $isValid = false;
        }

        if ($this->is_null_or_empty($apiKey)) {
            $logMsg  = static::PLUGIN_NO_API_KEY_LOG_MSG;
            $isValid = false;
        }

        if ($this->is_null_or_empty($merchantId)) {
            $logMsg  = static::PLUGIN_NO_MERCHANT_ID_SET_LOG_MSG;
            $isValid = false;
        }

        if (! $isValid) {
            $order->cancel_order($logMsg);
            $this->logValidationError($clientMsg);
        }

        return $isValid;
    }

    /**
     * @param $message
     */

    private function logValidationError($message)
    {
        wc_add_notice(__('Payment error: ', 'woothemes') . $message, 'error');
    }

    /**
     * Ensure the customer is being billed from and is shipping to, Australia.
     *
     * @param WC_Order $order
     *
     * @return bool
     */
    private function checkCustomerLocation($order)
    {

        $countries     = array( $order->get_billing_country(), $order->get_shipping_country() );
        $set_addresses = array_filter($countries);
        $countryCode   = $this->getCountryCode();
        $countryName   = $this->getCountryName();

        return true;
    }

    /**
     * @return string
     */
    private function getCountryCode()
    {
        return $this->settings['country'];
    }

    /**
     * @return string
     */
    private function getCountryName()
    {
        return $this->currentConfig->countries[ $this->getCountryCode() ]['name'];
    }

    /**
     * Ensure the order amount is >= $20
     * Also ensure order is <= max_purchase
     *
     * @param WC_Order $order
     *
     * @return true
     */
    private function checkOrderAmount($order)
    {
        if ($this->currentConfig->getDisplayName() == 'humm') {
            return true;
        }
        $total = $order->get_total();
        $min   = $this->getMinPurchase();
        if ($total < $min) {
            $errorMessage = '&nbsp;Orders under ' . $this->getCurrencyCode() . $this->getCurrencySymbol() . $min . ' are not supported by ' . $this->pluginDisplayName . '. Please select a different payment option.';
            $order->cancel_order($errorMessage);
            $this->logValidationError($errorMessage);

            return false;
        }
        $max = $this->getMaxPurchase();
        if ($total > $max) {
            $errorMessage = '&nbsp;Orders over ' . $this->getCurrencyCode() . $this->getCurrencySymbol() . $max . ' are not supported by ' . $this->pluginDisplayName . '. Please select a different payment option!';
            $order->cancel_order($errorMessage);
            $this->logValidationError($errorMessage);

            return false;
        }

        return true;
    }

    /**
     * @return mixed
     */

    private function getMinPurchase()
    {
        return $this->getMinPrice();
    }

    /**
     * @return string
     */
    private function getCurrencyCode()
    {
        return $this->currentConfig->countries[ $this->getCountryCode() ]['currency_code'];
    }

    /**
     * @return string
     */
    private function getCurrencySymbol()
    {
        return $this->currentConfig->countries[ $this->getCountryCode() ]['currency_symbol'];
    }

    /**
     * @return int
     */
    private function getMaxPurchase()
    {
        return $this->getMaxPrice();
    }

    /**
     * @param $query
     * @param $api_key
     * @return mixed
     */
    public function flexi_sign($query, $api_key)
    {
        $clear_text = '';
        ksort($query);
        foreach ($query as $key => $value) {
            if (substr($key, 0, 2) === 'x_' && $key !== 'x_signature') {
                $clear_text .= $key . $value;
            }
        }
        $hash = hash_hmac('sha256', $clear_text, $api_key);
        return str_replace('-', '', $hash);
    }

    /**
     * Log a message using the 2.7 logging infrastructure
     *
     * @param string $message Message log
     * @param string $level WC_Log_Levels
     */
    public function log($message, $level = WC_Log_Levels::DEBUG)
    {
        if ($this->logger != null && $this->settings['enable_logging']) {
            $this->logger->log($level, $message, $this->logContext);
        }
    }

    /**
     * Renders plugin configuration markup
     */
    public function admin_options()
    {
        include plugin_dir_path(dirname(__FILE__)) . 'includes/view/backend/admin-options.php';
        $countryUrls = array();
        foreach ($this->currentConfig->countries as $countryCode => $country) {
            $countryUrls[ $countryCode ] = array( 'gateway' => $this->getGatewayUrl($countryCode) );
        }
        if (count($countryUrls) > 0) {
            ?>
            <script>
                var countryUrls = <?php echo json_encode($countryUrls); ?>;
            </script>
            <?php
        }
    }


    /**
     * This is a filter setup to receive the results from the flexi services to show the required
     * outcome for the order based on the 'x_result' property
     *
     * @param $order_id
     *
     * @return mixed
     */
    public function payment_finalisation($order_id)
    {
        $order           = wc_get_order($order_id);
        $cart            = WC()->cart;
        $msg             = '';
        $isAsyncCallback = $_SERVER['REQUEST_METHOD'] === 'POST' ? true : false;
        if ($order->get_data()['payment_method'] !== $this->pluginFileName) {
            // we don't care about it because it's not an flexi order
            // log in debug level
            WC()->session->set('flexi_result_note', '');
            $this->log(sprintf('No action required. orderId: %s is not a %s order, (isAsyncCallback=%s)', $order_id, $this->pluginDisplayName, $isAsyncCallback));
            return $order_id;
        }
        $params = $this->getParams($isAsyncCallback);

        if ($order->has_status(array( 'processing', 'completed', 'Cancelled','Refunded'))) {
            $flexi_result_note = __(sprintf('Payment Status: %s using %s ,Gateway_reference# %s', $params['x_result'], $this->pluginDisplayName, $params['x_gateway_reference']), 'woocommerce');
            WC()->session->set('flexi_result_note', $flexi_result_note);
            $this->log(sprintf('order has processed.%s order status: %s orderId: %s (isAsyncCallback=%s)', $flexi_result_note, $order->get_status(), $order_id, $isAsyncCallback));
            return $order_id;
        }
        // we need order information in order to complete the order
        if (empty($order)) {
            $this->log(sprintf('unable to get order information for orderId: %s, (isAsyncCallback=%s)', $order_id, $isAsyncCallback));
            return $order_id;
        }

        $api_key    = $this->settings[ $this->pluginFileName . '_api_key' ];
        $sig_exists = isset($params['x_signature']);
        $sig_match  = false;
        if ($sig_exists) {
            $expected_sig = $this->flexi_sign($params, $api_key);
            $sig_match    = $expected_sig === $params['x_signature'];
            $mer_match    = $this->settings[ $this->pluginFileName . '_merchant_id' ] === $params['x_account_id'];
            $amount_match = floatval($order->get_total()) === floatval($params['x_amount']);
        }

        if ($sig_exists && $sig_match && $mer_match && $amount_match) {
            $this->log(sprintf('Finalising orderId: %s, (isAsyncCallback=%s)', $order_id, $isAsyncCallback));
            if (! empty($params)) {
                $this->log(json_encode($params));
            }
            $flexi_result_note = '';
            switch ($params['x_result']) {
                case 'completed':
                    $flexi_result_note = __('Payment approved using ' . $this->pluginDisplayName . '. Gateway_Reference #' . $params['x_gateway_reference'], 'woocommerce');
                    $order->add_order_note($flexi_result_note);
                    $order->update_meta_data('flexi_purchase_number', $params['x_gateway_reference']);
                    $order->payment_complete($params['x_reference']);
                    if (! is_null($cart) && ! empty($cart)) {
                        $cart->empty_cart();
                    }
                    $msg = 'complete';
                    break;
                case 'failed':
                    $flexi_result_note = __('Payment declined using ' . $this->pluginDisplayName . '. Gateway Reference #' . $params['x_gateway_reference'], 'woocommerce');
                    $order->add_order_note($flexi_result_note);
                    $order->update_status('failed');
                    $msg = 'failed';
                    WC()->session->set('flexi_result', 'failed');
                    break;
                case 'error':
                    $flexi_result_note = __('Payment error using ' . $this->pluginDisplayName . '. Gateway Reference #' . $params['x_gateway_reference'], 'woocommerce');
                    $order->add_order_note($flexi_result_note);
                    $order->update_status('failed', 'Error may have occurred with ' . $this->pluginDisplayName . '. Gateway Reference #' . $params['x_gateway_reference']);
                    $msg = 'error';
                    WC()->session->set('flexi_result', 'failed');
                    break;
            }
            WC()->session->set('flexi_result_note', $flexi_result_note);
        } else {
            $order->add_order_note(
                __(
                    $this->pluginDisplayName . ' payment response failed signature validation. Please check your Merchant Number and API key or contact ' . $this->pluginDisplayName . ' for assistance.' .
                    '</br></br>isJSON: ' . $isAsyncCallback .
                    '</br>Payload: ' . print_r($params, true) .
                    '</br>Expected Signature: ' . $expected_sig,
                    0,
                    'woocommerce'
                )
            );
            $msg = 'signature error ,merchantID or amount mismatch';
            WC()->session->set('flexi_result_note', $this->pluginDisplayName . ' signature error');
        }

        if ($isAsyncCallback) {
            $return = array(
                'message' => $msg,
                'id'      => $order_id,
            );
            wp_send_json($return);
        }

        return $order_id;
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public function payment_callback($params)
    {
        $isAsyncCallback = true;
        $returnSuccess = array(
            'x_reference' => $params['x_reference'],
            'x_result' => 'Approved'
        );
        $returnFailed = array(
            'x_reference' => $params['x_reference'],
            'x_result' => 'Declined'
        );
        $cart            = WC()->cart;
        if ($params['x_reference']) {
            $order_id = $params['x_reference'];
            $order           = wc_get_order($order_id);
            $msg             = '';
        } else {
            $order_id = -1;
            return $returnFailed;
        }
        if ($order->get_data()['payment_method'] !== $this->pluginFileName) {
            $this->log(sprintf('Callback No action required. orderId: %s is not a %s order, (isAsyncCallback)', $order_id, $this->pluginDisplayName));
            return  $returnFailed;
        }

        if ($order->has_status(array( 'processing', 'completed', 'Cancelled','Refunded'))) {
            $flexi_result_note = __(sprintf('Payment Status: %s using %s ,Gateway_reference# %s', $params['x_result'], $this->pluginDisplayName, $params['x_gateway_reference']), 'woocommerce');
            $this->log(sprintf('callback order has processed yet. %s order status: %s orderId: %s (isAsyncCallback=%s)', $flexi_result_note, $order->get_status(), $order_id, $isAsyncCallback));
            $this->log(json_encode($flexi_result_note));
            return $returnFailed;
        }
        if (empty($order)) {
            $this->log(sprintf('callback:unable to get order information for orderId: %s, (isAsyncCallback)', $order_id));
            return $returnFailed;
        }

        $api_key    = $this->settings[ $this->pluginFileName . '_api_key' ];
        $sig_exists = isset($params['x_signature']);
        $sig_match  = false;
        if ($sig_exists) {
            $expected_sig = $this->flexi_sign($params, $api_key);
            $sig_match    = $expected_sig === $params['x_signature'];
            $mer_match    = $this->settings[ $this->pluginFileName . '_merchant_id' ] === $params['x_account_id'];
            $amount_match = floatval($order->get_total()) === floatval($params['x_amount']);
        }

        if ($sig_exists && $sig_match && $mer_match && $amount_match) {
            if (! empty($params)) {
                $this->log(json_encode($params));
            }
            $flexi_result_note = '';
            $order->update_meta_data('is_async_callback_yes', $params['x_result']);
            switch ($params['x_result']) {
                case 'completed':
                    $flexi_result_note = __('Payment approved using ' . $this->pluginDisplayName . '. Gateway_Reference #' . $params['x_gateway_reference'], 'woocommerce');
                    $order->add_order_note($flexi_result_note);
                    $order->update_meta_data('flexi_purchase_number', $params['x_gateway_reference']);
                    $order->payment_complete($params['x_reference']);
                    if (! is_null($cart) && ! empty($cart)) {
                        $cart->empty_cart();
                    }
                    $msg = 'complete';
                    break;
                case 'failed':
                    $flexi_result_note = __('Payment declined using ' . $this->pluginDisplayName . '. Gateway Reference #' . $params['x_gateway_reference'], 'woocommerce');
                    $order->add_order_note($flexi_result_note);
                    $order->update_status('failed');
                    $msg = 'failed';
                    break;
                case 'error':
                    $flexi_result_note = __('Payment error using ' . $this->pluginDisplayName . '. Gateway Reference #' . $params['x_gateway_reference'], 'woocommerce');
                    $order->add_order_note($flexi_result_note);
                    $order->update_status('failed', 'Error may have occurred with ' . $this->pluginDisplayName . '. Gateway Reference #' . $params['x_gateway_reference']);
                    $msg = 'error';
                    break;
            }
        } else {
            $order->add_order_note(
                __(
                    $this->pluginDisplayName . ' payment response failed signature validation. Please check your Merchant Number and API key or contact ' . $this->pluginDisplayName . ' for assistance.' .
                    '</br></br>isJSON: ' . $isAsyncCallback .
                    '</br>Payload: ' . print_r($params, true) .
                    '</br>Expected Signature: ' . $expected_sig,
                    0,
                    'woocommerce'
                )
            );
            $msg = 'signature error ,merchantID or amount mismatch';
        }
        $this->log($flexi_result_note);
        if ($msg === 'complete') {
            return $returnSuccess;
        }
        return $returnFailed;
    }

    /**
     * @param $original_message
     * @return array|string
     */

    public function thankyou_page_message($original_message)
    {
        if (WC()->session->get('chosen_payment_method') == $this->pluginFileName) {
            if (! empty(WC()->session->get('flexi_result_note'))) {
                return WC()->session->get('flexi_result_note');
            }
        }
        return $original_message;
    }

    public function add_humm_cart_widget()
    {       
        if (!is_cart())
            return;

        $merchantId = $this->getMerchantId();
        
        ?>
        <script>

            (function ($) {
                $( document ).on( 'updated_cart_totals', function(){
                    
                    var cartTotal = $('div.cart_totals tr.order-total span.woocommerce-Price-amount').html();
                    cartTotal = cartTotal.replace(/,/g, ''); 
                    cartTotal = cartTotal.match(/\d+.\d+/g);
                    cartTotal = parseFloat(cartTotal);

                    if ( !isNaN(cartTotal) ){ 
                        var hummCartWidgetHtml = '<script src="https://bpi.humm-au.com/au/content/scripts/price-info_sync.js?productPrice='+cartTotal+'&element=.humm-cart-widget&amp;merchantId=<?php echo $merchantId; ?>&bigThings">';
                        $(".humm-cart-widget").after(hummCartWidgetHtml);                    
                    }
                });
            })( jQuery );
        </script>

        <?php
        
    }

    
    /**
     * @param $isAsyncCallback
     * @return array
     */
    public function getParams($isAsyncCallback)
    {
        $params = array();
        if ($isAsyncCallback) {
            foreach ($_POST as $key => $value) {
                if (preg_match('/x_/', $key)) {
                    $params[ $key ] = wp_kses_data($value);
                }
            }
        } else {
            $scheme = 'http';
            if (! empty($_SERVER['HTTPS'])) {
                $scheme = 'https';
            }
            $full_url = sprintf(
                '%s://%s%s',
                $scheme,
                $_SERVER['HTTP_HOST'],
                $_SERVER['REQUEST_URI']
            );
            $parts    = parse_url(esc_url_raw($full_url), PHP_URL_QUERY);
            parse_str($parts, $params);
        }
        return $params;
    }


    /**
     * This is a filter setup to override the title on the order received page
     * in the case where the payment has failed
     *
     * @param $title
     *
     * @return string
     */
    // public function order_received_title($title)
    // {
    //     global $wp_query;
    //     try {
    //         if (! is_wc_endpoint_url('order-received') || empty($_GET['key'])) {
    //             return $title;
    //         }
    //         $order_id = wc_get_order_id_by_order_key($_GET['key']);
    //         $order    = wc_get_order($order_id);
    //         if ($order->get_data()['payment_method'] !== $this->pluginFileName) {
    //             return $title;
    //         }
    //         $endpoint = WC()->query->get_current_endpoint();
    //         if (! is_null($wp_query) && ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_wc_endpoint_url() && ($endpoint == 'order-received')) {
    //             if (empty($_GET['x_result'])) {
    //                 $title = 'Redirect to humm portal ...';
    //             }
    //             if (! empty($_GET['x_result']) && ($_GET['x_result'] == 'failed')) {
    //                 $title = 'Payment Failed';
    //             }
    //         }
    //     } catch (Exception $e) {
    //         $this->log(sprintf('%s in the order_received_title', $e->getMessage()));
    //     }
    //     return $title;
    // }
    /**
     * @param string $feature
     * @return bool
     */
    public function supports($feature)
    {
        return in_array($feature, array( 'products', 'refunds' )) ? true : false;
    }
    /**
     * Can the order be refunded?
     *
     * @param WC_Order $order
     * @return    bool
     */
    public function can_refund_order($order)
    {
        return ($order->get_status() == 'processing' || $order->get_status() == 'on-hold' || $order->get_status() == 'completed');
    }

    /**
     * @param int    $order_id
     * @param null   $amount
     * @param string $reason
     * @return bool
     */

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $reason = $reason ? $reason : 'not provided';

        $order       = wc_get_order($order_id);
        $purchase_id = get_post_meta($order_id)['flexi_purchase_number'][0];
        if (! $purchase_id) {
            $this->log(__('humm Purchase ID not found. Can not proceed with online refund', 'woocommerce'));
            return false;
        }

        if (isset($this->settings['country'])) {
            $countryCode = $this->settings['country'];
        } else {
            $countryCode = 'AU';
        }

        $environment    = ($this->isTesting() == 'no') ? 'live' : 'sandbox';
        $refund_address = $this->currentConfig->getUrlAddress($countryCode)[ $environment . '_refund_address' ];

        $refund_details              = array(
            'x_merchant_number' => $this->settings[ $this->pluginFileName . '_merchant_id' ],
            'x_purchase_number' => $purchase_id,
            'x_amount'          => $amount,
            'x_reason'          => $reason,
        );
        $refund_signature            = $this->flexi_sign($refund_details, $this->settings[ $this->pluginFileName . '_api_key' ]);
        $refund_details['signature'] = $refund_signature;

        $response = wp_remote_post(
            $refund_address,
            array(
                'method'      => 'POST',
                'data_format' => 'body',
                'body'        => json_encode($refund_details),
                'timeout'     => 3600,
                'user-agent'  => 'Woocommerce ' . WC_VERSION,
                'httpversion' => '1.1',
                'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            )
        );
        if (is_wp_error($response)) {
            $this->log(__('There was a problem connecting to the refund gateway.', 'woocommerce'));
            return false;
        }
        if (empty($response['response'])) {
            $this->log(__('Empty response.', 'woocommerce'));
            return false;
        }

        $refund_result  = $response['response'];
        $refund_message = '';
        if ($response['body']) {
            $refund_message = json_decode($response['body'], true)['Message'];
        }

        if (isset($refund_result['code']) && $refund_result['code'] == '204') {
            $order->add_order_note(sprintf(__('Refunding of $%1$s for order #%2$s through %3$s succeeded', 'woocommerce'), $amount, $order->get_order_number(), $this->pluginDisplayName));

            return true;
        } elseif (isset($refund_result['code']) && $refund_result['code'] == '400') {
            $order->add_order_note(sprintf(__('Refunding of $%1$s for order #%2$s through %3$s failed. Error Code: %4$s', 'woocommerce'), $amount, $order->get_order_number(), $this->pluginDisplayName, $refund_message));
        } elseif (isset($refund_result['code']) && $refund_result['code'] == '401') {
            $order->add_order_note(sprintf(__('Refunding of $%1$s for order #%2$s through %3$s failed Signature Check', 'woocommerce')));
        } else {
            $order->add_order_note(sprintf(__('Refunding of $%1$s for order #%2$s through %3$s failed with unknown error', 'woocommerce')));
        }

        return false;
    }
    

    /**
     * @return int
     */
    public function getMerchantId()
    {
        $merchantId = sprintf('%s_merchant_id', $this->pluginFileName);
        return $this->settings[ $merchantId ];
    }
    /**
     * @return mixed
     */
    public function getWidgetHook()
    {
        $widgetHook = 'hook_widget_selector';
        return $this->settings[ $widgetHook ];
    }

    /**
     * /**
     * Load javascript for WordPress admin
     */
    public function admin_scripts()
    {
        wp_enqueue_style('humm_css', plugins_url('../assets/css/humm.css', __FILE__));
        wp_register_script('humm_admin', plugins_url('../assets/js/admin.js', __FILE__), array( 'jquery' ), '0.4.5');
        wp_enqueue_script('humm_admin');
    }

    /**
     * @return void
     */
    public function front_scripts()
    {
        wp_enqueue_style('humm-front-css', plugins_url('../assets/css/humm-front.css', __FILE__), "1.0");
    }
    /**
     * @return string|null
     */
    public function humm_get_callback_url($order_id)
    {
        $namespace = 'humm/v1';
        $route = sprintf("/callback-endpoint/%s", $order_id);
        return home_url('wp-json/' . $namespace . $route);
    }

    /***
     * @param $order_id
     * @return string|null
     */
    // public function humm_get_ping_url()
    // {
    //     $namespace = 'humm/v1';
    //     $route = "/callback-endpoint/ping";
    //     return home_url('wp-json/' . $namespace . $route);
    // } 
}
