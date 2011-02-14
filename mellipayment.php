<?php
	/*******************************************************************************
	*                                                                             *
	* @version  MelliPayment.php version 1.0                                      *
	* @copyright Copyright (c) 2011.                                              *
	* @license http://www.opensource.org/licenses/gpl-2.0.php GNU Public License. *
	* @author Mahdi Razavi  Razavi.Dev@gmail.com                                  *
	*                                                                             *
	*******************************************************************************/

	class mellipayment extends PaymentModule {

		private $_html = '';
		private $_postErrors = array();
		private $_postMessages = array();
		private $_responseReasonText = null;
		private $_ID;
		private $_TRANSKEY;
		private $_webURL;
		private $_webURLTest;
		private $_webURLVerify;
		private $_webURLVerifyTest;
		private $_testMode;
		private $_sequence;
		private $_TotalAmount;
		private $_CurrencyCode;

		public function __construct() {
			$this->name = 'mellipayment';
			$this->tab = 'Payment';
			$this->version = '1.1.0';
			$this->currencies = true;
			$this->currencies_mode = 'radio';
			parent::__construct();
			/* The parent construct is required for translations */
			$this->page = basename(__FILE__, '.php');
			$this->limited_countries = array('ir');
			$this->displayName = $this->l('Melli bank payment');
			$this->description = $this->l('Accept payments by Melli bank.');
			$this->confirmUninstall = $this->l('Are you sure, you want to delete your details?');
			if ($_SERVER['SERVER_NAME'] == 'localhost')
				$this->warning = $this->l('You are in localhost, Melli bank can\'t validate your orders.');
			$config = Configuration::getMultiple(array($this->name . '_ID', ''));
			if (!isset($config[$this->name . '_ID']))
				$this->warning = $this->l('Your Login ID must be configured in order to use this module');

			$config = Configuration::getMultiple(array($this->name . '_TRANSKEY', ''));
			if (!isset($config[$this->name . '_TRANSKEY']))
				$this->warning = $this->l('Your Transaction ID must be configured in order to use this module');

			$this->_ID = Configuration::get($this->name . '_ID');
			$this->_TRANSKEY = Configuration::get($this->name . '_TRANSKEY');
			$this->_webURL = 'https://Damoon.bankmelli-iran.com/DamoonPrePaymentController';
			$this->_webURLTest ='https://Damoon.bankmelli-iran.com/MerchantsIntegrationTestController';
			$this->_webURLVerify = 'https://Damoon.bankmelli-iran.com/DamoonVerificationController';
			$this->_webURLVerifyTest = 'https://Damoon.bankmelli-iran.com/VerificationTestController';

			$this->_testMode=false;
			$this->_CurrencyCode="Rial";
		}

		public function install() {
			if (!parent::install()
			OR !$this->registerHook('invoice')
			OR !$this->registerHook('payment')
			OR !$this->registerHook('paymentReturn')
			OR !$this->createPaymentTable() //calls function to create payment card table
			OR !Configuration::updateValue($this->name . '_ID', '')
			OR !Configuration::updateValue($this->name . '_TRANSKEY', ''))
				return false;
			return true;
		}

		public function uninstall() {
			if (!parent::uninstall()
			OR !Configuration::deleteByName($this->name . '_ID')
			OR !Configuration::deleteByName($this->name . '_TRANSKEY'))
				return false;
			return true;
		}

		public function getContent() {
			if (Tools::isSubmit('submit')) {
				if (!$loginId = Tools::getValue($this->name . '_ID') OR empty($loginId))
					$this->_postErrors[] = $this->l('Melli bank LoginId is required.');
				if (!$transactionKey = Tools::getValue($this->name . '_TRANSKEY') OR empty($transactionKey))
					$this->_postErrors[] = $this->l('Melli bank TransactionKey is required.');
				if (sizeof($this->_postErrors) < 1) {
					Configuration::updateValue($this->name . '_ID', Tools::getValue($this->name . '_ID'));
					Configuration::updateValue($this->name . '_TRANSKEY', Tools::getValue($this->name . '_TRANSKEY'));
					$this->_html .= '<div class="conf confirm">';
					$this->_html .= $this->l('Settings updated.');
					$this->_html .= '</div>';

				} else {
					$this->_html .= $this->l('Error in settings update.');
					//$this->displayErrors();
				}
			} 
			if(Tools::isSubmit('verify')) {
				$sequence = Tools::getValue('sequence_code');
				//$this->_html .= $this->l('Error in verify' . $sequence);
				$this->_postMessages[] = $this->l('Melli bank LoginId is required.');
				$verifyResult = $this->verificationOrders($sequence);
			}


			$this->_displayForm();
			return $this->_html;
		}

		private function _displayForm() {
			$this->_html .= '<h2>' . $this->l('Melli bank payment') . '</h2>';
			$this->_html .= '
			<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
			<label>' . $this->l('Login ID') . '</label>
			<div class="margin-form">
			<input type="text" name="' . $this->name . '_ID" value="' . Configuration::get($this->name . '_ID') . '"/>
			</div>
			<br/>
			<label>' . $this->l('Transaction Key') . '</label>
			<div class="margin-form">
			<input type="text" name="' . $this->name . '_TRANSKEY" value="' . Configuration::get($this->name . '_TRANSKEY') . '"/>
			</div>
			<input type="submit" name="submit" value="' . $this->l('Update') . '" class="button" />
			</form><br/><br/>';


			$incompleteOrders=$this->loadIncompleteOrderInfo();
			if($incompleteOrders)
			{
				$htmlTemp='<br/><h2>' . $this->l('List of incompelete transaction for verification') . '</h2> <br/> ';
				$htmlTemp .= '<table style="border-color:Gray; border-width:thin;" border=".1">
				<tr><td><b>id</b></td><td><b>cart_id</b></td><td><b>customer_id</b></td><td><b>sequence</b></td><td><b>fp_hash</b></td><td><b>trans_id</b></td>
				<td><b>response_code</b></td><td><b>total_amount</b></td><td><b>payment</b></td><td><b>time_start</b></td><td><b>Verify</b></td></tr>';
				foreach ($incompleteOrders AS $row)
				{
					$htmlTemp .= '<tr><td>'. $row['id'] .'</td>';
					$htmlTemp .= '<td>'. $row['cart_id'] .'</td>';
					$htmlTemp .= '<td>'. $row['customer_id'] .'</td>';
					$htmlTemp .= '<td>'. $row['sequence'] .'</td>';
					$htmlTemp .= '<td>'. $row['fp_hash'] .'</td>';
					$htmlTemp .= '<td>'. $row['trans_id'] .'</td>';
					$htmlTemp .= '<td>'. $row['response_code'] .'</td>';
					$htmlTemp .= '<td>'. $row['total_amount'] .'</td>';
					$htmlTemp .= '<td>'. $row['payment'] .'</td>';
					$htmlTemp .= '<td>'. $row['time_start'] .'</td>';
					$htmlTemp .= '<td> <form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
					$htmlTemp .= '<input type="hidden" name="sequence_code" value="' . $row['sequence'] . '" />';
					$htmlTemp .= '<input type="submit" name="verify" value="' . $this->l('Verification') . '" class="button" />	</form></td></tr>';

				}
				$htmlTemp .= '</table><br/>';

				$this->_html .=$htmlTemp;
			}

			//$this->_html .= 
			//$verifyResult = $this->verificationOrders();

		}

		public function displayErrors() {
			$nbErrors = sizeof($this->_postErrors);
			$this->_html .= '
			<div class="alert error">
			<h3>' . ($nbErrors > 1 ? $this->l('There are') : $this->l('There is')) . ' ' . $nbErrors . ' ' . ($nbErrors > 1 ? $this->l('errors') : $this->l('error')) . '</h3>
			<ol>';
			foreach ($this->_postErrors AS $error)
				$this->_html .= '<li>' . $error . '</li>';
			$this->_html .= '
			</ol>
			</div>';
		}

		public function displayMessages() {
			$nbMessages = sizeof($this->_postMessages);
			$this->_html .= '
			<div class="conf confirm">
			<h3>' . ($nbMessages > 1 ? $this->l('There are') : $this->l('There is')) . ' ' . $nbMessages . ' ' . ($nbMessages > 1 ? $this->l('messages') : $this->l('message')) . '</h3>
			<ol>';
			foreach ($this->_postMessages AS $message)
				$this->_html .= '<li>' . $message . '</li>';
			$this->_html .= '
			</ol>
			</div>';
		}

		private function createPaymentTable() {
			$db = Db::getInstance();
			$sQuery = "CREATE TABLE `" . _DB_PREFIX_ . "module_mellipayment` (
			`id` INT(10) NOT NULL AUTO_INCREMENT,
			`sequence` CHAR(20) NOT NULL,
			`fp_hash` CHAR(40) NOT NULL,
			`trans_id` CHAR(20) NULL,
			`response_code` CHAR(20) NULL,
			`response_subcode` CHAR(20) NULL,
			`response_reason_code` CHAR(20) NULL,
			`response_reason_text` CHAR(50) NULL,
			`total_amount` INT NOT NULL,
			`payment` INT NOT NULL DEFAULT 0,
			`time_start` INT(12) NOT NULL,
			`cart_id` INT(10) NULL,
			`customer_id` INT(10) NULL,
			`order_id` INT(10) NULL,
			primary key(id),
			unique(sequence),
			index(trans_id)) ENGINE = MYISAM COLLATE utf8_general_ci";

			$db->Execute($sQuery);

			return true;
		}

		public function hookInvoice($params) {
			$id_order = $params['id_order'];

			global $smarty;
			$onlinePaymentDetails = $this->readOnlinePaymentDetails($id_order);
			if($onlinePaymentDetails)
			{
				$onlinePaymentDetails['time_start'] = date("Y-m-d-H:i:s",$onlinePaymentDetails['time_start']);

				$smarty->assign(array(
				'transID ' => $onlinePaymentDetails['trans_id '],
				'timeStart' => $onlinePaymentDetails['time_start'],
				'paymentAmount' => $onlinePaymentDetails['payment'],
				'id_order' => $id_order,
				'this_page' => $_SERVER['REQUEST_URI'],
				'this_path' => $this->_path,
				'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . "modules/{$this->name}/"));
				return $this->display(__FILE__, 'invoice_melli.tpl');
			}
			else
			{
				return false;
			}
		}

		private function readOnlinePaymentDetails($intOrderID) {
			$db = Db::getInstance();
			$result = $db->ExecuteS("SELECT * FROM `" . _DB_PREFIX_ . "module_mellipayment` WHERE `order_id` =".intval($intOrderID).";");
			if (!$result) 
			{
				return false;
			}
			return $result[0];
		}

		public function hookPayment($params) {
			global $smarty;

			$smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . "modules/{$this->name}/"));
			return $this->display(__FILE__, 'mellipayment.tpl');
		}

		public function hookPaymentReturn($params) {
			if (!$this->active)
				return;

			return $this->display(__FILE__, 'confirmation.tpl');
		}

		private function hmac ($key, $data)	{
			return (bin2hex (mhash(MHASH_MD5, $data, $key)));
		}

		public function createSequence() {
			$db = Db::getInstance();
			$sequence = 0;
			do {
				$m = "777".microtime(true);
				$sequence = substr($m, 0, 12);
				$search = $db->ExecuteS("SELECT sequence FROM `" . _DB_PREFIX_ . "module_mellipayment` WHERE sequence = '$sequence'");
				if (empty($search)) {
					break;
				}
			} while (true);

			$this->_sequence = $sequence;

			return $sequence;
		}

		public function execPayment($cart) {
			$cartID = $cart->id;
			$customerID = $cart->id_customer;

			$purchase_currency = $this->GetCurrency();
			if ($cookie->id_currency == $purchase_currency->id)
				$PurchaseAmount = number_format($cart->getOrderTotal(true, 3), 0, '', '');
			else
				$PurchaseAmount= number_format(Tools::convertPrice($cart->getOrderTotal(true, 3), $purchase_currency), 0, '', '');

			global $smarty;

			$this->_sequence = $this->createSequence();
			$this->_TotalAmount = $cart->getOrderTotal(true, 3);

			$tstamp = time();

			$currencycode = $this->_CurrencyCode;

			$fingerprint = $this ->hmac($this->_TRANSKEY, $this->_ID . "^" . $this->_sequence . "^" . $tstamp . "^" . $this->_TotalAmount . "^" . $currencycode);

			$this->saveOrderInfo($cart->getOrderTotal(true, 3), $tstamp,$fingerprint,$cartID,$customerID);

			$smarty->assign(array(
			'x_amount' => $this->_TotalAmount,
			'x_login' => Configuration::get($this->name . '_ID'),
			'x_fp_hash' => $fingerprint,
			'x_fp_sequence' => $this->_sequence,
			'x_fp_timestamp' => $tstamp,
			'x_description' => Configuration::get('PS_SHOP_NAME'),
			'x_currency_code' => $currencycode,
			'x_test_request' => $this->_testMode,
			'x_action' => ($this->_testMode ? $this->_webURLTest :$this->_webURL),
			'this_path' => $this->_path,
			'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . "modules/{$this->name}/"));

			return $this->display(__FILE__, 'payment_execution.tpl');
		}

		protected function saveOrderInfo($totalAmont,$time,$fingerprint,$cartID,$customerID) {
			//$time = time();
			//$this->totalAmont = $totalAmont;
			//$this->createResNum();

			$db = Db::getInstance();
			$sQuery = "INSERT INTO " . _DB_PREFIX_ . "module_mellipayment SET sequence = '$this->_sequence', total_amount = '$totalAmont', time_start = '$time', fp_hash = '$fingerprint', cart_id = '$cartID', customer_id = '$customerID'";
			return $db->Execute($sQuery);
		}

		public function checkReceipt($post,$adminpage) {
			/*foreach ($post as $key => $value) 
			{
			$this->_postErrors[] = $this->l("Key= $key; Value= $value<br />\n");
			}*/

			if( isset($post['x_trans_id']) )
			{
				$x_trans_id = $post['x_trans_id'];
				$x_response_code = $post['x_response_code'];
				$x_response_subcode = $post['x_response_subcode'];
				$x_response_reason_code = $post['x_response_reason_code'];
				$x_response_reason_text = $post['x_response_reason_text'];
				$x_login = $post['x_login'];
				$x_fp_sequence = $post['x_fp_sequence'];
				$x_fp_timestamp = $post['x_fp_timestamp'];
				$x_amount = $post['x_amount'];
				$x_currency_code = $post['x_currency_code'];
				$x_fp_hash = $post['x_fp_hash'];

				/*if($x_trans_id == 'NOT_AVAILABLE')
				$x_trans_id = '';*/
				$x_currency_code = $this->_CurrencyCode;	

				$this->_TRANSKEY = Configuration::get($this->name . '_TRANSKEY');
				$fingerprint = $this ->hmac($this->_TRANSKEY, $x_trans_id . "^" . $x_response_code . "^" .$x_response_subcode . "^" .$x_response_reason_code . "^" .  $x_response_reason_text . "^" . $x_login . "^" . $x_fp_sequence . "^" . $x_fp_timestamp . "^" . $x_amount . "^" . $x_currency_code);

				$x_fp_hash=rtrim($x_fp_hash);
				if($fingerprint != $x_fp_hash)
				{

					$this->_postErrors[] = $this->l('Error in Fingerprint.');    
				}
				else
				{
					if($x_response_code != 4)
						$this->saveBankResponse($x_fp_sequence,$x_trans_id,$x_response_code,$x_response_subcode,$x_response_reason_code,$x_response_reason_text,$x_amount);

					if($x_response_code == 1 )
					{
						$this->_html .= '<h2>' . $this->l('Payment Succesfull') . '</h2>';
						//$this->_postMessages[] = $this->l('Payment Succesfull');
						//$this->_html .=$this->l('Sequence Number = ') .$this->checkOrderInfo($x_fp_sequence).'<br />';
						$result = true;
					}
					else
					{
						$result = false;
						switch($x_response_code)
						{
							case "2":
								$this->_postErrors[] = $this->l('RCP-ERR-Transaction Declined');
								break;    
							case "3":
								$this->_postErrors[] = $this->l('RCP-ERR-Error in payment');
								break;
							case "4":
								$this->_postErrors[] = $this->l('RCP-ERR-Ambiguous-Wait for certain response');
								break;
						}
					}
				}
			}
			else 
			{
				$this->_postErrors[] = $this->l('Error in payment, Transaction not exist.');
				$result=false;
			}

			$this->displayErrors();
			//$this->displayMessages();
			if(!$adminpage)
				echo $this->_html;
			return $result;
		}

		private function checkOrderInfo($sequence)	{
			$db = Db::getInstance();
			$search = $db->ExecuteS("SELECT sequence FROM `" . _DB_PREFIX_ . "module_mellipayment` WHERE sequence = '$sequence'");
			if (!$search) {
				return false;
			}
			$redult = $search[0];

			return $redult['sequence'];
		}

		private function checkOrderInfoAll($sequence)	{
			$db = Db::getInstance();
			$search = $db->ExecuteS("SELECT * FROM `" . _DB_PREFIX_ . "module_mellipayment` WHERE sequence = '$sequence'");
			if (!$search) {
				return false;
			}
			return $search[0];
		}

		private function loadIncompleteOrderInfo()	{
			$db = Db::getInstance();
			$search = $db->ExecuteS("SELECT * FROM `" . _DB_PREFIX_ . "module_mellipayment` WHERE trans_id is NULL");
			if (!$search) {
				return false;
			}
			return $search;
		}

		private function saveBankResponse($x_fp_sequence,$x_trans_id,$x_response_code,$x_response_subcode,$x_response_reason_code,$x_response_reason_text,$x_amount){
			$db = Db::getInstance();
			$sQuery = " UPDATE `" . _DB_PREFIX_ . "module_mellipayment` SET trans_id = '$x_trans_id' ,response_code = '$x_response_code',response_subcode  = '$x_response_subcode',response_reason_code  = '$x_response_reason_code',response_reason_text  = '$x_response_reason_text',payment = '$x_amount' WHERE sequence = '$x_fp_sequence'";
			return $db->Execute($sQuery);
		}

		public function saveOrderID($x_fp_sequence,$order_id){
			if($order_id != '')
			{
				$db = Db::getInstance();
				$sQuery = " UPDATE `" . _DB_PREFIX_ . "module_mellipayment` SET order_id = '$order_id' WHERE sequence = '$x_fp_sequence'";
				return $db->Execute($sQuery); 
			}
		}

		private function verificationTransactions($x_fp_sequence,$x_fp_timestamp,$x_amount,$x_currency_code,$x_description)	{
			$x_tran_key = $this->_TRANSKEY;
			$x_login = $this->_ID;

			$x_fp_hash = $this -> hmac ($x_tran_key, $x_login . "^" . $x_fp_sequence . "^" . $x_fp_timestamp . "^" . $x_amount . "^" . $x_currency_code);
			//return $response=$this->doVerification($x_fp_timestamp,$x_fp_sequence,$x_fp_hash,$x_login,$x_description,$x_amount,$x_currency_code);

			$ch = curl_init();
			if(!$this->_testMode)
				curl_setopt($ch, CURLOPT_URL, $this->_webURLVerify);
			else
				curl_setopt($ch, CURLOPT_URL, $this->_webURLVerifyTest);


			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt ($ch, CURLOPT_POST, 1);
			$post="x_fp_timestamp=$x_fp_timestamp&x_fp_sequence=$x_fp_sequence&x_fp_hash=$x_fp_hash&x_login=$x_login&x_description=$x_description&x_amount=$x_amount&x_currency_code=$x_currency_code";

			curl_setopt ($ch, CURLOPT_POSTFIELDS, $post);
			$estore = curl_exec ($ch);
			curl_close ($ch);
			if(!$estore){
				$this->_postErrors[] = $this->l('Error in verification');
				//$this->displayErrors();
				return false;
			}
			$tagPos = strpos($estore,'<');
			return substr($estore,0,$tagPos);
			return $estore;
		}	

		private function verificationOrders($sequence)	{
			$x_description = Configuration::get('PS_SHOP_NAME');
			$x_currency_code = $this->_CurrencyCode;
			//$incompleteOrders=$this->loadIncompleteOrderInfo();
			$resultArray = array();
			//$arrayResult2 = array();

			$incompeleteOrder = $this->checkOrderInfoAll($sequence);

			if(!$incompeleteOrder)
				return false;

			$result = $this->verificationTransactions($incompeleteOrder['sequence'],$incompeleteOrder['time_start'],$incompeleteOrder['total_amount'],$x_currency_code,$x_description);
			/*if($this->_testMode)
			{
			$this->_html .= $this->l($result);
			$this->displayErrors();
			}*/

			$result = explode("&",$result);
			foreach ($result AS $temp) 
			{
				$temp = explode("=",$temp);
				$resultArray[$temp[0]] = $temp[1];
			}
			return $this->checkReceipt($resultArray,true);
		}
	}
?>