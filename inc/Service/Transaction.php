<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * This service provides functions to deal with wallee transactions.
 */
class Wallee_Service_Transaction extends Wallee_Service_Abstract
{

    /**
     * Cache for cart transactions.
     *
     * @var \Wallee\Sdk\Model\Transaction[]
     */
    private static $transactionCache = array();

    /**
     * Cache for possible payment methods by cart.
     *
     * @var \Wallee\Sdk\Model\PaymentMethodConfiguration[]
     */
    private static $possiblePaymentMethodCache = array();

    /**
     * The transaction API service.
     *
     * @var \Wallee\Sdk\Service\TransactionService
     */
    private $transactionService;

    /**
     * The charge attempt API service.
     *
     * @var \Wallee\Sdk\Service\ChargeAttemptService
     */
    private $chargeAttemptService;

    /**
     * Returns the transaction API service.
     *
     * @return \Wallee\Sdk\Service\TransactionService
     */
    protected function getTransactionService()
    {
        if ($this->transactionService === null) {
            $this->transactionService = new \Wallee\Sdk\Service\TransactionService(
                Wallee_Helper::getApiClient());
        }
        return $this->transactionService;
    }

    /**
     * Returns the charge attempt API service.
     *
     * @return \Wallee\Sdk\Service\ChargeAttemptService
     */
    protected function getChargeAttemptService()
    {
        if ($this->chargeAttemptService === null) {
            $this->chargeAttemptService = new \Wallee\Sdk\Service\ChargeAttemptService(
                Wallee_Helper::getApiClient());
        }
        return $this->chargeAttemptService;
    }

    /**
     * Wait for the transaction to be in one of the given states.
     *
     * @param Order $order
     * @param array $states
     * @param int $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Order $order, array $states, $maxWaitTime = 10)
    {
        $startTime = microtime(true);
        while (true) {
            
            $transactionInfo = Wallee_Model_TransactionInfo::loadByOrderId($order->id);
            if (in_array($transactionInfo->getState(), $states)) {
                return true;
            }
            
            if (microtime(true) - $startTime >= $maxWaitTime) {
                return false;
            }
            sleep(2);
        }
    }

    /**
     * Returns the URL to wallee's JavaScript library that is necessary to display the payment form.
     *
     * @param Cart $cart
     * @return string
     */
    public function getJavascriptUrl(Cart $cart)
    {
        $transaction = $this->getTransactionFromCart($cart);
        return $this->getTransactionService()->buildJavaScriptUrl($transaction->getLinkedSpaceId(),
            $transaction->getId());
    }

