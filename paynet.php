<?php

defined ('_JEXEC') or die('Restricted access');

if (!class_exists ('vmPSPlugin'))
{
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVMPaymentPaynet extends vmPSPlugin
{
    /**
     * @param       JDispatcher     $subject        Joomla dispatcher
     * @param       array           $config         Plugin config
     */
	function __construct (JDispatcher $subject, array $config)
    {
		parent::__construct($subject, $config);

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
	function plgVmDeclarePluginParamsPayment($name, $id, TablePaymentmethods $table)
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
	function plgVmSetOnTablePluginParamsPayment($name, $id, TablePaymentmethods $table)
    {
		return $this->setOnTablePluginParams($name, $id, $table);
	}
}
