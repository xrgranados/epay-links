<?php
if (! defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Enqueues library scripts and styles for the WordPress admin area.
 *
 * @return void
 */
function epay_admin_scripts()
{
    if (is_admin()) {
        wp_enqueue_style(
            'epay-links-styles',
            plugins_url('assets/css/styles.css', dirname(__FILE__)),
            array(),
            '1.1.15'
        );

        wp_enqueue_style(
            'jquery-ui-css',
            plugins_url('assets/jquery-ui-1.14.0/jquery-ui.min.css', dirname(__FILE__)),
            array(),
            '1.14.0'
        );

        wp_register_script(
            'jquery-ui',
            plugins_url('assets/jquery-ui-1.14.0/jquery-ui.min.js', dirname(__FILE__)),
            array('jquery'),
            '1.14.0',
            true
        );

        wp_enqueue_script('jquery-ui');

        wp_enqueue_script(
            'epay-scripts',
            plugins_url('assets/js/scripts.js', dirname(__FILE__)),
            array('jquery'),
            '1.2',
            true
        );

        // wp_localize_script('epay-links-script', 'epayLinks', array(
        //     'ajax_url' => admin_url('admin-ajax.php'),
        // ));

        // wp_enqueue_script('epay-links-script');
    }
}
// Hook the function to the 'admin_enqueue_scripts' action
add_action('admin_enqueue_scripts', 'epay_admin_scripts');

/**
 * Enqueues library scripts and styles for the form payment area.
 *
 * @return void
 */
function enqueue_epay_payment_form_assets()
{
    // Verificar si el shortcode 'epay_payment_form' está en la página
    if (has_shortcode(get_post()->post_content, 'epay_payment_form')) {

        // Registrar y cargar los estilos
        wp_enqueue_style(
            'epay-payment-form-style',
            plugins_url('assets/css/epay-payment-form.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );

        // Registrar y cargar los scripts
        wp_enqueue_script(
            'card-validator',
            plugins_url('assets/js/jquery.creditCardValidator.js', dirname(__FILE__)),
            array('jquery'),
            '1.0',
            true
        );
        wp_enqueue_script(
            'input-mask',
            plugins_url('assets/js/jquery.inputmask.min.js', dirname(__FILE__)),
            array('jquery'),
            '1.0',
            true
        );

        wp_enqueue_script(
            'epay-payment-form-script',
            plugins_url('assets/js/epay-payment-form.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('epay-payment-form-script', 'epayPaymentData', array(
            'transaction_id' => sanitize_text_field($_GET['transaction_id']),
        ));
    }
}

// Hook the function to the 'enqueue_epay_payment_form_assets' action
add_action('wp_enqueue_scripts', 'enqueue_epay_payment_form_assets');
