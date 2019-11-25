<?php declare(strict_types=1);

namespace SwagVueStorefront\VueStorefront\PageResult\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Storefront\Framework\Routing\Router;
use SwagVueStorefront\VueStorefront\Controller\PageController;
use SwagVueStorefront\VueStorefront\PageLoader\Context\PageLoaderContext;
use SwagVueStorefront\VueStorefront\PageResult\Navigation\AggregationResultHydrator\AggregationResultHydratorInterface;

class NavigationPageResultHydrator
{
    /**
     * @var NavigationPageResult
     */
    private $pageResult;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var AggregationResultHydratorInterface[]
     */
    private $aggregationResultHydrators;

    public function __construct(Router $router, iterable $aggregationResultHydrators)
    {
        $this->router = $router;

        $this->pageResult = new NavigationPageResult();

        /** @var AggregationResultHydratorInterface[] $aggregationResultHydrators */
        foreach($aggregationResultHydrators as $resultHydrator)
        {
            $this->aggregationResultHydrators[$resultHydrator->getSupportedAggregationType()] = $resultHydrator;
        }
    }

    public function hydrate(PageLoaderContext $pageLoaderContext, CategoryEntity $category, ?CmsPageEntity $cmsPageEntity): NavigationPageResult
    {
        $this->pageResult->setCmsPage($cmsPageEntity);

        $this->setBreadcrumbs(
            $category,
            $pageLoaderContext->getContext()->getSalesChannel()->getNavigationCategoryId()
        );

        $this->pageResult->setResourceType($pageLoaderContext->getResourceType());
        $this->pageResult->setResourceIdentifier($pageLoaderContext->getResourceIdentifier());

        $this->pageResult->setListingConfiguration($this->getAvailableFilters());

        return $this->pageResult;
    }

    private function getAvailableFilters(): array
    {
        if($this->pageResult->getCmsPage() === null)
        {
            return [];
        }

        // Assuming a page only has one listing
        $listingSlot = $this->pageResult->getCmsPage()->getFirstElementOfType('product-listing');

        if($listingSlot === null)
        {
            return [];
        }

        /** @var ProductListingResult $listing */
        $listing = $listingSlot->getData()->getListing();
        $filters = [];

        foreach($listing->getAggregations() as $key => $aggregation)
        {
            $filters[$key] = $this->aggregationResultHydrators[get_class($aggregation)]->hydrate($aggregation);
        }

        $currentFilters = $listing->getCurrentFilters();

        $listingConfig = [
            'availableSortings' => $listing->getSortings(),
            'availableFilters' => $filters,
            'activeFilters' => $currentFilters,
        ];

        return $listingConfig;
    }

    private function setBreadcrumbs(CategoryEntity $category, string $rootCategoryId): void
    {
        $breadcrumbs = [];

        $categoryBreadcrumbs = $category->buildSeoBreadcrumb($rootCategoryId) ?? [];

        foreach($categoryBreadcrumbs as $id => $name)
        {
            $breadcrumbs[$id] = [
                'name' => $name,
                'path' => $this->router->generate(PageController::NAVIGATION_PAGE_ROUTE, ['navigationId' => $id]),
            ];
        }

        $this->pageResult->setBreadcrumb($breadcrumbs);
    }
}
