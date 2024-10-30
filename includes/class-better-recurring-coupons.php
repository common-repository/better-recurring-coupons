<?php

namespace SPBRC;

use WC_Coupon;
use WC_Product;
use WC_Subscriptions_Coupon;

class Better_Recurring_Coupons
{

    /**
     * @var Better_Recurring_Coupons $instance
     */
    private static $instance;


    /**
     * Get instance
     *
     * @param $plugin_file
     *
     * @return Better_Recurring_Coupons
     */
    final public static function instance($plugin_file): Better_Recurring_Coupons{
        if(null === static::$instance){
            static::$instance = new static($plugin_file);
        }

        return static::$instance;
    }


    /**
     * @return void
     */
    public static function initialize(){

        // Backup check to ensure WC Subscription dependency is actually active and loading before we proceed with initialization
        if(!class_exists('WC_Subscriptions')){
            wp_admin_notice(esc_html__('The "Better Recurring Coupons" plugin requires WooCommerce Subscriptions', 'better-recurring-coupons'), [
                'type'        => 'error',
                'dismissible' => true
            ]);
            deactivate_plugins(SPBRC_PLUGIN_BASENAME);
        }
        // Backup check to ensure WooCommerce dependency is actually active and loading before we proceed with initialization
        if(!class_exists('WooCommerce')){
            wp_admin_notice(esc_html__('The "Better Recurring Coupons" plugin requires WooCommerce', 'better-recurring-coupons'), [
                'type'        => 'error',
                'dismissible' => true
            ]);
            deactivate_plugins(SPBRC_PLUGIN_BASENAME);
        }

        self::add_brc_hooks();

    }

