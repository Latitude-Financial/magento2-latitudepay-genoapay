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
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\App\Emulation;

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
     * @SuppressWarnings(PHPMD.Superglobals)
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $this->logger->info('Callback received');
        $this->logger->info('Raw (RESPONSE): ', $_SERVER);
        $result = [];
        try {
            // Log payload callback
            $post = $this->getRequest()->getParams();
            $hash = $post['hash'];
            $totalPaidAmount = $post['totalPaidAmount'];
            $this->logger->info('Order Status (RESPONSE): ', $post);
            $this->_initToken(false);
            $incrementId = $post['reference'];
            // @codingStandardsIgnoreStart
            $orderFactory = ObjectManager::getInstance()->get(OrderFactory::class);
            $quoteFactory = ObjectManager::getInstance()->get(QuoteFactory::class);
            $quoteManagement = ObjectManager::getInstance()->get(QuoteManagement::class);
            $cartRepository = ObjectManager::getInstance()->get(\Magento\Quote\Api\CartRepositoryInterface::class);
            $eventManager = ObjectManager::getInstance()->get(\Magento\Framework\Event\ManagerInterface::class);
            // @codingStandardsIgnoreEnd
            $order = $orderFactory->create()->loadByIncrementId($incrementId);
            $quote = null;
            if($order->getId()){
                $quote = $quoteFactory->create()->load($order->getQuoteId());
                $this->_initCheckout($quote);
                $this->checkout->validatePayload($post);
                $result = ['success' => true];
                /** @var \Magento\Framework\Controller\Result\Json $result */
                $resultJson = $this->resultJsonFactory->create();
                $resultJson->setData($result);
                return $resultJson;
            } else {
                // Create Order From Quote
                $quote = $quoteFactory->create()->load($incrementId,'reserved_order_id');
                $this->_initCheckout($quote);
                $this->checkout->validatePayload($post);
                if($post['result'] !== 'COMPLETED') {
                    return;
                }
                if($quote->getId() && $quote->getIsActive() && !$quote->getOrigOrderId() && in_array($quote->getPayment()->getMethod(),['latitudepay','genoapay'])){
                    $quote = $cartRepository->get($quote->getId());
                    $currency = $quote->getCurrency();
                    $quote->setTotalsCollectedFlag(true);
                    $order = $quoteManagement->placeOrder($quote->getId());
                }
            }
            if(!is_null($quote)){
                if(!$this->checkout->update($post,$hash,$totalPaidAmount)) {
                    $result = ['error' => false];
                    /** @var \Magento\Framework\Controller\Result\Json $result */
                    $resultJson = $this->resultJsonFactory->create();
                    $resultJson->setData($result);
                    return $resultJson;
                }
                $result = ['success' => true];
            } else {
                throw new InvalidRequestException("Can't Find Quote. Invalid Order Id");
            }
        } catch (RemoteServiceUnavailableException $e) {
            $this->logger->error($e->getMessage(),["errors" => __('Unable to get callback message')]);
            $this->getResponse()->setStatusHeader(503, '1.1', 'Service Unavailable')->sendResponse();
            /** @todo eliminate usage of exit statement */
            // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
            exit;
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
