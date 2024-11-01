<?php

/**
 * Plugin Name: ThoughtMetric for WooCommerce
 * Plugin URI: https://thoughtmetric.io
 * Description: ThoughtMetric Marketing Attribution for WooCommerce
 * Text Domain: thoughtmetric
 * Version: 1.26.0
 * Requires at least: 5.2
 * WC requires at least: 2.2
 * WC tested up to: 8.2.0
 * Author: ThoughtMetric
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
//NOTE: when updating version number, also update the 'stable tag' in the readme.txt file

    //===========================================
    // Set up plugin
    //===========================================
    // Exit if accessed directly
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    //declaring compatability with the woocommerce HPOS https://webkul.com/blog/woocommerce-plugin-high-performance-order-storage-compatible/
    add_action('before_woocommerce_init', function(){
      if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
      }
    });

    //load stylesheets
    function thoughtmetric_stylesheet(){
        wp_enqueue_style( 'thoughtmetric-styles', plugins_url( 'css/style.css', __FILE__ ) );
        wp_enqueue_style( 'roboto', 'https://fonts.googleapis.com/css?family=Roboto:400,500' );
    }
    add_action( 'admin_print_styles', 'thoughtmetric_stylesheet' );


    require plugin_dir_path( __FILE__ ) . '/inc/thoughtmetric-admin-settings.php';

    $plugin_name = plugin_basename( __FILE__ );

    //set up settings link
    function thoughtmetric_plugin_settings_link( $links ) {
        $url = esc_url( add_query_arg(
            'page',
            'thoughtmetric',
            get_admin_url() . 'admin.php'
        ));

        $settings_link = "<a href='$url'>" . __( 'Settings', 'thoughtmetric' ) . '</a>';

        array_push( $links, $settings_link );
        return $links;
    }
    add_filter( "plugin_action_links_{$plugin_name}", 'thoughtmetric_plugin_settings_link' );


    //===========================================
    // Inserting Base Snippet to All Pages
    //===========================================
    //hooks into wp_head action to insert snippet
    add_action('wp_head', 'thoughtmetric_insert_snippet');
    add_action('wfocu_header_print_in_head', 'thoughtmetric_insert_snippet'); //hardcoded compatability for FunnelKit upsell pages

    //inserts the snippet into wp_head
    function thoughtmetric_insert_snippet() {
        echo thoughtmetric_get_snippet();
    }

    //get snippet function
    function thoughtmetric_get_snippet() {
        $snippet = '';

        if ( ! empty( get_option( 'thoughtmetric_snippet' ) ) ) {
            $snippet = get_option( 'thoughtmetric_snippet' );
        }

        return $snippet;
    }


    //===========================================
    // Inserting Event Tracking
    //===========================================
    //hook into wp_head to add event tracking code
    add_action('wp_head', 'thoughtmetric_insert_event_code');
    add_action('wfocu_header_print_in_head', 'thoughtmetric_insert_event_code'); //hardcoded compatability for FunnelKit upsell pages
    //hook into the WooCommerce order processing event
    add_action( 'woocommerce_checkout_order_processed', 'thoughtmetric_save_order_to_session', 1);


    function thoughtmetric_save_order_to_session( $order_id = '' ) {
      try {
        //if we don't have an order id, return
        if ( empty( $order_id ) ) {
          return;
        }

        //get the order object
        $order_data = thoughtmetric_get_event_object($order_id);

        //save order data to session
        if(WC()->session){
          WC()->session->set( 'thoughtmetric_order', $order_data );
        } 
      } 
      catch (Exception $e) {
      }
    }


    function thoughtmetric_insert_event_code() {
      try {
        global $wp;

        //see if we have an order_id set in the query vars, this usually indicates that we are on a woocommerce thank you page
        $order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
        //if we have an order from the query vars, insert the event tracking code
        if ( $order_id > 0 ){
          //build the order data object
          $order_data = thoughtmetric_get_event_object($order_id);
          if ( ! empty($order_data) ) {
            //echo the actual script code
            echo thoughtmetric_get_event_code($order_data);
          }
          return;
        }

          //if not, check if we have an order in the session object
        if(WC()->session){
          $session_order_data = WC()->session->get( 'thoughtmetric_order' );
          if ( ! empty( $session_order_data ) ) {
            //echo the actual script code
            echo thoughtmetric_get_event_code($session_order_data);
            //delete the session data after
            WC()->session->set( 'thoughtmetric_order', null );
          }
        }
      } 
      catch (Exception $e) {
      }
    }


    //builds the order data from an order_id
    function thoughtmetric_get_event_object($order_id = '') {
      try {

        //if we don't have an order id, return
        if ( empty( $order_id ) ) {
          return;
        }

        //get order
        $order = wc_get_order( $order_id );

        // can't track an order that doesn't exist
        if ( ! $order || ! $order instanceof \WC_Order ) {
          return;
        }

        //==================
        // Build order info
        //==================
        $items    = $order->get_items();
        $products = array();

        foreach( $items as $item ) {
          $terms               = get_the_terms ( $item->get_product_id(), 'product_cat' );
          $quantity            = (int)$item->get_quantity();
          $price               = $quantity > 0 ? (float)$item->get_subtotal() / $quantity : 0;

          $product = array(
            'product_name'          => $item->get_name(),
            'unit_price'         => $price,
            'quantity'      => $quantity,
          );

          $products[] = $product;
        }
        $item_quantity = count($products);

        //build discount code array
        $coupon_codes = $order->get_coupon_codes();
        $coupon_codes = array_map('strval', $coupon_codes);


        $data = array(
          'transaction_id'       => (string)$order_id,
          'status' => (string)$order->get_status(),
          'total_price'    => (float)$order->get_total(),
          'subtotal_price' => (float)$order->get_subtotal(),
          'currency'      => get_option('woocommerce_currency'), // We use option instead of get_woocommerce_currency because it will not be overridden by currency switching plugins.
          'orderCurrency'      => $order->get_currency(),
          'total_tax'      => (float)$order->get_total_tax(),
          'total_shipping' => (float)$order->calculate_shipping(),
          'total_discounts' => (float)$order->get_total_discount(),
          'discount_codes' => $coupon_codes,
          'item_quantity' => $item_quantity,
          'items'      => $products,
          'platform' => 'woocommerce',
        );


        //====================
        // Build customer info
        //====================
        $user_email = $order->get_billing_email();
        $first_name= $order->get_billing_first_name();
        $last_name= $order->get_billing_last_name();
        $address1= $order->get_billing_address_1();
        $address2= $order->get_billing_address_2();
        $city= $order->get_billing_city();
        $state= $order->get_billing_state();
        $country= $order->get_billing_country();
        $zip= $order->get_billing_postcode();

        $customerData = array (
          'email' => $user_email,
          'first_name' => $first_name,
          'last_name' => $last_name,
          'address1' => $address1,
          'address2' => $address2,
          'city' => $city,
          'state' => $state,
          'country' => $country,
          'zip' => $zip,
          'platform' => 'woocommerce',
        );

        $customer_id = $order->get_customer_id();
        if ( $customer_id > 0 ) {
          $customerData['external_id'] = (string)$customer_id;
          $customer = new WC_Customer( $customer_id );
          $created_at= $customer->get_date_created();
          $customerData['created_at'] = $created_at->date(DateTime::ATOM);
        }

        $data_object = array (
          'order' => $data,
          'customer' => $customerData,
          'order_date_created' => $order->get_date_created()
        );

        return $data_object;

      } 
      catch (Exception $e) {
      }
    }

    //builds the event tracking code
    function thoughtmetric_get_event_code($data_object) {

      //if we don't have a data object, just return
			if ( empty($data_object) || empty($data_object['order']) || empty($data_object['customer']) ) {
				return;
			}

      //check to see if the order is older than 24 hours, in which case this page view is someone checking on the status of their old order and not a net new order. if this is the case, dont insert the order code, but do insert the identify code
      if (!is_null($data_object['order_date_created']) && $data_object['order_date_created'] < (new DateTime("now"))->modify("-1 day")) {
        return "
          <script>
              thoughtmetric('identify', '". esc_js($data_object['customer']['email'])."', ". json_encode($data_object['customer']).");
          </script>
          ";
      }

      return "
      <script>
          thoughtmetric('event', 'order', " . json_encode($data_object['order']). " );
          thoughtmetric('identify', '". esc_js($data_object['customer']['email'])."', ". json_encode($data_object['customer']).");
      </script>
      ";
    }




    //===========================================
    // Inserting Micro Event Tracking
    //===========================================
    //hook into wp_head to add event tracking code
    add_action('wp_head', 'thoughtmetric_insert_add_to_cart');
    add_action('woocommerce_before_single_product', 'thoughtmetric_insert_view_content');

    //function that inserts the event tracking code
    function thoughtmetric_insert_add_to_cart() {
      //if the add_to_cart action already happened trigger the add to cart event
      if(did_action('woocommerce_add_to_cart') > 0) {
      echo "
      <script>
         thoughtmetric('event', 'addToCart',{\"platform\":\"woocommerce\"});
      </script>
      ";

      }

      // register ajax listener to listen for future add to cart events
      echo "
      <script>
      jQuery(document).ready(function($){
          $( 'body' ).on( 'added_to_cart', function( e, fragments, cart_hash, this_button ) {
              thoughtmetric('event', 'addToCart',{\"platform\":\"woocommerce\"});
          });
      });
      </script>
      ";
    }

    //function that inserts the event tracking code
    function thoughtmetric_insert_view_content() {
      echo "
      <script>
          thoughtmetric('event', 'viewContent',{\"platform\":\"woocommerce\"});
      </script>
      ";
    }


