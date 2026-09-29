<?php

/**
 * EpayTransaction Class for Processing Epay Transactions in WordPress
 *
 * This class handles the interaction with the Epay gateway for processing
 * payments within a WooCommerce environment. It provides methods for:
 *  - Retrieving Epay gateway settings
 *  - Processing a payment using the Epay API
 *  - Updating Epay transaction status in the database
 *  - Retrieving user IP address and merchant IP address
 *
 * @since 1.0.0
 */
class EpayTransaction
{
    /** @var object $gateway Epay gateway data retrieved from WooCommerce settings */
    protected $gateway;

    /** @var string $live_merchant_user Epay live merchant user ID (from WooCommerce settings) */
    private $live_merchant_user;

    /** @var string $live_merchant_passwd Epay live merchant password (from WooCommerce settings) */
    private $live_merchant_passwd;

    /** @var string $live_terminal_id Epay live terminal ID (from WooCommerce settings) */
    private $live_terminal_id;

    /** @var string $live_merchant Epay live merchant code (from WooCommerce settings) */
    private $live_merchant;

    /** @var string $sandbox_merchant_user Epay sandbox merchant user ID (from WooCommerce settings) */
    private $sandbox_merchant_user;

    /** @var string $sandbox_merchant_passwd Epay sandbox merchant password (from WooCommerce settings) */
    private $sandbox_merchant_passwd;

    /** @var string $sandbox_terminal_id Epay sandbox terminal ID (from WooCommerce settings) */
    private $sandbox_terminal_id;

    /** @var string $sandbox_merchant Epay sandbox merchant code (from WooCommerce settings) */
    private $sandbox_merchant;

    /** @var string $testmode Indicates whether to use sandbox or live environment (from WooCommerce settings) */
    private $testmode;

    /** @var int $timeout Connection timeout for SOAP requests (seconds) */
    private $timeout;

    /** @var string $url Epay API endpoint URL (based on test mode) */
    private $url;

    /** @var array $params Transaction parameters to be sent to Epay API */
    private $params = [];

    /** @var string $paymentgwIP Epay payment gateway IP address (hardcoded for now) */
    private $paymentgwIP = '190.149.69.135';

    /** @var object $transaction Transaction data retrieved from the database */
    private $transaction;

    /** @var array $postData Payment data submitted by the customer */
    private $postData;

    /** @var int $installments Number of installments selected by the customer (if applicable) */
    private $installments;

    /** @var array $response Contains the response data returned from the payment gateway. */
    private $response;

    /**
     * Array of response codes and their corresponding descriptions.
     *
     * This array maps the response codes returned by the payment gateway
     * to their respective descriptions. The keys are the response codes,
     * and the values are the corresponding descriptions in Spanish.
     *
     * @var array $responseCodes
     */
    const RESPONSE_CODES = [
        '00' => 'Aprobada',
        '01' => 'Refiérase al Emisor',
        '02' => 'Refiérase al Emisor',
        '05' => 'Transacción No Aceptada',
        '13' => 'Monto Inválido',
        '19' => 'Transacción no realizada, intente de nuevo',
        '31' => 'Tarjeta no soportada por switch',
        '35' => 'Transacción ya ha sido ANULADA',
        '36' => 'Transacción a ANULAR no EXISTE',
        '37' => 'Transacción de ANULACION REVERSADA',
        '38' => 'Transacción a ANULAR con Error',
        '41' => 'Tarjeta Extraviada',
        '43' => 'Tarjeta Robada',
        '51' => 'No tiene fondos disponibles',
        '57' => 'Transacción no permitida',
        '58' => 'Transacción no permitida en la terminal',
        '65' => 'Límite de actividad excedido',
        '89' => 'Terminal inválida',
        '91' => 'Emisor no disponible',
        '94' => 'Transacción duplicada',
        '96' => 'Error del sistema, intente más tard',
    ];

    const ACTION_PAYMENT = 'payment';
    const ACTION_REFUND = 'refund';
    const ACTION_REVERSE = 'reverse';

    const ACTIONS = [
        self::ACTION_PAYMENT => 'Pago/venta',
        self::ACTION_REFUND => 'Anulación',
        self::ACTION_REVERSE => 'Reversión',
    ];

