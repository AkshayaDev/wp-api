<?php
error_reporting(0);
/**
 * Does this user exist?
 *
 * @param  int|string|WP_User $user_id User ID or object.
 * @return bool Whether the user exists.
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

/**
 * product exist in db?
 * @return bool Whether the product exists.
 */
function lloyd_product_db_exists($userid,$productid,$remove) {
    $option_name = 'usercart_'.$userid;
    $productexists = false;
    if (get_option( $option_name ) !== false) {
            $cartdata = get_option( $option_name );
            foreach($cartdata as $key => $cdata) {
                if($cdata['product_id']==$productid) {
                    $productexists = true;                    
                    if($remove) {
                        $rkey = $cdata['key'];
                        unset($cartdata[$rkey]);
                    }
                }
            }
            if($remove) {
             update_option('usercart_'.$userid, $cartdata);   
            }
            return $productexists;
        } else {
            return false;
        }
}

function lloyd_product_db_update($userid,$productid,$quantity) {
    $option_name = 'usercart_'.$userid;
    if (get_option( $option_name ) !== false) {
            $cartdata = get_option( $option_name );
            foreach($cartdata as $key => $cdata) {
                if($cdata['product_id']==$productid) {
                    
                }
            }
            update_option('usercart_'.$userid, $cartdata);
            return get_option( $option_name );
        } else {
            return false;
        }
}

/**
 * Format db cart data
 * @return array cart data.
 */
