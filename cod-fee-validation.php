<?php
/**
 * Plugin Name: LeanCart COD Fee & Validation
 * Plugin URI:  https://sleepclinicguide.com/
 * Description: When cash on delivery (COD) is selected, adds a fee to the cart, blocks invalid phone numbers, and reduces shipping return rates.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cod-fee-validation
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_COD_FEE_VALIDATION_DEMO_SITE' ) ) {
	define( 'LEANCART_COD_FEE_VALIDATION_DEMO_SITE', 'https://sleepclinicguide.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_COD_Fee_Validation {

	const SLUG = 'cod-fee-validation';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart COD Fee & Validation',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_COD_FEE_VALIDATION_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'cod-fee-validation', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart COD Fee & Validation' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'cod-fee-validation' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'fee', __('COD fee', 'cod-fee-validation'), __('Fixed amount. Set to 0 for no fee.', 'cod-fee-validation'), 'number' );
		LeanCart_Framework::field_checkbox( 'validate_phone', __('Validate phone', 'cod-fee-validation'), __('Block orders with invalid numbers.', 'cod-fee-validation') );
	}


	public static function hooks() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'fee' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'script' ) );
	}

	public static function fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
		$method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
		if ( 'cod' !== $method ) { return; }
		$fee = (float) LeanCart_Framework::setting( 'fee', 0 );
		if ( $fee > 0 ) { $cart->add_fee( __( 'Cash on delivery fee', 'cod-fee-validation' ), $fee, true ); }
	}

	public static function validate() {
		if ( 'yes' !== LeanCart_Framework::setting( 'validate_phone', 'yes' ) ) { return; }
		if ( ! isset( $_POST['payment_method'] ) || ! is_scalar( $_POST['payment_method'] ) || 'cod' !== sanitize_key( wp_unslash( $_POST['payment_method'] ) ) ) { return; }
		$raw   = isset( $_POST['billing_phone'] ) && is_string( $_POST['billing_phone'] ) ? wp_unslash( $_POST['billing_phone'] ) : '';
		$phone = $raw ? preg_replace( '/[^0-9]/', '', sanitize_text_field( $raw ) ) : '';
		if ( strlen( $phone ) < 10 ) {
			wc_add_notice( __( 'Please enter a valid phone number (at least 10 digits).', 'cod-fee-validation' ), 'error' );
		}
	}

	public static function script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
		LeanCart_Framework::enqueue_js( "jQuery(document).on('change', 'input[name=payment_method]', function(){ jQuery('body').trigger('update_checkout'); });" );
	}


}

LeanCart_COD_Fee_Validation::init();

