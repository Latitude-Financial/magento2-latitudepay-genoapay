<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Latitude\Payment\Controller\Latitude\AbstractLatitude;

use Magento\Framework\Exception\LocalizedException;

/**
 * Class PlaceOrder
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Placeorder extends \Latitude\Payment\Controller\Latitude\AbstractLatitude
{

    /**
     * Submit the order
     * @return void|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $post = $this->getRequest()->getParams();

        if($post && $post["result"] != "COMPLETED"){
            $this->_redirect('*/*/cancel',['_query' => $post]);
            return;
        }

        try {
            $this->_initCheckout();
            $tokenId = $post["token"];
            /**  Populate quote  with information about billing and shipping addresses*/

            $this->checkout->returnFromLatitude($tokenId);

            // Log payload callback
            $this->logCallback($post);

            $this->checkout->place($post);


            // prepare session to success or cancellation page
            $this->_getCheckoutSession()->clearHelperData();

            // "last successful quote"
            $quoteId = $this->_getQuote()->getId();
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_getCheckoutSession()->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            $order = $this->checkout->getOrder();
            if ($order) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getCheckoutSession()->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
            }

            $this->_eventManager->dispatch(
                'latitudepay_place_order_success',
                [
                    'order' => $order,
                    'quote' => $this->_getQuote()
                ]
            );

            // redirect to latitudepay
            $url = $this->checkout->getRedirectUrl();
            if ($url) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->getResponse()->setRedirect($url);
                return;
            }
            $this->_initToken(false); // no need in token anymore
            $this->_redirect('checkout/onepage/success');
            return;
        }  catch (LocalizedException $e) {
            $this->messageManager->addError($e->getRawMessage());
            $this->processException($e, $e->getRawMessage());
        } catch (\Exception $e) {
            $this->processException($e, 'We can\'t place the order.');
        }
    }

    /**
     * Process exception.
     *
     * @param \Exception $exception
     * @param string $message
     *
     * @return void
     */
    private function processException(\Exception $exception, $message)
    {
        $this->messageManager->addExceptionMessage($exception, __($message));

        $this->_redirect('checkout?cancel', ['_fragment' => 'payment']);
    }
}