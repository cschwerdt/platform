<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Page;

class AccountOverviewPage extends Page
{
    /** @var OrderEntity|null */
    private $newestOrder;

    public function setNewestOrder(OrderEntity $order)
    {
        $this->newestOrder = $order;
    }

    public function getNewestOrder(): ?OrderEntity
    {
        return $this->newestOrder;
    }
}