    /**
     * Returns the transaction with the given id.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \Wallee\Sdk\Model\Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->getTransactionService()->read($spaceId, $transactionId);
    }

    /**
     * Returns the last failed charge attempt of the transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \Wallee\Sdk\Model\ChargeAttempt
     */
    public function getFailedChargeAttempt($spaceId, $transactionId)
    {
        $chargeAttemptService = $this->getChargeAttemptService();
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('charge.transaction.id', $transactionId),
                $this->createEntityFilter('state', \Wallee\Sdk\Model\ChargeAttemptState::FAILED)
            ));
        $query->setFilter($filter);
        $query->setOrderBys(array(
            $this->createEntityOrderBy('failedOn')
        ));
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Updates the line items of the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @return \Wallee\Sdk\Model\TransactionLineItemVersion
     */
    public function updateLineItems($spaceId, $transactionId, $lineItems)
    {
        $updateRequest = new \Wallee\Sdk\Model\TransactionLineItemUpdateRequest();
        $updateRequest->setTransactionId($transactionId);
        $updateRequest->setNewLineItems($lineItems);
        return $this->getTransactionService()->updateTransactionLineItems($spaceId, $updateRequest);
    }

    /**
     * Stores the transaction data in the database.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param Order $order
     * @return Wallee_Model_TransactionInfo
     */
    public function updateTransactionInfo(\Wallee\Sdk\Model\Transaction $transaction, Order $order)
    {
        $info = Wallee_Model_TransactionInfo::loadByTransaction($transaction->getLinkedSpaceId(),
            $transaction->getId());
        $info->setTransactionId($transaction->getId());
        $info->setAuthorizationAmount($transaction->getAuthorizationAmount());
        $info->setOrderId($order->id);
        $info->setState($transaction->getState());
        $info->setSpaceId($transaction->getLinkedSpaceId());
        $info->setSpaceViewId($transaction->getSpaceViewId());
        $info->setLanguage($transaction->getLanguage());
        $info->setCurrency($transaction->getCurrency());
        $info->setConnectorId(
            $transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getConnector() : null);
        $info->setPaymentMethodId(
            $transaction->getPaymentConnectorConfiguration() != null &&
                 $transaction->getPaymentConnectorConfiguration()
                    ->getPaymentMethodConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                    ->getPaymentMethodConfiguration()
                    ->getPaymentMethod() : null);
        $info->setImage($this->getResourcePath($this->getPaymentMethodImage($transaction, $order)));
        $info->setLabels($this->getTransactionLabels($transaction));
        if ($transaction->getState() == \Wallee\Sdk\Model\TransactionState::FAILED ||
             $transaction->getState() == \Wallee\Sdk\Model\TransactionState::DECLINE) {
            $failedChargeAttempt = $this->getFailedChargeAttempt($transaction->getLinkedSpaceId(),
                $transaction->getId());
            if ($failedChargeAttempt != null && $failedChargeAttempt->getFailureReason() != null) {
                $info->setFailureReason(
                    $failedChargeAttempt->getFailureReason()
                        ->getDescription());
            }
        }
        $info->save();
        return $info;
    }

    /**
     * Returns an array of the transaction's labels.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @return string[]
     */
    protected function getTransactionLabels(\Wallee\Sdk\Model\Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }
            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Returns the successful charge attempt of the transaction.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @return \Wallee\Sdk\Model\ChargeAttempt
     */
    protected function getChargeAttempt(\Wallee\Sdk\Model\Transaction $transaction)
    {
        $chargeAttemptService = $this->getChargeAttemptService();
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('charge.transaction.id', $transaction->getId()),
                $this->createEntityFilter('state', \Wallee\Sdk\Model\ChargeAttemptState::SUCCESSFUL)
            ));
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($transaction->getLinkedSpaceId(), $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Returns the payment method's image.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param Order $order
     * @return string
     */
    protected function getPaymentMethodImage(\Wallee\Sdk\Model\Transaction $transaction, Order $order)
    {
        if ($transaction->getPaymentConnectorConfiguration() == null) {
            $moduleName = $order->modue;
            if ($moduleName == "wallee") {
                $id = Wallee_Helper::getOrderMeta($order, 'walleeMethodId');
                $methodConfiguration = new Wallee_Model_MethodConfiguration($id);
                return $methodConfiguration->getImage();
            }
            return null;
        }
        if ($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() !=
             null) {
            return $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()->getResolvedImageUrl();                
        }
        return null;
        
    }

    /**
     * Returns the payment methods that can be used with the current cart.
     *
     * @param Cart $cart
     * @return \Wallee\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Cart $cart)
    {
        $currentCartId = $cart->id;
        
        if (! isset(self::$possiblePaymentMethodCache[$currentCartId]) ||
             self::$possiblePaymentMethodCache[$currentCartId] == null) {
            $transaction = $this->getTransactionFromCart($cart);
            $paymentMethods = $this->getTransactionService()->fetchPossiblePaymentMethods(
                $transaction->getLinkedSpaceId(), $transaction->getId());
            $methodConfigurationService = Wallee_Service_MethodConfiguration::instance();
            foreach ($paymentMethods as $paymentMethod) {
                $methodConfigurationService->updateData($paymentMethod);
            }
            self::$possiblePaymentMethodCache[$currentCartId] = $paymentMethods;
        }
        return self::$possiblePaymentMethodCache[$currentCartId];
    }

    
    public function checkTransactionPending(Cart $cart){
        $ids = Wallee_Helper::getCartMeta($cart, 'mappingIds');
        $transaction = $this->getTransaction($ids['spaceId'], $ids['transactionId']);
        if($transaction->getState() != \Wallee\Sdk\Model\TransactionState::PENDING){
            throw new Exception(Wallee_Helper::getModuleInstance()->l("The transaction timed out, please try again."));
        }
    }

    /**
     * Update the transaction with the given orders data.
     * The $dataSource is for the address and id information for the transaction.
     * The $orders are use to compile all lineItems, this array needs to include the $dataSource order
     *
     * @param Order $dataSource
     * @param Order[] $orders
     * @return \Wallee\Sdk\Model\Transaction
     */
    public function confirmTransaction(Order $dataSource, array $orders)
    {
        $last = new Exception('Unexpected Error');
        for ($i = 0; $i < 5; $i ++) {
            try {
                $needsCreation = false;
                $ids = Wallee_Helper::getOrderMeta($dataSource, 'mappingIds');
                $spaceId = $ids['spaceId'];
                $transaction = $this->getTransactionService()->read($ids['spaceId'],
                    $ids['transactionId']);
                
                if ($transaction->getState() != \Wallee\Sdk\Model\TransactionState::PENDING) {
                    throw new Exception(Wallee_Helper::getModuleInstance()->l("Invalid State"));
                }
                $pendingTransaction = new \Wallee\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleOrderTransactionData($dataSource, $orders, $pendingTransaction);
                $result = $this->getTransactionService()->confirm($spaceId, $pendingTransaction);
                Wallee_Helper::updateOrderMeta($dataSource, 'mappingIds',
                    array(
                        'spaceId' => $result->getLinkedSpaceId(),
                        'transactionId' => $result->getId()
                    ));
                return $result;
            } catch (\Wallee\Sdk\VersioningException $e) {
                $last = $e;
            }
        }
        throw $last;
    }


    /**
     * Assemble the transaction data for the given orders.
     *
     * @param Order $dataSource
     * @param Order[] $orders
     * @param \Wallee\Sdk\Model\TransactionPending $transaction
     */
    protected function assembleOrderTransactionData(Order $dataSource, array $orders,
        \Wallee\Sdk\Model\AbstractTransactionPending $transaction)
    {
        $transaction->setCurrency(Wallee_Helper::convertCurrencyIdToCode($dataSource->id_currency));
        $transaction->setBillingAddress($this->getAddress($dataSource->id_address_invoice));
        $transaction->setShippingAddress($this->getAddress($dataSource->id_address_delivery));
        $transaction->setCustomerEmailAddress(
            $this->getEmailAddressForCustomerId($dataSource->id_customer));
        $transaction->setCustomerId($dataSource->id_customer);
        $transaction->setLanguage(Wallee_Helper::convertLanguageIdToIETF($dataSource->id_lang));
        $transaction->setShippingMethod(
            $this->fixLength($this->getShippingMethodNameForCarrierId($dataSource->id_carrier), 200));
        
        $transaction->setLineItems(Wallee_Service_LineItem::instance()->getItemsFromOrders($orders));
        
        $transaction->setMerchantReference($dataSource->id);
        $transaction->setInvoiceMerchantReference(
            $this->fixLength($this->removeNonAscii($dataSource->reference), 100));
        
        $transaction->setSuccessUrl(
            Context::getContext()->link->getModuleLink('wallee', 'return',
                array(
                    'order_id' => $dataSource->id,
                    'secret' => Wallee_Helper::computeOrderSecret($dataSource),
                    'action' => 'success'
                ), true));
        
        $transaction->setFailedUrl(
            Context::getContext()->link->getModuleLink('wallee', 'return',
                array(
                    'order_id' => $dataSource->id,
                    'secret' => Wallee_Helper::computeOrderSecret($dataSource),
                    'action' => 'failure'
                ), true));
    }

    /**
     * Returns the transaction for the given cart.
     *
     * If no transaction exists, a new one is created.
     *
     * @param Cart $cart
     * @return \Wallee\Sdk\Model\Transaction
     */
    public function getTransactionFromCart(Cart $cart)
    {
        $currentCartId = $cart->id;
        if (! isset(self::$transactionCache[$currentCartId]) ||
             self::$transactionCache[$currentCartId] == null) {
            $ids = Wallee_Helper::getCartMeta($cart, 'mappingIds');
            if (empty($ids)) {
                $transaction = $this->createTransactionFromCart($cart);
            } else {
                $transaction = $this->loadAndUpdateTransactionFromCart($cart);
            }
            self::$transactionCache[$currentCartId] = $transaction;
        }
        return self::$transactionCache[$currentCartId];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Cart $cart
     * @return \Wallee\Sdk\Model\TransactionCreate
     */
    protected function createTransactionFromCart(Cart $cart)
    {
        $spaceId = Configuration::get(Wallee::CK_SPACE_ID, null, $cart->id_shop_group,
            $cart->id_shop);
        $createTransaction = new \Wallee\Sdk\Model\TransactionCreate();
        $createTransaction->setCustomersPresence(
            \Wallee\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT);
        $createTransaction->setAutoConfirmationEnabled(false);
        $createTransaction->setDeviceSessionIdentifier(Context::getContext()->cookie->wle_device_id);
        $createTransaction->setSpaceViewId(
            Configuration::get(Wallee::CK_SPACE_VIEW_ID, null, null, $cart->id_shop));
        $this->assembleCartTransactionData($cart, $createTransaction);
        $transaction = $this->getTransactionService()->create($spaceId, $createTransaction);
        Wallee_Helper::updateCartMeta($cart, 'mappingIds',
            array(
                'spaceId' => $transaction->getLinkedSpaceId(),
                'transactionId' => $transaction->getId()
            ));
        return $transaction;
    }

    /**
     * Loads the transaction for the given cart and updates it if necessary.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     * @param Cart $cart
     * @return \Wallee\Sdk\Model\TransactionPending
     */
    protected function loadAndUpdateTransactionFromCart(Cart $cart)
    {
        $last = new Exception('Unexpected Error');
        for ($i = 0; $i < 5; $i ++) {
            try {
                $ids = Wallee_Helper::getCartMeta($cart, 'mappingIds');
                $transaction = $this->getTransaction($ids['spaceId'],
                    $ids['transactionId']);
                if ($transaction->getState() != \Wallee\Sdk\Model\TransactionState::PENDING) {
                    return $this->createTransactionFromCart($cart);
                }
                $pendingTransaction = new \Wallee\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleCartTransactionData($cart, $pendingTransaction);
                return $this->getTransactionService()->update($ids['spaceId'],
                    $pendingTransaction);
            } catch (\Wallee\Sdk\VersioningException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    /**
     * Assemble the transaction data for the given quote.
     *
     * @param Cart $cart
     * @param \Wallee\Sdk\Model\TransactionPending $transaction
     */
    protected function assembleCartTransactionData(Cart $cart,
        \Wallee\Sdk\Model\AbstractTransactionPending $transaction)
    {
        $transaction->setCurrency(Wallee_Helper::convertCurrencyIdToCode($cart->id_currency));
        $transaction->setBillingAddress($this->getAddress($cart->id_address_invoice));
        $transaction->setShippingAddress($this->getAddress($cart->id_address_delivery));
        if($cart->id_customer != 0){
            $transaction->setCustomerEmailAddress(
                $this->getEmailAddressForCustomerId($cart->id_customer));
            $transaction->setCustomerId($cart->id_customer);
        }
        $transaction->setLanguage(Wallee_Helper::convertLanguageIdToIETF($cart->id_lang));
        $transaction->setShippingMethod(
            $this->fixLength($this->getShippingMethodNameForCarrierId($cart->id_carrier), 200));
        
        $transaction->setLineItems(Wallee_Service_LineItem::instance()->getItemsFromCart($cart));
        
        $transaction->setAllowedPaymentMethodConfigurations(array());
    }

    /**
     * Returns the billing address of the current session.
     *
     * @param int $addressId
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function getAddress($addressId)
    {
        $prestaAddress = new Address($addressId);
        
        $address = new \Wallee\Sdk\Model\AddressCreate();
        $address->setCity($this->fixLength($prestaAddress->city, 100));
        $address->setFamilyName($this->fixLength($prestaAddress->lastname, 100));
        $address->setGivenName($this->fixLength($prestaAddress->firstname, 100));
        $address->setOrganizationName($this->fixLength($prestaAddress->company, 100));
        $address->setPhoneNumber($prestaAddress->phone);
        
        if ($prestaAddress->id_country != null) {
            $country = new Country(intval($prestaAddress->id_country));
            $address->setCountry($country->iso_code);
        }
        if ($prestaAddress->id_state != null) {
            $state = new State(intval($prestaAddress->id_state));
            $code = $state->iso_code;
            if (! empty($code)) {
                $address->setPostalState($code);
            }
        }
        $address->setPostCode($this->fixLength($prestaAddress->postcode, 40));
        $address->setStreet(
            $this->fixLength(trim($prestaAddress->address1 . "\n" . $prestaAddress->address2), 300));
        $address->setEmailAddress($this->getEmailAddressForCustomerId($prestaAddress->id_customer));
        return $address;
    }

    /**
     * Returns the current customer's email address.
     *
     * @param
     *            $id
     * @return string
     */
    protected function getEmailAddressForCustomerId($id)
    {
        $customer = new Customer($id);
        return $customer->email;
    }

    /**
     * Returns the shipping name
     *
     * @param int $carrierId
     * @return string
     */
    protected function getShippingMethodNameForCarrierId($carrierId)
    {
        $carrier = new Carrier($carrierId);
        return $carrier->name;
    }
}
