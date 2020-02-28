<?php

namespace Humm\HummPaymentGateway\Cron;

use Humm\HummPaymentGateway\Helper\HummLogger;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Humm\HummPaymentGateway\Gateway\Config;
use Magento\Sales\Model\Order;

/**
 * Class UpdateHummOrder
 * @package Humm\HummPaymentGateway\Cron
 * @author Roger.bi@flexigroup.com.au
 */
class UpdateHummOrder
{
    /**
     * @var HummLogger
     */
    protected $_hummlogger;

    /**
     * @var CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var
     */
    protected $_orderManager;
    /**
     * @var
     */
    protected $_collection;
    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_timeZone;
    /**
     * @var Config\Config
     */
    protected $_hummConfig;

    const paymentMethod = 'humm';
    const statuses = ['pending'];


    /**
     * UpdateHummOrder constructor.
     * @param HummLogger $hummLogger
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param CollectionFactory $orderCollectionFactory
     * @param Config\Config $config
     */
    public function __construct(
        \Humm\HummPaymentGateway\Helper\HummLogger $hummLogger,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Humm\HummPaymentGateway\Gateway\Config\Config $config

    )
    {
        $this->_hummlogger = $hummLogger;
        $this->_timeZone = $timezone;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_hummConfig = $config;

    }

    /**
     * @return $this
     * @throws \Exception
     */

    public function execute()
    {
        $yesNo= $this->_hummConfig->getConfigdata('humm_conf/pending_order');
        if(!intval($yesNo))
        {
            $this->_hummlogger->log("Clean Pend Order in Crontab Disable");
            return $this;
        }
        $daysSkip = intval($this->_hummConfig->getConfigdata('humm_conf/pending_orders_timeout'));
        $time = $this->_timeZone->scopeTimeStamp();
        $dateNow = (new \DateTime())->setTimestamp($time);
        $to = $dateNow->format('Y-m-d H:i:s');
        $from = $dateNow->sub(new \DateInterval('P'.$daysSkip.'D'))->format('Y-m-d H:i:s');
        $this->_hummlogger->log(sprintf("Start Crontab..time now%s OpenFlag[%s..]" ,$to,$yesNo));
        $this->_hummlogger->log(sprintf("from %s to %s", $from, $to));
        $_collection = $this->getOrderCollectionPaymentMethod(self::paymentMethod, $from, $to);
        $this->processCollection($_collection);
        return $this;
    }

    /**
     * @param null $paymentMethod
     * @param $from
     * @param $to
     * @return $this
     */
    public function getOrderCollectionPaymentMethod($paymentMethod = null, $from, $to)
    {
        $collection = $this->_orderCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('created_at',
                ['gteq' => $from]
            )
            ->addFieldToFilter('created_at',
                ['lteq' => $to]
            )
            ->addFieldToFilter('status', ['in' => self::statuses]
            );

        $collection->getSelect()
            ->join(
                ["sop" => "sales_order_payment"],
                'main_table.entity_id = sop.parent_id',
                array('method', 'amount_paid', 'amount_ordered')
            )
            ->where('sop.method like "%humm%" and sop.amount_paid is NULL');

        $collection->setOrder(
            'created_at',
            'desc'
        );

        return $collection;

    }

    /**
     * @param $collection
     */

    public function processCollection($collection)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        foreach ($collection as $key => $item) {
            $this->_hummlogger->log($item->getData('increment_id') . $item->getData('state') . $item->getData('status'), true);
            $hummOrderId = $item->getData('increment_id');
            $this->processHummOrder($hummOrderId, $objectManager);
        }

    }

    /**
     * @param $hummOrderId
     * @param $objectManager
     */

    public function processHummOrder($hummOrderId, $objectManager)
    {

        $hummOrder = $objectManager->create('\Magento\Sales\Model\Order')->load($hummOrderId);

        if ($hummOrder->getId() && $hummOrder->getState() != Order::STATE_CANCELED) {
            $this->_hummlogger->log(sprintf("Order ID %s, Order State %s", $hummOrderId,$hummOrder->getState()));
            $hummPayment = $hummOrder->getPayment()->setAdditionalInformation(array(sprintf("Update Humm Pending OrderId %s to Cancelled",$hummOrderId) => "Cancelled"));;
            $this->_hummlogger->log(sprintf("Payment:%s",json_encode($hummPayment)));
            $hummOrder->registerCancellation('cancelled by customer Cron Humm Payment ')->save();
        }
    }

    /**
     * @param array $statuses
     * @return mixed
     */

    public function getOrderCollectionByStatus($statuses = [])
    {
        $collection = $this->_orderCollectionFactory()->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('status',
                ['in' => $statuses]
            );

        return $collection;

    }
}

