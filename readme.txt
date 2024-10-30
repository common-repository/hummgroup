=== humm for WooCommerce===

Contributors: hummau
Donate link: https://shophumm.com.au
Tags:  humm, humm for WooCommerce, humm config, humm_gateway, hummgroup_gateway
Requires at least: WP 4.0
Tested up to: 6.6
Stable tag: 3.1.4
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html


== Description ==

Australia – **humm**

humm offers a seamless and convenient Buy Now and Pay Later service in easy interest-free instalments. Customers can make purchases of up to $30,000, depending on where they shop. All purchases with humm are interest free forever.

humm is integrated at in-store, online and point of sale.

With humm, you can pre-approve up to $5,000 or you can apply in-store for up to $30000 depending on the retailer. Depending on the merchant’s terms available, you may be able to repay in 3, 6, 12, 24…. all the way up to 72 months.

Most important questions are answered in the [FAQ](https://www.shophumm.com/au/faqs/)


== Installation ==

= Pre-requisites =
A working WooCommerce plugin (version >=3.0) installation

= Other assumptions =
You have received a valid Merchant Number and API key from the [Humm](https://docs.shophumm.com.au/request_api.html)

= Automatic Installation =
*   Login to your WordPress Admin area
*   Go to "Plugins > Add New" from the left hand menu
*   In the search box type "humm for WooCommerce"
*   From the search result you will see "humm for WooCommerce Plugin" click on "Install Now" to install the plugin
*   A popup window will ask you to confirm your wish to install the Plugin.
*   If successful, click "Activate Plugin" to activate it, or "Return to Plugin Installer" for further actions.

= Manual Installation =
*.  Download the plugin zip file
*.  Login to your WordPress Admin. Click on "Plugins > Add New" from the left hand menu.
*.  Click on the "Upload" option, then click "Choose File"to select the zip file from your computer. Once selected, press "OK" and press the "Install Now" button.
*.  Activate the plugin.
*.  Open the Settings page for WooCommerce and click the "Payment" tab.
*.  Click on the sub tab for "Humm".
*.  Configure your "Humm" settings. See below for details.

This Plugin is available in [Australia](https://shophumm.com.au).


Please refer to the [User/Installation Guide](https://docs.shophumm.com.au/ecommerce/woocommerce_au.html)


== Frequently Asked Questions ==

 What do you need to know before you start ?

 *. Locate your [humm Credentials](https://docs.shophumm.com.au/ecommerce/guide.html)

 *. Most important questions are answered in the [FAQ](https://docs.shophumm.com.au/ecommerce/woocommerce_au.html#faq).

 *. There is also the option to create a support integration assistance in the official [Website](https://docs.shophumm.com.au/integration_assistance.html).


== Screenshots ==

1. config admin console.
2. cart widget.
3. checkout widget.
4. variable product widget.
5. search plugin.
6. install plugin.


== Upgrade Notice ==

**2.4+ version supports PHP8.1


== Changelog ==

=3.1.4 =

*Release Date: Thursday 13 June 2024

* Fixed the Dynamic Pricing update on Cart page
* Fixed the placement of the humm messaging on the checkout to show it above the payment methods sections

=3.1.3 =

*Release Date: Wednesday 08 May 2024

* Added the visual note on checkout when the Test mode is enabled

=3.1.2 =

*Release Date: Wednesday 06 Feb 2024

* Fixed the error on checkout caused due to the overlapping product title
* Updated the humm branding on the settings and checkout
* Reordered and grouped the fields on the humm settings in the order of their relevance
* Improved and reworded the user facing copy, labels and messages to make them meaningful
* Updated the plugin with Big Things branding 
* Enforced $80 minimum purchase limit on humm orders at checkout
* Removed support for Little Things
* Fixed the widget configuration to show the humm banner when enabled
* Deferred the widgets loading to avoid slowdown of the website

=3.1.1 =

*Release Date: Wednesday 10 May 2023

*Remove buyer data from the API request

*WordPress Version Support: Update the WordPress version support to the latest version (6.2) to ensure compatibility and improve overall performance.
*Add Restful CallBack API: We have completely rebuilt the plugin's Restful API for asynchronous server-to-server calls,
 providing faster response times and a more efficient workflow.
*API Retry  Functions: We have added new functions to the API that allow for automatic retries on failed responses
*API Ping Service: We have implemented an API ping service to help monitor and maintain server uptime and ensure quick response times.
*API Rate Limit: rate limiting to prevent overload.
*IP validation: validate callback request and its ip address
*Legacy API Callback: We have kept the legacy API callback function (V1) to ensure backwards compatibility with existing integrations.
*API Key Security: We have improved the security of the plugin by hiding the API key input field, reducing the risk of unauthorised access.
*Admin Console Interface: We have completely redesigned the admin console interface, providing a more intuitive and user-friendly experience.
*Transaction Logs: We have rebuilt the transaction logs, providing a more detailed and accurate record of all API interactions.
*Other Rebuilds and Improvements: We have implemented various other improvements and rebuilds to the plugin,
 providing a more stable and efficient platform for eCommerce businesses.
 *add new log file

=3.0.2 =

*Release Date: Wednesday 12 Oct 2022

*Marketing, Logo and ScreenShots

=3.0.1 =

*Release Date: Wednesday 12 Oct 2022

*Marketing, Logo and ScreenShots

=3.0.0 =

*Release Date: Monday 19 Sep 2022

*Overall improvements and QA for wordpress plugin directory list
*Tested and verified support for WordPress 6.0 and WooCommerce 6.6.1.


=2.4.0 =

*overall improvements on the plugin for all WooCommerce websites

*Security check and improvements

*variable product widgets support

*past issues update

*develop new JS library in different widget supports

*improve UI

*Support PHP 8.1+ and PHP5.6+

=2.3.0 =

*Product widgets show in the hooks' locations

*woocommerce_single_product_summary,woocommerce_after_add_to_cart_form, woocommerce_after_single_product_summary and woocommerce_before_add_to_cart_button etc

*Input dynamic hooks in the admin console field "Product page hook", then click button 'save changes' on the bottom


*add_action($wigHook, array($this, 'add_price_widget'), 11);  $wigHook is used to store WooCommerce hook and please adjust priority '11' to place widget in the proper order if the group payment providers widgets stay together


*Support page builder plugins ex elementor page builders ,and flexible dynamic Hook

*The groups of page builders plugins ex elementor build one widget in one product page by inserting a shortcode in the page ex:[woocommerce_humm_elementor]


*Please enable field:humm Widget ShortCode in Builder Page on the admin console


*one typical use case is to allow all product widgets showing together with specific product widget built by elementor plugin


*Support widget automation between LT and BT purchase

*Add support error information



= 2.2.0 =

*add BT widget and Widget automation for different merchants

*update merchant type for merchants to show LT and BT separately


=2.1.1=

*In addition to 2.1.0, remove redundancy PHP code: ex remove legend redirect PHP code for better safe and fast performance


=2.1.0=

*Rebuild Checkout and remove call outside redirect PHP code


=2.0.0=

*rebuild plugins

