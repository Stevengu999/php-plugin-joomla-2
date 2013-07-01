<?php

defined ('_JEXEC') or die('Restricted access');

require_once JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
require_once __DIR__ . '/PaynetProcesorAggregate.php';

use PaynetEasy\Paynet\PaynetProcessorAggregate;

class plgVMPaymentPaynet extends vmPSPlugin
{
    /**
     * Aggregate for order processing
     * For lazy loading use plgVMPaymentPaynet::getPaynetProcessorAggregate()
     *
     * @see plgVMPaymentPaynet::getPaynetProcessorAggregate()
     *
     * @var PaynetProcessorAggregate
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
	 * @param       $cart
	 * @param       $order
     *
	 * @return      bool|null
	 */
	public function plgVmConfirmedOrder(VirtueMartCart $cart, array $order)
    {
        $config = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);

		if (    !$config
            ||  !$this->selectedThisElement($config->payment_element))
        {
			return false; // Another method was selected, do nothing
		}

        $response = $this
            ->getPaynetProcessorAggregate($config)
            ->startSale($order);

        header("Location: {$response->getRedirectUrl()}");
        exit;
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
            'transport_stage'                       => 'char(64)',
            'status'                                => 'char(64)'
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
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, array &$htmlIn = array())
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
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName)
    {
		return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName);
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
     * @param       VirtueMartCart          $cart           Shopping cart
     * @param       stdClass                $method         Payment method options
	 * @param       array                   $prices         Shopping cart price list
     *
	 * @return      boolean
	 */
	protected function checkConditions($cart, $method, $prices)
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
            $this->paynetProcessorAggregate = new PaynetProcessorAggregate($config);
        }

        return $this->paynetProcessorAggregate;
    }
}
