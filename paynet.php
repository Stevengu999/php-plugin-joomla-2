<?php

defined ('_JEXEC') or die('Restricted access');

jimport('joomla.log.log');

require_once JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
require_once JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';

use PaynetEasy\PaynetEasyApi\PaynetProcessorAggregate;

class plgVMPaymentPaynet extends vmPSPlugin
{
    /**
     * Aggregate for order processing
     * For lazy loading use plgVMPaymentPaynet::getPaynetProcessorAggregate()
     *
     * @see plgVMPaymentPaynet::getPaynetProcessorAggregate()
     *
     * @var PaynetEasy\PaynetEasyApi\PaynetProcessorAggregate
     */
    protected $paynetProcessorAggregate;

    /**
     * @param       JDispatcher     $dispatcher         Joomla dispatcher
     * @param       array           $config             Plugin config
     */
	public function __construct(JDispatcher $dispatcher, array $config)
    {
		parent::__construct($dispatcher, $config);

		$this->_tablepkey   = 'id';
		$this->_tableId     = 'id';
        $this->tableFields  = array_keys($this->getTableSQLFields());
		$this->setConfigParameterable($this->_configTableFieldName, $this->getVarsToPush());
	}

	/**
     * Start payment
     *
	 * @param       $cart
	 * @param       $order
     *
	 * @return      bool|null
	 */
	public function plgVmConfirmedOrder(VirtueMartCart $cart, array $order)
    {
        $config  = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);
        $address = $this->getAddress($order);

		if (    !$config
            ||  !$this->selectedThisElement($config->payment_element))
        {
			return null; // Another method was selected, do nothing
		}

        try
        {
            $response = $this
                ->getPaynetProcessorAggregate($config)
                ->startSale($address, $this->getReturnUrl($address));
        }
        catch (Exception $e)
        {
            $this->logException($e);
            $this->saveAddress($address);
            $this->cancelOrder($address, $config);

            JRequest::setVar('html', $this->getErrorMessage(), 'post');
            return;
        }

        $this->saveAddress($address);

		$cart->_confirmDone     = false;
		$cart->_dataValidated   = false;
		$cart->setCartIntoSession();

