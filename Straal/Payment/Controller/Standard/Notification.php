<?php

namespace Straal\Payment\Controller\Standard;
use Psr\Log\LoggerInterface;

class Notification extends \Straal\Payment\Controller\StraalAbstract {
	protected $_logger;

    public function execute() {        
		$this->checkHttpAuth();
		$json = file_get_contents('php://input');				
		$pay_resp = json_decode($json);
		$this->getPaymentMethod()->addLog('Notification Response Received from Straal :',$pay_resp);
		if ( !empty($pay_resp) ) {
			if ( $pay_resp->event == 'checkout_attempt_finished' ) {
				$notification_data = $pay_resp->data;
				$order_id = $notification_data->checkout->order_reference;
				$status = $notification_data->checkout_attempt->status;
				
				if ( !empty($status) ) {
					
						$paymentMethod = $this->getPaymentMethod();
						$order = $this->getOrderById($order_id);
            			$orderStatus = $order->getStatus();
						if($orderStatus=="pending"){ 
                			if ($status == "succeeded") {                    			
                    			$payment = $order->getPayment();
                    			$paymentMethod->postProcessing($order, $payment, $notification_data);
                    			$this->messageManager->addSuccess(__('Your payment was successful'));

                			} else {
                    			$order->cancel()->save();
                    			$this->_checkoutSession->restoreQuote();
                    			$this->messageManager->addErrorMessage(__('Payment failed.'));                   			
                			}
            			}
					
				}				
			}
		}
		
		echo "OK";
		exit;

        
    }
	
	function checkHttpAuth()
    {
		$NotifyUser = $this->getPaymentMethod()->getNotifyUser();  
		$NotifyPass = $this->getPaymentMethod()->getNotifyPass();  
		if (!empty($NotifyUser)){
        	if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            	header('WWW-Authenticate: Basic realm= magento');
            	header('HTTP/1.0 401 Unauthorized');
            	echo 'Access denied';
            	exit;
        	} else {
            	if (($_SERVER['PHP_AUTH_USER'] != $NotifyUser) || ($_SERVER['PHP_AUTH_PW'] != $NotifyPass)) {
                
                header('HTTP/1.0 401 Unauthorized');
                echo 'Access denied';
				$this->getPaymentMethod()->addLog('invalid notification use and password');	
                exit;
            }
        }
		}
    }
	
	}
