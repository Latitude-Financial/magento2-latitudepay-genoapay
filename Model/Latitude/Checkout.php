<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Latitude\Payment\Model\Latitude;

use Magento\Customer\Api\Data\CustomerInterface as CustomerDataObject;
use Latitude\Payment\Model\Cart as LatitudeCart;
use Latitude\Payment\Model\Config as LatitudeConfig;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

/**
 * Wrapper that performs Latitudepay and Checkout communication
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @noinspection PhpUndefinedMethodInspection
 */
class Checkout
{
    /**
     * Keys for passthrough variables in sales/quote_payment and sales/order_payment
     */
    const PAYMENT_PURCHASE_TOKEN    = 'latitude_purchase_token';

    /**
     * Flag which says that was used Latitude Checkout button for checkout
     * Uses additional_information as storage
     * @var string
     */
    const PAYMENT_INFO_BUTTON = 'button';

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    /**
     * Config instance
     *
     * @var LatitudeConfig
     */
    protected $config;

    /**
     * API instance
     *
     * @var \Latitude\Payment\Model\Api\Lpay
     */
    protected $api;

    /**
     * Api Model Type
     *
     * @var string
     */
    protected $apiType = \Latitude\Payment\Model\Api\Lpay::class;

    /**
     * State helper variable
     *
     * @var string
     */
    protected $redirectUrl = '';

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Redirect urls supposed to be set to support latitudepay
     *
     * @var array
     */
    protected $latitudepayUrls = [];
    /**
     * Customer ID
     *
     * @var int
     */
    protected $customerId;
    /**
     * Order
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * Checkout data
     *
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutData;
    /**
     * Tax data
     *
     * @var \Magento\Tax\Helper\Data
     */
    protected $taxData;
    /**
     * @var \Latitude\Payment\Logger\Logger
     */
    protected $logger;
    /**
     * @var \Latitude\Payment\Model\Info
     */
    protected $latitudeInfo;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $coreUrl;
    /**
     * @var \Latitude\Payment\Model\CartFactory
     */
    protected $cartFactory;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var \Latitude\Payment\Model\Api\Type\Factory
     */
    protected $apiTypeFactory;

    /**
     * @var \Magento\Quote\Model\Quote\TotalsCollector
     */
    protected $totalsCollector;
    /**
     * @var \Magento\Framework\Session\Generic
     */
    protected $latitudeSession;
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;
    /**
     * @var \Latitude\Payment\Helper\Curl
     */
    protected $curlHelper;
    /**
     * @var \Latitude\Payment\Helper\Config
     */
    protected $configHelper;

    /**
     * @param \Latitude\Payment\Logger\Logger $logger
     * @param \Magento\Tax\Helper\Data $taxData
     * @param \Magento\Checkout\Helper\Data $checkoutData
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Latitude\Payment\Model\Info $latitudeInfo
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $coreUrl
     * @param \Latitude\Payment\Model\CartFactory $cartFactory
     * @param \Magento\Quote\Api\CartManagementInterface $quoteManagement
     * @param \Latitude\Payment\Model\Api\Type\Factory $apiTypeFactory,
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param OrderSender $orderSender
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector
     * @param \Latitude\Payment\Model\Config $Config
     * @param \Magento\Framework\Session\Generic $latitudeSession
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Latitude\Payment\Helper\Curl $curlHelper
     * @param \Latitude\Payment\Helper\Config $configHelper
     * @throws \Exception
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Latitude\Payment\Logger\Logger $logger,
        \Magento\Tax\Helper\Data $taxData,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Customer\Model\Session $customerSession,
        \Latitude\Payment\Model\Info $latitudeInfo,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $coreUrl,
        \Latitude\Payment\Model\CartFactory $cartFactory,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Latitude\Payment\Model\Api\Type\Factory $apiTypeFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        OrderSender $orderSender,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Latitude\Payment\Model\Config $Config,
        \Magento\Framework\Session\Generic $latitudeSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Latitude\Payment\Helper\Curl $curlHelper,
        \Latitude\Payment\Helper\Config $configHelper
    ) {
        $this->logger = $logger;
        $this->taxData = $taxData;
        $this->checkoutData = $checkoutData;
        $this->customerSession = $customerSession;
        $this->latitudeInfo = $latitudeInfo;
        $this->storeManager = $storeManager;
        $this->coreUrl = $coreUrl;
        $this->cartFactory = $cartFactory;
        $this->quoteManagement = $quoteManagement;
        $this->apiTypeFactory = $apiTypeFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->orderSender = $orderSender;
        $this->quoteRepository = $quoteRepository;
        $this->totalsCollector = $totalsCollector;
        $this->config = $Config;
        $this->quote = $checkoutSession->getQuote();
        $this->latitudeSession = $latitudeSession;
        $this->messageManager = $messageManager;
        $this->curlHelper      = $curlHelper;
        $this->configHelper      = $configHelper;

    }

    /**
     * Setter that enables Latitudepay redirects flow
     *
     * @param string $successUrl - payment success result
     * @param string $cancelUrl  - payment cancellation result
     * @param string $pendingUrl - pending payment result
     * @return $this
     */
    public function prepareLatitudeUrls($successUrl, $cancelUrl, $pendingUrl)
    {
        $this->latitudepayUrls = [$successUrl, $cancelUrl, $pendingUrl];
        return $this;
    }