    /**
     * @return void
     */
    public static function add_brc_hooks(){
        add_filter('wcs_bypass_coupon_removal', array(__CLASS__, 'allow_subscription_coupon'), 50, 3);
        remove_filter('woocommerce_coupon_is_valid_for_product', array(
            WC_Subscriptions_Coupon::class,
            'validate_brc_for_product'
        ), 10, 3);
        add_filter('woocommerce_coupon_is_valid_for_product', array(__CLASS__, 'validate_brc_for_product'), 10, 3);
        add_action('woocommerce_coupon_options', array(__CLASS__, 'add_admin_coupon_fields'), 10);
        add_action('woocommerce_coupon_options_save', array(__CLASS__, 'save_coupon_fields'), 10);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'ensure_only_one_coupon_in_cart'), 10, 1);
        add_action('plugins_loaded', array(__CLASS__, 'delayed_brc_hooks'), 10);
    }

    /**
     * @param $bypass_default_checks
     * @param $coupon
     * @param $calculation_type
     *
     * @return mixed|true
     */
    public static function allow_subscription_coupon($bypass_default_checks, $coupon, $calculation_type){

        $cart = WC()->cart;

        if($coupon->get_meta('_allow_subscriptions') !== 'yes'){
            return $bypass_default_checks;
        }

        // Bypass this check if a third party has already opted to bypass default conditions.
        if($bypass_default_checks){
            return $bypass_default_checks;
        }

        // Special handling for a single payment coupon.
        if('recurring_total' === $calculation_type && $coupon->get_meta('_apply_to_first_cycle_only') === 'yes' && 0 < $cart->get_coupon_discount_amount($coupon->get_code())){
            $cart->remove_coupon($coupon->get_code());
        }

        return true;
    }

    /**
     * @param $id
     *
     * @return void
     */
    public static function add_admin_coupon_fields($id){
        $coupon = new WC_Coupon($id);

        woocommerce_wp_checkbox(
            array(
                'id'            => 'brc_allow_subscriptions',
                'label'         => __('Allow Subscriptions', 'better-recurring-coupons'),
                'desc_tip'      => true,
                'description'   => __('When enabled will allow this coupon to be used in subscriptions.', 'better-recurring-coupons'),
                'wrapper_class' => 'brc_allow_subscriptions_wrapper',
                'value'         => wc_bool_to_string($coupon->get_meta('_allow_subscriptions')),
            )
        );

    }

    /**
     * @param $id
     *
     * @return void
     */
    public static function save_coupon_fields($id){
        // Check the nonce
        if(empty($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')){
            return;
        }

        $input = isset($_POST['brc_allow_subscriptions']) ? sanitize_text_field(wp_unslash($_POST['brc_allow_subscriptions'])) : null;

        $allow_subscriptions = ['_allow_subscriptions' => $input];

        $coupon_meta = apply_filters('spbrc_add_coupon_meta', $allow_subscriptions);

        // Sanitize the metadata before we have Woo add it to the DB
        wc_clean($coupon_meta);

        $coupon = new WC_Coupon($id);

        foreach($coupon_meta as $key => $value){
            $coupon->update_meta_data($key, $value, true);
        }
        $coupon->save();
    }

    /**
     * @return void
     */
    public static function enqueue_admin_scripts(){
        wp_enqueue_script('brc-subscription-coupon-admin', SPBRC_PLUGINS_URL . 'assets/js/admin.js', array('jquery'), '1.0.0', true);
    }

    /**
     * Validates a subscription coupon's use for a given product.
     *
     * @param bool $is_valid Whether the coupon is valid for the product.
     * @param WC_Product $product The product object.
     * @param WC_Coupon $coupon The coupon object.
     *
     * @return bool Whether the coupon is valid for the product.
     */
    public static function validate_brc_for_product($is_valid, $product, $coupon){

        // Exit early if the coupon is already invalid.
        if(!$is_valid){
            return $is_valid;
        }

        if($coupon->get_meta('_allow_subscriptions') !== 'yes'){
            return WC_Subscriptions_Coupon::validate_subscription_coupon_for_product($is_valid, $product, $coupon);
        }

        return $is_valid;
    }

    /**
     * @return int|void
     */
    private static function calculate_coupon_discount($cart){

        /** If there is no coupon, none of this matters, so we stop the script */
        if(empty($cart->get_applied_coupons())){
            return;
        }

        /** Next we get the woo coupon object */
        $c = self::get_cart_coupon($cart);

        /** Then we validate if the coupon discount type. If it's not a percent or fixed_product we stop */
        if($c->discount_type === 'fixed_product'){
            return (int) ($c->amount * $cart->get_cart_contents_count());
        }elseif($c->discount_type === 'percent'){
            return (int) ($cart->get_subtotal() * ($c->amount / 100));
        }

        // For fixed_cart or other custom coupon types, return the amount as-is.
        return (int) $c->amount;

    }

    /**
     * @param $cart
     *
     * @return void
     */
    public static function override_totals($cart){

        if(empty($cart->recurring_cart_key)){
            $cart->set_total($cart->get_subtotal() - self::calculate_coupon_discount($cart));
        }

    }

    /**
     * @return void
     */
    public static function override_nonrecurring_coupon_value($cart){
        if(!empty(self::get_cart_coupon($cart)->code)){
            $cart->set_coupon_discount_totals([self::get_cart_coupon($cart)->code => self::calculate_coupon_discount($cart)]);
        }
    }

    /**
     * @param $cart
     *
     * @return WC_Coupon
     */
    private static function get_cart_coupon($cart): WC_Coupon{
        return new WC_Coupon(array_values($cart->get_applied_coupons())[0]);
    }

    /**
     * @return void
     */
    public static function ensure_only_one_coupon_in_cart($cart, $override = false){
        if(is_admin() && !defined('DOING_AJAX')){
            return;
        }

        if(apply_filters('spbrc_limit_override', $override)){
            return;
        }

        // For more than 1 applied coupons only
        if(sizeof($cart->get_applied_coupons()) > 1 && $coupons = $cart->get_applied_coupons()){
            // Remove the first coupon keeping only the most recent applied coupon
            $cart->remove_coupon(reset($coupons));

            wc_clear_notices();
            wc_add_notice(esc_html__("Coupon code successfully replaced.", 'better-recurring-coupons'), 'info');
        }
    }

    /**
     * Groups some hooks that need to be fired after plugins are loaded
     * @return void
     */
    public function delayed_spbrc_hooks(){
        add_filter('woocommerce_after_calculate_totals', array(__CLASS__, 'override_nonrecurring_coupon_value'), 10, 1);
        add_filter('woocommerce_after_calculate_totals', array(__CLASS__, 'override_totals'), 10, 1);
    }

}
