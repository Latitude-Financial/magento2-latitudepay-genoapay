<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Latitude\Payment\Controller\Latitude;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Abstract Express Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractLatitude extends AppAction  implements RedirectLoginInterface
{
    /**
     * API instance
     *
     * @var \Latitude\Payment\Model\Api\Lpay
     */
    protected $api;

    /**
     * @var \Latitude\Payment\Model\Api\Type\Factory
     */
    protected $apiTypeFactory;

    /**
     * @var \Latitude\Payment\Model\Latitude\Checkout
     */
    protected $checkout;

    /**
     * Api Model Type
     *
     * @var string
     */
    protected $apiType = \Latitude\Payment\Model\Api\Lpay::class;

    /**
     * Internal cache of checkout models
     *
     * @var array
     */
    protected $checkoutTypes = [];

    /**
     * @var \Latitude\Payment\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    /**
     * Config mode type
     *
     * @var string
     */
    protected $configType;

    /**
     * Config method type
     *
     * @var string
     */
    protected $configMethod;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected $checkoutType;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Latitude\Payment\Model\Latitude\Checkout\Factory
     */
    protected $checkoutFactory;

    /**
     * @var \Magento\Framework\Session\Generic
     */
    protected $latitudeSession;

    /**
     * @var \Magento\Framework\Url\Helper\Data
     */
    protected $urlHelper;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $customerUrl;
    /**
     * @var \Latitude\Payment\Logger\Logger
     */
    protected $logger;
    /**
     * @var \Latitude\Payment\Helper\Curl
     */
    protected $curllHelper;
    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;
    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    protected $checkoutMethod;

    /**
     * @var \Latitude\Payment\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Latitude\Payment\Model\Latitude\Checkout\Factory $checkoutFactory
     * @param \Magento\Framework\Session\Generic $latitudeSession
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param \Latitude\Payment\Logger\Logger  $logger
     * @param \Latitude\Payment\Helper\Curl $curllHelper
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Magento\Framework\Controller\ResultFactory $resultFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Latitude\Payment\Helper\Config $configHelper
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
     * @param \Latitude\Payment\Model\Api\Type\Factory $apiTypeFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Latitude\Payment\Model\Latitude\Checkout\Factory $checkoutFactory,
        \Magento\Framework\Session\Generic $latitudeSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \Latitude\Payment\Logger\Logger $logger,
        \Latitude\Payment\Helper\Curl $curllHelper,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Latitude\Payment\Helper\Config $configHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Latitude\Payment\Model\Api\Type\Factory $apiTypeFactory
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory    = $orderFactory;
        $this->checkoutFactory = $checkoutFactory;
        $this->latitudeSession = $latitudeSession;
        $this->urlHelper       = $urlHelper;
        $this->customerUrl     = $customerUrl;
        $this->logger          = $logger;
        $this->curllHelper     = $curllHelper;
        $this->checkoutHelper  = $checkoutHelper;
        $this->resultFactory   = $resultFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configHelper     = $configHelper;
        $this->apiTypeFactory = $apiTypeFactory;
        $this->remoteAddress = $remoteAddress;
        parent::__construct($context);
        $configMethod= $this->getRequest()->getParam('method');
        $parameters = ['params' => [$configMethod]];
        $this->config = $this->_objectManager->create($this->configType, $parameters);
        if($configMethod == 'Latitudepay'){
            $this->checkoutMethod    = 'Latitudepay';
        }elseif($configMethod == 'Genoapay'){
            $this->checkoutMethod   =  'Genoapay';
        }
    }

    /**
     * Instantiate quote and checkout
     *
     * @param CartInterface|null $quoteObject
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _initCheckout(CartInterface $quoteObject = null)
    {
        $quote = $quoteObject ? $quoteObject : $this->_getQuote();
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$quote->hasItems() || $quote->getHasError()) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new \Magento\Framework\Exception\LocalizedException(__('There was an error with your payment, please try again or select other payment method.'));
        }
        if (!(float)$quote->getGrandTotal()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Latitude can\'t process orders with a zero balance due. ' )
            );
        }
        if (!isset($this->checkoutTypes[$this->checkoutType])) {


            $parameters = [
                'params' => [
                    'quote' => $quote,
                    'config' => $this->config,
                    'method' => $this->checkoutMethod,
                ],
            ];

            $this->checkoutTypes[$this->checkoutType] = $this->checkoutFactory
                ->create($this->checkoutType, $parameters);

        }


        $this->checkout = $this->checkoutTypes[$this->checkoutType];

    }

    /**
     * Get Proper Checkout Token
     *
     * Search for proper checkout token in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @param string|null $setToken
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _initToken($setToken = null)
    {
        if (null !== $setToken) {
            if (false === $setToken) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getSession()->unsLatitudeCheckoutToken();
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getSession()->setLatitudeCheckoutToken($setToken->authToken);
            }
            return $this;
        }
        $setToken = $this->getRequest()->getParam('token');
        if ($setToken) {
            /** @noinspection PhpUndefinedMethodInspection */
            if ($setToken !== $this->_getSession()->getLatitudeCheckoutToken()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('A wrong Express Checkout Token is specified.')
                );
            }
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            $setToken = $this->_getSession()->getLatitudeCheckoutToken();
        }

        return $setToken;
    }

    /**
     * Latitude session instance getter
     *
     * @return \Magento\Framework\Session\Generic
     */
    protected function _getSession()
    {
        return $this->latitudeSession;
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * Return checkout quote object
     *
     * @return \Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function _getQuote()
    {
        if (!$this->quote) {
            $this->quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->quote;
    }

    /**
     * @inheritdoc
     */
    public function getCustomerBeforeAuthUrl()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getActionFlagList()
    {
        return [];
    }

    /**
     * Returns login url parameter for redirect
     *
     * @return string
     */
    public function getLoginUrl()
    {
        return $this->customerUrl->getLoginUrl();
    }

    /**
     * Returns action name which requires redirect
     *
     * @return string
     */
    public function getRedirectActionName()
    {
        return 'start';
    }

    /**
     * Log payload callback
     *
     * @param mixed $payload
     * @return void
     */
    public function logCallback($payload)
    {
        $quote = $this->_getQuote();
        if (
            $payload &&
            $quote &&
            $quote->getPayment()->getMethod() &&
            $this->configHelper->getConfigData('logging',$quote->getStoreId(),$quote->getPayment()->getMethod())
        ) {
            $remoteIp = $this->remoteAddress->getRemoteAddress();
            $this->logger->info('Get Callback From Remote IP: ', [$remoteIp]);
            $this->logger->info('Order Status (RESPONSE): ', $payload);
        }
    }

    /**
     * Get api
     *
     * @return \Latitude\Payment\Model\Api\Lpay
     */
    protected function _getApi()
    {
        if (null === $this->api) {
            $this->api = $this->apiTypeFactory->create($this->apiType)->setConfigObject($this->config);
        }
        return $this->api;
    }

    /**
     * @inheritdoc
     */
    abstract public function execute();
}
