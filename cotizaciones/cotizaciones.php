<?php

if (!defined('_CAN_LOAD_FILES_'))
    exit;

class Cotizaciones extends PaymentModule {

    public function __construct() {
        $this->name = 'cotizaciones';
        $this->tab = 'payments_gateways';
        $this->version = '0.1';
        $this->author = 'Francisco Gonzalez';

        $this->currencies = false;

        parent::__construct();

        $this->displayName = $this->l('Cotizar ordenes');
        $this->description = $this->l('Acepta ordenes para cotizar');
    }

	public function install()
	{
		if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn'))
			return false;
		return true;
	}

    public function hookPayment($params) {
        if (!$this->active)
            return;

        global $smarty;

        // Check if cart has product download
        foreach ($params['cart']->getProducts() AS $product) {
            $pd = ProductDownload::getIdFromIdProduct((int) ($product['id_product']));
            if ($pd AND Validate::isUnsignedInt($pd))
                return false;
        }

        $smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }


    public function validateOrderMail($id_cart, $id_order_state, $amountPaid, $paymentMethod = 'Unknown', $message = NULL, $extraVars = array(), $currency_special = NULL, $dont_touch_amount = false, $secure_key = false) {
        global $cart;
        if (!$this->active)
            return;
	
        $cart = new Cart((int) ($id_cart));
        // Does order already exists ?
        if (Validate::isLoadedObject($cart) AND $cart->OrderExists() == 0) {
            if ($secure_key !== false AND $secure_key != $cart->secure_key)
                die(Tools::displayError());

            // Copying data from cart
            $order = new Order();
            $order->id_carrier = (int) ($cart->id_carrier);
            $order->id_customer = (int) ($cart->id_customer);
            $order->id_address_invoice = (int) ($cart->id_address_invoice);
            $order->id_address_delivery = (int) ($cart->id_address_delivery);

            $vat_address = new Address((int) ($order->id_address_delivery));
            $order->id_currency = ($currency_special ? (int) ($currency_special) : (int) ($cart->id_currency));
            $order->id_lang = (int) ($cart->id_lang);
            $order->id_cart = (int) ($cart->id);

            $customer = new Customer((int) ($order->id_customer));
            $order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($customer->secure_key));
            $order->payment = Tools::substr($paymentMethod, 0, 32);
            if (isset($this->name))
                $order->module = $this->name;
            $order->recyclable = $cart->recyclable;
            $order->gift = (int) ($cart->gift);
            $order->gift_message = $cart->gift_message;

            $currency = new Currency($order->id_currency);
            $order->conversion_rate = $currency->conversion_rate;
            $amountPaid = !$dont_touch_amount ? Tools::ps_round((float) ($amountPaid), 2) : $amountPaid;
            $order->total_paid_real = $amountPaid;
            $order->total_products = (float) ($cart->getOrderTotal(false, Cart::ONLY_PRODUCTS));
            $order->total_products_wt = (float) ($cart->getOrderTotal(true, Cart::ONLY_PRODUCTS));
            $order->total_discounts = (float) (abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS)));
            //$order->total_shipping = (float) ($cart->getOrderShippingCost());
            $order->total_shipping = "0"; /* Agregar si es neceario con order total cart*/
            $order->carrier_tax_rate = (float) Tax::getCarrierTaxRate($cart->id_carrier, (int) $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
            $order->total_wrapping = (float) (abs($cart->getOrderTotal(true, Cart::ONLY_WRAPPING)));
            $order->total_paid = (float) (Tools::ps_round((float) ($cart->getOrderTotal(true, Cart::BOTH)), 2));
            $order->invoice_date = date('Y-m-d H:i:s');
            $order->delivery_date = '0000-00-00 00:00:00';
            // Amount paid by customer is not the right one -> Status = payment error
            if ($order->total_paid != $order->total_paid_real)
                $id_order_state = _PS_OS_ERROR_;
            // Creating order
            if ($cart->OrderExists() == 0) {
                //$result = $order->add();
                $order->id = $order->id_cart;  // fake id!
                $result = true;
            } else {
                $errorMessage = Tools::displayError('An order has already been placed using this cart.');
                Logger::addLog($errorMessage, 4, '0000001', 'Cart', intval($order->id_cart));
                die($errorMessage);
            }

            // Next !
            if ($result AND isset($order->id)) {
                if (!$secure_key)
                    $message .= $this->l('Warning : the secure key is empty, check your payment account before validation');
                // Optional message to attach to this order
                if (isset($message) AND !empty($message)) {
                    $msg = new Message();
                    $message = strip_tags($message, '<br>');
                    if (!Validate::isCleanHtml($message))
                        $message = $this->l('Payment message is not valid, please check your module!');
                    $msg->message = $message;
                    $msg->id_order = (int) ($order->id);
                    $msg->private = 1;
                    $msg->add();
                }

                // Insert products from cart into order_detail table
                $products = $cart->getProducts();
                $productsList = '';


                $db = Db::getInstance();

                //$query = 'INSERT INTO `' . _DB_PREFIX_ . 'orders` (`id_carrier`, `id_lang`, `id_customer`, `id_cart`, `id_currency`, `id_address_delivery`, `id_address_invoice`, `current_state`, `secure_key`, `payment`, `conversion_rate`, `module`, `recyclable`, `gift`, `gift_message`, `shipping_number`, `total_discounts`, `total_paid`, `total_paid_real`, `total_products`, `total_products_wt`, `total_shipping`, `carrier_tax_rate`, `total_wrapping`, `invoice_number`, `delivery_number`, `invoice_date`, `delivery_date`, `valid`, `date_add`, `date_upd`, `total_paid_tax_incl`, `total_paid_tax_excl`) VALUES ("' . $order->id_carrier . '", "' . $order->id_lang . '", "' . $order->id_customer . '", "' . $order->id_cart . '", "' . $order->id_currency . '", "' . $order->id_address_delivery . '", "' . $order->id_address_invoice . '", "13", "' . $order->secure_key . '", "' . $order->payment . '", "' . $order->conversion_rate . '", "' . $order->module . '", "' . $order->recyclable . '", "' . $order->gift . '", "' . $order->gift_message . '", "", "' . $order->total_discounts . '", "' . $order->total_paid . '", "' . $order->total_paid_real . '", "' . $order->total_products . '", "' . $order->total_products_wt . '", "' . $order->total_shipping . '", "' . $order->carrier_tax_rate . '", "' . $order->total_wrapping . '", 0, 0, "' . $order->invoice_date . '", "' . $order->delivery_date . '", 0, "' . date("Y-m-d H:i:s") . '", "' . date("Y-m-d H:i:s") . '", "' . $order->total_products_wt . '", "' . $order->total_products . '")';
                $query = 'INSERT INTO `' . _DB_PREFIX_ . 'orders` (`reference`,`id_carrier`, `id_lang`, `id_customer`, `id_cart`, `id_currency`, `id_address_delivery`, `id_address_invoice`, `current_state`, `secure_key`, `payment`, `conversion_rate`, `module`, `recyclable`, `gift`, `gift_message`, `shipping_number`, `total_discounts`, `total_paid`, `total_paid_real`, `total_products`, `total_products_wt`, `total_shipping`, `carrier_tax_rate`, `total_wrapping`, `invoice_number`, `delivery_number`, `invoice_date`, `delivery_date`, `valid`, `date_add`, `date_upd`, `total_paid_tax_incl`, `total_paid_tax_excl`) 
        VALUES ("' . $order->reference . '", "' . $order->id_carrier . '", "' . $order->id_lang . '", "' . $order->id_customer . '", "' . $order->id_cart . '", "' . $order->id_currency . '", "' . $order->id_address_delivery . '", "' . $order->id_address_invoice . '", "'.$id_order_state.'", "' . $order->secure_key . '", "' . $order->payment . '", "' . $order->conversion_rate . '", "' . $order->module . '", "' . $order->recyclable . '", "' . $order->gift . '", "' . $order->gift_message . '", "", "' . $order->total_discounts . '", "' . $order->total_paid . '", "' . $order->total_paid_real . '", "' . $order->total_products . '", "' . $order->total_products_wt . '", "' . $order->total_shipping . '", "' . $order->carrier_tax_rate . '", "' . $order->total_wrapping . '", 0, 0, "' . $order->invoice_date . '", "' . $order->delivery_date . '", 1, "' . date("Y-m-d H:i:s") . '", "' . date("Y-m-d H:i:s") . '", "' . $order->total_products_wt . '", "' . $order->total_products . '")';

                $db->Execute($query);

                $queryResult = $db->ExecuteS('SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` where id_customer="' . $order->id_customer . '" order by id_order desc limit 1');

            if ($queryResult[0]['id_order']) {

                        $query = 'INSERT INTO `' . _DB_PREFIX_ . 'order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`) values (0, "' . $queryResult[0]['id_order'] . '", "'.$id_order_state.'", "' . date("Y-m-d H:i:s") . '")';

                        $db->Execute($query);
                    
                }

                @$idOrdenCompra=$queryResult[0]['id_order'];

                $customizedDatas = Product::getAllCustomizedDatas((int) ($order->id_cart));
                Product::addCustomizationPrice($products, $customizedDatas);
                $outOfStock = false;
                foreach ($products AS $key => $product) {
                    $productQuantity = (int) (Product::getQuantity((int) ($product['id_product']), ($product['id_product_attribute'] ? (int) ($product['id_product_attribute']) : NULL)));
                    $quantityInStock = ($productQuantity - (int) ($product['cart_quantity']) < 0) ? $productQuantity : (int) ($product['cart_quantity']);

                    //  NO ACTUALIZAR STOCK
                    /*
                      if ($id_order_state != _PS_OS_CANCELED_ AND $id_order_state != _PS_OS_ERROR_){
                      if (Product::updateQuantity($product, (int)$order->id))
                      $product['stock_quantity'] -= $product['cart_quantity'];
                      if ($product['stock_quantity'] < 0 && Configuration::get('PS_STOCK_MANAGEMENT'))
                      $outOfStock = true;

                      Hook::updateQuantity($product, $order);
                      Product::updateDefaultAttribute($product['id_product']);
                      }
                     */

                    $price = Product::getPriceStatic((int) ($product['id_product']), false, ($product['id_product_attribute'] ? (int) ($product['id_product_attribute']) : NULL), 6, NULL, false, true, $product['cart_quantity'], false, (int) ($order->id_customer), (int) ($order->id_cart), (int) ($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));
                    $price_wt = Product::getPriceStatic((int) ($product['id_product']), true, ($product['id_product_attribute'] ? (int) ($product['id_product_attribute']) : NULL), 2, NULL, false, true, $product['cart_quantity'], false, (int) ($order->id_customer), (int) ($order->id_cart), (int) ($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));

                    /* insertar detalles de la orden */
                    
                    $query = 'INSERT INTO `' . _DB_PREFIX_ . 'order_detail` (`id_order`, `product_id`, `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`, `original_product_price`, `unit_price_tax_incl`, `unit_price_tax_excl`,`product_reference`)
                     values ("' . $queryResult[0]['id_order'] . '", "' . $product['id_product'] . '", "' . $product['name']. '", "' . $product['cart_quantity'] . '", "' . $quantityInStock . '", "' . $price . '", "' . $price . '", "' . $price_wt . '", "' . $price . '", "' . $product['reference']. '")';

                    $db->Execute($query);
                    
                    //Obtener status final
                    $product['status']="";
                    $queryStatus="select fvl.value from " . _DB_PREFIX_ . "feature_value_lang fvl
                                inner join " . _DB_PREFIX_ . "feature_product fp on fp.`id_feature_value`=fvl.`id_feature_value`
                                inner join " . _DB_PREFIX_ . "feature_lang fl on fl.`id_feature`=fp.`id_feature`
                                where fvl.`id_lang`=4 and fl.`id_lang`=4 and fl.`name`='STATUS FINAL' and fp.`id_product`='".$product['id_product']."'";
                    if($resultStatus = $db->ExecuteS($queryStatus)){
                        $product['status']=$resultStatus[0]['value'];
                    }
                   
                    $customizationQuantity = 0;
                    if (isset($customizedDatas[$product['id_product']][$product['id_product_attribute']])) {
                        $customizationText = '';
                        foreach ($customizedDatas[$product['id_product']][$product['id_product_attribute']] AS $customization)
                            if (isset($customization['datas'][_CUSTOMIZE_TEXTFIELD_]))
                                foreach ($customization['datas'][_CUSTOMIZE_TEXTFIELD_] AS $text)
                                    $customizationText .= $text['name'] . $this->l(':') . ' ' . $text['value'] . ', ';
                        $customizationText = rtrim($customizationText, ', ');

                        $customizationQuantity = (int) ($product['customizationQuantityTotal']);
                        $productsList .=
                                '<tr style="background-color: ' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
							<td style="padding: 0.6em 0.4em;">' . $product['reference'] . '</td>
							<td style="padding: 0.6em 0.4em;"><strong>' . $product['name'] . (isset($product['attributes_small']) ? ' ' . $product['attributes_small'] : '') . ' - ' . $this->l('Customized') . (!empty($customizationText) ? ' - ' . $customizationText : '') . '</strong></td>
				
							<td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt, $currency, false, null) . '</td>
							<td style="padding: 0.6em 0.4em; text-align: center;">' . $customizationQuantity . '</td>
							<td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice($customizationQuantity * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt), $currency, false, null) . '</td>
						</tr>';
                    }

                    if (!$customizationQuantity OR (int) $product['cart_quantity'] > $customizationQuantity)
                        $productsList .=
                                '<tr style="background-color: ' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
							<td style="padding: 0.6em 0.4em;">' . $product['reference'] . '</td>
							<td style="padding: 0.6em 0.4em;"><strong>' . $product['name'] . (isset($product['attributes_small']) ? ' ' . $product['attributes_small'] : '') . '</strong></td>
            
							<td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt, $currency, false, null) . '</td>
							<td style="padding: 0.6em 0.4em; text-align: center;">' . ((int) ($product['cart_quantity']) - $customizationQuantity) . '</td>
							<td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice(((int) ($product['cart_quantity']) - $customizationQuantity) * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt), $currency, false, null) . '</td>
						</tr>';
                } // end foreach ($products)
        
                // Insert discounts from cart into order_discount table
                $discountsList = '';        

                // Specify order id for message
                $oldMessage = Message::getMessageByCartId((int) ($cart->id));
                if ($oldMessage) {
                    $message = new Message((int) $oldMessage['id_message']);
                    $message->id_order = (int) $order->id;
                    $message->update();
                }
              
                if ($id_order_state != _PS_OS_ERROR_ AND $id_order_state != _PS_OS_CANCELED_ AND $customer->id) {
                    $invoice = new Address((int) ($order->id_address_invoice));
                    $delivery = new Address((int) ($order->id_address_delivery));
                    $carrier = new Carrier((int) ($order->id_carrier), $order->id_lang);
                    $delivery_state = $delivery->id_state ? new State((int) ($delivery->id_state)) : false;
                    $invoice_state = $invoice->id_state ? new State((int) ($invoice->id_state)) : false;

                    $data = array(
                        '{firstname}' => $customer->firstname,
                        '{lastname}' => $customer->lastname,
                        '{email}' => $customer->email,
                        '{delivery_block_txt}' => $this->__getFormatedAddress($delivery, "\n"),
                        '{invoice_block_txt}' => $this->__getFormatedAddress($invoice, "\n"),
                        '{delivery_block_html}' => $this->__getFormatedAddress($delivery, "<br />", array(
                            'firstname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>'
                            , 'lastname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>'
                        )),
                        '{invoice_block_html}' => $this->__getFormatedAddress($invoice, "<br />", array(
                            'firstname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>'
                            , 'lastname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>'
                        )),
						'{delivery_dni}' => $delivery->dni,
						
                        '{delivery_company}' => $delivery->company,
                        '{delivery_firstname}' => $delivery->firstname,
                        '{delivery_lastname}' => $delivery->lastname,
                        '{delivery_address1}' => $delivery->address1,
                        '{delivery_address2}' => $delivery->address2,
                        '{delivery_city}' => $delivery->city,
                        '{delivery_postal_code}' => $delivery->postcode,
                        '{delivery_country}' => $delivery->country,
                        '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
                        '{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
                        '{delivery_other}' => $delivery->other,
                        '{invoice_company}' => $invoice->company,
                        '{invoice_vat_number}' => $invoice->vat_number,
                        '{invoice_firstname}' => $invoice->firstname,
                        '{invoice_lastname}' => $invoice->lastname,
                        '{invoice_address2}' => $invoice->address2,
                        '{invoice_address1}' => $invoice->address1,
                        '{invoice_city}' => $invoice->city,
                        '{invoice_postal_code}' => $invoice->postcode,
                        '{invoice_country}' => $invoice->country,
                        '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
                        '{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
                        '{invoice_other}' => $invoice->other,
                        '{order_name}' => sprintf("#%06d", (int) ($idOrdenCompra)),
                      //  '{date}' => Tools::displayDate(date('Y-m-d H:i:s'), (int) ($order->id_lang), 1),
                        '{date}' => Tools::displayDate(date('Y-m-d H:i:s')),
                        '{carrier}' => $carrier->name,
                        '{payment}' => $order->payment,
                        '{products}' => $productsList,
                        '{discounts}' => $discountsList,
                        '{total_paid}' => Tools::displayPrice($order->total_paid, $currency, false, null),
                        '{total_products}' => Tools::displayPrice($order->total_paid - $order->total_shipping - $order->total_wrapping + $order->total_discounts, $currency, false, null),
                        '{total_discounts}' => Tools::displayPrice($order->total_discounts, $currency, false, null),
                        '{total_shipping}' => Tools::displayPrice($order->total_shipping, $currency, false, null),
                        '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $currency, false, null));

                    if (is_array($extraVars))
                        $data = array_merge($data, $extraVars);

                    // NO 	Join PDF invoice 
                    $fileAttachment = NULL;
                    
                    if (Validate::isEmail($customer->email)) {
                        Mail::Send(
                            (int) ($order->id_lang),
                            'order_conf',
                            Mail::l('Confirmación de Cotización'),
                            $data,
                            $customer->email,
                            $customer->firstname . ' ' . $customer->lastname, 
                            NULL, 
                            NULL, 
                            $fileAttachment, 
                            NULL, 
                            _PS_MODULE_DIR_ . "cotizaciones/mails/"
                        );
                    }

                  
                    $shopEmail = Configuration::get("PS_SHOP_EMAIL");
                    Mail::Send((int) ($order->id_lang), 'order_conf_admin', Mail::l('Admin - Notificación de Cotización'), $data, $shopEmail, NULL, NULL, NULL, $fileAttachment, NULL, _PS_MODULE_DIR_ . "cotizaciones/mails/");
                 
					
                }
                /* comentar para guardar carro*/
                $cart->delete();

                $this->currentOrder = (int) ($order->id);
                return true;
            } else {
                $errorMessage = Tools::displayError('Order creation failed');
                Logger::addLog($errorMessage, 4, '0000002', 'Cart', intval($order->id_cart));
                die($errorMessage);
            }
        } else {
            $errorMessage = Tools::displayError('Cart can\'t be loaded or an order has already been placed using this cart');
            Logger::addLog($errorMessage, 4, '0000001', 'Cart', intval($cart->id));
            die($errorMessage);
        }
    }

    /**
     * @param Object Address $the_address that needs to be txt formated
     * @return String the txt formated address block
     */
  private function __getFormatedAddress(Address $the_address, $line_sep, $fields_style = array()) {
        $out = '';
        $adr_fields = AddressFormat::getOrderedAddressFields($the_address->id_country);

        $r_values = array();

        foreach ($adr_fields as $fields_line) {
            $tmp_values = array();

            if( $fields_line == "Country:name"){


            }else{
                foreach (explode(' ', $fields_line) as $field_item) {
                    $field_item = trim($field_item);
                    $tmp_values[] = (isset($fields_style[$field_item])) ? sprintf($fields_style[$field_item], $the_address->{$field_item}) : $the_address->{$field_item};

                }
                $r_values[] = implode(' ', $tmp_values);

            }

        }


        $out = implode($line_sep, $r_values);
        return $out;
    }

}