    /**
     * Class constructor
     *
     * Initializes the class properties and retrieves Epay gateway settings
     * from WooCommerce.
     */
    public function __construct()
    {
        $this->getEpayGateway();

        $this->live_merchant_user = $this->getGatewaySetting('live_merchant_user');
        $this->live_merchant_passwd = $this->getGatewaySetting('live_merchant_passwd');
        $this->live_terminal_id = $this->getGatewaySetting('live_terminal_id');
        $this->live_merchant = $this->getGatewaySetting('live_merchant');
        $this->sandbox_merchant_user = $this->getGatewaySetting('sandbox_merchant_user');
        $this->sandbox_merchant_passwd = $this->getGatewaySetting('sandbox_merchant_passwd');
        $this->sandbox_terminal_id = $this->getGatewaySetting('sandbox_terminal_id');
        $this->sandbox_merchant = $this->getGatewaySetting('sandbox_merchant');
        $this->testmode = $this->getGatewaySetting('testmode');
        $this->installments = $this->getGatewaySetting('installments');
        $this->paymentgwIP = '190.149.69.135';

        // Configure timeout and WSDL URL based on test mode
        if ($this->testmode == 'yes') {
            $this->timeout = 10;
            $this->url = 'https://epaytestvisanet.com.gt/paymentcommerce.asmx?WSDL';
        } else {
            $this->timeout = 60;
            $this->url = 'https://epayvisanet.com.gt/paymentcommerce.asmx?WSDL';
        }
    }

    /**
     * Converts a monetary amount from a string format to an integer format.
     *
     * This method takes a total amount as a string, processes it to remove
     * any thousand separators and decimal points, and converts it to an
     * integer representation. The conversion is done in such a way that
     * amounts with decimals are transformed into whole numbers by removing
     * the decimal point, while amounts without decimals are multiplied by
     * 100 to represent cents.
     *
     * For example:
     * - Input: "1,234.56" will be converted to 123456
     * - Input: "1,234" will be converted to 123400
     *
     * @param string $total The total amount as a string, which may include
     *                      thousand separators and a decimal point.
     * @return string The converted amount as an integer in string format.
     */
    public function convertAmountToInteger($total)
    {
        // Check if the number contains a decimal point
        if (strpos($total, '.') !== false) {
            // If it has decimals, remove thousand separators and decimal point
            $amount = str_replace(array(',', '.'), '', $total);
        } else {
            // If it does not have decimals, remove thousand separators and append '00'
            $amount = str_replace(',', '', $total) . '00';
        }

        return $amount; // Return the converted amount
    }

    /**
     * Masks the credit card number, displaying only the last four digits.
     *
     * This static method takes a credit card number as input, ensures that it contains
     * only digits, and then masks all but the last four digits. The masked number is
     * returned in the format "**** **** **** 1234", where "1234" represents the last
     * four digits of the original card number.
     *
     * This method is useful for displaying card information securely, ensuring that
     * sensitive data is not exposed while still providing the user with identifiable
     * information about their card.
     *
     * @param string $cardNumber The original credit card number to be masked.
     * @return string The masked credit card number with only the last four digits visible.
     */
    public static function maskCardNumber($cardNumber)
    {
        // Ensure that the card number contains only digits
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        // Get the last 4 digits of the card number
        $last_four_digits = substr($cardNumber, -4);

        // Return the masked number
        return str_repeat('**** ', 3) . $last_four_digits;
    }

    /**
     * Retrieves the last four digits of a credit card number.
     *
     * This static method takes a credit card number as input and ensures that it contains
     * only numeric digits. It then extracts and returns the last four digits of the card number.
     * This is useful for displaying a masked version of the card number while still allowing
     * identification of the card.
     *
     * @param string $card_number The original credit card number from which to extract the last four digits.
     * @return string The last four digits of the credit card number.
     */
    public static function getLastFourDigits($card_number)
    {
        // Remove all non-digit characters from the card number
        $card_number = preg_replace('/\D/', '', $card_number);

        // Return the last four digits of the card number
        return substr($card_number, -4);
    }

    /**
     * Retrieves the ePay gateway settings and initializes the gateway object.
     *
     * This method fetches the available payment gateways from WooCommerce and assigns
     * the ePay gateway settings to the class property for further processing.
     */
    private function getEpayGateWay()
    {
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $ePayGateway = $available_gateways['epay'];
        $this->gateway = (object) $ePayGateway;
    }

