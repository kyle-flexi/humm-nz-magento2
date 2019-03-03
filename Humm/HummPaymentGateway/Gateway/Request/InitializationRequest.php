<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Humm\HummPaymentGateway\Gateway\Request;

use Humm\HummPaymentGateway\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class InitializationRequest implements BuilderInterface {
    private $_logger;
    private $_session;
    private $_gatewayConfig;

    /**
     * @param Config $gatewayConfig
     * @param LoggerInterface $logger
     * @param Session $session
     */
    public function __construct(
        Config $gatewayConfig,
        LoggerInterface $logger,
        Session $session
    ) {
        $this->_gatewayConfig = $gatewayConfig;
        $this->_logger        = $logger;
        $this->_session       = $session;
    }

    /**
     * Checks the quote for validity
     *
     * @param OrderAdapter $order
     *
     * @return bool;
     */
    private function validateQuote( OrderAdapter $order ) {

        $this->_logger->debug( '[InitializationRequest][validateQuote]$this->_gatewayConfig->getSpecificCountry():' . ( $this->_gatewayConfig->getSpecificCountry() ) );
        $allowedCountriesArray = explode( ',', $this->_gatewayConfig->getSpecificCountry() );

        $this->_logger->debug( '[InitializationRequest][validateQuote]$order->getBillingAddress()->getCountryId():' . ( $order->getBillingAddress()->getCountryId() ) );
        if ( ! in_array( $order->getBillingAddress()->getCountryId(), $allowedCountriesArray ) ) {
            $this->_logger->debug( '[InitializationRequest][validateQuote]Country is not in array' );
            $this->_session->setHummErrorMessage( __( 'Orders from this country are not supported by Humm. Please select a different payment option.' ) );

            return false;
        }

        $this->_logger->debug( '[InitializationRequest][validateQuote]$order->getShippingAddress()->getCountryId():' . ( $order->getShippingAddress()->getCountryId() ) );
        if ( ! in_array( $order->getShippingAddress()->getCountryId(), $allowedCountriesArray ) ) {
            $this->_session->setHummErrorMessage( __( 'Orders shipped to this country are not supported by Humm. Please select a different payment option.' ) );

            return false;
        }

        return true;
    }

    /**
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build( array $buildSubject ) {

        $payment     = $buildSubject['payment'];
        $stateObject = $buildSubject['stateObject'];

        $order = $payment->getOrder();

        if ( $this->validateQuote( $order ) ) {
            $stateObject->setState( Order::STATE_PENDING_PAYMENT );
            $stateObject->setStatus( Order::STATE_PENDING_PAYMENT );
            $stateObject->setIsNotified( false );
        } else {
            $stateObject->setState( Order::STATE_CANCELED );
            $stateObject->setStatus( Order::STATE_CANCELED );
            $stateObject->setIsNotified( false );
        }

        return [ 'IGNORED' => [ 'IGNORED' ] ];
    }
}
