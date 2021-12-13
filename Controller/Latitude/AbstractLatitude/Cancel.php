<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Latitude\Payment\Controller\Latitude\AbstractLatitude;

use Magento\Framework\Controller\ResultFactory;

class Cancel extends \Latitude\Payment\Controller\Latitude\AbstractLatitude
{
    /**
     * Cancel Latitudepay Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $this->_initToken(false);
            // Log payload callback
            $post = $this->getRequest()->getParams();
            if(!$this->_getApi()->validateSignature($post)){
                $this->_redirect('checkout');
                return;
            }
            $this->logCallback($post);
            // if there is an order - cancel it
            /** @noinspection PhpUndefinedMethodInspection */
            $orderId = $this->_getCheckoutSession()->getLastOrderId();
            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->orderFactory->create()->load($orderId) : false;
            if ($order && $order->getId() && $order->getQuoteId() == $this->_getCheckoutSession()->getQuoteId()) {
                $order->cancel()->save();
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getCheckoutSession()
                    ->unsLastQuoteId()
                    ->unsLastSuccessQuoteId()
                    ->unsLastOrderId()
                    ->unsLastRealOrderId();
                $this->logger->error('There was an error with your payment, please try again or select other payment method');

            } else {
                $this->logger->error('There was an error with your payment, please try again or select other payment method');
            }

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array("errors" => __('Unable to cancel Checkout')));
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('checkout?cancel', ['_fragment' => 'payment']);
    }
}