    /**
     * Retrieves a specific setting from the ePay gateway settings.
     *
     * @param string $option The setting key to retrieve.
     * @return mixed The value of the requested setting.
     */
     private function getGatewaySetting($option)
    {
        return $this->gateway->settings[$option];
    }

    /**
     * Gets the number of installments configured for the payment.
     *
     * @return mixed The number of installments.
     */
    public function getInstallments()
    {
        return $this->installments;
    }

    /**
     * Sets additional parameters needed for the payment request.
     *
     * @return void
     */
    private function setSettingsParams()
    {
        $this->params['posEntryMode'] = '012';

        // Manage audit number
        $auditNumber = get_option('audit_number');
        if ((int) $auditNumber > 999999) {
            update_option('audit_number', '000001');
        }
        $this->params['auditNumber'] = $auditNumber;

        // Set IP addresses and merchant credentials based on the mode
        $this->params['paymentgwIP'] = $this->paymentgwIP;
        $this->params['shopperIP'] = $this->getUserIp();
        $this->params['merchantServerIP'] = $this->getMerchantIp();

        if ($this->testmode == 'yes') {
            $this->params['merchantUser'] = $this->sandbox_merchant_user;
            $this->params['merchantPasswd'] = $this->sandbox_merchant_passwd;
            $this->params['terminalId'] = $this->sandbox_terminal_id;
            $this->params['merchant'] = $this->sandbox_merchant;
        } else {
            $this->params['merchantUser'] = $this->live_merchant_user;
            $this->params['merchantPasswd'] = $this->live_merchant_passwd;
            $this->params['terminalId'] = $this->live_terminal_id;
            $this->params['merchant'] = $this->live_merchant;
        }
    }

    /**
     * Processes the payment based on the provided data and transaction ID.
     *
     * @param array $postData The payment data submitted by the user.
     * @param string $transactionId The ID of the transaction being processed.
     * @return array An array containing the result message and status.
     */
    public function processPayment($postData, $transactionId)
    {
        $this->postData = $postData;

        // Retrieve transaction details
        $this->transaction = EpayLink::getLinkTransaction($transactionId);

        // Format the expiration date
        $date = explode('/', $postData['epay_expdate']);
        $expdate = $date[1] . $date[0]; // Formato YYMM

        // Set parameters for the payment request
        $this->params['pan'] = $postData['epay_ccNo'];
        $this->params['expdate'] = trim($expdate);
        $this->params['amount'] = $this->convertAmountToInteger(
            $this->transaction->amount
        );
        $this->params['cvv2'] = $postData['epay_cvv'];
        $this->params['messageType'] = '0200';
        $this->params['additionalData'] = '';

        // Handle installment periods if provided
        if (!empty($postData['installment_period'])) {
            $installment_period = $postData['installment_period'];
            $periods = [2, 3, 4, 6, 8];
            if (in_array($installment_period, $periods)) {
                $installment_period = 'VC0' . $installment_period;
            } else {
                $installment_period = 'VC' . $installment_period;
            }
            $this->params['additionalData'] = $installment_period;
        }

        // Set additional parameters and run the transaction
        $this->setSettingsParams();

        $this->response = $this->runTransaction();

        if (!isset($this->response['responseCode'])) {
            return [
                'message' => $this->response['message'],
                'result' => 'error'
            ];
        }

        // Check response code and update payment status accordingly
        if ($this->response['responseCode'] === '00') {
            $this->updateEpayPaymentStatus(
                $this->transaction->id,
                EpayLink::STATUS_PAID
            );
            $this->updateAuditNumber();

            return [
                'message' => 'Transacción realizada con éxito.',
                'result' => 'success'
            ];
        } else {
            $this->updateEpayPaymentStatus(
                $this->transaction->id,
                EpayLink::STATUS_REJECTED
            );

            $this->updateAuditNumber();
            return [
                'message' => 'Transacción rechazada. Por favor verifique los datos de la tarjeta',
                'result' => 'error'
            ];
        }
    }

