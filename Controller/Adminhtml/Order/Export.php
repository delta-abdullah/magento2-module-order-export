<?php

namespace Dinesh\OrderExport\Controller\Adminhtml\Order;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface as OrderCollectionFactoryInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\File\Csv;
use Magento\Framework\App\Filesystem\DirectoryList;

class Export extends Action {
    protected $_orderCollectionFactory;
    protected $_timeZone;
    protected $_fileFactory;
    protected $_csvProcessor;
    protected $_directoryList;

    public function __construct(
        Context $context,
        OrderCollectionFactoryInterface $orderCollectionFactory,
        TimezoneInterface $timezoneInterface,
        FileFactory $fileFactory,
        Csv $csvProcessor,
        DirectoryList $directoryList
    )
    {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_timeZone = $timezoneInterface;
        $this->_fileFactory = $fileFactory;
        $this->_csvProcessor = $csvProcessor;
        $this->_directoryList = $directoryList;
        parent::__construct($context);
    }

    /**
     * 
     * @return ResultInterface|ResponseInterface 
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            try {
                $dateFrom = $data['date_from'];
                $dateTo = $data['date_to'];
                $this->exportOrdersToCsv($dateFrom, $dateTo);
                $this->messageManager->addSuccessMessage(__('Order(s) have been exported to CSV.'));
                return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            }
            $this->messageManager->addErrorMessage(__('We can\'t find a orders to export.'));
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }
    }

    public function exportOrdersToCsv($dateFrom, $dateTo)
    {
        $ordersFromDate = $this->_timeZone->date($dateFrom)->setTime(0,0,0)->format('Y-m-d H:i:s');
        $orderToDate = $this->_timeZone->date($dateTo)->setTime(23,59,59)->format('Y-m-d H:i:s');

        $ordersData = $this->getOrderData($ordersFromDate, $orderToDate);

        if ($ordersData) {
            $fileName = 'orders_export_' . $this->_timeZone->date()->getTimestamp() . '.csv';
            $filePath = $this->_directoryList->getPath(DirectoryList::VAR_DIR) . '/' . $fileName;

            $this->_csvProcessor->setDelimiter(',')->setEnclosure('"')->appendData($filePath, $ordersData);

            // return $this->_fileFactory->create(
            //     $fileName,
            //     [
            //         'type'  =>  'filename',
            //         'value' =>  $fileName,
            //         'rm'    =>  true
            //     ],
            //     DirectoryList::VAR_DIR,
            //     'application/octet-stream'
            // );
            return true;
        }
        return false;
    }

    protected function getOrderData($ordersFromDate, $orderToDate)
    {
        $result = [];

        $collection = $this->_orderCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter(
                'created_at',
                [
                    'or' => [
                        0 => ['date' => true, 'to' => $orderToDate],
                        1 => ['is' => new \Zend_Db_Expr('null')]
                    ]
                ],
                'left'
            )->addFieldToFilter(
                'created_at',
                [
                    'or' => [
                        0 => ['date' => true, 'from' => $ordersFromDate],
                        1 => ['is' => new \Zend_Db_Expr('null')]
                    ]
                ],
                'left'
            )->setOrder('created_at', \Magento\Framework\Api\SortOrder::SORT_DESC);

        if ($collection->getSize()) {
            $result[] = [
                'order_id',
                'grand_total',
                'subtotal',
                'sku',
                'qty',
                'price',
                'first_name',
                'last_name',
                'street_line1',
                'street_line2',
                'city',
                'country',
                'postcode',
                'email',
                'phone'
            ];
            /** @var \Magento\Sales\Model\Order */
            foreach ($collection as $order) {
                $itemLine = 1;
                foreach ($order->getAllItems() as $item) {
                    if ($itemLine == 1) {
                        $result[] = [
                            $order->getIncrementId(),
                            $order->getGrandTotal(),
                            $order->getSubtotal(),
                            $item->getSku(),
                            $item->getQtyOrdered(),
                            $item->getPrice(),
                            $order->getShippingAddress()->getFirstname(),
                            $order->getShippingAddress()->getLastname(),
                            $order->getShippingAddress()->getStreetLine(1),
                            $order->getShippingAddress()->getStreetLine(2),
                            $order->getShippingAddress()->getCity(),
                            $order->getShippingAddress()->getCountryId(),
                            $order->getShippingAddress()->getPostcode(),
                            $order->getShippingAddress()->getEmail(),
                            $order->getShippingAddress()->getTelephone()
                        ];
                    } else {
                        $result[] = [
                            $order->getIncrementId(),
                            null,
                            null,
                            $item->getSku(),
                            $item->getQtyOrdered(),
                            $item->getPrice(),
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null
                        ];
                    }
                    $itemLine++;
                }
            }
        }
        return $result;
    }
}