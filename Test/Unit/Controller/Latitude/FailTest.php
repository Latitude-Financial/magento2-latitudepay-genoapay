<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Latitude\Payment\Test\Unit\Controller\Latitude;

use Latitude\Payment\Test\Unit\LatitudeTestCase;
use Latitude\Payment\Controller\Latitude\Fail;
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
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
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
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
/**
 * @covers \Latitude\Payment\Controller\Latitude\Fail
 */
class FailTest extends LatitudeTestCase
{
    /**
     * @var Http|MockObject
     */
    private $request;

    /**
     * @var Redirect|MockObject
     */
    private $resultRedirectMock;

    /**
     * @var MockObject
     */
    private $redirectMock;
    
    /**
     * @var Fail
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
    protected $objectManager;

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
        $this->objectManager = new ObjectManager($this);
        $this->jsonHelperMock = $this->createMock(Data::class);
        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->latitudeSessionMock = $this->createMock(LatitudeSession::class);
        $this->messageManagerMock = $this->getMockBuilder(ManagerInterface::class)->getMockForAbstractClass();
        $this->contextMock = $this->basicMock(Context::class);
        $this->objectManagerMock = $this->basicMock(ObjectManagerInterface::class);
        $this->url = $this->getMockForAbstractClass(UrlInterface::class);

        $this->resultRedirectMock = $this->basicMock(Redirect::class);
        $resultRedirectFactoryMock = $this->getMockBuilder(RedirectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $resultRedirectFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->resultRedirectMock);
        
        $this->latitudeCheckoutFactoryMock = $this->createMock(LatitudeCheckoutFactory::class);
        $this->latitudeCheckoutMock = $this->getMockBuilder(LatitudeCheckout::class)
            ->disableOriginalConstructor()
            ->getMock();
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

        $this->resultFactoryMock = $this->createMock(ResultFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        
        $resultRedirectFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->resultRedirectMock);
        
        $this->resultJsonFactoryMock = $this->createPartialMock(
                ResultJsonFactory::class,
                ['create']
            );
        $apiObject = $this->basicMock(\Latitude\Payment\Model\Api\Lpay::class);

        $apiObject->expects($this->any())
            ->method('validateSignature')
            ->willReturn(true);

        // objectManagerMock
        $objectManagerReturns = [
            [LatitudeSession::class, $this->latitudeSessionMock],
        ];
        $this->objectManagerMock->expects($this->any())
            ->method('get')
            ->willReturnMap($objectManagerReturns);
        $this->basicStub($this->objectManagerMock, 'create')
            ->willReturn($this->basicMock(UrlInterface::class));

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
        
        $this->request = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['isSecure', 'getHeader'])
            ->getMock();
        
        // context stubs
        $this->basicStub($this->contextMock, 'getObjectManager')->willReturn($this->objectManagerMock);
        $this->basicStub($this->contextMock, 'getRequest')->willReturn($this->request);
        $this->basicStub($this->contextMock, 'getResponse')->willReturn($this->responseMock);
        $this->basicStub($this->contextMock, 'getMessageManager')
            ->willReturn($this->basicMock(ManagerInterface::class));
        $this->basicStub($this->contextMock, 'getRedirect')->willReturn($this->redirectMock);
        $this->basicStub($this->contextMock, 'getUrl')->willReturn($this->url);
        $this->basicStub($this->contextMock, 'getResultRedirectFactory')->willReturn($resultRedirectFactoryMock);
        $this->basicStub($this->contextMock, 'getResultFactory')->willReturn($this->resultFactoryMock);

        $this->controller = $this->objectManager->getObject(
            Fail::class,
            [
                'context' => $this->contextMock,
                'latitudeSession' => $this->latitudeSessionMock,
                'customerSession' => $this->customerSessionMock,
                'checkoutSession' => $this->checkoutSessionMock,
                'messageManager'  => $this->messageManagerMock,
                'checkoutFactory' => $this->latitudeCheckoutFactoryMock,
                'resultFactory'   => $this->resultFactoryMock,
                'api'             => $apiObject
            ]
        );
    }

    /**
     * Test Execute Method
     */
    public function testExecute(): void
    {
        $this->resultFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($this->resultRedirectMock);
        $expectedPath = 'checkout?cancel';

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with($expectedPath)
            ->willReturnSelf();
        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }
}