<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

abstract class StorefrontController extends AbstractController
{
    protected function renderStorefront(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $view = $this->resolveView($view);

        $request = $this->get('request_stack')->getCurrentRequest();

        $master = $this->get('request_stack')->getMasterRequest();

        $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $event = new StorefrontRenderEvent($view, $parameters, $request, $context);

        $this->get('event_dispatcher')->dispatch($event, StorefrontRenderEvent::NAME);

        return $this->render($view, $event->getParameters(), $response);
    }

    protected function trans(string $snippet, array $parameters = []): string
    {
        return $this->container
            ->get('translator')
            ->trans($snippet, $parameters);
    }

    protected function createActionResponse(Request $request): Response
    {
        if ($request->get('redirectTo')) {
            $params = $request->get('redirectParameters');

            if (is_string($params)) {
                $params = json_decode($params, true);
            }

            if (empty($params)) {
                $params = [];
            }

            return $this->redirectToRoute($request->get('redirectTo'), $params);
        }

        if ($request->get('forwardTo')) {
            return $this->forwardToRoute($request->get('forwardTo'));
        }

        return new Response();
    }

    protected function forwardToRoute(string $routeName, array $parameters = [], array $routeParameters = []): Response
    {
        $router = $this->container->get('router');

        $url = $this->generateUrl($routeName, $routeParameters);

        // for the route matching the request method is set to "GET" because
        // this method is not ought to be used as a post passthrough
        // rather it shall return templates or redirects to display results of the request ahead
        $method = $router->getContext()->getMethod();
        $router->getContext()->setMethod(Request::METHOD_GET);

        $route = $router->match($url);
        $router->getContext()->setMethod($method);

        return $this->forward($route['_controller'], $parameters);
    }

    protected function resolveView(string $view): string
    {
        //remove static template inheritance prefix
        if (strpos($view, '@') === 0) {
            $viewParts = explode('/', $view);
            array_shift($viewParts);
            $view = implode('/', $viewParts);
        }

        /** @var TemplateFinder $templateFinder */
        $templateFinder = $this->get(TemplateFinder::class);

        return $templateFinder->find($view);
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    protected function denyAccessUnlessLoggedIn(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getMasterRequest();

        if (!$request) {
            return;
        }

        /** @var SalesChannelContext|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if ($context && $context->getCustomer() && $context->getCustomer()->getGuest() === false) {
            return;
        }

        throw new CustomerNotLoggedInException();
    }
}
