<?php

/**
 * Class EpayLink
 *
 * This class manages the integration with the ePay payment gateway, providing
 * functionalities for processing payment transactions, managing transaction
 * details, and storing or retrieving payment links in the database. It leverages
 * the WordPress database abstraction layer (wpdb) for database operations.
 *
 * Key Features:
 * - Constants for various transaction statuses (e.g., disabled, refunded, pending).
 * - Methods for retrieving allowed and all possible transaction statuses.
 * - Methods for storing payment links and retrieving transaction details by ID.
 * - Error handling and status updates for payment transactions.
 * - Support for both live and sandbox environments for testing purposes.
 *
 * The class also includes arrays that map statuses to their corresponding labels
 * and CSS classes for user interface representation.
 */
class EpayLink
{
    const DB_TABLE = 'epay_transactions';

    // Define possible statuses for ePay links
    const STATUS_DISABLED = 'disabled'; // Status indicating the link is disabled
    const STATUS_REFUNDED = 'refunded'; // Status indicating the link has been refunded
    const STATUS_REJECTED = 'rejected'; // Status indicating the link has been rejected
    const STATUS_EXPIRED = 'expired'; // Status indicating the link has expired
    const STATUS_PENDING = 'pending'; // Status indicating the link is pending
    const STATUS_ERROR = 'error'; // Status indicating there was an error with the link
    const STATUS_PAID = 'paid'; // Status indicating the link has been paid

    /**
     * Array mapping statuses to their corresponding labels.
     *
     * This array provides a human-readable label for each status in the ePay system,
     * allowing for easier display and localization. The keys are the status constants,
     * and the values are the corresponding translated strings.
     *
     * @var array $STATUS_LABEL
     */
    const STATUS_LABEL = [
        self::STATUS_DISABLED => 'Deshabilitado',
        self::STATUS_REFUNDED => 'Anulado',
        self::STATUS_REJECTED => 'Rechazado',
        self::STATUS_EXPIRED => 'Expirado',
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_ERROR => 'Error',
        self::STATUS_PAID => 'Pagado',
    ];

    /**
     * Array mapping statuses to their corresponding CSS classes for styling.
     *
     * This array associates each status with a CSS class that can be used to style
     * the display of the status in the user interface. The keys are the status constants,
     * and the values are the corresponding CSS class names.
     *
     * @var array $STATUS_COLOR
     */
    const STATUS_COLOR = [
        self::STATUS_DISABLED => 'light', // Light color for disabled status
        self::STATUS_REJECTED => 'danger', // Danger color for rejected status
        self::STATUS_EXPIRED => 'light', // Light color for expired status
        self::STATUS_PENDING => 'info', // Info color for pending status
        self::STATUS_ERROR => 'danger', // Danger color for error status
        self::STATUS_PAID => 'success', // Success color for paid status
        self::STATUS_REFUNDED => 'warning', // Warning color for refunded status
    ];

    /**
     * Retrieves an array of transaction statuses that allow processing payments.
     *
     * This method returns an array of statuses in which transactions can be processed.
     * It is useful for determining the current state of a payment link and whether
     * transactions can be initiated based on its status.
     *
     * @return array An array of allowed transaction statuses.
     */
    public static function getAllowedTransactionStatuses()
    {
        return [
            self::STATUS_REJECTED,
            self::STATUS_PENDING,
            self::STATUS_ERROR
        ];
    }

    /**
     * Retrieves an array of all possible statuses for ePay links.
     *
     * This static method returns an array containing the various statuses
     * that an ePay link can have throughout its lifecycle. These statuses
     * are used to track the state of payment links and determine their
     * eligibility for processing transactions.
     *
     * The statuses included in the array are:
     * - STATUS_DISABLED: The payment link is disabled and cannot be used.
     * - STATUS_REFUNDED: The payment link has been refunded.
     * - STATUS_REJECTED: The payment link has been rejected.
     * - STATUS_EXPIRED: The payment link has expired and is no longer valid.
     * - STATUS_PENDING: The payment link is pending and awaiting action.
     * - STATUS_ERROR: An error occurred with the payment link.
     * - STATUS_PAID: The payment link has been successfully paid.
     *
     * @return array An array of ePay link statuses.
     */
    public static function getAllStatuses()
    {
        return [
            self::STATUS_DISABLED,
            self::STATUS_REFUNDED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_PENDING,
            self::STATUS_ERROR,
            self::STATUS_PAID,
        ];
    }

    /**
     * Stores a payment link and transaction details in the database.
     *
     * This static method inserts the provided data into the ePay transactions table.
     * It uses the global $wpdb object to perform the database operation. The method
     * returns the result of the insert operation, which can be used to determine
     * if the operation was successful.
     *
     * @param array $data An associative array containing the transaction details to be stored.
     * @return int|false The number of rows affected or false on error.
     */
    public static function storeLink($data)
    {
        global $wpdb;

        // Define the table name for storing ePay transactions
        $tableName = $wpdb->prefix . self::DB_TABLE;

        // Insert the payment link and transaction details into the database
        $record = $wpdb->insert(
            $tableName,
            $data
        );

        return $record; // Return the result of the insert operation
    }

    /**
     * Retrieves a transaction from the database by its transaction ID.
     *
     * This static method queries the ePay transactions table to fetch the details
     * of a specific transaction based on the provided transaction ID. It constructs
     * a SQL query to select all columns from the table where the transaction_id
     * matches the given ID. The result of the query is returned as a single row object.
     *
     * @param string $transactionId The unique identifier of the transaction to retrieve.
     * @return object|null The transaction details as an object, or null if no matching record is found.
     */
    public static function getLinkTransaction($transactionId)
    {
        global $wpdb;

        // Define the table name with the WordPress prefix
        $tableName = $wpdb->prefix . self::DB_TABLE;

        // Prepare and execute the query to retrieve the transaction
        $query = $wpdb->prepare("SELECT * FROM $tableName WHERE transaction_id = %s", $transactionId);

        // Return the result of the query as a single row object
        return $wpdb->get_row($query);
    }

    /**
     * Retrieves a transaction link from the database by its unique ID.
     *
     * This static method queries the ePay transactions table to fetch the details
     * of a specific transaction based on the provided link ID. It constructs a
     * SQL query to select all columns from the table where the ID matches the
     * given link ID. The result of the query is returned as a single row object.
     *
     * @param string $linkId The unique identifier of the transaction link to retrieve.
     * @return object|null The transaction details as an object, or null if no matching record is found.
     */
    public static function getTransactionLinkById($linkId)
    {
        global $wpdb;

        // Define the table name for ePay transactions
        $tableName = $wpdb->prefix . self::DB_TABLE;

        // Prepare the SQL query to select the transaction details by ID
        $query = $wpdb->prepare("SELECT * FROM $tableName WHERE id = %s", $linkId);

        // Execute the query and return the result as a single row object
        return $wpdb->get_row($query);
    }
} // End EpayLink
