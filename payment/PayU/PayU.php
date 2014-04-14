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

require_once('api/Simpla.php');

class PayU extends Simpla
{	

	public function checkout_form($order_id, $button_text = null)
	{
		if(empty($button_text))
			$button_text = 'Перейти к оплате';
		
		$order = $this->orders->get_order((int)$order_id);
		$purchases = $this->orders->get_purchases(array('order_id'=>intval($order->id)));
		$payment_method = $this->payment->get_payment_method($order->payment_method_id);
		$payment_currency = $this->money->get_currency(intval($payment_method->currency_id));
		$settings = $this->payment->get_payment_settings($payment_method->id);
		$price = round($this->money->convert($order->total_price, $payment_method->currency_id, false), 2);

		$currency = $payment_currency->code;

		$total_price = 0;
		$ORDER_PNAME = array();
		$ORDER_PCODE = array();
		$ORDER_PRICE = array();
		$ORDER_QTY = array();
		$ORDER_VAT = array();
		foreach($purchases as $purchase)
		{
			$ORDER_PNAME[] = trim($purchase->product_name.' '.$purchase->variant_name);
			$ORDER_PCODE[] = $purchase->sku ? $purchase->sku : $purchase->product_id;
			$ORDER_PRICE[] = $this->money->convert($purchase->price, $payment_method->currency_id, false);
			$ORDER_QTY[] = $purchase->amount;
			$ORDER_VAT[] = 0;
			$total_price += $this->money->convert($purchase->price, $payment_method->currency_id, false);
		}
			

		$option = array();
		$option['MERCHANT']			= $settings['payu_merchant']; //Идентификатор (код) продавца. Данное значение присваивается Вам со стороны PayU. Также оно доступно в панели управления (Управление учетными записями – Информация об учетной записи – Код продавца). 
		$option['ORDER_REF']		= $order->id; //Уникальный номер заказа в Вашей системе для дальнейшей идентификации заказа (*Необязательное поле). 
		$option['ORDER_DATE']		= date('Y-m-d H:i:s', strtotime($order->date)); //Дата размещения заказа. Формат: yyyy-mm-dd hh:mm:ss (пример: 2011-10-01 12:12:12) 


		if ($currency == 'RUR')
			$currency = 'RUB';


		$option['ORDER_PNAME']		= $ORDER_PNAME;
		$option['ORDER_PCODE']		= $ORDER_PCODE;
		$option['ORDER_PRICE']		= $ORDER_PRICE;
		$option['ORDER_QTY'] 		= $ORDER_QTY;
		$option['ORDER_VAT']		= $ORDER_VAT; //Массив с кодом ставки НДС для каждого товара заказа. (к примеру 19 для 18%). 
		$option['ORDER_SHIPPING']	= 0;
		// Если стоимость доставки входит в сумму заказа
		if (!$order->separate_delivery && $order->delivery_price>0)
		{
			$option['ORDER_SHIPPING'] = $this->money->convert($order->delivery_price, $payment_method->currency_id, false);
			$total_price += $option['ORDER_SHIPPING'];
		}



		$option['PRICES_CURRENCY'] 	= $currency;  //Валюта, в которой будет отображаться цены товаров и стоимость доставки
		$option['DISCOUNT'] 		= 0; //Скидка на весь заказ в валюте PRICES_CURRENCY. Формат: положительное число, не более двух разрядов после точки. (*Необязательное поле). 
		if($order->discount > 0)
		{
			$option['DISCOUNT'] = round($this->money->convert($total_price-$order->total_price, $payment_method->currency_id, false), 2);
		}

		$option['BACK_REF']			= $this->config->root_url.'/order/'.$order->url;

		// параметры которые игнорируем при генерации hash
		$ignoredKeys = array(
			'AUTOMODE',
			'BACK_REF',
			'DEBUG',
			'BILL_FNAME',
			'BILL_LNAME',
			'BILL_EMAIL',
			'BILL_PHONE',
			'BILL_ADDRESS',
			'BILL_CITY',
			'DELIVERY_FNAME',
			'DELIVERY_LNAME',
			'DELIVERY_PHONE',
			'DELIVERY_ADDRESS',
			'DELIVERY_CITY',
			'LU_ENABLE_TOKEN',
			'LU_TOKEN_TYPE',
			'TESTORDER',
			'LANGUAGE'
		);

		$hash = '';
		foreach ($option as $dataKey => $dataValue) {
			if (in_array($dataKey, $ignoredKeys)) {
				continue;
			}
			if(is_array($dataValue))
			{
				foreach ($dataValue as $v) 
					$hash .= strlen($v) . $v;

			}
			else
			{
				$hash .= strlen($dataValue) . $dataValue;

			}
		}


		$option['ORDER_HASH']		= hash_hmac('md5', $hash, $settings['payu_secretkey']);
		$option['BILL_EMAIL']		= $order->email; //Email плательщика
		$option['BILL_PHONE']		= $order->phone; //Телефон плательщика
		$option['BILL_CITY']		= $order->location; //Город плательщика 
		$option['BILL_ADDRESS']		= $order->address; //Адрес плательщика 
		$option['TESTORDER']		= $settings['payu_testorder']; //Флаг (логический) тестовой операции. Значения: TRUE, FALSE (*Необязательное поле). 
		$option['DEBUG']			= $settings['payu_debug']; //Флаг режима отладки. Значения: 0-режим выключен, 1-режим включен (*Необязательное поле)
		$option['LANGUAGE']			= $settings['payu_language']; //Язык платежной страницы. Пример: RU, EN (*Необязательное поле). 



		$button =	'<form method="post" action="https://secure.payu.ru/order/lu.php" accept-charset="utf-8">';
		foreach ( $option as $name => $value )
		{
			if(!is_array($value)) 
				$button .= '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars($value).'">';
			elseif(is_array($value)) 
				foreach ($value as $avalue) 
					$button .= '<input type="hidden" name="'.$name.'[]" value="'.htmlspecialchars($avalue).'">';
		}	

		$button .= '<input type="submit" class="checkout_button" value="'.$button_text.'">';
		$button .= '</form>';
		return $button;
	}
}

