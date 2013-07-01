<?php

defined ('_JEXEC') or die('Restricted access');

if (!class_exists ('vmPSPlugin'))
{
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVMPaymentPaynet extends vmPSPlugin
{
    /**
     * @param       JDispatcher     $dispatcher         Joomla dispatcher
     * @param       array           $config             Plugin config
     */
	public function __construct(JDispatcher $dispatcher, array $config)
    {
		parent::__construct($dispatcher, $config);

		$this->setConfigParameterable($this->_configTableFieldName, $this->getVarsToPush());
	}

    /**
     * Display plugin config
     *
     * @param       string                  $name           Plugin name
     * @param       integer                 $id             Plugin id
     * @param       TablePaymentmethods     $table          Table with plugin config
     *
     * @return      boolean
     */
	public function plgVmDeclarePluginParamsPayment($name, $id, TablePaymentmethods $table)
    {
		return $this->declarePluginParams('payment', $name, $id, $table);
	}

	/**
     * Save plugin config to DB
     *
     * @param       string                  $name           Plugin name
     * @param       integer                 $id             Plugin id
     * @param       TablePaymentmethods     $table          Table with plugin config
     *
	 * @return      boolean
	 */
	public function plgVmSetOnTablePluginParamsPayment($name, $id, TablePaymentmethods $table)
    {
		return $this->setOnTablePluginParams($name, $id, $table);
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
}
