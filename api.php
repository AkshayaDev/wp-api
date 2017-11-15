<?php
error_reporting(0);
/**
 * Does this user exist?
 *
 * @param  int|string|WP_User $user_id User ID or object.
 * @return bool                        Whether the user exists.
 */
function llyod_does_user_exist($user_id = '') {
	if ( $user_id instanceof WP_User ) {
		$user_id = $user_id->ID;
	}
	return (bool) get_user_by( 'id', $user_id );
}

/**
 * Removes a specific product from the cart
 * @param $product_id Product ID to be removed from the cart
 */
function remove_product_from_cart( $product_id ) {
     $prod_unique_id = WC()->cart->generate_cart_id( $product_id );
    // Remove it from the cart by un-setting it
    unset( WC()->cart->cart_contents[$prod_unique_id] );
}

add_action( 'rest_api_init', 'add_to_cart_custom_api');

function add_to_cart_custom_api() {
    register_rest_route( 'addcart/v1', '/users/id=(?P<id>\d+)/productid=(?P<productid>\d+)/quantity=(?P<quantity>\d+)', array(
        'methods' => 'GET',
        'callback' => 'add_product_to_cart',
    ));
}

function add_product_to_cart($data) {
    $apiuserid = $data['id'];
    $mainproduct = wc_get_product($data['productid']);

	if ( false === $mainproduct ) {
	    $return = array(
            'error' => 0,
            'message' => 'product not exists'
            );
        return new WP_REST_Response( $return, 200 );
	}
    
    if(isset($data['quantity']) && $data['quantity'] <=0) {
        $return = array(
            'error' => 0,
            'message' => 'please provide valid quantity'
            );
        return new WP_REST_Response( $return, 200 );
    }
    
    if(llyod_does_user_exist($data['id'])) {
        if(is_user_logged_in()) {
            // Get current user ID
            $curruser_id = get_current_user_id();
            if($curruser_id==$data['id']) {
                // Clear current cart
                //WC()->cart->empty_cart();
                // simply add to cart data
                WC()->cart->add_to_cart($data['productid'], $data['quantity'] );
                $return = array(
                    'error' => 1,
                    'message' => 'product successfully added to cart',
                    'cart' => WC()->cart->get_cart()
                );
                update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
                return new WP_REST_Response( $return, 200 );
            }else{
                // force logout then login
                wp_logout();
                $apiuser = get_user_by( 'id', $data['id']);
                    if($apiuser) {
                        wp_set_current_user($data['id'], $apiuser->user_login );
                        wp_set_auth_cookie($data['id']);
                        do_action( 'wp_login', $apiuser->user_login );
                        // Clear current cart
                        //WC()->cart->empty_cart();                        
                        WC()->cart->add_to_cart($data['productid'], $data['quantity'] );
                        $return = array(
                            'error' => 1,
                            'message' => 'product successfully added to cart',
                            'cart' => WC()->cart->get_cart()
                        );
                        update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
                        return new WP_REST_Response( $return, 200 );
                    }
            }
        }else{
            // force login user
            $napiuser = get_user_by( 'id', $data['id']); 
                    if($napiuser) {
                        wp_set_current_user($data['id'], $napiuser->user_login );
                        wp_set_auth_cookie($data['id']);
                        do_action( 'wp_login', $napiuser->user_login );
                        // Clear current cart
                        //WC()->cart->empty_cart();
                        WC()->cart->add_to_cart($data['productid'], $data['quantity'] );
                        $return = array(
                            'error' => 1,
                            'message' => 'product successfully added to cart',
                            'cart' => WC()->cart->get_cart()
                        );
                        update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
                        return new WP_REST_Response( $return, 200 );
                    }
        }
    }else{
        $return = array(
            'error' => 0,
            'message' => 'user not found'
        );
        return new WP_REST_Response( $return, 200 );
    }
}

add_action( 'rest_api_init', 'update_cart_custom_api');

function update_cart_custom_api() {
    register_rest_route( 'update/v1', '/users/id=(?P<id>\d+)/productid=(?P<productid>\d+)/quantity=(?P<quantity>\d+)', array(
        'methods' => 'GET',
        'callback' => 'update_product_to_cart',
    ));
}

