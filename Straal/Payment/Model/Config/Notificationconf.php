<?php
namespace Straal\Payment\Model\Config;



class Notificationconf extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $methodCode = \Straal\Payment\Model\Payment::STRAAL_PAYMENT_CODE;    
    
    protected $method;
	
	public function __construct(\Magento\Payment\Helper\Data $paymenthelper){
        $this->method = $paymenthelper->getMethodInstance($this->methodCode);
    }
	
	public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
		$html = '<td colspan="5"><h2><b>'.$element->getLabel().'</b></h2><span class="value">' . $element->getComment() .'</span></td>';		
        
        return $this->decorateRowHtml($element, $html);
		
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @param string $html
     * @return string
     */
    private function decorateRowHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element, $html)
    {
        return sprintf(
            '<tr id="row_%s">%s</tr>',
            $element->getHtmlId(),
            $html
        );
    }	
	
	
    
}
