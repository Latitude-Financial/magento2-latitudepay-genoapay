<?php
namespace Latitude\Payment\Model\Payment;
use \Latitude\Payment\Helper\Config;

/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
class Latitude extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'latitudepay';

    protected $_supportedCurrencyCodes = array('AUD');
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;
    /**
     * @var \Latitude\Payment\Helper\Curl
     */
    protected $curlHelper;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
    * @var \Magento\Directory\Model\CurrencyFactory
    */
    public $currencyFactory;


    protected $_canRefund = true;

    protected $_canRefundInvoicePartial = true;

    const PAYMENT_AUTHRIZATION_TOKEN    = 'latitude_atuthorization_token';

    const PAYMENT_PURCHASE_TOKEN        = 'latitude_purchase_token';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Latitude\Payment\Helper\Curl $curlHelper
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Latitude\Payment\Helper\Curl $curlHelper,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->curlHelper = $curlHelper;
        $this->storeManager= $storeManager;
        $this->currencyFactory = $currencyFactory;
    }


    /**
     * @inheritdoc
     */
    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canUseForCurrency($currencyCode)
    {
        $paymentCurrency =$this->getConfigData('currency');
        $currency = (isset($paymentCurrency) ? $paymentCurrency : $currencyCode);
        /** @noinspection PhpUndefinedMethodInspection */
        $currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        if ($currentCurrencyCode == $currency) {
            return true;
        }
        return false;
    }

    /**
     * Checkout redirect URL getter for onepage checkout
     *
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->urlBuilder->getUrl('latitude/latitude/start');
    }

    /**
     * Refund a capture transaction
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     * @noinspection PhpUndefinedMethodInspection
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $purchaseToken = $payment->getParentTransactionId();
        if ($purchaseToken) {
            /** @noinspection PhpUndefinedMethodInspection */
            $order = $payment->getOrder();
            $requestbody = [];
            $requestbody['amount']    = $this->getRefundDetails($amount,$order);
            $requestbody['reason']    = 'size not correct';
            /** @noinspection PhpUndefinedMethodInspection */
            $requestbody['reference'] = $order->getIncrementId();
            /** @noinspection PhpUndefinedMethodInspection */
            $storeId                  = $order->getStore()->getId();
            /** @noinspection PhpUndefinedMethodInspection */
            $methodCode               = $order->getPayment()->getMethod();
            $this->curlHelper->createRefund($requestbody, $purchaseToken,$storeId,$methodCode);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We can\'t issue a refund transaction because there is no capture transaction.')
            );
        }
        return $this;
    }

    /**
     * Refund a capture transaction
     *
     * @param float $amount
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function getRefundDetails($amount,$order)
    {
        if($order->getBaseGrandTotal() != $order->getGrandTotal() && $order->getBaseCurrencyCode() != $order->getOrderCurrencyCode()){
            $amount = $this->convertPrice($amount,$order->getBaseCurrencyCode(),$order->getOrderCurrencyCode());
        }
        $refundDetails= array(
            "amount" => $amount,
            "currency" => $order->getOrderCurrencyCode()
        );

        return $refundDetails;
    }

    /**
     * Is active
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)(int)$this->_scopeConfig->getValue(
                Config::LATITUDE_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            ) && (bool)(int)$this->getConfigData('active', $storeId);
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        //@TODO:
        //1. check if API crediential coorect.
        //2. check currency is correct again
        //3. check quote amount is correct.
        return $this->isActive() && $quote && ($quote->getGrandTotal() >= 20);
    }

    /**
     * To check if the payment method is available for the default country
     *
     * @param string $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        return 'AU' === $country ? true : false;
    }

    /**
     * Convert price from one currency code to other currency code
     *
     * @param int|float $price
     * @param string $currencyCodeFrom
     * @param string $currencyCodeTo
     * @return int|float
     */
    protected function convertPrice($price, $currencyCodeFrom, $currencyCodeTo)
    {
        $rate = $this->currencyFactory->create()
                        ->load($currencyCodeFrom)
                        ->getAnyRate($currencyCodeTo);
        $convertedPrice = $price * $rate;

        return $convertedPrice;
    }
}


