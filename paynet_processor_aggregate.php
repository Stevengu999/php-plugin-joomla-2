<?php

namespace PaynetEasy\PaynetEasyApi;

require_once __DIR__ . '/vendor/autoload.php';

use TablePaymentmethods;
use JText;
use ShopFunctions;
use VmModel;
use stdClass;

use RuntimeException;

use PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction;
use PaynetEasy\PaynetEasyApi\PaymentData\Payment;
use PaynetEasy\PaynetEasyApi\PaymentData\Customer;
use PaynetEasy\PaynetEasyApi\PaymentData\BillingAddress;

use PaynetEasy\PaynetEasyApi\Utils\Validator;
use PaynetEasy\PaynetEasyApi\PaymentData\QueryConfig;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;

use PaynetEasy\PaynetEasyApi\PaymentProcessor;

class PaynetProcessorAggregate
{
    /**
     * Order processor instance.
     * For lazy loading use PaynetProcessorAggregate::getPaymentProcessor()
     *
     * @see PaynetProcessorAggregate::getPaymentProcessor
     *
     * @var \PaynetEasy\PaynetEasyApi\PaymentProcessor
     */
    protected $paymentProcessor;

    /**
     * @var TablePaymentmethods
     */
    protected $paymentConfig;

    /**
     * @param       TablePaymentmethods         $paymentConfig          Config for payment method
     */
    public function __construct(TablePaymentmethods $paymentConfig)
    {
        $this->paymentConfig = $paymentConfig;
    }

    /**
     * Starts order processing.
     * Method executes query to PaynetEasy gateway and returns response from gateway.
     * After that user must be redirected to the Response::getRedirectUrl()
     *
     * @param       stdClass        $joomlaAddress                      Joomla address
     * @param       string          $returnUrl                          Url for final payment processing
     *
     * @return      \PaynetEasy\PaynetEasyApi\Transport\Response        Gateway response object
     */
    public function startSale(stdClass $joomlaAddress, $returnUrl)
    {
        $paynetTransaction = $this->getPaynetTransaction($joomlaAddress, $returnUrl);

        try
        {
            $response = $this
                ->getPaymentProcessor()
                ->executeQuery('sale-form', $paynetTransaction);
        } catch (Exception $e) {}
        // finally
        {
            $this->updateAddress($joomlaAddress, $paynetTransaction);
            if (isset($e)) throw $e;
        }

        return $response;
    }

    /**
     * Finish order processing.
     * Method checks callnack data and returns object with them.
     * After that order must be updated and saved.
     *
     * @param       stdClass        $joomlaAddress                      Joomla address
     * @param       array           $callbackData                       Callback data
     *
     * @return      \PaynetEasy\PaynetEasyApi\Transport\CallbackResponse       Callback data object
     */
    public function finishSale(stdClass $joomlaAddress, array $callbackData)
    {
        $paynetTransaction = $this->getPaynetTransaction($joomlaAddress);

        try
        {
            $callbackResponse = $this
                ->getPaymentProcessor()
                ->processCustomerReturn(new CallbackResponse($callbackData), $paynetTransaction);
        } catch (Exception $e) {}
        // finally
        {
            $this->updateAddress($joomlaAddress, $paynetTransaction);
            if (isset($e)) throw $e;
        }

        return $callbackResponse;
    }

