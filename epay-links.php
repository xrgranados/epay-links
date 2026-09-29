<?php
/*
Plugin Name: ePay Links Payments
Description: A comprehensive solution for generating payment links within WooCommerce, enabling seamless transactions and providing a detailed transaction status table for easy management and tracking of payments.
Version: 1.0.1
Author: Rafael Granados
*/

// Prevent direct access to the file
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Ensure that WooCommerce is active before proceeding
if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit; // Exit if WooCommerce is not active
}

// Include necessary files for the ePay functionality
require_once('inc/class-epay-link.php'); // Include the class for handling ePay link
require_once('inc/class-epay-transaction.php'); // Include the class for handling ePay transactions
require_once('inc/functions.php'); // Include additional helper functions
require_once('inc/queue.php'); // Include the queue handling functionality


// Function to record the transaction table (must be executed when activating the plugin)
register_activation_hook(__FILE__, 'epay_create_transaction_table');

/**
 * Creates the ePay transactions table in the database upon plugin activation.
 *
 * This function is registered to run on plugin activation and creates the table
 * used to store ePay transaction details.
 *
 * @return void
 */
function epay_create_transaction_table()
{
    global $wpdb;

    // Define the table name and charset collation
    $table_name = $wpdb->prefix . EpayLink::DB_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    // SQL query to create the table
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        transaction_id varchar(50) NOT NULL,
        product_name varchar(255) NOT NULL,
        amount decimal(10, 2) NOT NULL,
        installments int(11) NOT NULL,
        payment_link varchar(255) NOT NULL,
        audit_number varchar(50) DEFAULT NULL,
        status varchar(50) DEFAULT 'pending',
        disabled tinyint(1) DEFAULT 0,
        last_digits varchar(4) NULL,
        cardholder_name varchar(255) DEFAULT NULL,
        authorization_number varchar(50) DEFAULT NULL,
        transaction_response text DEFAULT NULL,
        notes text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Include WordPress upgrade functions and execute the SQL query
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Registers the activation hook for the ePay Links plugin.
 *
 * This function is triggered when the plugin is activated. It checks if the
 * 'audit_number_epay' option exists in the WordPress database. If it does not,
 * it initializes the option with a default value of '000001'. This option is used
 * to keep track of the audit number for ePay transactions.
 *
 * @return void
 */
register_activation_hook(__FILE__, 'wc_epay_links_plugin_activate');

/**
 * Sets the initial value for the audit number option upon plugin activation.
 *
 * This function checks if the 'audit_number' option is already set. If it is not,
 * it adds the option with a default value of '000001'. This ensures that the audit
 * number starts from a known state when the plugin is first activated.
 *
 * @return void
 */
function wc_epay_links_plugin_activate()
{
    if (!get_option('audit_number')) {
        add_option('audit_number', '000001');
    }
}

/**
 * Schedules a recurring event to expire old ePay links.
 *
 * This function checks if a scheduled event for expiring old ePay links
 * already exists. If not, it schedules the event to run hourly. The scheduled
 * event is responsible for updating the status of ePay links that have
 * exceeded their validity period, ensuring that outdated links are managed
 * appropriately.
 *
 * @return void
 */
function schedule_expire_old_epay_links()
{
    // Check if the 'expire_old_epay_links_event' is already scheduled
    if (!wp_next_scheduled('expire_old_epay_links_event')) {
        // Schedule the event to run hourly
        wp_schedule_event(time(), 'hourly', 'expire_old_epay_links_event');
    }
}

// Hook the scheduling function to the 'wp' action
add_action('wp', 'schedule_expire_old_epay_links');

// Hook the expiration function to the scheduled event
add_action('expire_old_epay_links_event', 'expire_old_epay_links');

/**
 * Unschedules the event to expire old ePay links.
 *
 * This function checks if there is a scheduled event for expiring old ePay links.
 * If such an event exists, it unschedules it to prevent it from running. This is
 * typically called when the plugin is deactivated to clean up any scheduled tasks
 * that are no longer needed.
 *
 * @return void
 */
function unschedule_expire_old_epay_links()
{
    // Get the timestamp of the next scheduled event for expiring old ePay links
    $timestamp = wp_next_scheduled('expire_old_epay_links_event');

    // If a timestamp exists, unschedule the event
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'expire_old_epay_links_event');
    }
}

// Register the unscheduling function to run upon plugin deactivation
register_deactivation_hook(__FILE__, 'unschedule_expire_old_epay_links');
