<?php

namespace Dinesh\OrderExport\Block\Adminhtml\Order;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class ExportButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('Export'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90
        ];
    }
}