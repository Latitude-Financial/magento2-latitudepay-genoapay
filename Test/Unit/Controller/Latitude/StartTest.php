<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Latitude\Payment\Test\Unit\Controller\Latitude;

use Latitude\Payment\Controller\Latitude\Start;
use Latitude\Payment\Model\Latitude\Checkout as LatitudeCheckout;
use Latitude\Payment\Model\Latitude\Checkout\Factory as LatitudeCheckoutFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json as ResultJson;
use Magento\Framework\Controller\Result\JsonFactory as ResultJsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory as ResultRedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Session\Generic as LatitudeSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\Json\Helper\Data;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

use Latitude\Payment\Test\Unit\LatitudeTestCase;
/**
 * @covers \Latitude\Payment\Controller\Latitude\Start
 */
class StartTest extends LatitudeTestCase
{
    /**
     * @var Start
     */
    private $controller;

    /**
     * @var ConfigInterface|MockObject
     */
    private $configMock;

    /**
     * @var ResultFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var ResultJsonFactory|MockObject
     */
    private $resultJsonFactoryMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlMock;

    /**
     * @var ObjectManagerInterface|MockObject
     */
    protected $_objectManager;

    /**
     * @var Registry|MockObject
     */
    protected $_registry;

    /**
     * @var LatitudeSession|MockObject
     */
    protected $latitudeSessionMock;
    /**
     * @var CustomerSession|MockObject
     */
    protected $customerSessionMock;
    /**
     * @var CheckoutSession|MockObject
     */
    protected $checkoutSessionMock;

    /**
     * @var ManagerInterface|MockObject
     */
    protected $messageManagerMock;
    /**
     * @var MockObject
     */
    protected $quoteMock;

    /** @var Data|MockObject */
    protected $jsonHelperMock;

    /** @var RequestInterface|MockObject */
    protected $requestMock;

    /** @var ResponseInterface|MockObject */
    protected $responseMock;

    /** @var LatitudeCheckoutFactory|MockObject */
    protected $latitudeCheckoutFactoryMock;

    /** @var LatitudeCheckout|MockObject */
    protected $latitudeCheckoutMock;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->jsonHelperMock = $this->createMock(Data::class);
        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->latitudeSessionMock = $this->createMock(LatitudeSession::class);
        $this->messageManagerMock = $this->getMockBuilder(ManagerInterface::class)->getMockForAbstractClass();
        
        $this->latitudeCheckoutFactoryMock = $this->createMock(LatitudeCheckoutFactory::class);
        $this->latitudeCheckoutMock = $this->getMockBuilder(LatitudeCheckout::class)
            ->disableOriginalConstructor()
            ->getMock();
        $apiMock = $this->basicMock(\Latitude\Payment\Model\Api\Lpay::class);

        $apiMock->expects($this->any())
            ->method('formatPrice')
            ->willReturn(200);

        $this->latitudeCheckoutFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->latitudeCheckoutMock);
        
        $this->customerSessionMock->expects($this->any())
            ->method('isLoggedIn')
            ->willReturn(true);

        $customerDataObject = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerSessionMock->expects($this->any())
            ->method('getCustomerDataObject')
            ->willReturn($customerDataObject);

        $this->resultFactoryMock = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->resultJsonFactoryMock = $this->createPartialMock(
                ResultJsonFactory::class,
                ['create']
            );
        
        $this->_objectManager = $this->getMockForAbstractClass(ObjectManagerInterface::class);
        $this->_objectManager->expects(
            $this->any()
        )->method(
            'get'
        )->willReturnMap(
            [[LatitudeSession::class, $this->latitudeSessionMock]]
        );

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->addMethods(['getGrandTotal'])
            ->onlyMethods(['hasItems','getItemsCount', 'collectTotals', 'save', 'getShippingAddress', 'getStoreId', '__wakeup','getCheckoutMethod'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock->expects($this->any())
        ->method('hasItems')
        ->willReturn(true);
        $this->quoteMock->expects($this->any())
        ->method('getCheckoutMethod')
        ->willReturn(Onepage::METHOD_REGISTER);
        
        $this->quoteMock->expects($this->any())
        ->method('getGrandTotal')
        ->willReturn(200);
        
        
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
        ->onlyMethods(['getQuote'])
        ->disableOriginalConstructor()
        ->getMock();
        $this->checkoutSessionMock->expects($this->any())
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        

        $this->controller = (new ObjectManagerHelper($this))->getObject(
            Start::class,
            [
                'latitudeSession' => $this->latitudeSessionMock,
                'customerSession' => $this->customerSessionMock,
                'checkoutSession' => $this->checkoutSessionMock,
                'messageManager'  => $this->messageManagerMock,
                'checkoutFactory' => $this->latitudeCheckoutFactoryMock,
                'resultJsonFactory' => $this->resultJsonFactoryMock,
                'api'             => $apiMock
            ]
        );
    }

    /**
     * Test Execute Method
     */
    public function testExecuteWithEmptyToken(): void
    {
        $this->assertEquals(null, $this->controller->execute());
    }

    /**
     * Test Execute Method
     */
    public function testExecuteWithTokenError(): void
    {
        $responseData = [
            'error' => 'true',
            'message' => __('There was an error with your payment, please try again or select other payment method.')
        ];
        $resultJson = $this->createMock(ResultJson::class);
        $resultJson->expects($this->any())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        $this->resultJsonFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($resultJson);

        $this->basicStub($this->latitudeCheckoutMock, 'start')
            ->willReturn((object)[
                "authToken" => "xxxxxxxxxxxxxxxxxxxxxxxx",
                "expiryDate" =>"2029-08-24T14:15:22Z"
            ]);
        $this->assertEquals($resultJson, $this->controller->execute());
    }

    /**
     * Test Execute Method
     */
    public function testExecuteWithTokenSuccess(): void
    {
        $redirectUrl = 'https://example.com';
        $responseData = [
            "success" => true,
            "redirect_url" => $redirectUrl
        ];
        $resultJson = $this->createMock(ResultJson::class);
        $resultJson->expects($this->any())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        $this->resultJsonFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($resultJson);

        $this->basicStub($this->latitudeCheckoutMock, 'start')
            ->willReturn((object)[
                "authToken" => "xxxxxxxxxxxxxxxxxxxxxxxx",
                "expiryDate" =>"2029-08-24T14:15:22Z"
            ]);
        $this->basicStub($this->latitudeCheckoutMock, 'getRedirectUrl')
            ->willReturn($redirectUrl);
            
        $this->assertEquals($resultJson, $this->controller->execute());
    }
}