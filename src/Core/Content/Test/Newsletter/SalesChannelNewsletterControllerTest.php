<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelNewsletterControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->context = $context = Context::createDefaultContext();
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->getContainer()->get('system_config.repository');

        /*
         * Add subscribeDomain because Headless SalesChannels don't have a domain
         */
        $this->getSalesChannelClient(); // must be called for initializing the SalesChannel
        $newsletterDomainConfig = [
            'id' => $id ?? Uuid::randomHex(),
            'configurationKey' => 'core.newsletter.subscribeDomain',
            'configurationValue' => 'http://localhost',
            'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
        ];

        $systemConfigRepository->upsert([$newsletterDomainConfig], $context);
    }

    public function testSubscribe(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals(NewsletterSubscriptionServiceInterface::STATUS_NOT_SET, $subscriptions->first()->getStatus());
    }

    public function testConfirm(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        $hash = $subscriptions->first()->getHash();

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/confirm', [
            'hash' => $hash,
        ]);

        $response = $this->getSalesChannelClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals(NewsletterSubscriptionServiceInterface::STATUS_OPT_IN, $subscriptions->first()->getStatus());
    }

    public function testUnsubscribeBeforeConfirm(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/unsubscribe', [
            'email' => $email,
        ]);

        $response = $this->getSalesChannelClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals(NewsletterSubscriptionServiceInterface::STATUS_OPT_OUT, $subscriptions->first()->getStatus());
    }

    public function testUnsubscribeAfterConfirm(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        $hash = $subscriptions->first()->getHash();

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/confirm', [
            'hash' => $hash,
        ]);

        $response = $this->getSalesChannelClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals(NewsletterSubscriptionServiceInterface::STATUS_OPT_IN, $subscriptions->first()->getStatus());

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/unsubscribe', [
            'email' => $email,
        ]);

        $response = $this->getSalesChannelClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals(NewsletterSubscriptionServiceInterface::STATUS_OPT_OUT, $subscriptions->first()->getStatus());
    }

    public function testUpdate(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $firstName = Uuid::randomHex() . 'FirstName';
        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals(NewsletterSubscriptionServiceInterface::STATUS_NOT_SET, $subscriptions->first()->getStatus());
        static::assertEmpty($subscriptions->first()->getFirstName());

        $this->getSalesChannelClient()->request('PATCH', '/sales-channel-api/v1/newsletter', [
            'id' => $subscriptions->first()->getId(),
            'firstName' => $firstName,
        ]);

        $response = $this->getSalesChannelClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals($firstName, $subscriptions->first()->getFirstName());
    }
}
