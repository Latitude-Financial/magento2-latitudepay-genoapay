<?php
namespace Latitude\Payment\Model;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Exception\LocalizedException;
/**
 * Class CustomConfigProvider
 * @package Tryzens\ExtendShipping\Model
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{
    const LATITUDEPAY = 'latitudepay';
    const GENOAPAY = 'genoapay';
    const INSTALLMENTNO = 'installmentno';
    const CURRENCY_SYMBOL = 'currency_symbol';
    const LATITUDEPAY_INSTALLMENT_BLOCK = 'lpay_installment_block';
    const GENOAPAY_INSTALLMENT_BLOCK    = 'gpay_installment_block';
    const UTIL_JS = 'utilJs';

    /**
     * Latitudepay/Genoapay Checkout
     */
    const METHOD_LPAY = 'latitudepay';
    const METHOD_GPAY = 'genoapay';
    const INSTALLMENT_NO = 10;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Latitude\Payment\Logger\Logger
     */
    protected $logger;
    /**
     * @var \Latitude\Payment\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;
    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        self::METHOD_LPAY,
        self::METHOD_GPAY
    ];
    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /** @var LayoutInterface  */

    protected $layout;

    protected $currency;

    /**
     * CustomConfigProvider constructor.
     *@param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     *@param   Repository $assetRepo,
     *@param RequestInterface $request,
     *@param UrlInterface $urlBuilder,
     *@param \Latitude\Payment\Logger\Logger $logger,
     *@param \Latitude\Payment\Helper\Config $configHelper,
     *@param \Magento\Checkout\Model\Cart $cart,
     *@param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
     *@param PaymentHelper $paymentHelper,
     *@param LayoutInterface $layout
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        \Latitude\Payment\Logger\Logger $logger,
        \Latitude\Payment\Helper\Config $configHelper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        PaymentHelper $paymentHelper,
        LayoutInterface $layout,
        \Magento\Directory\Model\Currency $currency
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->configHelper  = $configHelper;
        $this->cart  = $cart;
        $this->priceCurrency = $priceCurrency;
        $this->paymentHelper = $paymentHelper;
        $this->layout = $layout;
        $this->currency = $currency;

        foreach ($this->methodCodes as $code) {
            try {
                $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
            } catch (LocalizedException $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * get config
     *
     * @return array
     * @throws LocalizedException
     */
    public function getConfig()
    {
        $lpayinstallmentBlockId = "latitude_installment_block";
        $gpayinstallmentBlockId = "genoapay_installment_block";
        /** @noinspection PhpUndefinedMethodInspection */
        /** @noinspection PhpUndefinedMethodInspection */
        $config = [
            'latitudepayments' => [
                self::LATITUDEPAY => $this->getViewFileUrl('Latitude_Payment::images/latitude-pay-logo.png'),
                self::GENOAPAY => $this->getViewFileUrl('Latitude_Payment::images/genoapay_logo_header.svg'),
                self::INSTALLMENTNO => $this->getInstallmentNo(),
                self::CURRENCY_SYMBOL => $this->currency->getCurrencySymbol(),
                self::UTIL_JS => $this->configHelper->getUtilJs(),
                self::LATITUDEPAY_INSTALLMENT_BLOCK => '<img class="lpay_snippet" src="'.$this->getSnippetImage().'" alt="LatitudePay" style="cursor: pointer;">',
                self::GENOAPAY_INSTALLMENT_BLOCK    => '<img class="lpay_snippet" src="'.$this->getSnippetImage().'" alt="GenoaPay" style="cursor: pointer;">',
            ],
        ];

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable($this->cart->getQuote())) {
                $config['payment']['latitude']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
            }
        }
        return $config;
    }

    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }



    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string
     */
    public function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }

    /**
     * Gets amount for current product
     * @return string|false
     */
    public function getAmount()
    {
        $totalAmount = $this->cart->getQuote()->getGrandTotal();
        return $totalAmount;
    }

    /**
     * Retrieve Payment Installment Text
     *
     * @return string|false
     * @throws LocalizedException
     */
    public function getInstallmentNo()
    {
        $installment = $this->configHelper->getConfigData('installment_no');
        return ($installment ? $installment :self::INSTALLMENT_NO);
    }

    /**
     * Retrieve Snippet Image
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getSnippetImage()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $param = [
            'amount' => '__AMOUNT__',
            'services' => $this->configHelper->getLatitudepayPaymentServices(),
            'terms' => $this->configHelper->getLatitudepayPaymentTerms(),
            'style' => 'checkout'
        ];
        return $this->configHelper->getSnippetImageUrl() . '?' . http_build_query($param);
    }
}

