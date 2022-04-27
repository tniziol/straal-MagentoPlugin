<?php

namespace Straal\Payment\Controller\Standard;


class Redirect extends \Straal\Payment\Controller\StraalAbstract {		
    public function execute() {				
        $this->getPaymentMethod()->getstraalUrl();        
    }

}
