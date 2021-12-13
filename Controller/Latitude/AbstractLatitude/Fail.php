<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Latitude\Payment\Controller\Latitude\AbstractLatitude;

use Latitude\Payment\Controller\Latitude\GetToken;
use \Magento\Framework\Controller\ResultFactory;

/**
 * Class Fail
 */
class Fail extends GetToken
{
    /**
     * Process Failed Latitudepay Checkout
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        try {
            $this->_initToken(false);
            // Log payload callback
            $post = $this->getRequest()->getParams();
            $this->logCallback($post);
            if(!$this->_getApi()->validateSignature($post)){
                $this->_redirect('checkout');
                return;
            }
            // if there is an order - cancel it
            /** @noinspection PhpUndefinedMethodInspection */
            $orderId = $this->_getCheckoutSession()->getLastOrderId();
            /** @var \Magento\Sales\Model\Order $order */
            /** @noinspection PhpUndefinedMethodInspection */
            $order = $orderId ? $this->orderFactory->create()->load($orderId) : false;
            if ($order && $order->getId() && $order->getQuoteId() == $this->_getCheckoutSession()->getQuoteId()) {
                /** @noinspection PhpDeprecationInspection */
                $order->cancel()->save();
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getCheckoutSession()
                    ->unsLastQuoteId()
                    ->unsLastSuccessQuoteId()
                    ->unsLastOrderId()
                    ->unsLastRealOrderId();
                $this->messageManager->addSuccessMessage(
                    __('Checkout and Order have been canceled.')
                );
            } 

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to cancel Checkout'));
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('checkout?cancel', ['_fragment' => 'payment']);
    }
}
