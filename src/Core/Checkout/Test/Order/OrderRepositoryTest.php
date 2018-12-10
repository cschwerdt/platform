<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OrderRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderPersister
     */
    private $orderPersister;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CheckoutContextFactory
     */
    private $checkoutContextFactory;

    public function setUp()
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->checkoutContextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
    }

    public function testCreateOrder()
    {
        $orderId = Uuid::uuid4()->getHex();
        $orderData = $this->getOrderData($orderId);
        $defaultContext = Context::createDefaultContext();
        $this->orderRepository->create($orderData, $defaultContext);

        $order = $this->orderRepository->read(new ReadCriteria([$orderId]), $defaultContext);
        static::assertEquals($orderId, $order->first()->get('id'));
    }

    public function testDeleteOrder()
    {
        $token = Uuid::uuid4()->getHex();
        $cart = new Cart('test', $token);

        $cart->add(
            (new LineItem('test', 'test'))
                ->setLabel('test')
                ->setGood(true)
                ->setPriceDefinition(new AbsolutePriceDefinition(10))
        );

        $customerId = $this->createCustomer();

        $context = $this->checkoutContextFactory->create(
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $customerId,
            ]);

        $cart = $this->processor->process($cart, $context);

        $result = $this->orderPersister->persist($cart, $context);

        $orders = $result->getEventByDefinition(OrderDefinition::class);
        $orders = $orders->getIds();
        $id = array_shift($orders);

        $count = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(1, $count);

        $this->orderRepository->delete([
            ['id' => $id],
        ], Context::createDefaultContext());

        $count = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(0, $count);
    }

    private function createCustomer(): string
    {
        $addressId = Uuid::uuid4()->getHex();
        $customerId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Mr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Mr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function getOrderData($orderId)
    {
        $addressId = Uuid::uuid4()->getHex();
        $orderLineItemId = Uuid::uuid4()->getHex();

        $order = [
            [
            'id' => $orderId,
            'date' => date(DATE_ISO8601),
            'amountTotal' => 0,
            'amountNet' => 0,
            'positionPrice' => 0,
            'shippingTotal' => 0,
            'shippingNet' => 0,
            'isNet' => true,
            'isTaxFree' => false,
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'salesChannelId' => Defaults::SALES_CHANNEL,

            'lineItems' => [
                [
                    'id' => $orderLineItemId,
                    'identifier' => 'test',
                    'quantity' => 1,
                    'unitPrice' => 1,
                    'totalPrice' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'priority' => 100,
                    'good' => true,
                ],
            ],
            'deliveries' => [
                [
                    'orderStateId' => Defaults::ORDER_STATE_OPEN,
                    'shippingMethodId' => Defaults::SHIPPING_METHOD,
                    'shippingDateEarliest' => date(DATE_ISO8601),
                    'shippingDateLatest' => date(DATE_ISO8601),
                    'positions' => [
                        [
                            'orderLineItemId' => $orderLineItemId,
                            'unitPrice' => 1,
                            'totalPrice' => 1,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
            'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
            'orderCustomer' => [
                'email' => 'test@example.com',
                'firstName' => 'Noe',
                'lastName' => 'Hill',
                'salutation' => 'Mr',
                'title' => 'Doc',
                'customerNumber' => 'Test',
            ],
            'billingAddress' => [
                'salutation' => 'mr',
                'firstName' => 'Floy',
                'lastName' => 'Glover',
                'zipcode' => '59438-0403',
                'city' => 'Stellaberg',
                'street' => 'street',
                'countryId' => Defaults::COUNTRY,
                'id' => $addressId,
            ],
         ],
        ];

        return $order;
    }
}
