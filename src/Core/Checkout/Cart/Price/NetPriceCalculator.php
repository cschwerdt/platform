<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;

class NetPriceCalculator
{
    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    /**
     * @var PriceRoundingInterface
     */
    private $priceRounding;

    public function __construct(TaxCalculator $taxCalculator, PriceRoundingInterface $priceRounding)
    {
        $this->taxCalculator = $taxCalculator;
        $this->priceRounding = $priceRounding;
    }

    public function calculateCollection(PriceDefinitionCollection $collection): PriceCollection
    {
        $prices = $collection->map(
            function (QuantityPriceDefinition $definition) {
                return $this->calculate($definition);
            }
        );

        return new PriceCollection($prices);
    }

    public function calculate(QuantityPriceDefinition $definition): CalculatedPrice
    {
        $unitPrice = $this->getUnitPrice($definition);

        $taxRules = $definition->getTaxRules();

        $calculatedTaxes = $this->taxCalculator->calculateNetTaxes(
            $unitPrice,
            $definition->getPrecision(),
            $definition->getTaxRules()
        );

        foreach ($calculatedTaxes as $tax) {
            $tax->setPrice($tax->getPrice() * $definition->getQuantity());
            $tax->setTax($tax->getTax() * $definition->getQuantity());
        }

        $price = $this->priceRounding->round(
            $unitPrice * $definition->getQuantity(),
            $definition->getPrecision()
        );

        return new CalculatedPrice($unitPrice, $price, $calculatedTaxes, $taxRules, $definition->getQuantity());
    }

    private function getUnitPrice(QuantityPriceDefinition $definition): float
    {
        //unit price already calculated?
        if ($definition->isCalculated()) {
            return $definition->getPrice();
        }

        return $this->priceRounding->round(
            $definition->getPrice(),
            $definition->getPrecision()
        );
    }
}
