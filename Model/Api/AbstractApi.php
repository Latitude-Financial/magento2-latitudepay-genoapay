<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Latitude\Payment\Model\Api;


use \Magento\Customer\Helper\Address;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Formatter;
use \Latitude\Payment\Logger\Logger;
use \Magento\Framework\Locale\ResolverInterface;
use \Magento\Directory\Model\RegionFactory;
use \Magento\Checkout\Model\Session;

/**
 * Abstract class for API wrappers
 */
abstract class AbstractApi extends \Magento\Framework\DataObject
{
    use Formatter;

    /**
     * Config instance
     *
     * @var \Latitude\Payment\Model\Config
     */
    protected $config;

    /**
     * Global private to public interface map
     * @var array
     */
    protected $_globalMap = [];

    /**
     * Global private to public interface map
     * @var array
     */
    protected $exportProductInformation = [];

    /**
     * Filter callbacks for exporting $this data to API call
     *
     * @var array
     */
    protected $_exportToRequestFilters = [];

    /**
     * Filter callbacks for importing API result to $this data
     *
     * @var array
     */
    protected $importFromRequestFilters = [];

    /**
     * Line items export to request mapping settings
     *
     * @var array
     */
    protected $lineItemExportItemsFormat = [];

    /**
     * @var array
     */
    protected $lineItemExportItemsFilters = [
        'name' => 'strval'
    ];

    /**
     * @var array
     */
    protected $lineItemTotalExportMap = [];

    /**
     * Shopping cart instance
     *
     * @var \Latitude\Payment\Model\Cart
     */
    protected $cart;

    /**
     * Shipping options export to request mapping settings
     *
     * @var array
     */
    protected $shippingOptionsExportItemsFormat = [];

    /**
     * Customer address
     *
     * @var \Magento\Customer\Helper\Address
     */
    protected $customerAddress;

    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * By default is looking for first argument as array and assigns it as object
     * attributes This behavior may change in child classes
     *
     * @param Address $customerAddress
     * @param Logger $logger
     * @param ResolverInterface $localeResolver
     * @param RegionFactory $regionFactory
     *  @param Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        Address $customerAddress,
        Logger $logger,
        ResolverInterface $localeResolver,
        RegionFactory $regionFactory,
        Session $checkoutSession,
        array $data = []
    ) {
        $this->customerAddress = $customerAddress;
        $this->logger = $logger;
        $this->localeResolver = $localeResolver;
        $this->regionFactory = $regionFactory;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($data);
    }

    /**
     * Payment action getter
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->_getDataOrConfig('payment_action');
    }


    /**
     * Set Latitude cart instance
     *
     * @param \Latitude\Payment\Model\Cart $cart
     * @return $this
     */
    public function setLatitudeCart(\Latitude\Payment\Model\Cart $cart)
    {
        $this->cart = $cart;
        return $this;
    }

    /**
     * Config instance setter
     *
     * @param \Latitude\Payment\Model\Config $config
     * @return $this
     */
    public function setConfigObject(\Latitude\Payment\Model\Config $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Current locale code getter
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }


    /**
     * Export $this public data to private request array
     *
     * @param array $privateRequestMap
     * @param array $request
     * @return array
     */
    protected function &_exportToRequest(array $privateRequestMap, array $request = [])
    {
        $map = [];
        foreach ($privateRequestMap as $key) {
            if (isset($this->_globalMap[$key])) {
                $map[$this->_globalMap[$key]] = $key;
            }
        }
        $result = \Magento\Framework\DataObject\Mapper::accumulateByMap([$this, 'getDataUsingMethod'], $request, $map);
        return $result;
    }



    /**
     * Import $this public data from a private response array
     *
     * @param array $privateResponseMap
     * @param array $response
     * @return void
     */
    protected function _importFromResponse(array $privateResponseMap, array $response)
    {
        $map = [];
        foreach ($privateResponseMap as $key) {
            if (isset($this->_globalMap[$key])) {
                $map[$key] = $this->_globalMap[$key];
            }
            if (isset($response[$key]) && isset($this->importFromRequestFilters[$key])) {
                $callback = $this->importFromRequestFilters[$key];
                $response[$key] = call_user_func([$this, $callback], $response[$key], $key, $map[$key]);
            }
        }
        \Magento\Framework\DataObject\Mapper::accumulateByMap($response, [$this, 'setDataUsingMethod'], $map);
    }

    /**
     * Prepare line items request
     *
     * Returns true if there were line items added
     *
     * @param array $request
     * @return array|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _exportTotal(array $request)
    {
        if (!$this->checkoutSession->getQuote()) {
            return null;
        }
        $totals = [];
        // always add cart totals, even if line items are not requested
       if ($request) {
            $total = $this->checkoutSession->getQuote()->getGrandTotal();
            $total = round($total, 2);
            $totals['amount'] =  $total ? $this->formatPrice($total) :0.00;
            $totals['currency'] = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();
        }

        return $totals;
    }

    /**
     * Prepare line items request
     *
     * Returns true if there were line items added
     *
     * @param array $request
     * @return array|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _exportTaxAmount(array $request)
    {
        if (!$this->checkoutSession->getQuote()) {
            return null;
     }
        $totals = [];
        // always add cart totals, even if line items are not requested
       if ($request) {
            $taxAmount = $this->checkoutSession->getQuote()->getTaxAmount();
            $taxAmount = round($taxAmount, 2);
            $totals['amount'] =  $taxAmount ? $this->formatPrice($taxAmount) :0.00;
            $totals['currency'] = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();
        }
        return $totals;
    }

    /**
     * Prepare ShippingDetails
     *
     * @return array
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getShippingDetails()
    {
        $shippinglines = array(
            "carrier" =>  $this->checkoutSession->getQuote()->getShippingAddress()->getShippingMethod(),
            "price" => array(
                "amount" => $this->formatPrice($this->checkoutSession->getQuote()->getShippingAddress()->getShippingAmount()),
                "currency" => $this->checkoutSession->getQuote()->getQuoteCurrencyCode()
            )
        );

        return $shippinglines;
    }


    /**
     * Unified getter that looks in data or falls back to config
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null $default
     */
    protected function _getDataOrConfig($key, $default = null)
    {
        if ($this->hasData($key)) {
            return $this->getData($key);
        }
        return $this->config->getValue($key) ? $this->config->getValue($key) : $default;
    }
}