function update_product_to_cart($data) {
    $apiuserid = $data['id'];
    $mainproduct = wc_get_product($data['productid']);
	if ( false === $mainproduct ) {
	    $return = array(
            'error' => 0,
            'message' => 'product not exists'
            );
        return new WP_REST_Response( $return, 200 );
	}
    
    if(isset($data['quantity']) && $data['quantity'] <=0) {
        $return = array(
            'error' => 0,
            'message' => 'please provide valid quantity'
            );
        return new WP_REST_Response( $return, 200 );
    }
    
    if(llyod_does_user_exist($data['id'])) {
        if(is_user_logged_in()) {
            // Get current user ID
            $curruser_id = get_current_user_id();
            if($curruser_id==$data['id']) {
                // Clear current cart
                //WC()->cart->empty_cart();
                remove_product_from_cart( $data['productid'] );
                // simply add to cart data
                WC()->cart->add_to_cart($data['productid'], $data['quantity'] );
                $return = array(
                    'error' => 1,
                    'message' => 'product successfully updated to cart',
                    'cart' => WC()->cart->get_cart()
                );
                update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
                return new WP_REST_Response( $return, 200 );
            }else{
                // force logout then login
                wp_logout();
                $apiuser = get_user_by( 'id', $data['id']);
                    if($apiuser) {
                        wp_set_current_user($data['id'], $apiuser->user_login );
                        wp_set_auth_cookie($data['id']);
                        do_action( 'wp_login', $apiuser->user_login );
                        // Clear current cart
                        //WC()->cart->empty_cart();
                        remove_product_from_cart( $data['productid'] );
                        WC()->cart->add_to_cart($data['productid'], $data['quantity'] );
                        $return = array(
                            'error' => 1,
                            'message' => 'product successfully updated to cart',
                            'cart' => WC()->cart->get_cart()
                        );
                        update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
                        return new WP_REST_Response( $return, 200 );
                    }
            }
        }else{
            // force login user
            $napiuser = get_user_by( 'id', $data['id']); 
                    if($napiuser) {
                        wp_set_current_user($data['id'], $napiuser->user_login );
                        wp_set_auth_cookie($data['id']);
                        do_action( 'wp_login', $napiuser->user_login );
                        // Clear current cart
                        //WC()->cart->empty_cart();
                        remove_product_from_cart( $data['productid'] );
                        WC()->cart->add_to_cart($data['productid'], $data['quantity'] );
                        $return = array(
                            'error' => 1,
                            'message' => 'product successfully updated to cart',
                            'cart' => WC()->cart->get_cart()
                        );
                        update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
                        return new WP_REST_Response( $return, 200 );
                    }
        }
    }else{
        $return = array(
            'error' => 0,
            'message' => 'user not found'
        );
        return new WP_REST_Response( $return, 200 );
    }
}

add_action( 'rest_api_init', 'create_order_custom_api');

function create_order_custom_api() {
    register_rest_route( 'orders/v1', '/createorder/', array(
        'methods' => 'POST',
        'callback' => 'create_orders_api',
    ));
}

function create_orders_api($data) {
    global $woocommerce;
    $apiuserid = $data['user_id'];
	// Now we create the order
	$order = wc_create_order();
	
	if(!empty($data['products'])) {
	    foreach($data['products'] as $productid => $product_qunatity) {
	        $order->add_product( get_product($productid), $product_qunatity);
	    }
	}
	
	// Set addresses
	$order->set_address($data['address'], 'billing' );
	$order->set_address($data['address'], 'shipping' );
	
	// Set payment gateway
	$payment_gateways = WC()->payment_gateways->payment_gateways();
	$order->set_payment_method( $payment_gateways['bacs'] );
	
	// Calculate totals
	$order->calculate_totals();
	$order->update_status( 'Completed', 'Order created dynamically - ', TRUE);
	$order->set_created_via('rest-api');
	$order->set_customer_id($data['user_id']);
	$order->set_currency( get_woocommerce_currency() );
	WC()->cart->empty_cart();
	update_option('usercart_'.$apiuserid, '' );
	// Save the order.
	return $order_id = $order->save();
}

add_action( 'rest_api_init', 'delete_product_custom_api');

function delete_product_custom_api() {
    register_rest_route( 'delete/v1', '/users/id=(?P<id>\d+)/productid=(?P<productid>\d+)', array(
        'methods' => 'GET',
        'callback' => 'delete_product_from_lloyd_cart',
    ));
}

function delete_product_from_lloyd_cart($data) {
    $apiuserid = $data['id'];
    $mainproduct = wc_get_product($data['productid']);
	if ( false === $mainproduct ) {
	    $return = array(
            'error' => 0,
            'message' => 'product not exists'
            );
        return new WP_REST_Response( $return, 200 );
	}
    
    if(WC()->cart->is_empty()) {
       $return = array(
            'error' => 0,
            'message' => 'cart is empty'
        );
        return new WP_REST_Response( $return, 200 );
    }else{
        $product_exists_in_cart = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
            $cart_item_id = $cart_item['data']->id;
            
            if($cart_item_id==$data['productid']) {
                $product_exists_in_cart = true;
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }
        if($product_exists_in_cart) {
            $return = array(
            'error' => 1,
            'message' => 'product removed from cart'
            );
        update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
        return new WP_REST_Response( $return, 200 );
        }
    }
}

add_action( 'rest_api_init', 'get_stored_cart_api');

function get_stored_cart_api() {
    register_rest_route( 'getcart/v1', '/user/id=(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_stored_lloyd_cart',
    ));
}

function get_stored_lloyd_cart($data) {
    $apiuserid = $data['id'];
    $option_name = 'usercart_'.$apiuserid;
    if(llyod_does_user_exist($data['id'])) {
        if (get_option( $option_name ) !== false) {
            $return = array(
            'error' => 1,
            'message' => 'cart data found',
            'cart' => get_option($option_name)
            );
            return new WP_REST_Response( $return, 200 );
        } else {
            $return = array(
            'error' => 0,
            'message' => 'cart data not exists'
            );
            return new WP_REST_Response( $return, 200 );
        }
    }else{
        $return = array(
            'error' => 0,
            'message' => 'user not found'
        );
        return new WP_REST_Response( $return, 200 );
    }
}
