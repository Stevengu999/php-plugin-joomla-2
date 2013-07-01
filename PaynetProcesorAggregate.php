<?php

namespace PaynetEasy\Paynet;

require_once __DIR__ . '/vendor/autoload.php';

use TablePaymentmethods;
use JRoute;
use JURI;
use JText;
use ShopFunctions;
use VmModel;

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
     * @param array $joomlaOrder
     */
    public function startSale(array $joomlaOrder)
    {
        $redirectUrl = $this->getRedirectUrl($joomlaOrder);
        $queryConfig = $this->getQueryConfig($redirectUrl);
        $paynetOrder = $this->getPaynetOrder($joomlaOrder);

        $response = $this
            ->getOrderProcessor()
            ->executeQuery('sale-form', $queryConfig, $paynetOrder);

        return $response;
    }

    public function finishSale(array $joomlaOrder)
    {
        ;
    }

    /**
     * Get payment query config
     *
     * @return      array       Payment query config
     */
    protected function getQueryConfig($redirectUrl = null)
    {
        $config = array
        (
            'end_point' => $this->paymentConfig->end_point,
            'login'     => $this->paymentConfig->login,
            'control'   => $this->paymentConfig->control,
        );

        if ($redirectUrl)
        {
            $config['redirect_url']         = $redirectUrl;
            $config['server_callback_url']  = $redirectUrl;
        }

        return $config;
    }

    /**
     * Transform joomla order to paynet order
     *
     * @param   array       $joomlaOrder                Joomla order
     *
     * @return  \PaynetEasy\Paynet\OrderData\Order      Paynet order
     */
    protected function getPaynetOrder(array $joomlaOrder)
    {
        $joomlaAddress  = $this->getJoomlaAddress($joomlaOrder);
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
            ->setDescription($this->getPaynetOrderDescription($joomlaOrder))
            ->setAmount($joomlaAddress->order_total)
            ->setCurrency($currencyCode)
            ->setIpAddress($joomlaAddress->ip_address)
            ->setCustomer($paynetCustomer)
        ;

        if (isset($joomlaAddress->paynet_order_id))
        {
            $paynetOrder->setPaynetOrderId($joomlaAddress->paynet_order_id);
        }

        return $paynetOrder;
    }

    /**
     * Get paynet order description
     *
     * @param           array       $joomlaOrder          Joomla order
     *
     * @return          string
     */
    protected function getPaynetOrderDescription(array $joomlaOrder)
    {
        $joomlaAddress  = $this->getJoomlaAddress($joomlaOrder);
        $storeName      = VmModel::getModel('Vendor')
                            ->getVendor($joomlaAddress->virtuemart_vendor_id)
                            ->vendor_store_name;

        return  JText::_('VMPAYMENT_PAYNET_SHOPPING_IN') . ': ' . $storeName . '; ' .
                JText::_('COM_VIRTUEMART_ORDER_ID') . ': ' . $joomlaAddress->order_numer;
    }

    /**
     * Get url for final payment processing
     *
     * @param       array       $joomlaOrder        Order
     *
     * @return      string
     */
    protected function getRedirectUrl(array $joomlaOrder)
    {
        $joomlaAddress  = $this->getJoomlaAddress($joomlaOrder);

        return JRoute::_(JURI::root() . 'index.php?option=com_virtuemart' .
                                                 '&view=pluginresponse' .
                                                 '&task=pluginresponsereceived' .
                                                 '&on=' . $joomlaAddress->order_number .
                                                 '&pm=' . $joomlaAddress->virtuemart_paymentmethod_id);
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
     * Get order billing or shipping address
     *
     * @param       array       $joomlaOrder        Order
     *
     * @return      stdClass                        Address
     */
    protected function getJoomlaAddress(array $joomlaOrder)
    {
        return $joomlaOrder['details']['ST'] ?: $joomlaOrder['details']['BT'];
    }
}