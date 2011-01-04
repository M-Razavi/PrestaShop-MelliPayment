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
echo $mellipayment->execPayment($cart);

include_once(dirname(__FILE__).'/../../footer.php');

?>