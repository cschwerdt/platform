<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelApiController
{
    /**
     * @var SalesChannelDefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    public function __construct(
        SalesChannelDefinitionInstanceRegistry $registry,
        RequestCriteriaBuilder $criteriaBuilder,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->responseFactory = $responseFactory;
    }

    public function searchIds(Request $request, SalesChannelContext $context, string $entity): Response
    {
        $entity = $this->urlToSnakeCase($entity);

        $repository = $this->registry->getSalesChannelRepository($entity);

        /** @var SalesChannelDefinitionInterface|EntityDefinition $definition */
        $definition = $this->registry->getByEntityName($entity);

        $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $definition, $context->getContext());

        $definition->processCriteria($criteria, $context);

        $result = $repository->searchIds($criteria, $context);

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => array_values($result->getIds()),
        ]);
    }

    /**
     * @throws InvalidUuidException
     * @throws ResourceNotFoundException
     */
    public function detail(Request $request, string $id, SalesChannelContext $context, string $entity): Response
    {
        $entity = $this->urlToSnakeCase($entity);

        $repository = $this->registry->getSalesChannelRepository($entity);
        /** @var SalesChannelDefinitionInterface|EntityDefinition $definition */
        $definition = $this->registry->getByEntityName($entity);

        if (!Uuid::isValid($id)) {
            throw new InvalidUuidException($id);
        }

        $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria([$id]), $definition, $context->getContext());

        $definition->processCriteria($criteria, $context);

        $result = $repository->search($criteria, $context);

        if (!$result->has($id)) {
            throw new ResourceNotFoundException($definition->getEntityName(), ['id' => $id]);
        }

        return $this->responseFactory->createDetailResponse($result->get($id), $definition, $request, $context->getContext());
    }

    public function search(Request $request, SalesChannelContext $context, string $entity): Response
    {
        $entity = $this->urlToSnakeCase($entity);

        $repository = $this->registry->getSalesChannelRepository($entity);
        /** @var SalesChannelDefinitionInterface|EntityDefinition $definition */
        $definition = $this->registry->getByEntityName($entity);

        $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $definition, $context->getContext());

        $definition->processCriteria($criteria, $context);

        $result = $repository->search($criteria, $context);

        return $this->responseFactory->createListingResponse($result, $definition, $request, $context->getContext());
    }

    private function urlToSnakeCase(string $name): string
    {
        return str_replace('-', '_', $name);
    }
}
