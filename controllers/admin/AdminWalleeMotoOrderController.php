<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2020 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class AdminWalleeMotoOrderController extends ModuleAdminController
{
    public function postProcess()
    {
        parent::postProcess();
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess(
            $this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        if ($access['edit'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l(
                        'You do not have permission to edit the order.',
                        'adminwalleeordercontroller'
                    )
                )
            );
            die();
        }
    }

    public function ajaxProcessLoadWalleePayment()
    {
		$cart_id = Tools::getValue('id_cart');

		if (!Validate::isUnsignedId($cart_id)) {
            return false;
        }

		$cart = new Cart((int)$cart_id);

		$possiblePaymentMethods = WalleeServiceTransaction::instance()->getPossiblePaymentMethods(
			$cart
		);

		$shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = WalleeModelMethodconfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(),
                $possible->getId(),
                $shopId
            );
            if (! $methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = array(
				'name' => $methodConfiguration->configuration_name,
				'value' => 'wallee_' . $methodConfiguration->id_method_configuration
			);
		}
		
		$result = array(
			'paymentMethods' => $methods,
			'initOrderState' => WalleeOrderstatus::getRedirectOrderStatus()->id
			//'formUrl' => WalleeServiceTransaction::instance()->getJavascriptUrl($cart)
		);
		echo Tools::jsonEncode($result);
	}
}
