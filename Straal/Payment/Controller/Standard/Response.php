<?php

namespace Straal\Payment\Controller\Standard;

class Response extends \Straal\Payment\Controller\StraalAbstract {

    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');
		

        try {
            $paymentMethod = $this->getPaymentMethod();
            
            $params = $paymentMethod->validateResponse();
			
			$status = $params['replyCode'];												
            
			
			$order_id  = trim($params['trans_refNum']);
			
			$order = $this->getOrderById($order_id);
            $orderStatus = $order->getStatus();
			
            if($orderStatus=="pending"){ 
                if ($status == "000") {
                    $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                    $payment = $order->getPayment();
                    $paymentMethod->postProcessing($order, $payment, $params);
                    $this->messageManager->addSuccess(__('Your payment was successful'));

                } else {
                    $order->cancel()->save();
                    $this->_checkoutSession->restoreQuote();
                    $this->messageManager->addErrorMessage(__('Payment failed. Please try again or choose a different payment method'));
                    $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/failure');
                }
            } else {
				if($orderStatus=="processing") $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
				else $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/failure');
                //$this->messageManager->addNotice(__('Your payment was already processed'));
            }    
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

        $this->getResponse()->setRedirect($returnUrl);
    }

}
