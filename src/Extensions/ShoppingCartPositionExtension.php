<?php

namespace SilverCart\Subscriptions\Extensions;

use DataExtension;
use Money;
use SilvercartConfig;

/**
 * Extension for SilverCart ShoppingCartPosition.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 23.11.2018
 * @copyright 2018 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class ShoppingCartPositionExtension extends DataExtension
{
    /**
     * Updates the positions PriceNice property.
     * 
     * @param string $priceNice Price to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updatePriceNice(&$priceNice, $forSingleProduct = false)
    {
        $product = $this->owner->SilvercartProduct();
        if ($product->IsSubscription) {
            $billingPeriodUCF = ucfirst($product->BillingPeriod);
            $billingPeriod    = $product->fieldLabel("BillingPeriod{$billingPeriodUCF}");
            if ($product->HasConsequentialCosts) {
                $priceNice = $this->owner
                        ->customise([
                            'Once'                    => $product->fieldLabel('Once'),
                            'Then'                    => $product->fieldLabel('Then'),
                            'BillingPeriodNice'       => $billingPeriod,
                            'ContextPrice'            => $this->owner->getPrice($forSingleProduct),
                            'PriceConsequentialCosts' => $this->owner->getPriceConsequentialCosts($forSingleProduct),
                        ])
                        ->renderWith('SilvercartSubscriptionsPrice_ShoppingCartHasConsequentialCosts');
            } else {
                $priceNice = $this->owner
                        ->customise([
                            'BillingPeriodNice' => $billingPeriod,
                            'ContextPrice'      => $this->owner->getPrice($forSingleProduct),
                        ])
                        ->renderWith('SilvercartSubscriptionsPrice_ShoppingCart');
            }
        }
    }
    
    /**
     * Updates the positions PriceNice property.
     * 
     * @param string $priceNice Price to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updateSinglePriceNice(&$priceNice)
    {
        $this->updatePriceNice($priceNice, true);
    }

    /**
     * price sum of this position
     *
     * @param boolean $forSingleProduct Indicates wether the price for the total
     *                                  quantity of products should be returned
     *                                  or for one product only.
     * @param boolean $priceType        'gross' or 'net'. If undefined it'll be automatically chosen.
     * 
     * @return Money the price sum
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function getPriceConsequentialCosts($forSingleProduct = false, $priceType = false)
    {
        $product = $this->owner->SilvercartProduct();
        $price   = 0;

        if ($product
         && $product->getPriceConsequentialCosts($priceType)->getAmount()
        ) {
            if ($forSingleProduct) {
                $price = $product->getPriceConsequentialCosts($priceType)->getAmount();
            } else {
                $price = $product->getPriceConsequentialCosts($priceType)->getAmount() * $this->owner->Quantity;
            }
        }

        $priceObj = Money::create();
        $priceObj->setAmount($price);
        $priceObj->setCurrency(SilvercartConfig::DefaultCurrency());

        return $priceObj;
    }
    
    /**
     * Returns the tax amount of the position's consequential costs.
     *
     * @param boolean $forSingleProduct Indicates wether the price for the total
     *                                  quantity of products should be returned
     *                                  or for one product only.
     * 
     * @return float
     */
    public function getTaxAmountConsequentialCosts($forSingleProduct = false) {
        if (SilvercartConfig::PriceType() == 'gross') {
            $taxRate = $this->owner->getPriceConsequentialCosts($forSingleProduct)->getAmount() -
                       ($this->owner->getPriceConsequentialCosts($forSingleProduct)->getAmount() /
                        (100 + $this->owner->SilvercartProduct()->getTaxRate()) * 100); 
        } else {
            $taxRate = $this->owner->getPriceConsequentialCosts($forSingleProduct)->getAmount() *
                       ($this->owner->SilvercartProduct()->getTaxRate() / 100);
        }
        return $taxRate;
    }
    
    /**
     * Returns whether this shopping cart position reflects a product with 
     * subscription.
     * 
     * @return boolean
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function isSubscription()
    {
        return $this->owner->SilvercartProduct()->IsSubscription;
    }
    
    /**
     * Returns whether this shopping cart position reflects a product with 
     * consequential costs.
     * 
     * @return boolean
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function hasConsequentialCosts()
    {
        return $this->owner->SilvercartProduct()->HasConsequentialCosts;
    }
}