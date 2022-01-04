<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Latitude\Payment\Controller\Latitude\AbstractLatitude;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\RemoteServiceUnavailableException;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Model\QuoteFactory;

class Callback extends \Latitude\Payment\Controller\Latitude\AbstractLatitude implements CsrfAwareActionInterface
{
    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Callback Latitudepay Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }
        $result = [];
        try {
            // Log payload callback
            $post = $this->getRequest()->getPostValue();
            if(empty($post)){
                return;
            }
            $this->logger->info('Callback received');
            $this->logCallback($post);
            $this->_initToken(false);
            $incrementId = $post['reference'];
            $orderFactory = ObjectManager::getInstance()->get(OrderFactory::class);
            $quoteFactory = ObjectManager::getInstance()->get(QuoteFactory::class);
            $order = $orderFactory->create()->loadByIncrementId($incrementId);
            $quote = $quoteFactory->create()->load($order->getQuoteId());
            $this->_initCheckout($quote);
            if(!$this->checkout->update($post)){
                $result = ['error' => false];
                /** @var \Magento\Framework\Controller\Result\Json $result */
                $resultJson = $this->resultJsonFactory->create();
                $resultJson->setData($result);
                return $resultJson;
            }
            $result = ['success' => true];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array("errors" => __('Unable to get callback message')));
            $result = ['error' => false,'message' => $e->getMessage()];
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData($result);
        return $resultJson;
    }
}