    /**
     * Transform joomla order to PaynetEasy order
     *
     * @param       stdClass        $joomlaAddress      Joomla address
     * @param       string          $redirectUrl        Url for final payment processing
     *
     * @return      PaymentTransaction                  PaynetEasy order
     */
    protected function getPaynetTransaction(stdClass $joomlaAddress, $redirectUrl = null)
    {
        $queryConfig        = new QueryConfig;
        $paynetAddress      = new BillingAddress;
        $paynetTransaction  = new PaymentTransaction;
        $paynetPayment      = new Payment;
        $paynetCustomer     = new Customer;

        $countryCode    = ShopFunctions::getCountryByID($joomlaAddress->virtuemart_country_id, 'country_2_code');
        $currencyCode   = ShopFunctions::getCurrencyByID($joomlaAddress->order_currency, 'currency_code_3');

        $paynetAddress
            ->setCountry($countryCode)
            ->setCity($joomlaAddress->city)
            ->setFirstLine($joomlaAddress->address_1)
            ->setZipCode($joomlaAddress->zip)
            ->setPhone($joomlaAddress->phone_1 ?: '(000) 00-00-00')
        ;

        if (isset($joomlaAddress->virtuemart_state_id))
        {
            $stateCode = ShopFunctions::getStateByID($joomlaAddress->virtuemart_state_id, 'state_2_code');
            $paynetAddress->setState($stateCode);
        }

        $paynetCustomer
            ->setEmail($joomlaAddress->email)
            ->setFirstName($joomlaAddress->first_name)
            ->setLastName($joomlaAddress->last_name)
            ->setIpAddress($joomlaAddress->ip_address)
        ;

        $paynetPayment
            ->setClientId($joomlaAddress->order_number)
            ->setDescription($this->getPaynetOrderDescription($joomlaAddress))
            ->setAmount($joomlaAddress->order_total)
            ->setCurrency($currencyCode)
            ->setCustomer($paynetCustomer)
            ->setBillingAddress($paynetAddress)
        ;

        if (isset($joomlaAddress->paynet_order_id))
        {
            $paynetPayment->setPaynetId($joomlaAddress->paynet_order_id);
        }

        if (isset($joomlaAddress->payment_status))
        {
            $paynetPayment->setStatus($joomlaAddress->payment_status);
        }

        $queryConfig
            ->setEndPoint($this->getConfig('end_point'))
            ->setLogin($this->getConfig('login'))
            ->setSigningKey($this->getConfig('signing_key'))
            ->setGatewayMode($this->getConfig('gateway_mode'))
            ->setGatewayUrlSandbox($this->getConfig('sandbox_gateway'))
            ->setGatewayUrlProduction($this->getConfig('production_gateway'))
        ;

        if (Validator::validateByRule($redirectUrl, Validator::URL, false))
        {
            $queryConfig
                ->setRedirectUrl($redirectUrl)
                ->setCallbackUrl($redirectUrl)
            ;
        }

        $paynetTransaction
            ->setPayment($paynetPayment)
            ->setQueryConfig($queryConfig)
        ;

        if (isset($joomlaAddress->transaction_status))
        {
            $paynetTransaction->setStatus($joomlaAddress->transaction_status);
        }

        return $paynetTransaction;
    }

    /**
     * Get PaynetEasy order description
     *
     * @param           stdClass        $joomlaAddress          Joomla address
     *
     * @return          string
     */
    protected function getPaynetOrderDescription(stdClass $joomlaAddress)
    {
        $storeName      = VmModel::getModel('Vendor')
                            ->getVendor($joomlaAddress->virtuemart_vendor_id)
                            ->vendor_store_name;

        return  JText::_('VMPAYMENT_PAYNET_SHOPPING_IN') . ': ' . $storeName . '; ' .
                JText::_('VMPAYMENT_PAYNET_CLIENT_ORDER_ID') . ': ' . $joomlaAddress->order_number;
    }

    /**
     * Get service for order processing
     *
     * @return      \PaynetEasy\PaynetEasyApi\PaymentProcessor
     */
    protected function getPaymentProcessor()
    {
        if (is_null($this->paymentProcessor))
        {
            $this->paymentProcessor = new PaymentProcessor;
        }

        return $this->paymentProcessor;
    }

    /**
     * Updates joomla address by PaynetEasy order data
     *
     * @param       stdClass                $joomlaAddress          Joomla address
     * @param       PaymentTransaction      $paymentTransaction     PaynetEasy payment transaction
     */
    protected function updateAddress(stdClass $joomlaAddress, PaymentTransaction $paymentTransaction)
    {
        $payment = $paymentTransaction->getPayment();

        $joomlaAddress->paynet_order_id     = $payment->getPaynetId();
        $joomlaAddress->transaction_status  = $paymentTransaction->getStatus();
        $joomlaAddress->payment_status      = $payment->getStatus();
    }

    /**
     * Get payment config node value
     *
     * @param       string      $key        Config node key
     *
     * @return      scalar                  Config node value
     */
    protected function getConfig($key)
    {
        if (isset($this->paymentConfig->$key))
        {
            return $this->paymentConfig->$key;
        }
        else
        {
            throw new RuntimeException("Unknown config node key '{$key}'");
        }
    }
}