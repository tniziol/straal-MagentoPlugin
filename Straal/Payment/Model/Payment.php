<?php
namespace Straal\Payment\Model;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\Registry;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class Payment extends \Magento\Payment\Model\Method\AbstractMethod {

    const STRAAL_PAYMENT_CODE = 'straal_payment';
	
   

    protected $_code = self::STRAAL_PAYMENT_CODE;

    /**
     *
     * @var \Magento\Framework\UrlInterface 
     */
    protected $_urlBuilder;
    
    protected $_checkoutSession;
	protected $_order;   
	protected $_orderFactory;	
	protected $endpoint  ;	
	
	protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $invoiceService;
	protected $transaction;
	protected $registry;
	protected $invoiceRepository;
	protected $invoiceSender;
    protected $_logger;
	protected $Psrlogger;
	

    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
      public function __construct(
        \Magento\Framework\Model\Context $context,        
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
 		Registry $registry, 
  	    \Magento\Sales\Model\OrderFactory $orderFactory,		  
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Straal\Payment\Helper\Payment $helper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Checkout\Model\Session $checkoutSession ,
     	InvoiceService $invoiceService,
		InvoiceSender $invoiceSender,
		InvoiceRepositoryInterface $invoiceRepository,
	    \Magento\Framework\DB\Transaction $transaction,
		\Psr\Log\LoggerInterface $Psrlogger  
    ) {
        

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,			
            $paymentData,
            $scopeConfig,
            $logger
        );
		  
