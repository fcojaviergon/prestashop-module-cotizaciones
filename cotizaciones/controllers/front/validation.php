<?php

if (!defined('_PS_VERSION_'))
    exit;

class CotizacionesValidationModuleFrontController extends ModuleFrontController {


    public function initContent() {
        parent::initContent();
        /* SSL Management */
        $useSSL = true;
        $this->cotizaciones = new Cotizaciones();
        $cart = $this->context->cart;
		
		
   if ($cart->id_customer == 0 OR $cart->id_address_delivery == 0 OR $cart->id_address_invoice == 0 OR !$this->cotizaciones->active)
       Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

        $customer = new Customer((int)$cart->id_customer);

        if (Validate::isLoadedObject($cart) AND $cart->OrderExists() == 0) {

        }else{

            Tools::redirectLink(__PS_BASE_URI__);
        }

        if (!Validate::isLoadedObject($customer))
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');


        if (Tools::getValue('confirm'))
        {

            $total = $cart->getOrderTotal(true, Cart::BOTH);

            $this->cotizaciones->validateOrderMail((int)($cart->id), 15, $total, $this->cotizaciones->displayName, NULL, array(), NULL, false,$customer->secure_key);


            $this->context->smarty->assign(array(
                'total' => $cart->getOrderTotal(true, Cart::BOTH),
                'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/quoteorder/'
            ));

            $this->context->smarty->assign('this_path', __PS_BASE_URI__.'modules/quoteorder/');

            Tools::safePostVars();

           

            $template = 'confirmationSimple.tpl';
            $this->setTemplate($template);


        }else{

            $order_process = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order' ;
            Tools::redirectLink($link->getPageLink("$order_process.php", true));

        }


    }


}

