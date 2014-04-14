<?php
/**
 * Simpla CMS
 * 
 * @link         http://rlab.com.ua
 * @author       OsBen
 * @mail         php@rlab.com.ua
 *
 * Оплата через PayU
 *
 */


// Работаем в корневой директории
chdir ('../../');
require_once('api/Simpla.php');
$simpla = new Simpla();

$order_id = $simpla->request->post('REFNOEXT', 'integer');
$order = $simpla->orders->get_order(intval($order_id));
if(empty($order))
	die('Оплачиваемый заказ не найден');

if($order->paid)
	die('Этот заказ уже оплачен');

$method = $simpla->payment->get_payment_method(intval($order->payment_method_id));
if(empty($method))
	die("Неизвестный метод оплаты");

$IPN_TOTALGENERAL = round($_POST['IPN_TOTALGENERAL'], 2); 
$price = round($simpla->money->convert($order->total_price, $method->currency_id, false), 2);
if($IPN_TOTALGENERAL != $price)
	die("Неверная сумма оплаты");


$settings = unserialize($method->settings);
$arr = array();
$merchant = $settings['payu_merchant'];

$arr = $_POST;
unset($arr["HASH"]);

$IPNcell = array( "IPN_PID", "IPN_PNAME", "IPN_DATE", "ORDERSTATUS" );
foreach ( $IPNcell as $name ) 
	if ( !isset( $arr[ $name ] ) ) 
		die( "Incorrect data" );

$sign = '';
foreach ($arr as $dataKey => $dataValue) {
	if(is_array($dataValue))
	{
		foreach ($dataValue as $v) 
			$sign .= strlen($v) . $v;	
	}
	else
	{
		$sign .= strlen($dataValue) . $dataValue;
	}
}

$sign = hash_hmac('md5', $sign, $settings['payu_secretkey']);
if ( $_POST["HASH"] != $sign ) 
	die("Контрольная подпись не верна");


$datetime = date("YmdHis");
$sign_return = '';
$sign_return .= strlen($arr["IPN_PID"][0]) . $arr["IPN_PID"][0];
$sign_return .= strlen($arr["IPN_PNAME"][0]) . $arr["IPN_PNAME"][0];
$sign_return .= strlen($arr["IPN_DATE"]) . $arr["IPN_DATE"];
$sign_return .= strlen($arr["DATE"]) . $datetime;
$sign_return = hash_hmac('md5', $sign_return, $settings['payu_secretkey']);
echo "<!-- <EPAYMENT>".$datetime."|".$sign_return."</EPAYMENT> -->";

if($_POST['ORDERSTATUS'] == 'TEST' || $_POST['ORDERSTATUS'] == 'COMPLETE')
{
	// Установим статус оплачен
	$simpla->orders->update_order(intval($order->id), array('paid'=>1));

	// Спишем товары  
	$simpla->orders->close(intval($order->id));
	$simpla->notify->email_order_user(intval($order->id));
	$simpla->notify->email_order_admin(intval($order->id));
}