function lloyd_format_cart_data($option_name) {
$returndata = array();
if (get_option( $option_name ) !== false) {
    $cartdata = get_option($option_name);
    $count = 0;
    foreach($cartdata as $key => $cartp) {
        $post_thumbnail_id = get_post_thumbnail_id($cartp['product_id']);
        $full_size_image   = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
        $returndata[$count]['product_id'] = $cartp['product_id'];
        $returndata[$count]['product_Title'] = get_the_title($cartp['product_id']);
        $returndata[$count]['product_Img_url'] = $full_size_image[0];
        $returndata[$count]['product_sale_price'] = get_post_meta($cartp['product_id'], '_sale_price', true );
        $returndata[$count]['product_regular_price'] = get_post_meta($cartp['product_id'], '_regular_price', true );
        $returndata[$count]['product_wish_status'] = false;
        $returndata[$count]['variation_id'] = $cartp['variation_id'];
        $returndata[$count]['variation'] = $cartp['variation'];
        $returndata[$count]['quantity'] = (int)$cartp['quantity'];
        $returndata[$count]['line_tax_data'] = $cartp['line_tax_data'];
        $returndata[$count]['line_subtotal'] = $cartp['line_subtotal'];
        $returndata[$count]['line_subtotal_tax'] = $cartp['line_subtotal_tax'];
        $returndata[$count]['line_total'] = $cartp['line_total'];
        $returndata[$count]['line_tax'] = $cartp['line_tax'];
        $returndata[$count]['data'] = $cartp['data'];
        $count++;
    }
}
return $returndata;
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
                update_option('usercart_'.$apiuserid, '' );
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
                        update_option('usercart_'.$apiuserid, '' );
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
                do_action('wp_login', $napiuser->user_login);
                // Clear current cart
                //WC()->cart->empty_cart();
                WC()->cart->add_to_cart($data['productid'], $data['quantity']);
                $return = array(
                    'error' => 1,
                    'message' => 'product successfully added',
                    'cart' => WC()->cart->get_cart()
                );
                //update_option('usercart_'.$apiuserid, '' );
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
                update_option('usercart_'.$apiuserid, '' );
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
                        update_option('usercart_'.$apiuserid, '' );
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
                update_option('usercart_'.$apiuserid, '' );
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
    
    if(!llyod_does_user_exist($data['user_id'])) {
	    $return = array(
            'error' => 0,
            'message' => 'user not found'
        );
        return new WP_REST_Response( $return, 200 );
	}
	// Now we create the order
	$order = wc_create_order();
	
	if(empty($data['products'])) {
	    $return = array(
            'error' => 0,
            'message' => 'Product not exists',
        );
        return new WP_REST_Response( $return, 200 );
	}else{
        foreach($data['products'] as $productid => $product_qunatity) {
	        $order->add_product( get_product($productid), $product_qunatity);
	    }
	}
	
	if(empty($data['address'])) {
	    $return = array(
            'error' => 0,
            'message' => 'Address is empty',
        );
        return new WP_REST_Response( $return, 200 );
	}else{
	    // Set addresses
    	$order->set_address($data['address'], 'billing' );
	    $order->set_address($data['address'], 'shipping' );   
	}
	
	// Set payment gateway
	$payment_gateways = WC()->payment_gateways->payment_gateways();
	$order->set_payment_method( $payment_gateways['bacs'] );
	
	// Calculate totals
	$order->calculate_totals();
	$order->update_status( 'Completed', 'Order created dynamically - ', TRUE);
	$order->set_created_via('rest-api');
	$order->set_customer_id($data['user_id']);
	$order->set_currency(get_woocommerce_currency());
	// Save the order.
	$order_id = $order->save();
	if($order_id) {
	    WC()->cart->empty_cart();
	    update_option('usercart_'.$apiuserid, '' );
        $return = array(
            'error' => 1,
            'message' => 'Order Placed Successfully',
            'orderid' => $order_id
        );
        return new WP_REST_Response( $return, 200 );
	}else{
	   $return = array(
            'error' => 0,
            'message' => 'Something Went Wrong',
        );
        return new WP_REST_Response( $return, 200 );
	}
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
	
	if(!llyod_does_user_exist($data['id'])) {
	    $return = array(
            'error' => 0,
            'message' => 'user not found'
        );
        return new WP_REST_Response( $return, 200 );
	}
	
	// check product exists in database
	if(lloyd_product_db_exists($apiuserid,$data['productid'])) {
	    // remove product from db
	   lloyd_product_db_exists($apiuserid,$data['productid'],true);
	   $return = array(
            'error' => 1,
            'message' => 'product removed from cart'
            );
       return new WP_REST_Response( $return, 200 );     
	}
    
    if(!WC()->cart->is_empty()) {
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
        update_option('usercart_'.$apiuserid, '' );
        update_option('usercart_'.$apiuserid, WC()->cart->get_cart() );
        return new WP_REST_Response( $return, 200 );
        }
    }else{
        $return = array(
            'error' => 0,
            'message' => 'Something went wrong'
            );
        return new WP_REST_Response( $return, 200 );
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
        if (get_option($option_name) !== false) {
            
            if(empty(get_option($option_name))) {
                $return = array(
                    'error' => 0,
                    'message' => 'cart data not exists'
                );
                return new WP_REST_Response( $return, 200 );    
            }
            
            $return = array(
            'error' => 1,
            'message' => 'cart data found',
            'cart' => lloyd_format_cart_data($option_name)
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

add_action( 'rest_api_init', 'create_order_custom_api_v2');

function create_order_custom_api_v2() {
    register_rest_route( 'orders/v2', '/createorder/', array(
        'methods' => 'POST',
        'callback' => 'create_orders_api_v2',
    ));
}

function create_orders_api_v2($data) {
    $requestbody = $data->get_body();
    $requestbody = json_decode($requestbody,true);
    $user_id = $requestbody['user_id'];
    $products = $requestbody['products'];
    $address = $requestbody['address'];
    $payment_details = $requestbody['payment_details'];
    
    // check for user
    if(empty($user_id) || !llyod_does_user_exist($user_id)) {
	    $return = array(
            'error' => 0,
            'message' => 'user not found'
        );
        return new WP_REST_Response( $return, 200 );
	}
	
	// check for products
	if(empty($products)) {
	    $return = array(
            'error' => 0,
            'message' => 'Product not exists',
        );
        return new WP_REST_Response( $return, 200 );
	}
	
	//check for address
	if(empty($address)) {
	    $return = array(
            'error' => 0,
            'message' => 'address is empty',
        );
        return new WP_REST_Response( $return, 200 );
	}
	
	// payment details
	if(empty($payment_details)) {
	    $return = array(
            'error' => 0,
            'message' => 'payment details is empty',
        );
        return new WP_REST_Response( $return, 200 );
	}	
	
	// Now we create the order
	$order = wc_create_order();
	
	// Set addresses
    $order->set_address($address, 'billing' );
	$order->set_address($address, 'shipping' );
	
	if(!empty($products) && is_array($products)) {
	    foreach($products as $productid => $product_qunatity) {
	        $order->add_product( get_product($productid), $product_qunatity);
	    }
	}
	
	// Set payment gateway
	$payment_gateways = WC()->payment_gateways->payment_gateways();
	$order->set_payment_method( $payment_gateways['paypal'] );
	
	// Calculate totals
	$order->calculate_totals();
	$order->update_status('Completed', 'Order created dynamically - ', TRUE);
	$order->set_created_via('rest-api');
	$order->set_customer_id($user_id);
	$order->set_currency(get_woocommerce_currency());
	$order->set_date_created( current_time( 'timestamp', true ) );
	$order->update_status('completed');
	$order->add_order_note( sprintf( __( 'Payment captured, Transaction ID: %1$s', 'woocommerce' ), $payment_details['id'] ) );
	// Save the order.
	$order_id = $order->save();
	if($order_id) {
	    WC()->cart->empty_cart();
	    update_option('usercart_'.$user_id, '' );
	    // save payment details in order meta
	    update_post_meta($order_id,'_transaction_id',$payment_details['id']);
	    update_post_meta($order_id,'intent',$payment_details['intent']);
	    update_post_meta($order_id,'state',$payment_details['state']);
	    update_post_meta($order_id,'create_time',$payment_details['create_time']);
        $return = array(
            'error' => 1,
            'message' => 'Order Placed Successfully',
            'orderid' => $order_id
        );
        return new WP_REST_Response( $return, 200 );
	}else{
	   $return = array(
            'error' => 0,
            'message' => 'Something Went Wrong',
        );
        return new WP_REST_Response( $return, 200 );
	}
}
