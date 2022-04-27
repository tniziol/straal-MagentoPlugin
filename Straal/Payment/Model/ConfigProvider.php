<?php

namespace Straal\Payment\Model;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    protected $methodCode = \Straal\Payment\Model\Payment::STRAAL_PAYMENT_CODE;
    
    
    protected $method;
	

    public function __construct(\Magento\Payment\Helper\Data $paymenthelper){
        $this->method = $paymenthelper->getMethodInstance($this->methodCode);
    }

    public function getConfig(){

        return $this->method->isAvailable() ? [
            'payment'=>['payment'=>[
                'redirectUrl'=>$this->method->getRedirectUrl()  
            ]
        ]
        ]:[];
    }
}
