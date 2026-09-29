<?php

use Automattic\WooCommerce\GoogleListingsAndAds\Vendor\Google\Service\ShoppingContent\Date;

if (! defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Registers the ePay Links submenu pages in the WordPress admin menu.
 *
 * This function adds two submenu pages under the "WooCommerce" menu:
 *
 * @return void
 */
add_action('admin_menu', 'epay_links_menu');

/**
 * Adds submenu pages under the WooCommerce menu.
 *
 * This function utilizes the `add_submenu_page()` function to add submenu items:
 * - "Payment Links": A table to view and manage existing payment links.
 *
 * @return void
 */
function epay_links_menu()
{
    add_submenu_page(
        'woocommerce',
        'Links de pago',
        'Links de pago',
        'manage_options',
        'epay-links',
        'epay_links_page'
    );
}

/**
 * Displays the ePay links management page in the WordPress admin area.
 *
 * This function handles the display of the ePay links management interface.
 * It checks if the form to generate a new payment link has been submitted,
 * sanitizes the input data, and attempts to create a new payment link.
 * If successful, it displays a success notification with the generated link.
 * If an error occurs during link generation, it catches the exception and displays an error notification.
 *
 * The page consists of two tabs: one for displaying existing payment links
 * and another for generating new payment links.
 *
 * @return void
 */
function epay_links_page()
{
    // Check if the form has been submitted
    if (isset($_POST['generate_epay_payment_link'])) {
        // Sanitize and process form data
        $productName = sanitize_text_field($_POST['product_name']);
        $amount = floatval($_POST['amount']);
        $installments = intval($_POST['payment_installments']);

        try {
            // Call the function to generate the payment link
            $payment_link = generate_epay_payment_link($productName, $amount, $installments);

            $notice = __('Link de pago generado', 'epay-links') . ": <code>{$payment_link}</code>";
            $notice .= '<span class="copy-link-code dashicons dashicons-admin-page" title="' . __('Copiar link', 'epay-links') . '" data-link="' . esc_html($payment_link) . '"></span>';

            // Display success notification and payment link
            echo render_notice(
                $notice,
                'success'
            );
        } catch (\Exception $e) {
            // Display error notification if link generation failed
            echo render_notice(
                $e->getMessage(),
                'error'
            );
        }
    }
?>
    <div class="wrap">

        <h2><?php _e('Links de pago', 'epay-links'); ?></h2>
        <div id="tabs">
            <ul>
                <li><a href="#tab-1"><?php _e('Links de pago', 'epay-links'); ?></a></li>
                <li><a href="#tab-2"><?php _e('Generar link de pago', 'epay-links'); ?></a></li>
            </ul>

            <div id="tab-1">
                <?php renderEpayLinksTable(); ?>
            </div>
            <div id="tab-2">
                <?php renderForm(); ?>
            </div>
        </div>
    </div>
<?php
}

/**
 * Handles the AJAX request to disable an ePay payment link.
 *
 * This function is hooked to the 'wp_ajax_disable_epay_payment_link' action,
 * which is triggered when an AJAX request is made to disable a payment link.
 * It retrieves the link ID from the POST data, updates the corresponding
 * record in the database by setting the 'disabled' column to 1 and the 'status'
 * column to a predefined constant indicating that the link is disabled.
 * The function sends a JSON response indicating the success or failure of the
 * operation based on the result of the database update.
 *
 * @return void
 */
add_action('wp_ajax_disable_epay_payment_link', 'disable_epay_payment_link');
function disable_epay_payment_link()
{
    global $wpdb;

    // Retrieve the link ID from the POST data and convert it to an integer
    $link_id = intval($_POST['link_id']);

    // Check if a valid link ID is provided
    if ($link_id) {
        // Define the table name with the WordPress prefix
        $table_name = $wpdb->prefix . EpayLink::DB_TABLE;

        // Update the record in the database, setting 'disabled' to 1 and 'status' to disabled
        $updated = $wpdb->update(
            $table_name,
            array(
                'disabled' => 1,
                'status' => EpayLink::STATUS_DISABLED,
            ),
            array('id' => $link_id),
            array('%d', '%s'),
            array('%d')
        );

        // Check if the update was successful
        if ($updated !== false) {
            // Send a JSON success response
            wp_send_json_success();
        } else {
            // Send a JSON error response if the update failed
            wp_send_json_error();
        }
    } else {
        // Send a JSON error response if the link ID is not valid
        wp_send_json_error();
    }
}

/**
 * Handles the AJAX request to delete an ePay payment link.
 *
 * This function is hooked to the 'wp_ajax_delete_epay_payment_link' action,
 * which is triggered when an AJAX request is made to delete a payment link.
 * It retrieves the link ID from the POST data, updates the corresponding
 * record in the database by setting the 'deleted_at' column to the current
 * time, and sends a JSON response indicating the success or failure of the
 * deletion operation.
 *
 * @return void
 */
add_action('wp_ajax_delete_epay_payment_link', 'delete_epay_payment_link');
function delete_epay_payment_link()
{
    global $wpdb;

    // Retrieve the link ID from the POST data
    $link_id = intval($_POST['link_id']);

    // Check if a valid link ID is provided
    if ($link_id) {
        // Define the table name with the WordPress prefix
        $table_name = $wpdb->prefix . EpayLink::DB_TABLE;

        // Update the record in the database, setting the 'deleted_at' column to the current time
        $updated = $wpdb->update(
            $table_name,
            array('deleted_at' => current_time('mysql')),
            array('id' => $link_id),
        );

        // Check if the update was successful
        if ($updated !== false) {
            // Send a JSON success response
            wp_send_json_success();
        } else {
            // Send a JSON error response
            wp_send_json_error();
        }
    } else {
        // Send a JSON error response if the link ID is not valid
        wp_send_json_error();
    }
}

// Hook to handle the AJAX request for refunding an ePay transaction
add_action('wp_ajax_refund_epay_transaction', 'refund_epay_transaction');

/**
 * Handles the AJAX request to refund an ePay transaction.
 */
function refund_epay_transaction()
{
    // Retrieve the transaction ID from the POST request
    $transaction_id = intval($_POST['transaction_id']);

    // Validate the transaction ID
    if (!$transaction_id) {
        // Send an error response if the transaction ID is invalid
        wp_send_json_error(['message' => 'Invalid transaction ID.']);
    }

    try {
        // Process the refund using the EpayTransaction class
        $response = (new EpayTransaction)->processRefund($_POST, $transaction_id);

        // Check if the refund was successful
        if ($response->success !== false) {
            // Send a success response with the refund message
            wp_send_json_success(['message' => $response->message]);
        } else {
            // Send an error response with the refund message
            wp_send_json_error(['message' => $response->message]);
        }
    } catch (\Exception $e) {
        // Catch any exceptions and send an error response with the exception message
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Renders a notice message in the WordPress admin area.
 *
 * This function creates a dismissible notice box with a specified message and type.
 *
 * @param string $message The message to display in the notice.
 * @param string $type    The type of notice (e.g., 'info', 'error', 'success', 'warning').
 *                        Defaults to 'info'.
 * @return string The HTML markup for the notice.
 */
function render_notice($message, $type = 'info')
{
    $notice = "<div class=\"notice notice-{$type} is-dismissible\">";
    $notice .= "<p>{$message}</p>";
    $notice .= '</div>';

    return $notice;
}

/**
 * Renders a WooCommerce notice message.
 *
 * This function generates an HTML structure for displaying a notice message
 * in the WooCommerce context. The notice can be styled according to its type,
 * such as 'info', 'error', 'success', etc.
 *
 * @param string $message The message to be displayed in the notice. Defaults to an empty string.
 * @param string $type    The type of notice, which determines its styling (e.g., 'info', 'error', 'success').
 *                        Defaults to 'info'.
 * @return string The HTML markup for the WooCommerce notice.
 */
function renderWCNotice($message = '', $type = 'info')
{
    // Initialize an empty string for the notice HTML
    $notice = '';

    // Start the wrapper for WooCommerce notices
    $notice .= '<div class="woocommerce-notices-wrapper">';

    // Create the notice div with the appropriate type class
    $notice .= "<div class=\"woocommerce-message woocommerce-{$type}\" role=\"alert\">";

    // Add the message to the notice
    $notice .= $message;
    $notice .= '</div>';
    $notice .= '</div>';

    // Return the complete notice HTML
    return $notice;
}

/**
 * Renders the payment link generation form for the ePay payment gateway.
 *
 * This function generates an HTML form that allows users to create payment links
 * for the ePay payment gateway. It includes fields for the product name, amount,
 * and number of installments. The form is secured with a nonce field.
 *
 * The function retrieves the available installment options from the ePay gateway
 * and populates the corresponding dropdown options in the form.
 *
 * @return void
 */
function renderForm()
{
?>
    <div id="epay-links-form">
        <h3><?php _e('Generar link de pago', 'epay-links'); ?></h3>

        <hr>

        <form method="post" action="" id="generate-payment-link-form">
            <?php
            // Add a nonce field for security
            wp_nonce_field('generate_epay_payment_link', 'epay_payment_link_nonce');
            ?>

            <input type="hidden" name="generate_epay_payment_link" value="generate">

            <p class="form-group">
                <label for="name">
                    <?php _e('Nombre', 'epay-links'); ?>
                </label>
                <input type="text" id="product_name" name="product_name" value="" style="width: 350px;" required>
            </p>

            <p class="form-group">
                <label for="amount">
                    <?php _e('Monto a cubrir', 'epay-links'); ?>
                </label>
                <input type="number" id="amount" name="amount" value="" style="width: 150px;" required step="0.01" min="1" placeholder="0.00">
            </p>

            <p class="form-group">
                <label for="payment_installments">
                    <?php _e('Cuotas'); ?>
                </label>
                <select name="payment_installments" id="payment_installments" style="width: 150px;">
                    <option value="0"><?php _e('Un solo pago', 'epay-links'); ?></option>
                    <?php
                    // Get the available installment options from the ePay gateway
                    $installments = array(3, 6, 10, 12, 15, 18, 24, 36, 48);
                    foreach ($installments as $installment) {
                        $option = "<option value=\"$installment\">";
                        $option .= "$installment Cuotas";
                        $option .= '</option>';
                        echo $option;
                    }
                    ?>
                </select>
            </p>

            <hr>

            <p>
                <input id="submit-payment-link" type="submit" name="generate_epay_payment_link" value="<?php _e('Generar link de pago', 'epay-links'); ?>" class="button button-primary">
            </p>
        </form>
    </div>
<?php
}

/**
 * Retrieves ePay transaction links from the database with pagination and filtering options.
 *
 * This function queries the ePay transactions table to retrieve a list of transactions
 * that have not been deleted. It supports filtering by product name and allows for
 * pagination through the use of the `$paged` and `$per_page` parameters.
 *
 * @param int $paged    The current page number for pagination. Defaults to 1.
 * @param int $per_page The number of records to return per page. Defaults to 10.
 * @return array An associative array containing:
 *               - 'data': An array of transaction records.
 *               - 'total': The total number of records in the database.
 */
function get_epay_links($paged = 1, $per_page = 10)
{
    global $wpdb;

    // Define the table name for the ePay transactions
    $table_name = $wpdb->prefix . EpayLink::DB_TABLE;

    // Initialize the base query to retrieve transactions that are not deleted
    $query = "SELECT * FROM $table_name WHERE deleted_at IS NULL";

    // Apply filtering by product name if specified in the GET request
    if (!empty($_GET['filter_product_name'])) {
        $query .= $wpdb->prepare(
            " AND product_name LIKE %s",
            '%' . sanitize_text_field($_GET['filter_product_name']) . '%'
        );
    }

    // Apply filtering by status if specified in the GET request
    if (!empty($_GET['filter_status']) && $_GET['filter_status'] != 'all') {
        $query .= $wpdb->prepare(
            " AND status = %s",
            sanitize_text_field($_GET['filter_status'])
        );
    }

    // Append ordering and pagination to the query
    $query .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';

    // Calculate the offset for pagination
    $offset = ($paged - 1) * $per_page;

    // Execute the query to get the paginated results
    $results = $wpdb->get_results(
        $wpdb->prepare(
            $query,
            $per_page,
            $offset
        )
    );

    // Query to get the total number of records in the transactions table
    $countQuery = "SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL";
    // Apply filtering by product name if specified in the GET request
    if (!empty($_GET['filter_product_name'])) {
        $countQuery .= $wpdb->prepare(
            " AND product_name LIKE %s",
            '%' . sanitize_text_field($_GET['filter_product_name']) . '%'
        );
    }

    // Apply filtering by status if specified in the GET request
    if (!empty($_GET['filter_status']) && $_GET['filter_status'] != 'all') {
        $countQuery .= $wpdb->prepare(
            " AND status = %s",
            sanitize_text_field($_GET['filter_status'])
        );
    }
    $total = $wpdb->get_var($countQuery);

    // Return the results and total count as an associative array
    return array('data' => $results, 'total' => $total);
}

/**
 * Renders the table displaying ePay payment links and their statuses.
 *
 * This function displays a table in the WordPress admin area showing the status of ePay payment links.
 * It includes a form for filtering the results by order ID and customer name. The table displays
 * transaction details such as order ID, payment link, amount, status, approval date, and creation date.
 *
 * @return void
 */
function renderEpayLinksTable()
{
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $per_page = 10;

    $results = get_epay_links($paged, $per_page);

    $links = $results['data'];
    $total = $results['total'];

    $total_pages = ceil($total / $per_page);

    $statuses = EpayLink::getAllStatuses();
?>
    <div class="wrap">
        <h3><?php _e('Listado links de pago', 'epay-links'); ?></h3>

        <hr>

        <div style="margin: 20px 0 20px;">
            <form method="GET" action="" style="display: flex;">
                <input type="hidden" name="page" value="epay-links" />
                <input class="form-filter" type="search" name="filter_product_name" placeholder="<?php _e('Nombre', 'epay-links'); ?>" value="<?php echo old('filter_product_name'); ?>" />
                <select name="filter_status" class="form-filter">
                    <option value="all"><?php echo __('Todos los estados', 'epay-links'); ?></option>
                    <?php
                    foreach ($statuses as $status) {
                        $selected = old('filter_status') == $status ? 'selected' : '';
                        $option = "<option value=\"$status\" $selected>";
                        $option .= EpayLink::STATUS_LABEL[$status];
                        $option .= '</option>';
                        echo $option;
                    }
                    ?>
                </select>
                <input type="submit" class="button" value="<?php _e('Buscar', 'epay-links'); ?>" />
            </form>
        </div>
        <!-- Tabla de transacciones -->
        <table id="links-table" class="wp-list-table widefat fixed striped">
            <caption><?php _e('Listado de transacciones', 'epay-links'); ?></caption>
            <thead>
                <tr>
                    <th><?php _e('Nombre', 'epay-links'); ?></th>
                    <th><?php _e('Link', 'epay-links'); ?></th>
                    <th><?php _e('Monto', 'epay-links'); ?></th>
                    <th><?php _e('Cuotas', 'epay-links'); ?></th>
                    <th><?php _e('Estado', 'epay-links'); ?></th>
                    <th><?php _e('Autorización', 'epay-links'); ?></th>
                    <th><?php _e('Fecha/hora creado', 'epay-links'); ?></th>
                    <th><?php _e('Acciones', 'epay-links'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($links) {
                    // Output each result as a table row
                    foreach ($links as $row) {
                        renderRow($row);
                    }
                } else {
                    // Display a message if no data is found
                    echo '<tr><td colspan="8">' . __('No hay datos por mostrar', 'epay-links') . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
        // Pagination
        echo '<h3 class="tablenav-pages">';
        if ($total_pages > 1) {
            echo '<span class="pagination-links">';
            if ($paged > 1) {
                echo '<a class="first-page button button-link dashicons dashicons dashicons-controls-skipback" href="' . esc_url(add_query_arg('paged', 1)) . '"></a>';
                echo '<a class="prev-page button button-link dashicons dashicons-arrow-left-alt2" href="' . esc_url(add_query_arg('paged', $paged - 1)) . '"></a>';
            }
            echo '<span class="paging-input">Pagina ' . $paged . ' de ' . $total_pages . '</span>';
            if ($paged < $total_pages) {
                echo '<a class="next-page button button-link dashicons dashicons-arrow-right-alt2" href="' . esc_url(add_query_arg('paged', $paged + 1)) . '"></a>';
                echo '<a class="last-page button button-link dashicons dashicons-controls-skipforward" href="' . esc_url(add_query_arg('paged', $total_pages)) . '"></a>';
            }
            echo '</span>';
        }
        echo '</h3>';
        ?>
    </div>
    <script id="data-links" type="application/json">
        <?php echo json_encode($links); ?>
    </script>
<?php
    wp_localize_script('epay-links-data', 'epayLinksData', array(
        'linksData' => $links,
    ));
}

/**
 * Renders a table row for an ePay transaction link.
 *
 * This function generates an HTML table row displaying the details of an ePay transaction link,
 * including the product name, payment link, amount, number of installments, payment status,
 * approval date, creation date, and action buttons for disabling or deleting the link.
 *
 * The function uses the `renderStatus` function to display the payment status and escapes
 * the output to prevent potential XSS vulnerabilities.
 *
 * @param object $row The transaction link object containing the row data.
 * @return void
 */
function renderRow($row)
{
    $disabledPaid = fn($status) => $status == EpayLink::STATUS_PAID ? 'disabled' : '';

    $disabledBtn = function ($status) {
        if (
            $status == EpayLink::STATUS_EXPIRED
            or $status == EpayLink::STATUS_DISABLED
            or $status == EpayLink::STATUS_REFUNDED
        ) {
            return 'disabled';
        }
        return '';
    };

    $disabledDeleteBtn = fn ($status) => $status == EpayLink::STATUS_REFUNDED ? 'disabled' : '';

    $disabledRefund = fn($status) => ($status == EpayLink::STATUS_PENDING or $status == EpayLink::STATUS_REJECTED) ? 'disabled' : '';
?>
    <tr>
        <td><?php echo esc_html($row->product_name) ?></td>
        <td>
            <div class="text-truncate" style="max-width: 150px;" title="<?php echo esc_html($row->payment_link) ?>">
                <a href="<?php echo esc_html($row->payment_link); ?>" target="_blank">
                    <?php echo esc_html($row->payment_link); ?>
                </a>
            </div>
            <span class="copy-link dashicons dashicons-admin-page" title="<?php echo __('Copiar link', 'epay-links') ?>" data-link="<?php echo esc_html($row->payment_link) ?>"></span>
        </td>
        <td class="text-right"><?php echo wc_price($row->amount) ?></td>
        <td class="text-center"><?php echo esc_html($row->installments) ?></td>
        <td><?php echo renderStatus($row->status) ?></td>
        <td>
            <?php
            if ($row->authorization_number) {
                echo '<code>' . esc_html($row->authorization_number) . '</code>';
            } else {
                echo '—';
            }
            ?>
        </td>
        <td><?php echo esc_html($row->created_at) ?></td>
        <td>
            <button type="button" <?php echo $disabledBtn($row->status) ?> <?php echo $disabledPaid($row->status); ?> class="disable-link-button button button-link dashicons dashicons-remove" title="<?php echo __('Deshabilitar', 'epay-links') ?>" data-link-id="<?php echo esc_attr($row->id) ?>">
                <?php echo " "; ?>
            </button>
            <?php
            ?>
            <button type="button" <?php echo $disabledDeleteBtn($row->status) ?> <?php echo $disabledPaid($row->status) ?> class="delete-link-button button button-link button-link-delete dashicons dashicons-trash" title="<?php echo __('Eliminar', 'epay-links') ?>" data-link-id="<?php echo esc_attr($row->id) ?>">
                <?php echo " "; ?>
            </button>
            <button type="button" class="showDetails button button-link dashicons dashicons-text-page" title="<?php echo __('Ver detalles', 'epay-links'); ?>" data-link-id="<?php echo esc_attr($row->id); ?>">
                <?php echo " "; ?>
            </button>
            <button type="button" <?php echo $disabledBtn($row->status) ?> <?php echo $disabledRefund($row->status) ?> class="refund-transaction button button-link dashicons dashicons-image-rotate" title="<?php echo __('Reversión', 'epay-links'); ?>" data-link-id="<?php echo esc_attr($row->id); ?>">
                <?php echo " "; ?>
            </button>
        </td>
    </tr>
<?php
}

/**
 * Renders a formatted status badge for an ePay link.
 *
 * This function takes a status string as input and generates an HTML badge
 * element with the appropriate color and label based on the provided status.
 * It uses the EpayLink class constants to retrieve the corresponding color
 * and label for each status. If the status is not found in the EpayLink
 * constants, it defaults to a light color and the original status string.
 *
 * The generated badge HTML is returned for display in the user interface.
 *
 * @param string $status The status of the ePay link.
 * @return string The HTML badge element representing the status.
 */
function renderStatus($status)
{
    // Define an anonymous function to retrieve the status color
    $statusColor = function ($status) {
        // Get the color associated with the status from the EpayLink::STATUS_COLOR array
        // If the status is not found, default to 'light'
        return EpayLink::STATUS_COLOR[$status] ?? 'light';
    };

    // Define an anonymous function to retrieve the status label
    $statusLabel = function ($status) {
        // Get the label associated with the status from the EpayLink::STATUS_LABEL array
        // If the status is not found, default to the original status string
        return esc_html(EpayLink::STATUS_LABEL[$status]) ?? esc_html($status);
    };

    // Start building the badge HTML
    $badge = "<span class=\"badge badge-pill badge-{$statusColor($status)} \">";

    // Add the status label to the badge
    $badge .= $statusLabel($status);

    // Close the badge HTML
    $badge .= '</span>';

    // Return the generated badge HTML
    return $badge;
}

/**
 * Adds the transaction details modal to the admin footer.
 *
 * This function generates the HTML markup for a modal dialog that displays
 * the details of a transaction. The modal is hidden by default and can be
 * displayed when needed, for example, when the user clicks on a button or
 * link associated with a specific transaction.
 *
 * The modal includes a heading, a horizontal rule, a content area for
 * displaying the transaction details, and a close button.
 *
 * The function also hooks the modal generation to the 'admin_footer' action,
 * ensuring that the modal is added to the admin page footer.
 *
 * @return void
 */
function add_transaction_details_modal()
{
?>
    <div id="transaction-details-modal" style="display:none;" title="<?php echo __('Detalles de transacción', 'epay-links'); ?>">
        <!-- Modal content area -->
        <div class="modal-content">
            <dl id="content-details"></dl>
        </div>
    </div>
<?php
}

// Hook the modal generation to the 'admin_footer' action
add_action('admin_footer', 'add_transaction_details_modal');

/**
 * Function to add the transaction refund modal to the page.
 */
function add_transaction_refund_modal()
{
?>
    <!-- Modal dialog for refund form -->
    <div id="dialog-refund-form" title="Reversión" style="display: none;">
        <!-- Confirmation message -->
        <h4><?php echo __('¿Deseas realizar esta acción?', 'epay-links'); ?></h4>
        <p>
            <?php echo __('Al realizar la reversión de esta transacción de pago no podrás revertir esta acción.', 'epay-links'); ?>
        </p>

        <!-- Details of the transaction -->
        <dl>
            <div class="row-detail">
                <dt><?php echo __('Transacción Id', 'epay-links'); ?></dt>
                <dd id="uuid"></dd>
            </div>
            <div class="row-detail">
                <dt><?php echo __('Monto', 'epay-links'); ?></dt>
                <dd id="amount"></dd>
            </div>
            <div class="row-detail">
                <dt><?php echo __('Nombre', 'epay-links'); ?></dt>
                <dd id="name"></dd>
            </div>
        </dl>
        <?php echo __('', 'epay-links'); ?>

        <!-- Horizontal rule to separate details from form -->
        <hr>

        <!-- Form to submit the refund request -->
        <form id="refund-epay-transaction">
            <fieldset>
                <!-- Hidden input for transaction ID -->
                <input type="hidden" name="transaction_id" id="transaction_id" value="">

                <!-- Reason for refund input field -->
                <p>
                    <label for="reason"><?php echo __('Motivo', 'epay-links'); ?></label>
                </p>
                <textarea name="reason" id="reason" style="width: 100%"></textarea>

                <!-- Hidden submit button to allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
            </fieldset>
        </form>
    </div>
<?php
}

// Hook to add the refund modal to the admin footer
add_action('admin_footer', 'add_transaction_refund_modal');

/**
 * Generates a payment link for ePay and stores the transaction details in the database.
 *
 * @param int $order_id The ID of the WooCommerce order for which the payment link is generated.
 * @param float $amount The amount to be paid through the payment link.
 * @return string The generated payment link.
 */
function generate_epay_payment_link($productName, $amount, $installments)
{
    // Generate a unique transaction ID
    $transactionId = wp_generate_uuid4();

    // Generate the payment link with query parameters
    $paymentLink = add_query_arg(array(
        'transaction_id' => $transactionId,
    ), site_url('/epay')); // URL of the payment page

    // Insert the payment link and transaction details into the database
    $record = EpayLink::storeLink([
        'product_name' => $productName,
        'transaction_id' => $transactionId,
        'payment_link' => $paymentLink,
        'amount' => $amount,
        'status' => EpayLink::STATUS_PENDING,
        'installments' => $installments,
        'created_at' => current_time('mysql'),
    ]);

    if (!$record) {
        throw new Exception(__('Fallo al generar link de pago', 'epay-links'));
    }

    // Return the generated payment link
    return $paymentLink;
}

/**
 * Retrieves a transaction by its ID from the ePay transactions table.
 *
 * This function queries the ePay transactions table in the WordPress database
 * to fetch a specific transaction based on the provided transaction ID.
 *
 * @param string $transaction_id The unique identifier of the transaction to retrieve.
 * @return object|null The transaction object if found, or null if not found.
 */
function getTransactionById($transaction_id)
{
    global $wpdb;

    // Define the table name with the WordPress prefix
    $table_name = $wpdb->prefix . 'epay_transactions';

    // Prepare and execute the query to retrieve the transaction
    $query = $wpdb->prepare("SELECT * FROM $table_name WHERE transaction_id = %s", $transaction_id);

    return $wpdb->get_row($query);
}

/**
 * Shortcode handler to render the ePay payment form.
 *
 * This function generates the ePay payment form based on the transaction ID provided in the URL.
 * It checks the validity of the transaction ID, retrieves the corresponding payment link details,
 * and displays the payment form with the necessary fields for processing the payment.
 *
 * If the transaction ID is invalid or the payment link does not exist, an error notice is displayed.
 * Additionally, the function ensures that the payment link is only valid for 72 hours after creation.
 *
 * @return string The HTML markup for the ePay payment form or an error notice if applicable.
 */
function epay_payment_form_shortcode()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $recaptcha = sanitize_text_field($_GET['g-recaptcha-response']);

        if (!isset($recaptcha)) {
            return renderWCNotice(
                __('Lo sentimos, fallo al verificar el reCAPTCHA.', 'epay-links'),
                'error'
            );
        }
    }

    // Sanitize the transaction ID from the URL
    $transactionId = sanitize_text_field($_GET['transaction_id']);

    // Check if the transaction ID is provided
    if (!$transactionId) {
        return renderWCNotice(
            __('Lo sentimos, el enlace enviado no es correcto. Para acceder al recurso, por favor contacta a nuestro equipo de soporte.', 'epay-links'),
            'error'
        );
    }

    // Retrieve the payment link details by transaction ID
    $paymentLink = EpayLink::getLinkTransaction($transactionId);

    if (!$paymentLink) {
        return renderWCNotice(
            __('Lo sentimos, el recurso no está disponible. Para acceder al recurso, por favor contacta a nuestro equipo de soporte.', 'epay-links'),
            'error'
        );
    }

    if ($paymentLink->status == EpayLink::STATUS_PAID) {
        return renderWCNotice(
            __('El pago ya está compleado.', 'epay-links'),
            'success'
        );
    }

    // Calculate the time difference since the payment link was created
    $created_at = strtotime($paymentLink->created_at);
    $current_time = current_time('timestamp', 0);
    $time_difference = ($current_time - $created_at) / 3600;

    // Check if the payment link is older than 72 hours
    if ($time_difference > 72) {
        return renderWCNotice(
            __('Lo sentimos, el enlace ha expirado. Para acceder al recurso, por favor contacta a nuestro equipo de soporte.', 'epay-links'),
            'error'
        );
    }

    if (!in_array($paymentLink->status, EpayLink::getAllowedTransactionStatuses())) {
        return renderWCNotice(
            __('Lo sentimos, el enlace ha expirado o no está disponible, para acceder al recurso, por favor contacta a nuestro equipo de soporte para obtener ayuda.', 'epay-links'),
            'error'
        );
    }

    if ($paymentLink->deleted_at) {
        return renderWCNotice(
            __('El recurso no está disponible.', 'epay-links'),
            'error'
        );
    }

    // Extract transaction information
    $amount = $paymentLink->amount;
    $product_name = $paymentLink->product_name;
    $installment_period = (int) $paymentLink->installments;

    // Process the payment if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = epay_process_payment($paymentLink, $_POST);
    }

    // Start output buffering to capture HTML
    ob_start();
?>
    <div id="epay-payment">
        <?php
        // Display the payment response message if the form has been submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo renderWCNotice($response['message'], $response['result']);
        }
        ?>

        <h2><?php echo __('Pagar con Tarjeta', 'epay-links'); ?></h2>

        <hr>

        <p><?php echo __('Nombre', 'epay-links'); ?>: <?php echo esc_html($product_name); ?></p>
        <p><?php echo __('Monto a pagar', 'epay-links'); ?>: <?php echo wc_price($amount); ?></p>
        <p><?php echo __('Cuotas aplicadas', 'epay-links'); ?>: <?php echo esc_html($installment_period ?: '1 pago'); ?></p>

        <form id="epay-payment-form" method="post" action="" class="wc-credit-card-form wc-payment-form">
            <input type="hidden" name="transaction_id" value="<?php echo esc_attr($transactionId); ?>">

            <label for="epay_ccNo"><?php echo __('Número de Tarjeta', 'epay-links'); ?> <span class="required">*</span></label>
            <div id="epay-card-number">
                <input id="epay_ccNo" name="epay_ccNo" type="text" autocomplete="off" required>
                <span id="epay-card-type"></span>
                <span id="epay-card-success"></span>
            </div>

            <div class="form-row form-row-first">
                <label for="epay_expdate"><?php echo __('Fecha de Expiración (MM/YY)', 'epay-links'); ?> <span class="required">*</span></label>
                <input id="epay_expdate" name="epay_expdate" type="text" autocomplete="off" placeholder="MM / YY" required>
            </div>

            <div class="form-row form-row-wide">
                <label for="epay_cvv"><?php echo __('Código de seguridad (CVV)', 'epay-links'); ?> <span class="required">*</span></label>
                <input id="epay_cvv" name="epay_cvv" type="password" autocomplete="off" placeholder="CVC" required>
            </div>

            <div class="form-row form-row-wide">
                <label for="cardholder"><?php echo __('Nombre en tarjeta', 'epay-links'); ?> <span class="required">*</span></label>
                <input id="cardholder" name="cardholder" type="text" autocomplete="off" required oninput="this.value = this.value.toUpperCase();">
            </div>

            <input type="hidden" name="installment_period" value="<?php echo $installment_period; ?>">

            <?php do_action('woocommerce_review_order_before_submit'); ?>

            <div class="text-center">
                <button type="submit" class="button alt"><?php echo __('Pagar Ahora', 'epay-links'); ?></button>
            </div>
        </form>
    </div>
<?php
    // Return the captured HTML content
    return ob_get_clean();
}

// Register the shortcode for the payment form
add_shortcode('epay_payment_form', 'epay_payment_form_shortcode');

/**
 * Processes the payment for a given transaction using the provided payment data.
 *
 * This function validates the input data for required fields, creates an instance of the
 * EpayTransaction class, and calls its processPayment method to handle the actual payment
 * processing. If the required data is missing, it returns an error message.
 *
 * @param object $transaction The transaction object containing transaction details.
 * @param array $data The payment data submitted by the user, including credit card details.
 * @return array An associative array containing:
 *               - 'message': A message indicating the result of the payment processing.
 *               - 'result': The status of the payment process ('success' or 'error').
 */
function epay_process_payment($transaction, $data)
{
    // Validate the received payment data
    if (empty($data['epay_ccNo']) || empty($data['epay_expdate']) || empty($data['epay_cvv'])) {
        return [
            'message' => 'Faltan datos requeridos para procesar el pago.',
            'result' => 'error',
        ];
    }

    // Create an instance of the EpayTransaction class
    $tranxInstance = new EpayTransaction();

    // Call the processPayment method to handle the payment processing
    $response = $tranxInstance->processPayment($data, $transaction->transaction_id);

    // Return the response from the payment processing
    return $response;
}

/**
 * Expires old ePay links that have exceeded the defined time limit.
 *
 * This function checks the ePay transactions table for links that have been
 * created for more than 72 hours and are not already marked as expired.
 * If such links are found, their status is updated to 'expired', and a note
 * stating "Link expirado en proceso automático" is added to the notes field.
 * If there are existing notes, the new note is appended to the existing notes.
 *
 * @return void
 */
function expire_old_epay_links()
{
    global $wpdb;

    // Define the table name for ePay links
    $tableName = $wpdb->prefix . EpayLink::DB_TABLE;

    // Calculate the expiration limit in hours (72 hours in this case)
    $expiration_limit = 72;

    // Get the links that have exceeded the expiration limit and are not already expired
    $links_to_expire = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, notes
             FROM $tableName
             WHERE TIMESTAMPDIFF(HOUR, created_at, NOW()) > %d
             AND ( status = 'pending'
             OR status = 'error'
             OR status = 'rejected' )",
            $expiration_limit
        )
    );

    // Loop through each link that needs to be expired
    foreach ($links_to_expire as $link) {
        // Prepare the new note to be added
        $new_notes = "Link expirado en proceso automático.";

        // If there are existing notes, append the new note
        if (!empty($link->notes)) {
            $date = current_time('mysql');
            $new_notes = $link->notes . " | [$date] " . $new_notes;
        }

        // Update the status to 'expired' and set the new notes
        $wpdb->update(
            $tableName,
            array(
                'status' => 'expired',
                'notes' => $new_notes,
            ),
            array('id' => $link->id)
        );
    }
}

/**
 * Retrieves a value from the $_GET superglobal array, sanitizing it for safe output.
 *
 * This function checks if a specific field exists in the $_GET array. If the field is set,
 * it returns the sanitized value using esc_attr to prevent XSS attacks. If the field is not set,
 * it returns an empty string.
 *
 * @param string $field The name of the field to retrieve from the $_GET array.
 * @return string The sanitized value of the specified field, or an empty string if not set.
 */
function old($field)
{
    return isset($_GET[$field]) ? esc_attr($_GET[$field]) : '';
}

/**
 * Dumps the provided arguments in a human-readable format.
 *
 * This function retrieves all arguments passed to it using `func_get_args()`
 * and outputs them using `var_dump` enclosed in `<pre>` tags for better readability.
 * It is useful for debugging purposes to inspect the values of variables or objects.
 *
 * @return void
 */
function dd()
{
    // Retrieve all arguments passed to the function
    $args = func_get_args();

    // Output the arguments in a preformatted block
    echo '<pre>';
    var_dump($args);
    echo '</pre>';
}
