<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ErrorController
     */
    private $errorController;

    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        ErrorController $errorController
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->errorController = $errorController;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 40],
            ],
            KernelEvents::EXCEPTION => [
                ['showHtmlExceptionResponse', -100],
                ['customerNotLoggedInHandler'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest'],
            ],
            KernelEvents::RESPONSE => [
                ['setCanonicalUrl'],
            ],
            CustomerLoginEvent::EVENT_NAME => [
                'updateSession',
            ],
        ];
    }

    public function startSession(): void
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            return;
        }
        if (!$master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $session = $master->getSession();
        if (!$session) {
            return;
        }

        $applicationId = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

        if (!$session->isStarted()) {
            $session->setName('session-' . $applicationId);
            $session->start();
            $session->set('sessionId', $session->getId());
        }

        if (!$session->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $token = Random::getAlphanumericString(32);
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        }

        $master->headers->set(
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function updateSession(CustomerLoginEvent $event): void
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            return;
        }
        if (!$master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $session = $master->getSession();
        if (!$session) {
            return;
        }

        $session->migrate();
        $session->set('sessionId', $session->getId());

        $token = $event->getContextToken();
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function showHtmlExceptionResponse(ExceptionEvent $event): void
    {
        if ($event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            $event->stopPropagation();
            $content = $this->errorController->error(
                $event->getException(),
                $this->requestStack->getMasterRequest(),
                $event->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)
            );
            $event->setResponse($content);
        }
    }

    public function customerNotLoggedInHandler(ExceptionEvent $event): void
    {
        if (!$event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$event->getException() instanceof CustomerNotLoggedInException) {
            return;
        }

        $request = $event->getRequest();

        $parameters = [
            'redirectTo' => $request->attributes->get('_route'),
            'redirectParameters' => json_encode($request->attributes->get('_route_params')),
        ];

        $redirectResponse = new RedirectResponse($this->router->generate('frontend.account.login.page', $parameters));

        $event->setResponse($redirectResponse);
    }

    public function preventPageLoadingFromXmlHttpRequest(ControllerEvent $event): void
    {
        if (!$event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        if (!$event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $controller = $event->getController();

        // happens if Controller is a closure
        if (!is_array($controller)) {
            return;
        }

        $isAllowed = $event->getRequest()->attributes->getBoolean('XmlHttpRequest', false);

        if ($isAllowed) {
            return;
        }

        throw new AccessDeniedHttpException('PageController can\'t be requested via XmlHttpRequest.');
    }

    public function setCanonicalUrl(ResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        if ($canonical = $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK)) {
            $canonical = sprintf('<%s>; rel="canonical"', $canonical);
            $event->getResponse()->headers->set('Link', $canonical);
        }
    }
}
