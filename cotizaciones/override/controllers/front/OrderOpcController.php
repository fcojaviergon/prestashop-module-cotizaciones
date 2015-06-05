<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class OrderOpcController extends OrderOpcControllerCore
{

	protected function _getPaymentMethods()
	{
		if (!$this->isLogged)
			return '<p class="warning">'.Tools::displayError('Please sign in to see payment methods.').'</p>';
		if ($this->context->cart->OrderExists())
			return '<p class="warning">'.Tools::displayError('Error: This order has already been validated.').'</p>';
		if (!$this->context->cart->id_customer || !Customer::customerIdExistsStatic($this->context->cart->id_customer) || Customer::isBanned($this->context->cart->id_customer))
			return '<p class="warning">'.Tools::displayError('Error: No customer.').'</p>';
		$address_delivery = new Address($this->context->cart->id_address_delivery);
		$address_invoice = ($this->context->cart->id_address_delivery == $this->context->cart->id_address_invoice ? $address_delivery : new Address($this->context->cart->id_address_invoice));
		if (!$this->context->cart->id_address_delivery || !$this->context->cart->id_address_invoice || !Validate::isLoadedObject($address_delivery) || !Validate::isLoadedObject($address_invoice) || $address_invoice->deleted || $address_delivery->deleted)
			return '<p class="warning">'.Tools::displayError('Error: Please select an address.').'</p>';
		if (count($this->context->cart->getDeliveryOptionList()) == 0 && !$this->context->cart->isVirtualCart())
		{
			if ($this->context->cart->isMultiAddressDelivery())
				return '<p class="warning">'.Tools::displayError('Error: None of your chosen carriers deliver to some of the addresses you have selected.').'</p>';
			else
				return '<p class="warning">'.Tools::displayError('Error: None of your chosen carriers deliver to the address you have selected.').'</p>';
		}
		if (!$this->context->cart->getDeliveryOption(null, false) && !$this->context->cart->isVirtualCart())
			return '<p class="warning">'.Tools::displayError('Error: Please choose a carrier.').'</p>';
		if (!$this->context->cart->id_currency)
			return '<p class="warning">'.Tools::displayError('Error: No currency has been selected.').'</p>';
		if (!$this->context->cookie->checkedTOS && Configuration::get('PS_CONDITIONS'))
			return '<p class="warning">'.Tools::displayError('Please accept the Terms of Service.').'</p>';

		/* If some products have disappear */
		if (is_array($product = $this->context->cart->checkQuantities(true)))
			return '<p class="warning">'.sprintf(Tools::displayError('An item (%s) in your cart is no longer available in this quantity. You cannot proceed with your order until the quantity is adjusted.'), $product['name']).'</p>';

		if ((int)$id_product = $this->context->cart->checkProductsAccess())
			return '<p class="warning">'.sprintf(Tools::displayError('An item in your cart is no longer available (%s). You cannot proceed with your order.'), Product::getProductName((int)$id_product)).'</p>';

		/* Check minimal amount */
		$currency = Currency::getCurrency((int)$this->context->cart->id_currency);

		$minimal_purchase = Tools::convertPrice((float)Configuration::get('PS_PURCHASE_MINIMUM'), $currency);
		if ($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimal_purchase)
			return '<p class="warning">'.sprintf(
				Tools::displayError('A minimum purchase total of %1s (tax excl.) is required to validate your order, current purchase total is %2s (tax excl.).'),
				Tools::displayPrice($minimal_purchase, $currency), Tools::displayPrice($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS), $currency)
			).'</p>';

		/* Bypass payment step if total is 0 */
		/* Permitir productos sin precio para cotizar 
		if ($this->context->cart->getOrderTotal() <= 0)
			return '<p class="center"><button class="button btn btn-default button-medium" name="confirmOrder" id="confirmOrder" onclick="confirmFreeOrder();" type="submit"> <span>'.Tools::displayError('I confirm my order.').'</span></button></p>';
		*/
		$return = Hook::exec('displayPayment');
		if (!$return)
			return '<p class="warning">'.Tools::displayError('No payment method is available for use at this time. ').'</p>';
		return $return;
	}



}
