<?php
	/*******************************************************************************
	*                                                                             *
	* @version  MelliPayment.php version 1.0                                      *
	* @copyright Copyright (c) 2011.                                              *
	* @license http://www.opensource.org/licenses/gpl-2.0.php GNU Public License. *
	* @author Mahdi Razavi  Razavi.Dev@gmail.com                                  *
	*                                                                             *
	*******************************************************************************/

	include(dirname(__FILE__).'/../../config/config.inc.php');
	include(dirname(__FILE__).'/../../header.php');
	include(dirname(__FILE__).'/mellipayment.php');

	if (!$cookie->isLogged())
		Tools::redirect('authentication.php?back=order.php');

	$mellipayment = new mellipayment();
	$result = $mellipayment->checkReceipt($_POST,false);

	if($result)
	{		
		$currency = new Currency(intval(isset($_POST['currency_payement']) ? $_POST['currency_payement'] : $cookie->id_currency));
		$total = floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', ''));

		$validateResult = $mellipayment->validateOrder($cart->id,  _PS_OS_PREPARATION_, $total, $mellipayment->displayName, NULL, NULL, $currency->id);
		if($validateResult)
		{
			$mellipayment->saveOrderID($_POST['x_fp_sequence'],$mellipayment->currentOrder);
		}

		Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?id_cart='.$cart->id.'&id_module='.$mellipayment->id.'&id_order='.$mellipayment->currentOrder.'&key='.$order->secure_key);
	}
	include_once(dirname(__FILE__).'/../../footer.php');
?>