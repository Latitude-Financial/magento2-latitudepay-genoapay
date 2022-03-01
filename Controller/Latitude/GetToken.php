<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Latitude\Payment\Controller\Latitude;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception;

/**
 * Class GetToken
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetToken extends AbstractLatitude
{
    /**
     * Config mode type
     *
     * @var string
     */
    protected $configType = \Latitude\Payment\Model\Config::class;

    /**
     * Config method type
     *
     * @var string
     */
    protected $configMethod = \Latitude\Payment\Model\Config::METHOD_LATITUDEPAY;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected $checkoutType = \Latitude\Payment\Model\Latitude\Checkout::class;

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $controllerResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $token = $this->getToken();

            if ($token === null) {
                $token = false;
            }
            $this->_initToken($token);
            $url = $this->checkout->getRedirectUrl();

            /** @noinspection PhpUndefinedMethodInspection */
            $controllerResult->setData(['url' => $url]);
        } catch (LocalizedException $exception) {
            $this->logger->critical($exception);
            /** @noinspection PhpUndefinedMethodInspection */
            $controllerResult->setData([
                'message' => [
                    'text' => $exception->getMessage(),
                    'type' => 'error'
                ]
            ]);
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                __('There was an error with your payment, please try again or select other payment method.')
            );

            return $this->getErrorResponse($controllerResult);
        }

        return $controllerResult;
    }

    /**
     * @return array|string|null
     * @throws LocalizedException
     */
    protected function getToken()
    {
        $this->_initCheckout();
        $quote = $this->_getQuote();

        $quoteCheckoutMethod = $quote->getCheckoutMethod();
        $customerData = $this->customerSession->getCustomerDataObject();

        if ($quote->getIsMultiShipping()) {
            $quote->setIsMultiShipping(false);
            $quote->removeAllAddresses();
        }

        if ($customerData->getId()) {
            $this->checkout->setCustomerWithAddressChange(
                $customerData,
                $quote->getBillingAddress(),
                $quote->getShippingAddress()
            );
        } elseif ((!$quoteCheckoutMethod || $quoteCheckoutMethod !== Onepage::METHOD_REGISTER)
            && !$this->checkoutHelper->isAllowedGuestCheckout($quote, $quote->getStoreId())
        ) {

            $this->messageManager->addNoticeMessage(
                __('To check out, please sign in with your email address.')
            );


            $this->customerSession->setBeforeAuthUrl(
                $this->_url->getUrl('*/*/*', ['_current' => true])
            );

            return null;
        }

        $successUrl  =($this->configHelper->getConfigData('success_url') ?$this->_url->getUrl($this->configHelper->getConfigData('success_url')) : $this->_url->getUrl('latitude/latitude/placeorder'));
        $cancelUrl   =($this->configHelper->getConfigData('fail_url') ?$this->_url->getUrl($this->configHelper->getConfigData('fail_url')) : $this->_url->getUrl('latitude/latitude/cancel'));

        $total = $this->checkoutSession->getQuote()->getGrandTotal();
        $total = round($total, 2);
        $totalAmount =  $total ? $this->_getApi()->formatPrice($total) :0.00;
        $currency = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();

        $requestHash = sha1(implode('||',[$totalAmount,$currency]));

        $callbackUrl =($this->configHelper->getConfigData('callback_url') ? $this->_url->getUrl($this->configHelper->getConfigData('callback_url'),['_query'=>['hash'=>$requestHash,'totalPaidAmount' => $totalAmount,'method' => ucfirst(strtolower($this->configHelper->getMethodCode()))]]) : $this->_url->getUrl('latitude/latitude/callback',['_query'=>['hash'=>$requestHash,'totalPaidAmount' => $totalAmount,'method' => ucfirst(strtolower($this->configHelper->getMethodCode()))]]));

        // Latitude urls
        $this->checkout->prepareLatitudeUrls(
            $successUrl,
            $cancelUrl,
            $callbackUrl
        );
        return $this->checkout->start(
            $successUrl,
            $cancelUrl,
            $callbackUrl
        );
    }

    /**
     * @param ResultInterface $controllerResult
     * @return ResultInterface
     */
    private function getErrorResponse(ResultInterface $controllerResult)
    {
        $controllerResult->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
        /** @noinspection PhpUndefinedMethodInspection */
        $controllerResult->setData(['message' => __('Sorry, but something went wrong')]);

        return $controllerResult;
    }

}