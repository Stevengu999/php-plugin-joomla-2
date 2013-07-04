<?php

namespace PaynetEasy\Paynet;

require_once __DIR__ . '/vendor/autoload.php';

use TablePaymentmethods;
use JText;
use ShopFunctions;
use VmModel;
use stdClass;

use PaynetEasy\Paynet\OrderData\Order;
use PaynetEasy\Paynet\OrderData\Customer;

use PaynetEasy\Paynet\OrderProcessor;

class PaynetProcessorAggregate
{
    /**
     * Order processor instance.
     * For lazy loading use PaynetProcessorAggregate::getOrderProcessor()
     *
     * @see PaynetProcessorAggregate::getOrderProcessor
     *
     * @var \PaynetEasy\Paynet\OrderProcessor
     */
    protected $orderProcessor;

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
     * Method executes query to paynet gateway and returns response from gateway.
     * After that user must be redirected to the Response::getRedirectUrl()
     *
     * @param       stdClass        $joomlaAddress                      Joomla address
     * @param       string          $returnUrl                          Url for final payment processing
     *
     * @return      \PaynetEasy\Paynet\Transport\Response               Gateway response object
     */
    public function startSale(stdClass $joomlaAddress, $returnUrl)
    {
        $queryConfig = $this->getQueryConfig($returnUrl);
        $paynetOrder = $this->getPaynetOrder($joomlaAddress);

        try
        {
            $response = $this
                ->getOrderProcessor()
                ->executeQuery('sale-form', $queryConfig, $paynetOrder);
        } catch (Exception $e) {}
        // finally
        {
            $this->updateAddress($joomlaAddress, $paynetOrder);
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
     * @param       array           $callbacData                        Callback data
     *
     * @return      \PaynetEasy\Paynet\Transport\CallbackResponse       Callback data object
     */
    public function finishSale(stdClass $joomlaAddress, array $callbacData)
    {
        $queryConfig = $this->getQueryConfig();
        $paynetOrder = $this->getPaynetOrder($joomlaAddress);

        try
        {
            $response = $this
                ->getOrderProcessor()
                ->executeCallback($callbacData, $queryConfig, $paynetOrder);
        } catch (Exception $e) {}
        // finally
        {
            $this->updateAddress($joomlaAddress, $paynetOrder);
            if (isset($e)) throw $e;
        }

        return $response;
    }

    /**
     * Get payment query config
     *
     * @return      array       Payment query config
     */
    protected function getQueryConfig($returnUrl = null)
    {
        $config = array
        (
            'end_point' => $this->paymentConfig->end_point,
            'login'     => $this->paymentConfig->login,
            'control'   => $this->paymentConfig->control,
        );

        if ($returnUrl)
        {
            $config['redirect_url']         = $returnUrl;
            $config['server_callback_url']  = $returnUrl;
        }

        return $config;
    }

    /**
     * Transform joomla order to paynet order
     *
     * @param   stdClass        $joomlaAddress          Joomla address
     *
     * @return  \PaynetEasy\Paynet\OrderData\Order      Paynet order
     */
    protected function getPaynetOrder(stdClass $joomlaAddress)
    {
        $paynetOrder    = new Order;
        $paynetCustomer = new Customer;

        $countryCode    = ShopFunctions::getCountryByID($joomlaAddress->virtuemart_country_id, 'country_2_code');
        $currencyCode   = ShopFunctions::getCurrencyByID($joomlaAddress->order_currency, 'currency_code_3');

        $paynetCustomer
            ->setCountry($countryCode)
            ->setCity($joomlaAddress->city)
            ->setAddress($joomlaAddress->address_1)
            ->setZipCode($joomlaAddress->zip)
            ->setPhone($joomlaAddress->phone_1 ?: '(000) 00-00-00')
            ->setEmail($joomlaAddress->email)
            ->setFirstName($joomlaAddress->first_name)
            ->setLastName($joomlaAddress->last_name)
        ;

        if (isset($joomlaAddress->virtuemart_state_id))
        {
            $stateCode = ShopFunctions::getStateByID($joomlaAddress->virtuemart_state_id, 'state_2_code');
            $paynetCustomer->setState($stateCode);
        }

        $paynetOrder
            ->setClientOrderId($joomlaAddress->order_number)
            ->setDescription($this->getPaynetOrderDescription($joomlaAddress))
            ->setAmount($joomlaAddress->order_total)
            ->setCurrency($currencyCode)
            ->setIpAddress($joomlaAddress->ip_address)
            ->setCustomer($paynetCustomer)
        ;

        if (isset($joomlaAddress->paynet_order_id))
        {
            $paynetOrder->setPaynetOrderId($joomlaAddress->paynet_order_id);
        }

        if (isset($joomlaAddress->transport_stage))
        {
            $paynetOrder->setTransportStage($joomlaAddress->transport_stage);
        }

        if (isset($joomlaAddress->paynet_status))
        {
            $paynetOrder->setStatus($joomlaAddress->paynet_status);
        }

        return $paynetOrder;
    }

    /**
     * Get paynet order description
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
     * @return      \PaynetEasy\Paynet\OrderProcessor
     */
    protected function getOrderProcessor()
    {
        if (is_null($this->orderProcessor))
        {
            if ($this->paymentConfig->sandbox_enabled)
            {
                $gatewayUrl = $this->paymentConfig->sandbox_gateway;
            }
            else
            {
                $gatewayUrl = $this->paymentConfig->production_gateway;
            }

            $this->orderProcessor = new OrderProcessor($gatewayUrl);
        }

        return $this->orderProcessor;
    }

    /**
     * Updates joomla address by paynet order data
     *
     * @param       stdClass        $joomlaAddress          Joomla address
     * @param       Order           $paynetOrder            Paynet order
     */
    protected function updateAddress(stdClass $joomlaAddress, Order $paynetOrder)
    {
        $joomlaAddress->paynet_order_id = $paynetOrder->getPaynetOrderId();
        $joomlaAddress->transport_stage = $paynetOrder->getTransportStage();
        $joomlaAddress->paynet_status   = $paynetOrder->getStatus();
    }
}