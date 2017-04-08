<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Observer extends Magestore_Affiliateplus_Model_Observer
{
    public function orderPlaceAfter($observer)
    {
        // Changed By Adam 28/07/2014
        if (!Mage::helper('affiliateplus')->isAffiliateModuleEnabled())
            return;
        $order = $observer['order'];

        /*Added By Adam (27/08/2016): create transaction from existed order*/
        $affiliateAccount = $observer['affiliate'];
        $transactionObj = $observer['transaction'];

        // check to run this function 1 time for 1 order
        if (Mage::getSingleton('core/session')->getData("affiliateplus_order_placed_" . $order->getId())) {
            return $this;
        }
        Mage::getSingleton('core/session')->setData("affiliateplus_order_placed_" . $order->getId(), true);

        // Use Store Credit to Checkout
        if ($baseAmount = $order->getBaseAffiliateCredit()) {
            $session = Mage::getSingleton('checkout/session');
            $session->setUseAffiliateCredit('');
            $session->setAffiliateCredit(0);

            $account = Mage::getSingleton('affiliateplus/session')->getAccount();
            $payment = Mage::getModel('affiliateplus/payment')
                ->setPaymentMethod('credit')
                ->setAmount(-$baseAmount)
                ->setAccountId($account->getId())
                ->setAccountName($account->getName())
                ->setAccountEmail($account->getEmail())
                ->setRequestTime(now())
                ->setStatus(3)
                ->setIsRequest(1)
                ->setIsPayerFee(0)
                ->setData('is_created_by_recurring', 1)
                ->setData('is_refund_balance', 1);
            if (Mage::helper('affiliateplus/config')->getSharingConfig('balance') == 'store') {
                $payment->setStoreIds($order->getStoreId());
            }
            $paymentMethod = $payment->getPayment();
            $paymentMethod->addData(array(
                'order_id' => $order->getId(),
                'order_increment_id' => $order->getIncrementId(),
                'base_paid_amount' => -$baseAmount,
                'paid_amount' => -$order->getAffiliateCredit(),
            ));
            try {
                $payment->save();
                $paymentMethod->savePaymentMethodInfo();
            } catch (Exception $e) {

            }
        }

        if (!$order->getBaseSubtotal()) {
            return $this;
        }

        /*Added By Adam (27/08/2016): create transaction from existed order*/
        if($affiliateAccount && $affiliateAccount->getId()) {
            $info[$affiliateAccount->getIdentifyCode()] = array(
                'index' => 1,
                'code'  => $affiliateAccount->getIdentifyCode(),
                'account'   => $affiliateAccount,
            );
            $cookie = Mage::getSingleton('core/cookie');
            $infoObj = new Varien_Object(array(
                'info'	=> $info,
            ));
            Mage::dispatchEvent('affiliateplus_get_affiliate_info',array(
                'cookie'	=> $cookie,
                'info_obj'	=> $infoObj,
            ));
            $affiliateInfo = $infoObj->getInfo();

        } else {
            $code = Mage::registry('aff_code');
            $affiliateInfo = Mage::helper('bzaikia_api/cookie')->getAffiliateInfo($code);
        }
        Mage::log('foreach', null, 'aff.log');
        $account = '';
        foreach ($affiliateInfo as $info)
            if ($info['account']) {
                $account = $info['account'];
                break;
            }

        if ($account && $account->getId()) {

            // Log affiliate tracking referal - only when has sales
            if ($this->_getConfigHelper()->getCommissionConfig('life_time_sales')) {
                $tracksCollection = Mage::getResourceModel('affiliateplus/tracking_collection');
                if ($order->getCustomerId()) {
                    $tracksCollection->getSelect()
                        ->where("customer_id = {$order->getCustomerId()} OR customer_email = ?", $order->getCustomerEmail());
                } else {
                    $tracksCollection->addFieldToFilter('customer_email', $order->getCustomerEmail());
                }
                if (!$tracksCollection->getSize()) {
                    try {
                        Mage::getModel('affiliateplus/tracking')->setData(array(
                            'account_id' => $account->getId(),
                            'customer_id' => $order->getCustomerId(),
                            'customer_email' => $order->getCustomerEmail(),
                            'created_time' => now()
                        ))->save();
                    } catch (Exception $e) {

                    }
                }
            }

            $baseDiscount = $order->getBaseAffiliateplusDiscount();
            //$maxCommission = $order->getBaseGrandTotal() - $order->getBaseShippingAmount();
            // Before calculate commission
            $commissionObj = new Varien_Object(array(
                'commission' => 0,
                'default_commission' => true,
                'order_item_ids' => array(),
                'order_item_names' => array(),
                'commission_items' => array(),
                'extra_content' => array(),
                'tier_commissions' => array(),
                //'affiliateplus_commission_item' => '',
            ));
            Mage::dispatchEvent('affiliateplus_calculate_commission_before', array(
                'order' => $order,
                'affiliate_info' => $affiliateInfo,
                'commission_obj' => $commissionObj,
            ));

            $commissionType = $this->_getConfigHelper()->getCommissionConfig('commission_type');
            $commissionValue = floatval($this->_getConfigHelper()->getCommissionConfig('commission'));
            if (Mage::helper('bzaikia_api/cookie')->getNumberOrdered()) {
                if ($this->_getConfigHelper()->getCommissionConfig('use_secondary')) {
                    $commissionType = $this->_getConfigHelper()->getCommissionConfig('secondary_type');
                    $commissionValue = floatval($this->_getConfigHelper()->getCommissionConfig('secondary_commission'));
                }
            }
            $commission = $commissionObj->getCommission();
            $orderItemIds = $commissionObj->getOrderItemIds();
            $orderItemNames = $commissionObj->getOrderItemNames();
            $commissionItems = $commissionObj->getCommissionItems();
            $extraContent = $commissionObj->getExtraContent();
            $tierCommissions = $commissionObj->getTierCommissions();
//            $affiliateplusCommissionItem = $commissionObj->getAffiliateplusCommissionItem();

            $defaultItemIds = array();
            $defaultItemNames = array();
            $defaultAmount = 0;
            $defCommission = 0;

            /* Changed By Adam to customize function: Commission for whole cart 22/07/2014 */
            // Calculate the total price of items ~~ baseSubtotal
            $baseItemsPrice = 0;
            foreach ($order->getAllItems() as $item) {
                if ($item->getParentItemId()) {
                    continue;
                }

                // Kiem tra xem item da tinh trong program nao chua, neu roi thi ko tinh nua
                if (in_array($item->getId(), $commissionItems)) {
                    continue;
                }
                if ($item->getHasChildren() && $item->isChildrenCalculated()) {

                    foreach ($item->getChildrenItems() as $child) {
                        $baseItemsPrice += $item->getQtyOrdered() * ($child->getQtyOrdered() * $child->getBasePrice() - $child->getBaseDiscountAmount() - $child->getBaseAffiliateplusAmount() - $child->getBaseAffiliateplusCredit() - $child->getRewardpointsBaseDiscount());
                        //$baseItemsPrice += $item->getQtyOrdered() * ($child->getQty() * $child->getBasePrice() - $child->getBaseDiscountAmount() - $child->getBaseAffiliateplusAmount());
                    }
                } elseif ($item->getProduct()) {

                    $baseItemsPrice += $item->getQtyOrdered() * $item->getBasePrice() - $item->getBaseDiscountAmount() - $item->getBaseAffiliateplusAmount() - $item->getBaseAffiliateplusCredit() - $item->getRewardpointsBaseDiscount();
                }
            }

            if ($commissionValue && $commissionObj->getDefaultCommission()) {
                if ($commissionType == 'percentage') {
                    if ($commissionValue > 100)
                        $commissionValue = 100;
                    if ($commissionValue < 0)
                        $commissionValue = 0;
                }

                foreach ($order->getAllItems() as $item) {
                    $affiliateplusCommissionItem = '';
                    if ($item->getParentItemId()) {
                        continue;
                    }
                    if (in_array($item->getId(), $commissionItems)) {
                        continue;
                    }

                    if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                        // $childHasCommission = false;
                        foreach ($item->getChildrenItems() as $child) {
                            $affiliateplusCommissionItem = '';
                            if ($this->_getConfigHelper()->getCommissionConfig('affiliate_type') == 'profit')
                                $baseProfit = $child->getBasePrice() - $child->getBaseCost();
                            else
                                $baseProfit = $child->getBasePrice();
                            $baseProfit = $child->getQtyOrdered() * $baseProfit - $child->getBaseDiscountAmount() - $child->getBaseAffiliateplusAmount() - $child->getBaseAffiliateplusCredit() - $child->getRewardpointsBaseDiscount();
                            if ($baseProfit <= 0)
                                continue;

                            // $childHasCommission = true;
                            /* Changed By Adam: Commission for whole cart 22/07/2014 */
                            if ($commissionType == "cart_fixed") {
                                $commissionValue = min($commissionValue, $baseItemsPrice);
                                $itemPrice = $child->getQtyOrdered() * $child->getBasePrice() - $child->getBaseDiscountAmount() - $child->getBaseAffiliateplusAmount() - $child->getBaseAffiliateplusCredit() - $child->getRewardpointsBaseDiscount();
                                $itemCommission = $itemPrice * $commissionValue / $baseItemsPrice;
                                $defaultCommission = min($itemPrice * $commissionValue / $baseItemsPrice, $baseProfit);
                            } elseif ($commissionType == 'fixed')
                                $defaultCommission = min($child->getQtyOrdered() * $commissionValue, $baseProfit);
                            elseif ($commissionType == 'percentage')
                                $defaultCommission = $baseProfit * $commissionValue / 100;

                            // Changed By Adam 14/08/2014: Invoice tung phan
                            $affiliateplusCommissionItem .= $defaultCommission . ",";
                            $commissionObj = new Varien_Object(array(
                                'profit' => $baseProfit,
                                'commission' => $defaultCommission,
                                'tier_commission' => array(),
                                'base_item_price' => $baseItemsPrice, // Added By Adam 22/07/2014
                                'affiliateplus_commission_item' => $affiliateplusCommissionItem     // Added By Adam 14/08/2014
                            ));
                            Mage::dispatchEvent('affiliateplus_calculate_tier_commission', array(
                                'item' => $child,
                                'account' => $account,
                                'commission_obj' => $commissionObj
                            ));

                            if ($commissionObj->getTierCommission())
                                $tierCommissions[$child->getId()] = $commissionObj->getTierCommission();
                            $commission += $commissionObj->getCommission();
                            $child->setAffiliateplusCommission($commissionObj->getCommission());

                            // Changed By Adam 14/08/2014: Invoice tung phan
                            $child->setAffiliateplusCommissionItem($commissionObj->getAffiliateplusCommissionItem());

                            $defCommission += $commissionObj->getCommission();
                            $defaultAmount += $child->getBasePrice();

                            $orderItemIds[] = $child->getProduct()->getId();
                            $orderItemNames[] = $child->getName();

                            $defaultItemIds[] = $child->getProduct()->getId();
                            $defaultItemNames[] = $child->getName();
                        }
                        // if ($childHasCommission) {
                        // $orderItemIds[] = $item->getProduct()->getId();
                        // $orderItemNames[] = $item->getName();
                        // $defaultItemIds[] = $item->getProduct()->getId();
                        // $defaultItemNames[] = $item->getName();
                        // }
                    } else {
                        if ($this->_getConfigHelper()->getCommissionConfig('affiliate_type') == 'profit')
                            $baseProfit = $item->getBasePrice() - $item->getBaseCost();
                        else
                            $baseProfit = $item->getBasePrice();
                        $baseProfit = $item->getQtyOrdered() * $baseProfit - $item->getBaseDiscountAmount() - $item->getBaseAffiliateplusAmount() - $item->getBaseAffiliateplusCredit() - $item->getRewardpointsBaseDiscount();
                        if ($baseProfit <= 0)
                            continue;
                        //jack
                        if ($item->getProduct())
                            $inProductId = $item->getProduct()->getId();
                        else
                            $inProductId = $item->getProductId();
                        //
                        $orderItemIds[] = $inProductId;
                        $orderItemNames[] = $item->getName();

                        $defaultItemIds[] = $inProductId;
                        $defaultItemNames[] = $item->getName();

                        /* Changed BY Adam 22/07/2014 */
                        if ($commissionType == 'cart_fixed') {
                            $commissionValue = min($commissionValue, $baseItemsPrice);
                            $itemPrice = $item->getQtyOrdered() * $item->getBasePrice() - $item->getBaseDiscountAmount() - $item->getBaseAffiliateplusAmount() - $item->getBaseAffiliateplusCredit() - $item->getRewardpointsBaseDiscount();
                            $itemCommission = $itemPrice * $commissionValue / $baseItemsPrice;
                            $defaultCommission = min($itemPrice * $commissionValue / $baseItemsPrice, $baseProfit);
                        } elseif ($commissionType == 'fixed')
                            $defaultCommission = min($item->getQtyOrdered() * $commissionValue, $baseProfit);
                        elseif ($commissionType == 'percentage')
                            $defaultCommission = $baseProfit * $commissionValue / 100;

                        // Changed By Adam 14/08/2014: Invoice tung phan
                        $affiliateplusCommissionItem .= $defaultCommission . ",";
                        $commissionObj = new Varien_Object(array(
                            'profit' => $baseProfit,
                            'commission' => $defaultCommission,
                            'tier_commission' => array(),
                            'base_item_price' => $baseItemsPrice, // Added By Adam 22/07/2014
                            'affiliateplus_commission_item' => $affiliateplusCommissionItem, // Added By Adam 14/08/2014
                        ));
                        Mage::dispatchEvent('affiliateplus_calculate_tier_commission', array(
                            'item' => $item,
                            'account' => $account,
                            'commission_obj' => $commissionObj
                        ));

                        if ($commissionObj->getTierCommission())
                            $tierCommissions[$item->getId()] = $commissionObj->getTierCommission();
                        $commission += $commissionObj->getCommission();
                        $item->setAffiliateplusCommission($commissionObj->getCommission());
                        // Changed By Adam 14/08/2014: Invoice tung phan
                        $item->setAffiliateplusCommissionItem($commissionObj->getAffiliateplusCommissionItem());

                        $defCommission += $commissionObj->getCommission();
                        $defaultAmount += $item->getBasePrice();
                    }
                }
            }
            if (!$baseDiscount && !$commission)
                return $this;

            // $customer = Mage::getSingleton('customer/session')->getCustomer();
            // Create transaction
            $transactionData = array(
                'account_id' => $account->getId(),
                'account_name' => $account->getName(),
                'account_email' => $account->getEmail(),
                'customer_id' => $order->getCustomerId(), // $customer->getId(),
                'customer_email' => $order->getCustomerEmail(), // $customer->getEmail(),
                'order_id' => $order->getId(),
                'order_number' => $order->getIncrementId(),
                'order_item_ids' => implode(',', $orderItemIds),
                'order_item_names' => implode(',', $orderItemNames),
                'total_amount' => $order->getBaseSubtotal(),
                'discount' => $baseDiscount,
                'commission' => $commission,
                'created_time' => now(),
                'status' => '2',
                'store_id' => $order->getStoreId(),
                'extra_content' => $extraContent,
                'tier_commissions' => $tierCommissions,
                //'ratio'			=> $ratio,
                //'original_commission'	=> $originalCommission,
                'default_item_ids' => $defaultItemIds,
                'default_item_names' => $defaultItemNames,
                'default_commission' => $defCommission,
                'default_amount' => $defaultAmount,
                'type' => 3,
            );
            if ($account->getUsingCoupon()) {
                $session = Mage::getSingleton('checkout/session');
                $transactionData['coupon_code'] = $session->getData('affiliate_coupon_code');
                if ($program = $account->getUsingProgram()) {
                    $transactionData['program_id'] = $program->getId();
                    $transactionData['program_name'] = $program->getName();
                } else {
                    $transactionData['program_id'] = 0;
                    $transactionData['program_name'] = 'Affiliate Program';
                }
                $session->unsetData('affiliate_coupon_code');
                $session->unsetData('affiliate_coupon_data');
            }
            //jack
            else {
                $checkProgramByConfig = Mage::getStoreConfig('affiliateplus/program/enable');
                if ($checkProgramByConfig == 0 || !Mage::helper('core')->isModuleEnabled('Magestore_Affiliateplusprogram')) {
                    $transactionData['program_id'] = 0;
                    $transactionData['program_name'] = 'Affiliate Program';
                }
            }
            //
            $transaction = Mage::getModel('affiliateplus/transaction')->setData($transactionData)->setId(null);

            Mage::dispatchEvent('affiliateplus_calculate_commission_after', array(
                'transaction' => $transaction,
                'order' => $order,
                'affiliate_info' => $affiliateInfo,
            ));

            try {
                $transaction->save();
                Mage::dispatchEvent('affiliateplus_recalculate_commission', array(
                    'transaction' => $transaction,
                    'order' => $order,
                    'affiliate_info' => $affiliateInfo,
                ));

                if ($transaction->getIsChangedData())
                    $transaction->save();
                Mage::dispatchEvent('affiliateplus_created_transaction', array(
                    'transaction' => $transaction,
                    'order' => $order,
                    'affiliate_info' => $affiliateInfo,
                ));

                $transaction->sendMailNewTransactionToAccount();
                $transaction->sendMailNewTransactionToSales();
                if(is_object($transactionObj))
                    $transactionObj->setTransaction($transaction);
            } catch (Exception $e) {
                // Exception
            }
        }

    }

    /**
     * @param $observer
     */
    public function customerSaveAfter($observer)
    {
        $customer = $observer->getCustomer();
        $isAffiliate = $customer->getData('is_affiliate');

        if ($isAffiliate)
        {
            $account = Mage::getModel('affiliateplus/account')->loadByCustomerId($customer->getId());
            if (!$account->getId()) {
                Mage::helper('affiliateplus/account')->createAffiliateAccount($account->getEmail(), $account->getEmail(), $customer, 0, '', '', null, null, '');
            }
        }
    }

    /**
     * @param $observer
     */
    public function geneBraintreeSaleArray($observer)
    {
        $payment = $observer->getPayment();
        $customer = $payment->getOrder()->getCustomer();
        $request = $observer->getRequest();
        $data = $request->getData('sale_array');
        $data['customerId'] = $customer->getData('braintree_customer_id');
        $request->setData('sale_array', $data);
    }

    public function salesQuoteItemQtySetAfter($observer)
    {
        /**
         * @var $item Mage_Sales_Model_Quote_Item
         */
        $item = $observer->getItem();
        if ($item->getData('has_error')) {
            $product = $item->getProduct();
            $stockItem = $product->getStockItem();
            if ($stockItem->getData('is_in_stock')) {
                $item->setData('qty', $stockItem->getQty());
            } else {
                $item->setData('delete', 1);
            }
        }
    }
}