    public function processRefund($postData, $linkId)
    {
        $this->postData = $postData;

        // Retrieve transaction details
        $this->transaction = EpayLink::getTransactionLinkById($linkId);

        // Set parameters for the payment request
        $this->params['messageType'] = '0202';
        $this->params['additionalData'] = '';

        // Set additional parameters and run the transaction
        $this->setSettingsParams();

        $this->params['auditNumber'] = $this->transaction->audit_number;

        $this->response = $this->runTransaction(self::ACTION_REFUND);

        if (!isset($this->response['responseCode'])) {
            return [
                'message' => $this->response['message'],
                'result' => 'error'
            ];
        }

        // Check response code and update payment status accordingly
        if ($this->response['responseCode'] === '00') {
            $this->updateEpayPaymentStatus(
                $this->transaction->id,
                EpayLink::STATUS_REFUNDED,
                $this->postData['reason']
            );

            return (object) [
                'message' => 'Transacción de anulación realizada con éxito 🎉',
                'result' => 'success'
            ];
        } else {
            return (object) [
                'message' => 'Transacción de anulación rechazada. Por favor verifique los datos de la transacción 🙏',
                'result' => 'error'
            ];
        }
    }

    /**
     * Processes a refund transaction (reversion) for a payment.
     *
     * This method initiates a refund request by setting the appropriate parameters
     * for the transaction and executing the refund operation. It utilizes the WooCommerce
     * logger to log the outcome of the refund process, including success and error messages.
     *
     * @return void
     */
    public function processReversion()
    {
        // Set parameters for the refund request
        $this->params['messageType'] = '0400'; // Message type for refund
        $this->params['additionalData'] = '';

        // Run the refund transaction
        $this->response = $this->runTransaction('Revertion');
    }

    /**
     * Executes the payment transaction using the SOAP client.
     *
     * @return array An array containing the result message and status.
     * @throws SoapFault If there is an error during the SOAP request.
     */
    private function runTransaction($action = self::ACTION_PAYMENT)
    {
        $typeLog = $action == self::ACTION_PAYMENT ? 'info' : 'notice';
        $logData = [
            'source' => 'epay-links',
            'transaction' => [
                'transaction_id' => $this->transaction->transaction_id,
                'name' => $this->transaction->product_name,
            ],
            'request' => [
                'action' => $action,
                'posEntryMode' => $this->params['posEntryMode'],
                'additionalData' => $this->params['additionalData'],
                'messageType' => $this->params['messageType'],
                'shopperIP' => $this->params['shopperIP'],
                'auditNumber' => $this->params['auditNumber'],
            ]
        ];

        if ($this->transaction->authorization_number) {
            $logData['transaction']['authorization_number'] = $this->transaction->authorization_number;
        }

        if (isset($this->params['pan'])) {
            $logData['request']['pan'] = self::maskCardNumber($this->params['pan']);
        }

        if (isset($this->params['expdate'])) {
            $logData['request']['expdate'] = $this->params['expdate'];
        }

        if (isset($this->params['amount'])) {
            $logData['request']['amount'] = $this->params['amount'];
        }

        try {
            // Create an instance of the WooCommerce logger
            $logger = wc_get_logger();

            ini_set('default_socket_timeout', $this->timeout);

            // Create a SOAP client
            $client = new SoapClient(
                $this->url, array('connection_timeout' => $this->timeout)
            );

            // Prepare parameters for the SOAP call
            $params = array(array('AuthorizationRequest' => $this->params));
            $result = $client->__soapCall('AuthorizationRequest', $params);

            // Decode the response
            $response = json_decode(json_encode($result->response), true);

            $this->params['action'] = $action;

            $logData['responseCode'] = self::getResponseCodeDescription(
                $response['responseCode']
            );

            $logData['response'] = $response;

            if ($response['responseCode'] === '00') {
                // Log success message
                $logger->{$typeLog}(
                    "Transaction {$action} processed successfully for transaction ID: {$this->transaction->transaction_id}",
                    $logData
                );
            } else {
                $logger->error(
                    "Transaction {$action} process rejected for transaction ID: {$this->transaction->transaction_id}",
                    $logData
                );
            }

            return $response;
        } catch (SoapFault $e) {
            // Handle SOAP exceptions
            $this->updateEpayPaymentStatus(
                $this->transaction->id, EpayLink::STATUS_ERROR
            );

            $this->updateAuditNumber();

            $logData['error'] = $e->getMessage();

            // Log error message
            $logger->error(
                "{$action} processing failed for transaction ID: {$this->transaction->transaction_id}",
                $logData
            );

            $this->processReversion();

            return [
                'message' => 'Lo sentimos, ha ocurrido un error al procesar su solicitud. Por favor, intente nuevamente en unos minutos.',
                'result' => 'error',
            ];
        }
    }

