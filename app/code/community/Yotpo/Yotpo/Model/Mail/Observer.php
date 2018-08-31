<?php

class Yotpo_Yotpo_Model_Mail_Observer
{
	
	public function __construct()
	{
		
	}
	
	/**
	* send an api call to yotpo noitifying about new purchase
	* @param Varien_Event_Observer $observer
	* @return Yotpo_Yotpo_Model_Mail_Observer
	*/
	public function mail_after_purchase($observer)
	{
		try {

			$event = $observer->getEvent();
			$order = $event->getOrder();
                        $customerData = $order->getData();
			$store_id = $order->getStoreId();
			$orderStatuses = Mage::getStoreConfig('yotpo/yotpo_general_group/custom_order_status', $order->getStore());
			if ($orderStatuses == null) {
				$orderStatuses = array('complete');
			} else {
				$orderStatuses = array_map('strtolower', explode(' ', $orderStatuses));
			}

			if (!Mage::helper('yotpo/apiClient')->isEnabled($store_id))
			{
				return $this;
			}

			if (!in_array($order->getStatus(), $orderStatuses)) {
				return $this;
			}
			$data = array();
			if (!$order->getCustomerIsGuest()) {
				$data["user_reference"] = $order->getCustomerId();
			}
			$customer_name = $customerData['customer_firstname'].' '.$customerData['customer_lastname'];
			if($order->getCustomerIsGuest()){
				$customer_name = $order->getBillingAddress()->getName();
			}
			$data["email"] = $order->getCustomerEmail();
			$data["customer_name"] = $customer_name; 
			$data["order_id"] = $order->getIncrementId();
			$data["order_date"] = $this->getCreationDate($order)->toString('yyyy-MM-dd HH:mm:ss');
			$data['platform'] = 'magento';
			$data['currency_iso'] = $order->getOrderCurrency()->getCode();
			$data['products'] = Mage::helper('yotpo/apiClient')->prepareProductsData($order);

			$data['utoken'] = Mage::helper('yotpo/apiClient')->oauthAuthentication($store_id);
			if ($data['utoken'] == null) {
				//failed to get access token to api
				Mage::log('access token recieved from yotpo api is null');
				return $this;
			}

			Mage::helper('yotpo/apiClient')->createPurchases($data, $store_id);
			return $this;	

		} catch(Exception $e) {
			Mage::log('Failed to send mail after purchase. Error: '.$e);
		}
	}

    /**
     * @param Mage_Sales_Model_Order $order
     * @return Zend_Date
     */
    private function getCreationDate($order)
    {
        $statusesWarrantingShipmentDate = ["shipped", "complete"];
        if (in_array($order->getStatus(), $statusesWarrantingShipmentDate)) {
            /** @var Mage_Sales_Model_Resource_Order_Shipment_Collection $shipments */
            $shipments = $order->getShipmentsCollection();
            /** @var Mage_Sales_Model_Order_Shipment $lastShipment */
            $lastShipment = $shipments->getLastItem();
            if ($lastShipment->getId()){
                return $lastShipment->getCreatedAtDate();
            }
        }
        return $order->getCreatedAtDate();
    }
}