        JFactory::getApplication()->redirect($response->getRedirectUrl());
    }

	/**
     * Process response from payment system
	 */
	function plgVmOnPaymentResponseReceived(&$html)
    {
        $config = $this->getVmPluginMethod(JRequest::getInt('methodId', 0));

		if (    !$config
            ||  !$this->selectedThisElement($config->payment_element))
        {
			return null; // Another method was selected, do nothing
		}

        $order   = $this->getOrder(JRequest::getInt('orderId', 0));
        $address = $this->getAddress($order);

        try
        {
            $this
                ->getPaynetProcessorAggregate($config)
                ->finishSale($address, $_REQUEST);
        }
        catch (Exception $e)
        {
            $this->logException($e);
            $this->saveAddress($address);
            $this->cancelOrder($address, $config);

            $html = $this->getErrorMessage();
            return;
        }

        $this->saveAddress($address);

        if ($address->transaction_status == 'approved')
        {
            $html = $this->getSuccessMessage();
            $this->approveOrder($address, $config);
        }
        else
        {
            $this->cancelOrder($address, $config);
            $html = $this->getErrorMessage('VMPAYMENT_PAYNET_PAYMENT_DECLINED');
        }

		VirtueMartCart::getCart()->emptyCart();
	}

	/**
	 * Display stored payment data for an order
	 *
	 * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
	 */
	function plgVmOnShowOrderBEPayment($virtuemartOrderId, $paymentMethodId)
    {
		if (!$this->selectedThisByMethodId($paymentMethodId))
        {
			return null; // Another method was selected, do nothing
		}

        $order   = $this->getOrder($virtuemartOrderId);
        $address = $this->getAddress($order);

		$html  = '<table class="adminlist" width="50%">' . "\n";
		$html .= $this->getHtmlHeaderBE();

        $html .= $this->getHtmlRowBE('PAYNET_CLIENT_ORDER_ID',      $address->client_order_id);
        $html .= $this->getHtmlRowBE('PAYNET_PAYNET_ORDER_ID',      $address->paynet_order_id);
        $html .= $this->getHtmlRowBE('PAYNET_TRANSACTION_STATUS',   $address->transaction_status);
        $html .= $this->getHtmlRowBE('PAYNET_PAYMENT_STATUS',       $address->payment_status);

		$html .= '</table>' . "\n";
		return $html;
	}

	/**
     * Get plugin data table definition
     *
	 * @return      array
	 */
	public function getTableSQLFields()
    {
		return array
        (
			'id'                                    => 'int(11)         UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'                   => 'int(11)         UNSIGNED',
			'virtuemart_paymentmethod_id'           => 'int(3)          UNSIGNED',
			'client_order_id'                       => 'char(64)',
            'paynet_order_id'                       => 'char(64)',
            'transaction_status'                    => 'char(64)',
            'payment_status'                        => 'char(64)'
        );
	}

	/**
	 * Create the table for this plugin if it does not yet exist.
     *
     * @param       integer         $pluginId       Plugin ID
	 */
	function plgVmOnStoreInstallPaymentPluginTable($pluginId)
    {
		return $this->onStoreInstallPluginTable($pluginId);
	}

	/**
     * Creates plugin data table
     *
	 * @return      string      Query for table creation
	 */
	public function getVmPluginCreateTableSQL()
    {
		return $this->createTableSQL('Payment Paynet Table');
	}

    /**
     * Display plugin config
     *
     * @param       string                  $name           Plugin name
     * @param       integer                 $id             Plugin id
     * @param       TablePaymentmethods     $config         Table with plugin config
     *
     * @return      boolean
     */
	public function plgVmDeclarePluginParamsPayment($name, $id, TablePaymentmethods $config)
    {
		return $this->declarePluginParams('payment', $name, $id, $config);
	}

	/**
     * Save plugin config to DB
     *
     * @param       string                  $name           Plugin name
     * @param       integer                 $id             Plugin id
     * @param       TablePaymentmethods     $config         Table with plugin config
     *
	 * @return      boolean
	 */
	public function plgVmSetOnTablePluginParamsPayment($name, $id, TablePaymentmethods $config)
    {
		return $this->setOnTablePluginParams($name, $id, $config);
	}

	/**
	 * This event is fired to display the plugin methods in the cart
	 *
	 * @param       VirtueMartCart          $cart           Cart object
	 * @param       integer                 $selected       ID of the method selected
     * @param       array                   $htmlIn         Input html
     *
	 * @return      null|boolean                            True on succes, false on failures,
     *                                                      null when this plugin was not selected.
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, array &$htmlIn = array())
    {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/**
	 * Calculate the price (value, tax_id) of the selected method.
	 * It is called by the calculator.
     *
	 * @param       VirtueMartCart          $cart                   Cart object
	 * @param       array                   $cartPrices             New cart prices
	 * @param                               $cartPricesName         Varible for plugin tax html, fills by this method
     *
	 * @return      boolean|null                                    Null if the method was not selected,
     *                                                              false if the shipping rate is not valid any more,
     *                                                              true otherwise
	 */
	public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName)
    {
		return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName);
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @param       VirtueMartCart          $cart                   Cart object
	 * @param       array                   $cartPrices             New cart prices
	 * @param       integer                 $paymentCounter         Payment methods count
     *
	 * @return      null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cartPrices = array(), &$paymentCounter = null)
    {
		return $this->onCheckAutomaticSelected($cart, $cartPrices, $paymentCounter);
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @return      boolean     Always true
	 */
	protected function checkConditions($cart, $method, $cartPrices)
    {
		return true;
	}

    /**
     * Get aggregate for order processing
     *
     * @param       TablePaymentmethods     $config         Table with plugin config
     *
     * @return      PaynetProcessorAggregate
     */
    protected function getPaynetProcessorAggregate(TablePaymentmethods $config)
    {
        if (!$this->paynetProcessorAggregate)
        {
            require_once __DIR__ . '/paynet_processor_aggregate.php';
            $this->paynetProcessorAggregate = new PaynetProcessorAggregate($config);
        }

        return $this->paynetProcessorAggregate;
    }

    /**
     * Get order billing or shipping address
     *
     * @param       array           $order              Order
     *
     * @return      stdClass                            Address
     */
    protected function getAddress(array $order)
    {
        if (isset($order['details']['ST']))
        {
            return $order['details']['ST'];
        }
        elseif (isset($order['details']['BT']))
        {
            return $order['details']['BT'];
        }
        else
        {
            throw new RuntimeException('Address not found in order');
        }
    }

    /**
     * Save additional address data to DB
     *
     * @param       stdClass        $address            Address
     */
    protected function saveAddress(stdClass $address)
    {
        $this->storePSPluginInternalData(array
        (
            'virtuemart_order_id'           => $address->virtuemart_order_id,
			'virtuemart_paymentmethod_id'   => $address->virtuemart_paymentmethod_id,
			'client_order_id'               => $address->order_number,
            'paynet_order_id'               => $address->paynet_order_id,
            'transaction_status'            => $address->transaction_status,
            'payment_status'                => $address->payment_status
        ));
    }

    /**
     * Load additional address data from DB
     *
     * @param       stdClass        $address            Address
     */
    protected function loadAddress(stdClass $address)
    {
        $paynetData = $this->getDataByOrderId($address->virtuemart_order_id);

        $address->client_order_id       = $paynetData->client_order_id;
        $address->paynet_order_id       = $paynetData->paynet_order_id;
        $address->transaction_status    = $paynetData->transaction_status;
        $address->payment_status        = $paynetData->payment_status;
    }

    /**
     * Cancel order if error occured
     *
     * @param       stdClass                    $address            Address
     * @param       TablePaymentmethods         $config             Payment plugin config
     */
    protected function cancelOrder(stdClass $address, TablePaymentmethods $config)
    {
        $order['order_status']          = $config->order_failure_status;
        $order['virtuemart_order_id']   = $address->virtuemart_order_id;
        $order['customer_notified']     = 0;
        $order['comments']              = JText::_('VMPAYMENT_PAYNET_TECHNICAL_ERROR');

        VmModel::getModel('orders')->updateStatusForOneOrder($address->virtuemart_order_id, $order);
    }

    /**
     * Approve order
     *
     * @param       stdClass                    $address            Address
     * @param       TablePaymentmethods         $config             Payment plugin config
     */
    protected function approveOrder(stdClass $address, TablePaymentmethods $config)
    {
        $order['order_status']          = $config->order_success_status;
        $order['virtuemart_order_id']   = $address->virtuemart_order_id;
        $order['customer_notified']     = 1;
        $order['comments']              = JText::_('VMPAYMENT_PAYNET_PAYMENT_APPROVED');

        VmModel::getModel('orders')->updateStatusForOneOrder($address->virtuemart_order_id, $order);
    }

    /**
     * Display error message
     *
     * @param       string                      $message            Error message
     */
    protected function getErrorMessage($message = 'VMPAYMENT_PAYNET_TECHNICAL_ERROR')
    {
        JError::raiseWarning(500, JText::_($message));
        return JText::_('VMPAYMENT_PAYNET_PAYMENT_NOT_PASSED');
    }

    /**
     * Display success message
     */
    protected function getSuccessMessage()
    {
        return JText::_('VMPAYMENT_PAYNET_PAYMENT_APPROVED');
    }

    /**
     * Log exception
     *
     * @param       Exception       $exception          Exception to log
     */
    protected function logException(Exception $exception)
    {
        JLog::add($exception, JLog::ERROR);
    }

    /**
     * Get url for final payment processing
     *
     * @param       stdClass        $address        Joomla address
     *
     * @return      string
     */
    protected function getReturnUrl(stdClass $address)
    {
        return JRoute::_(JURI::root() . 'index.php?option=com_virtuemart' .
                                                 '&view=pluginresponse' .
                                                 '&task=pluginresponsereceived' .
                                                 '&orderId='  . $address->virtuemart_order_id .
                                                 '&methodId=' . $address->virtuemart_paymentmethod_id);
    }

    /**
     * Get order from DB by virtuemart order id.
     * Also method loads additional paynet address data.
     *
     * @param       integer         $orderId            Virtuemart order id
     *
     * @return      array                               Order
     */
    protected function getOrder($orderId)
    {
        $order = VmModel::getModel('orders')->getOrder($orderId);

        if (!$order)
        {
            $exception = new RuntimeException("Can not find order with id '{$orderId}'");
            $this->logException($exception);

            throw $exception;
        }

        $address = $this->getAddress($order);
        $this->loadAddress($address);

        return $order;
    }
}
