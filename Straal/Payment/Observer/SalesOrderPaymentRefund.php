<?php
/**
 * Copyright © Evalent Group AB, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Straal\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\App\RequestInterface;

class SalesOrderPaymentRefund implements ObserverInterface
{
    protected $_invoice;
    protected $_request;
    protected $methodCode = \Straal\Payment\Model\Payment::STRAAL_PAYMENT_CODE;        
    protected $method;
	protected $_Straal_Payment;	
    

    protected $_creditmemoTotal;
    protected $_creditmemoTotalControl;

    public function __construct(
        Invoice $invoice,
        RequestInterface $request,		
		\Magento\Payment\Helper\Data $paymenthelper,
		\Straal\Payment\Model\Payment $Straal_Payment
    ) {
        $this->_invoice = $invoice;
        $this->_request = $request;      
	    $this->method = $paymenthelper->getMethodInstance($this->methodCode);
		$this->_Straal_Payment = $Straal_Payment;
    }


    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
		
        $creditmemo = $observer->getEvent()->getData('creditmemo');		
		$refund_amt =  $creditmemo->getGrandTotal();
		$refund_amt = round($refund_amt * 100);
		
        if (!$creditmemo->getDoTransaction()) {
            return;
        }
        $order = $creditmemo->getOrder();
        $payment = $observer->getEvent()->getData('payment');
		if ($invoiceId = $this->_request->getParam('invoice_id')) 
        $invoiceModel = $this->_invoice->load($invoiceId);
		$Transaction_id  = $invoiceModel->getTransactionId();	
		$response_data =$this->_Straal_Payment->refund_transaction($Transaction_id, $refund_amt, __('Refund'));
		$this->_Straal_Payment->check_errors($response_data);        

        return $this;
    }
}