    /**
     * Updates the audit number for the payment transactions.
     */
    private function updateAuditNumber()
    {
        $auditIndex = (int)get_option('audit_number');
        $auditNumber = str_pad($auditIndex + 1, 6, '0', STR_PAD_LEFT);
        update_option('audit_number', $auditNumber);
    }

    /**
     * Updates the payment status in the database for a given transaction.
     *
     * @param int $transactionId The ID of the transaction to update.
     * @param string $newStatus The new status to set for the transaction.
     * @param array $response Optional response data from the payment gateway.
     */
    private function updateEpayPaymentStatus(
        int $transactionId, string $newStatus, ?string $reason = ''
    ) {
        if ($transactionId) {
            global $wpdb;

            // Define the table name
            $tableName = $wpdb->prefix . EpayLink::DB_TABLE;

            $transactionLink = EpayLink::getTransactionLinkById($transactionId);

            // Prepare data to update
            $data['status'] = $newStatus;

            if ($newStatus === EpayLink::STATUS_PAID) {
                $data['approved_at'] = current_time('mysql');

                if (isset($this->response['authorizationNumber'])) {
                    $data['authorization_number'] = $this->response['authorizationNumber'];
                }
            }

            if (isset($this->postData['epay_ccNo'])) {
                $data['last_digits'] = self::getLastFourDigits($this->postData['epay_ccNo']);
            }

            if (isset($this->postData['cardholder_name'])) {
                $data['cardholder_name'] = $this->postData['cardholder'];
            }

            $data['audit_number'] = $this->params['auditNumber'];

            if (!empty($this->response)) {
                $date = current_time('mysql');
                $notes = "[$date] ";
                $notes .= self::ACTIONS[$this->params['action']] . ': ';
                $notes .= self::getResponseCodeDescription(
                    $this->response['responseCode']
                );

                if (!empty($reason)) {
                    $notes .= " / Razón: {$reason}";
                }

                // If there are existing notes, append the new note
                if (!empty($transactionLink->notes)) {
                    $notes = $transactionLink->notes . " | " . $notes;
                }

                $data['notes'] = $notes;

                if (!empty($transactionLink->transaction_response)) {
                    $transactionResponse = json_decode($transactionLink->transaction_response, true);
                    $transactionResponse[$newStatus] = $this->response;
                } else {
                    $transactionResponse = [$newStatus => $this->response];
                }
                $data['transaction_response'] = json_encode($transactionResponse);
            }

            $data['updated_at'] = current_time('mysql');

            // Update the status and approval date of the transaction
            $wpdb->update(
                $tableName,
                $data,
                array('id' => $transactionId)
            );
        }
    }

    /**
     * Retrieves the user's IP address.
     *
     * @return string The user's IP address.
     */
    public function getUserIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Retrieves the merchant's server IP address.
     *
     * @return string|false The merchant's IP address or false on failure.
     */
    public function getMerchantIp()
    {
        $host = gethostname();
        $ip = gethostbyname($host);
        return $ip ?: false;
    }

    /**
     * Retrieves the description for a given response code.
     *
     * This method accepts a response code as an index and returns the corresponding
     * description from the responseCodes array. If the response code does not exist,
     * it returns a default message indicating that there is no description available.
     *
     * @param string $code The response code for which to retrieve the description.
     * @return string The description of the response code or a default message if not found.
     */
    public static function getResponseCodeDescription($code)
    {
        // Check if the response code exists in the responseCodes array
        if (array_key_exists($code, self::RESPONSE_CODES)) {
            return "Código {$code}: " . self::RESPONSE_CODES[$code]; // Return the corresponding description
        } else {
            return "Código {$code} no tiene descripción."; // Return default message if not found
        }
    }
} // End EpayTransaction class
