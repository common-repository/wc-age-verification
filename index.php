<?php
/*
Plugin Name:       Wc Age Verification
Plugin URI:        https://wordpress.org/plugins/wc-age-verification/
Description:       This plugin allows you to set age restrictions on orders
Version:           1.0.2
Author:            Ilario Tresoldi
Author URI:        http://www.webcreates.eu
Textdomain:        cav
Domain Path:       /language
License:           GPL-2.0+
License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
*/

/**
 * Wc Age Verification
 * Copyright (C) 2017 Ilario Tresoldi. All rights reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Contact the author at ilario.tresoldi@gmail.com
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	add_action( 'wp_enqueue_scripts', 'wcav_custom_enqueue_datepicker' );

	$domain = 'cav';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	$path   = plugins_url('wc-age-verification/language/'.$domain.'-'.$locale.'.mo');
	$loaded = load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/language/' );
	if ( !$loaded )
	{
		$path   = plugins_url('wc-age-verification/language/'.$domain.'-en_US.mo');
		$loaded = load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/language/' );
	}
 
	function wcav_custom_enqueue_datepicker() {
		wp_enqueue_script('jquery-ui-datepicker');
		wp_localize_jquery_ui_datepicker();
		wp_register_style('datepicker_css', plugins_url('css/jquery-ui.css',__FILE__ ));
		wp_enqueue_style('datepicker_css');
	}

	function wcav_my_custom_checkout_field( $checkout ) {
		$checkout = WC()->checkout();
	    echo '<div id="my_custom_checkout_field"><h3>' . __('Age Verification', 'cav') . '</h3>';
	    woocommerce_form_field( 'my_field_name', array(
	        'type'          => 'text',
		 	'required'      => true,
		 	'readonly'      => 'readonly',
	        'class'         => array('my-field-class form-row-wide'),
	        'label'         => _e('Your Birthdate (mm-dd-yyyy)', 'cav'),
	        'placeholder'   => _e(''),
			'id'=>__('MyDate')
        ), $checkout->get_value( 'my_field_name' ));
    	echo '</div>';
    {
?>
<script>
jQuery(document).ready(function() {
    jQuery('#MyDate').datepicker({
        dateFormat : 'mm-dd-yy',
		changeMonth: true,
      	changeYear: true,
	  	yearRange: '1900:2015',
    });
});
</script>
<?php
	}
}
add_action( 'woocommerce_checkout_before_customer_details' ,'wcav_my_custom_checkout_field' );

//validating
add_action('woocommerce_checkout_process', 'wcav_my_custom_checkout_field_process');
 
function wcav_my_custom_checkout_field_process() {
	$age = $_POST['my_field_name'];
	$y = explode("-", $age);
	$month=$y['0']; // user' age
	$day=$y['1'];
	$year=$y['2'];
	// get diffrence with cureent date
	$year_diff  = date("Y") - $year;
	$month_diff = date("m") - $month;
	$day_diff   = date("d") - $day;
	//logic
	$order_number_start = get_option( 'woocommerce_order_number_start', 1 );
	if ($year_diff == $order_number_start ) {
		if ( $month_diff == 0) {
			if($day_diff > 0) {
				$year_diff++; 
			}
		}
	}
	$final_age=$year_diff;
    if ( ! $_POST['my_field_name'] || $final_age < $order_number_start ||  $final_age == $order_number_start) {
		$order_number_start = get_option( 'woocommerce_order_number_start', 1 );
        wc_add_notice( __( 'Your age must be above ', 'cav' ).$order_number_start.' '.__( 'to complete this order.', 'cav' ), 'error' );
	}}
	// Saving data
	add_action( 'woocommerce_checkout_update_order_meta', 'wcav_my_custom_checkout_field_update_order_meta' );
	function wcav_my_custom_checkout_field_update_order_meta( $order_id ) {
	    if ( ! empty( $_POST['my_field_name'] ) ) {
	        update_post_meta( $order_id, 'My Field', sanitize_text_field( $_POST['my_field_name'] ) );
	    }
	}
	//showing data in admin
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wcav_my_custom_checkout_field_display_admin_order_meta', 10, 1 );
	function wcav_my_custom_checkout_field_display_admin_order_meta($order){
	    echo '<p><strong>'.__('Birthdate','cav').':</strong> ' . get_post_meta( $order->id, 'My Field', true ) . '</p>';
	}
	add_filter( 'woocommerce_general_settings', 'wcav_add_order_number_start_setting' );
	function wcav_add_order_number_start_setting( $settings ) {
  		$updated_settings = array();
  		foreach ( $settings as $section ) {
    		// at the bottom of the General Options section
    		if (isset( $section['id'] ) && 'general_options' == $section['id'] && isset( $section['type'] ) && 'sectionend' == $section['type']) {
    			$age_limit = __( 'Age Limit', 'cav' );
    			$desc_age_limit = __( 'Set Age limit to make order.', 'cav' );
      			$updated_settings[] = array(
			        'name'     => __( $age_limit, 'wc_seq_order_numbers' ),
			        'desc_tip' => __( $desc_age_limit, 'wc_seq_order_numbers' ),
			        'id'       => 'woocommerce_order_number_start',
			        'type'     => 'text',
			        'css'      => 'min-width:300px;',
			        'std'      => '1',  // WC < 2.0
			        'default'  => '18',  // WC >= 2.0
			        'desc'     => __( '', 'wc_seq_order_numbers' ),
      			);
    		}
    		$updated_settings[] = $section;
  		}
  		return $updated_settings;
	}
}
?>