    /**
     * Setter for customer
     *
     * @param CustomerDataObject $customerData
     * @return $this
     */
    public function setCustomerData(CustomerDataObject $customerData)
    {
        $this->quote->assignCustomer($customerData);
        $this->customerId = $customerData->getId();
        return $this;
    }

    /**
     * Setter for customer with billing and shipping address changing ability
     *
     * @param CustomerDataObject $customerData
     * @param Address|null $billingAddress
     * @param Address|null $shippingAddress
     * @return $this
     */
    public function setCustomerWithAddressChange(
        CustomerDataObject $customerData,
        $billingAddress = null,
        $shippingAddress = null
    ) {
        $this->quote->assignCustomerWithAddressChange($customerData, $billingAddress, $shippingAddress);
        $this->customerId = $customerData->getId();
        return $this;
    }

    /**
     * Reserve order ID for specified quote and start checkout on Latitude
     *
     * @param string $returnUrl
     * @param string $cancelUrl
     * @param bool|null $button
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @noinspection PhpUndefinedMethodInspection
     */

    public function start($returnUrl, $cancelUrl, $button = null)
    {
        $this->quote->collectTotals();

        if (!$this->quote->getGrandTotal()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Latitude can\'t process orders with a zero balance due. '
                    . 'To finish your purchase, please go through the standard checkout process.'
                )
            );
        }

        $this->quote->reserveOrderId();
        $this->quoteRepository->save($this->quote);
        // prepare API

        $totalAmount = round($this->quote->getBaseGrandTotal(), 2);


        /** @noinspection PhpUndefinedMethodInspection */

        $this->_getApi()->setAmount($totalAmount)
            ->setCurrencyCode($this->quote->getBaseCurrencyCode())
            ->setInvNum($this->quote->getReservedOrderId())
            ->setReturnUrl($returnUrl)
            ->setCancelUrl($cancelUrl)
            ->setPaymentAction($this->config->getValue('paymentAction'));


        if ($this->latitudepayUrls) {
            list($successUrl, $cancelUrl, $pendingUrl) = $this->latitudepayUrls;

            $this->_getApi()->addData(
                [

                    'success_url' =>$successUrl,
                    'fail_url' =>  $cancelUrl,
                    'callback_url' => $pendingUrl,
                ]
            );
        }

        if ($this->config->getValue('requireBillingAddress') == LatitudeConfig::REQUIRE_BILLING_ADDRESS_ALL) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_getApi()->setRequireBillingAddress(1);
        }

        // suppress or export shipping address
        $address = null;
        if ($this->quote->getIsVirtual()) {
            if ($this->config->getValue('requireBillingAddress')
                == LatitudeConfig::REQUIRE_BILLING_ADDRESS_VIRTUAL
            ) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getApi()->setRequireBillingAddress(1);
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_getApi()->setSuppressShipping(true);
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_getApi()->setBillingAddress($this->quote->getBillingAddress());

            $address = $this->quote->getShippingAddress();

            if (true === $address->validate()) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->_getApi()->setAddress($address);
            }
            $this->quote->getPayment()->save();
        }

        /** @var $cart \Latitude\Payment\Model\Cart */
        /** @noinspection PhpUndefinedMethodInspection */
        $cart = $this->cartFactory->create(['salesModel' => $this->quote]);

        $this->_getApi()->setLatitudeCart($cart);

        if (!$this->taxData->getConfig()->priceIncludesTax()) {
            $this->setShippingOptions($cart, $address);
        }
        /** @noinspection PhpUndefinedMethodInspection */
        $this->_getApi()->setQuote($this->quote);

        $token = $this->_getApi()->getToken();

        $this->_getApi()->callLatitudePayCheckout($token);

        $response = $this->_getApi()->getRedirectUrl();

        $this->_setRedirectUrl($response);

        $payment = $this->quote->getPayment();

        if (!empty($button)) {
            $payment->setAdditionalInformation(self::PAYMENT_INFO_BUTTON, 1);
        } elseif ($payment->hasAdditionalInformation(self::PAYMENT_INFO_BUTTON)) {
            $payment->unsAdditionalInformation(self::PAYMENT_INFO_BUTTON);
        }
        $payment->save();

        return $token;
    }

    /**
     * Update quote when returned from Latiude
     *
     * Rewrite billing address by Latiude, save old billing address for new customer, and
     * export shipping address in case address absence
     *
     * @param string $token
     * @return void
     * @noinspection PhpUndefinedMethodInspection
     */

    public function returnFromLatitude($token)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->_getApi()
            ->setToken($token);
        $quote = $this->quote;
        $method = $quote->getPayment()->getMethod();
        $this->ignoreAddressValidation();
        // check if we came from the Express Checkout button
        $isButton = (bool)$quote->getPayment()->getAdditionalInformation(self::PAYMENT_INFO_BUTTON);

        // import shipping address
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$quote->getIsVirtual()) {
                $shippingAddress = $quote->getShippingAddress();
                // import payment info
                $payment = $quote->getPayment();
                $payment->setMethod($method);
                $payment->setTransactionId($token);
                $payment->setParentTransactionId($payment->getTransactionId());
                $payment->setAdditionalInformation(self::PAYMENT_PURCHASE_TOKEN, $token);

        }

        // import billing address
        $requireBillingAddress = (int)$this->config->getValue(
                'requireBillingAddress'
            ) === \Latitude\Payment\Model\Config::REQUIRE_BILLING_ADDRESS_ALL;

        if ($isButton && !$requireBillingAddress && !$quote->isVirtual()) {
            $billingAddress = clone $shippingAddress;
            $billingAddress->unsAddressId()->unsAddressType()->setCustomerAddressId(null);
            $data = $billingAddress->getData();
            $data['save_in_address_book'] = 0;
            $quote->getShippingAddress()->setSameAsBilling(1);
        }

        $quote->setCheckoutMethod($this->getCheckoutMethod());
        // import payment info
        $payment = $quote->getPayment();

        $payment->setMethod($method);

        $this->latitudeInfo->importToPayment($this->_getApi(), $payment);

        $quote->collectTotals();
        $this->quoteRepository->save($quote);
    }

    /**
     * Place the order when customer returned from Latitude until this moment all quote data must be valid.
     *
     * @param string $token
     * @return void
     */
    public function place($payload)
    {
        $token = $payload['token'];
        $signature = $payload['signature'];
        $this->validateSignature($payload);

        if ($this->getCheckoutMethod() == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote();
        }

        $this->ignoreAddressValidation();
        $this->quote->collectTotals();
        $order = $this->quoteManagement->submit($this->quote);

        if (!$order) {
            return;
        }
        
        if($this->_getApi()->validateTotalAmount($token,$signature)) {
            $payment = $order->getPayment();
            $payment->setTransactionId($token)
                ->setCurrencyCode($order->getBaseCurrencyCode())
                ->setParentTransactionId($payment->getTransactionId())
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionClosed(0)
                ->registerCaptureNotification($order->getBaseGrandTotal());    
        } else {
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, true);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->addStatusToHistory($order->getStatus(), '<strong style="color:red;">Amount paid and Total cart amount Mismatch. Please investigate before shipping.</strong>');
            $this->messageManager->addWarningMessage('Your cart was updated resulting in a price mismatch. We have marked your order as Pending for a review.');
        }
        
        $order->save();

        switch ($order->getState()) {
            // even after placement Latiude can disallow to authorize/capture, but will wait until bank transfers money
            case \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT:
                break;
            // regular placement, when everything is ok
            case \Magento\Sales\Model\Order::STATE_PROCESSING:
            case \Magento\Sales\Model\Order::STATE_COMPLETE:
            case \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW:
                try {
                    if (!$order->getEmailSent()) {
                        $this->orderSender->send($order);
                    }
                } catch (\Exception $e) {
                    $this->logger->critical($e);
                }
                $this->checkoutSession->start();
                break;
            default:
                break;
        }
        $this->order = $order;
    }

    /**
     * Make sure addresses will be saved without validation errors
     *
     * @return void
     */
    private function ignoreAddressValidation()
    {
        $this->quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$this->quote->getIsVirtual()) {
            $this->quote->getShippingAddress()->setShouldIgnoreValidation(true);
            if (!$this->config->getValue('requireBillingAddress')
                && !$this->quote->getBillingAddress()->getEmail()
            ) {
                $this->quote->getBillingAddress()->setSameAsBilling(1);
            }
        }
    }

    /**
     * Determine whether redirect somewhere specifically is required
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }


    /**
     * Return order
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Get checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn()) {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER;
        }

        if (!$this->quote->getCheckoutMethod()) {
            if ($this->checkoutData->isAllowedGuestCheckout($this->quote)) {
                $this->quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
            } else {
                $this->quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_REGISTER);
            }
        }

        return $this->quote->getCheckoutMethod();
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
     * Attempt to collect address shipping rates and return them for further usage in instant update API
     *
     * Returns empty array if it was impossible to obtain any shipping rate and
     * if there are shipping rates obtained, the method must return one of them as default.
     *
     * @param Address $address
     * @param bool $mayReturnEmpty
     * @param bool $calculateTax
     * @return array|false
     */
    protected function _prepareShippingOptions(Address $address, $mayReturnEmpty = false, $calculateTax = false)
    {
        $options = [];
        $i = 0;
        $iMin = false;
        $min = false;
        $userSelectedOption = null;

        foreach ($address->getGroupedAllShippingRates() as $group) {
            foreach ($group as $rate) {
                $amount = (double)$rate->getPrice();
                if ($rate->getErrorMessage()) {
                    continue;
                }
                $isDefault = $address->getShippingMethod() === $rate->getCode();
                $amountExclTax = $this->taxData->getShippingPrice($amount, false, $address);
                $amountInclTax = $this->taxData->getShippingPrice($amount, true, $address);

                $options[$i] = new \Magento\Framework\DataObject(
                    [
                        'is_default' => $isDefault,
                        'name' => trim("{$rate->getCarrierTitle()} - {$rate->getMethodTitle()}", ' -'),
                        'code' => $rate->getCode(),
                        'amount' => $amountExclTax,
                    ]
                );
                if ($calculateTax) {
                    $options[$i]->setTaxAmount(
                        $amountInclTax - $amountExclTax + $address->getTaxAmount() - $address->getShippingTaxAmount()
                    );
                }
                if ($isDefault) {
                    $userSelectedOption = $options[$i];
                }
                if (false === $min || $amountInclTax < $min) {
                    $min = $amountInclTax;
                    $iMin = $i;
                }
                $i++;
            }
        }

        if ($mayReturnEmpty && $userSelectedOption === null) {
            $options[] = new \Magento\Framework\DataObject(
                [
                    'is_default' => true,
                    'name'       => __('N/A'),
                    'code'       => 'no_rate',
                    'amount'     => 0.00,
                ]
            );
            if ($calculateTax) {
                $options[$i]->setTaxAmount($address->getTaxAmount());
            }
        } elseif ($userSelectedOption === null && isset($options[$iMin])) {
            $options[$iMin]->setIsDefault(true);
        }



        return $options;
    }

    /**
     * Create payment redirect url
     * @param array $response
     * @return void
     */
    protected function _setRedirectUrl($response)
    {

        if (isset($response['paymentUrl']))
        {
            $this->redirectUrl = $response['paymentUrl'];
        }
    }

    /**
     * Get customer session object
     *
     * @return \Magento\Customer\Model\Session
     */
    public function getCustomerSession()
    {
        return $this->customerSession;
    }

    /**
     * Set shipping options to api
     *
     * @param \Latitude\Payment\Model\Cart $cart
     * @param \Magento\Quote\Model\Quote\Address|null $address
     * @return void
     */
    private function setShippingOptions(LatitudeCart $cart, Address $address = null)
    {

        $this->_getApi()->setIsLineItemsEnabled(1);

        // add shipping options if needed and line items are available
        $cartItems = $cart->getAllItems();
        if ($this->config->getValue(LatitudeConfig::TRANSFER_CART_LINE_ITEMS)
            && $this->config->getValue(LatitudeConfig::TRANSFER_SHIPPING_OPTIONS)
            && !empty($cartItems)
        ) {
            if (!$this->quote->getIsVirtual()) {
                $options = $this->_prepareShippingOptions($address, true);
                if ($options) {
                    $this->_getApi()->setShippingOptionsCallbackUrl(
                        $this->coreUrl->getUrl(
                            '*/*/shippingOptionsCallback',
                            ['quote_id' => $this->quote->getId()]
                        )
                    )->setShippingOptions($options);
                }
            }
        }
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function prepareGuestQuote()
    {
        $quote = $this->quote;

        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Validate Signature 
     *
     * @return boolean
     */
    public function validateSignature($payload)
    {
        $payloadValidate = $payload;
        unset($payloadValidate['signature']);
        $salesStringStripped              = $this->curlHelper->stripJsonFromSalesString(json_encode($payloadValidate, JSON_UNESCAPED_SLASHES));
        $salesStringStrippedBase64encoded = $this->curlHelper->base64EncodeSalesString(trim($salesStringStripped));
        $signatureHash                    = $this->curlHelper->getSignatureHash(trim($salesStringStrippedBase64encoded));
        if($payload['signature'] !== $signatureHash) {
            throw new  \Magento\Framework\Exception\LocalizedException(__('Invalid Signature'));
        }
        return true;
    }
}
