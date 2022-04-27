<?php
namespace Straal\Payment\Model\Config;


class Paymentview implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'embedded', 'label' => __('Embedded')], ['value' => 'redirect', 'label' => __('Redirect')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return ['embedded' => __('Embedded'), 'redirect' => __('Redirect')];
    }
}
