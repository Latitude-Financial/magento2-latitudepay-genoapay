<?php
namespace Latitude\Payment\Helper;

/**
 * Class Config
 * @package Latitude\Payment\Helper\Config
 */
class Config extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * General configurations
     */
    CONST SHOW_IN_MOBILE     = "show_in_mobile";
    CONST ENVIORNMENT           = "environment";
    CONST MERCHANTID         = "merchant_id";
    CONST CLIENTKEY          = "client_key";
    CONST CLIENTSECRET       = "client_secret";
    CONST SUCCESSURL         = "success_url";
    CONST FAILURL            = "fail_url";
    CONST CALLBACKURL        = "callback_url";
    CONST LOGGER             = "logger";
    CONST CURRENCY           = "currency";
    CONST ALLOWED_CURRENCIES = "allowed_currencies";
    CONST ALLOWED_SPECIFIC   = "allowspecific";
    CONST SPECIFIC_COUNTRY   = "specificcountry";
    CONST INSTALLMENT_NO     = "installment_no";
    CONST LATITUDE_ENABLED   = "payment/latitudepay/enabled";
    CONST GENOAPAY_ENABLED   = "payment/genoapay/enabled";
    CONST LATITUDE_CURRENCY  = "payment/latitudepay/currency";
    CONST GENOAPAY_CURRENCY  = "payment/genoapay/currency";
    CONST LATITUDE_PAYMENT_SERVICES  = "payment/latitudepay/payment_services";
    CONST LATITUDE_PAYMENT_TERMS  = "payment/latitudepay/payment_terms";
    CONST LATITUDE_IMAGE_API_URL = "payment/latitudepay/image_api_url";

    /**
     * Configuration value of whether to display billing address on payment method or payment page
     */
    CONST DISPLAY_BILLING_ADDRESS_ON_CONFIG_PATH = 'checkout/options/display_billing_address_on';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $code;
    /**
     * Configuration variables
     *
     * @var array
     */
    protected $configVars;

    protected $storeRepository;


    CONST AU_STORE_CODE = "au_store_view";
    CONST NZ_STORE_CODE = "newzland";

    protected $currentCurrencyCode;

    protected $supportedCurrencyCodes;

    protected $latitudepayCurrency;

    protected $genoapayCurrency ;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeRepository
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeRepository
    )
    {
        parent::__construct($context);

        $this->configVars = array(
            'show_in_mobile'     => self::SHOW_IN_MOBILE,
            'environment'        => self::ENVIORNMENT,
            'merchant_id'        => self::MERCHANTID,
            'client_key'         => self::CLIENTKEY,
            'client_secret'      => self::CLIENTSECRET,
            'success_url'        => self::SUCCESSURL,
            'fail_url'           => self::FAILURL,
            'callback_url'      => self::CALLBACKURL,
            'logger'             => self::LOGGER,
            'currency'           => self::CURRENCY,
            'allowed_currencies' => self::ALLOWED_CURRENCIES,
            'allowspecific'      => self::ALLOWED_SPECIFIC,
            'specificcountry'    => self::SPECIFIC_COUNTRY,
            'installment_no'     => self::INSTALLMENT_NO,
        );
        $this->storeRepository= $storeRepository;

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->currentCurrencyCode          =  $this->storeRepository->getStore()->getCurrentCurrencyCode();
        $this->latitudepayCurrency          =  $this->scopeConfig->getValue(self::LATITUDE_CURRENCY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->genoapayCurrency             =  $this->scopeConfig->getValue(self::GENOAPAY_CURRENCY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->supportedCurrencyCodes       = array($this->latitudepayCurrency=>'latitudepay',$this->genoapayCurrency=>'genoapay');

        if (isset($this->supportedCurrencyCodes[$this->currentCurrencyCode])) {
            $this->code = $this->supportedCurrencyCodes[$this->currentCurrencyCode];
        }
    }
    /**
     * Checks whether the Latitudepay payment method is enabled.
     *
     * @param null $store
     * @return mixed
     */
    public function isLatitudepayEnabled($store = null)
    {
        return $this->scopeConfig->getValue(self::LATITUDE_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store) && ($this->currentCurrencyCode == $this->latitudepayCurrency) ;
    }
    /**
     * get Genoapay Enabled
     *
     * @param null $store
     * @return mixed
     */
    public function isGenoapayEnabled($store = null)
    {
        return $this->scopeConfig->getValue(self::GENOAPAY_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store) && ($this->currentCurrencyCode == $this->genoapayCurrency);
    }
    /**
     * Retrieve information from Latitudepay/Genoapay configuration
     *@throws \Magento\Framework\Exception\LocalizedException
     * @param string $field
     * @param int $storeId
     * @param string $methodCode
     * @return  false|string
     */
    public function getConfigData($field,$storeId = null,$methodCode = null)
    {
        if($storeId == null){
            $storeId  = $this->storeRepository->getStore()->getId();
        }
        if ($methodCode) {
            $this->code = $methodCode;
        }
        if (empty($this->code)) {
            return false;
        }
        if (isset($this->configVars[$field])){
            $path = 'payment/' . $this->code . '/' . $field;
        } else {
            $path = '';
        }

        if($storeId){
            return $this->scopeConfig->getValue(
                $path,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,$storeId
            );
        }else{
            return $this->scopeConfig->getValue(
                $path,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }

    }

    /**
     * If display billing address on payment page is available, otherwise should be display on payment method
     *
     * @return bool
     */
    public function isDisplayBillingOnPaymentPageAvailable(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::DISPLAY_BILLING_ADDRESS_ON_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Set Base URL to Pennies API according to the environment.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironment($storeId = null,$methodCode= null)
    {
        $env  = $this->getConfigData('environment',$storeId,$methodCode);
        if($env == 'production') {
            return $this->scopeConfig->getValue('payment/'. $this->code . '/api_url_production', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }else{
            return $this->scopeConfig->getValue('payment/'. $this->code . '/api_url_sandbox', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    /**
     * Get Latitudepay Payment Services
     *
     * @param null $store
     * @return mixed
     */
    public function getLatitudepayPaymentServices($store = null)
    {
        if($this->isGenoapayEnabled()){
            return 'GPAY';
        }

        if($this->isLatitudepayEnabled()){
            if($this->scopeConfig->getValue(self::LATITUDE_PAYMENT_SERVICES, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store)){
                return $this->scopeConfig->getValue(self::LATITUDE_PAYMENT_SERVICES, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
            }
            return 'LPAY';
        }
        return '';
    }

    /**
     * Get Latitudepay Payment Terms
     *
     * @param null $store
     * @return mixed
     */
    public function getLatitudepayPaymentTerms($store = null)
    {
        if($this->isLatitudepayEnabled()){
            return $this->scopeConfig->getValue(self::LATITUDE_PAYMENT_TERMS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
        }
        return null;
    }

    /**
     * Get Image API URL.
     *
     * @param null $store
     * @return mixed
     */
    public function getImageApiUrl($store = null)
    {
        return $this->scopeConfig->getValue(self::LATITUDE_IMAGE_API_URL, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Retrieve util js
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getUtilJs()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->getImageApiUrl().'/util.js';
    }

    /**
     * Retrieve snippet image url
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getSnippetImageUrl()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->getImageApiUrl().'/snippet.svg';
    }

    /**
     * Retrieve current payment method code
     * @return string
     */
    public function getMethodCode()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->code;
    }
}