	    $this->helper = $helper;
        $this->orderSender = $orderSender;
        $this->httpClientFactory = $httpClientFactory;
        $this->_checkoutSession = $checkoutSession;
		$this->_orderFactory = $orderFactory;	
	    $this->endpoint  = 'https://api.straal.com/';
	    $this->invoiceService = $invoiceService;
		$this->transaction = $transaction;  
	    $this->registry = $registry;
		$this->invoiceRepository = $invoiceRepository;
		$this->invoiceSender = $invoiceSender;  
	  	$this->_logger = $Psrlogger;

    }

	
	protected function _getOrder()
    {
        if (!$this->_order) {
            $incrementId =  $this->_checkoutSession->getLastRealOrderId();
			
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    public function getRedirectUrl() {
        return $this->helper->getUrl($this->getConfigData('redirect_url'));
    }
	
	public function getNotificationUrl() {
        return $this->helper->getUrl($this->getConfigData('notification_url'));
    }

	public function getNotifyUser() {
        return $this->getConfigData('notify_user');
    }
	public function getNotifyPass() {
        return $this->getConfigData('notify_pass');
    }
	
	public function addLog($log, $logdata)
    {
       if ($this->getConfigData('debug')){
		$this->_logger->info($log. json_encode($logdata)); 
	   }
    }
	
	public function generate_customer_reference_order( $order ) {
        $customer_id = $order->getCustomerId();
        $customer_email = $order->getCustomerEmail();
        return $customer_id == 0 ? NULL : substr($customer_id . '#' . md5($customer_email), 0, 30);
    }
	

	public function create_customer( $order ) {
		
		$billing = $order->getBillingAddress();
        $customer_email = $order->getCustomerEmail();
        $customer_reference = $this->generate_customer_reference_order( $order );

        $customer_params = array(
            'email' 	=> $customer_email,
            'reference' => $customer_reference,
        );
		$this->addLog('create_customer: Requesting customer creation.', $customer_params);	
		
		$response = $this->api_call($this->endpoint . 'v1/customers', $customer_params);  
		
		$this->addLog('create_customer: Customer creation response.', $customer_params);	
		
        return $this->extract_customer_id_from_response($order, $response);
    }
	
	public function extract_customer_id_from_response( $order, $response ) {
        
            if (property_exists($response, 'errors')) {
                foreach ( $response->errors as $error ) {
                    if ( $error->code === 12005 ) {
                        return $this->get_customer_id_by_reference( $order );
                    }
                }
            }
		else {
			$this->check_errors($response);
			
            return $response->id;
        }
        
    }
	public function get_customer_id_by_reference( $order ) {
        $response = $this->api_call( $this->endpoint . 'v1/customers?reference__eq=' . urlencode( $this->generate_customer_reference_order( $order ) )); 
		$this->addLog( 'get_customer_id_by_reference: Customer data from Magento reference.', $response );
		
        $customer = array_values( $response->data )[0];
        return $customer->id;
    }

	
		
    /**
     * Return url according to environment
     * @return string
     */
	//payment page url
    public function getstraalUrl() {

		$order = $this->_getOrder();
		$billing = $order->getBillingAddress();				
		$order_id = $order->getEntityId();
		
		$amount = number_format(($order->getGrandTotal()), 2,'.', '');;		
		$amount = ($amount * 100);
		
		$desc =  'Order #' . $order_id;		
							
		$RequestData = array();
		$RequestData['amount']    	= $amount;			
		$RequestData['currency']    	= $order->getBaseCurrencyCode();												
		$RequestData['ttl']    	= 600;
		$RequestData['return_url']    	= $this->helper->getUrl('checkout/onepage/success');
		$RequestData['failure_url']    = $this->helper->getUrl('checkout/onepage/failure');				
		$RequestData['order_description']    = $desc;						
		$RequestData['order_reference']    = $order_id;		
		
		
		$customer_id = $this->create_customer($order);
		$this->addLog('initialize_checkout: Requesting checkout initialization.', $RequestData);		
		$response = $this->api_call( $this->endpoint . 'v1/customers/' . $customer_id . '/checkouts',$RequestData);			
		$this->addLog('initialize_checkout: Checkout initialization response.', $response);		
		$this->check_errors($response);
		$pay_url = $response->checkout_url;
		

		header("Location: $pay_url");
		exit;			
    }

	
	public function api_call($RequestUrl,  $RequestData = array ()) {	
		
		if ($this->getConfigData('sandbox_mode'))					
			$api_key = $this->getConfigData('sandbox_api_key');
		else  $api_key = $this->getConfigData('api_key');		
		
		
		$bauth = base64_encode(':' .$api_key);	
		
	
		$headers = array(
    		'Content-Type: application/json',
    		'Authorization: Basic '. $bauth			
		);	
		
	    $ch = curl_init($RequestUrl);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);    	
	
		curl_setopt($ch, CURLOPT_POST, 1);
		if (!empty($RequestData)) {						
			$RequestDataJson = json_encode($RequestData, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $RequestDataJson);	    
		}
		else curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
    	$PaymentRes = curl_exec($ch);				

		$PaymentAr = json_decode($PaymentRes);	
		
		
		return 	$PaymentAr;
}	
	
	public function check_errors($response_data){
	if (property_exists($response_data, 'errors')) {
		foreach ( $response_data->errors as $error ) {
			throw new \Magento\Framework\Exception\LocalizedException(__($error->message));	
		}
		}
		
	if (property_exists($response_data, 'message')) {		
		echo $response_data->message;
		exit;					
		}	
	}
		
  

    public function postProcessing(\Magento\Sales\Model\Order $order,
            \Magento\Framework\DataObject $payment, $notification_data) {
		
		$totalAmount = ($notification_data->transaction->amount / 100);
		$trans_id = $notification_data->transaction->id;		        
        $payment->setTransactionId($trans_id);        
        $payment->addTransaction(TransactionInterface::TYPE_ORDER);
		$payment->setStatus('APPROVED');				
        $payment->setIsTransactionClosed(0)->save();				
		
		
        $order->setStatus('processing');				
		$order->setTotalPaid($totalAmount);
        $order->save();		
		
		$this->createInvoice($order, $trans_id);
    }			 	
	
	protected function createInvoice($order, $trans_id)
    {
        
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
		$invoice->setTransactionId($trans_id);
        $invoice = $this->invoiceRepository->save($invoice);
		
        $this->registry->register('current_invoice', $invoice);        
        $invoice->getOrder()->setIsInProcess(true);

        $transactionSave = 
			$this->transaction->addObject($invoice)
			->addObject($invoice->getOrder());

        $transactionSave->save();
        $this->invoiceSender->send($invoice);
		
        //Send Invoice mail to customer
        //$order->addStatusHistoryComment(__('Notified customer about invoice creation #%1.', $invoice->getId()))->setIsCustomerNotified(true);


    }
	
	public function refund_transaction( $transaction_id, $amount, $reason ) {                
            $refund_params = array(
                'amount'     => $amount,
                'extra_data' => array(
                    'reason' => $reason
                )
            );            
			$this->addLog( 'refund_transaction: Requesting refund.', $refund_params );
			$response = $this->api_call( $this->endpoint . 'v1/transactions/' . $transaction_id . '/refund',$refund_params);	
			$this->addLog( 'refund_transaction: Refund request response.', $response );
           
            return $response;
	}
	
}