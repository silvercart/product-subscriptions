<?php

namespace SilverCart\Subscriptions\Extensions;

use SilverCart\Admin\Model\Config;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\ORM\FieldType\DBMoney;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Extension for SilverCart ShoppingCartPosition.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 23.11.2018
 * @copyright 2018 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property ShoppingCartPosition $owner Owner
 */
class ShoppingCartPositionExtension extends DataExtension
{
    /**
     * Display context billing period for shopping cart.
     *
     * @var string[]
     */
    protected $displayContextBillingPeriod = [];
    
    /**
     * Updates the positions PriceNice property.
     * 
     * @param DBHTMLText $priceNice        Price to update
     * @param bool       $forSingleProduct Update price for single or sum?
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updatePriceNice(DBHTMLText &$priceNice, bool $forSingleProduct = null) : void
    {
        $product = $this->owner->Product();
        if ($product->IsSubscription) {
            $billingPeriodUCF = ucfirst($product->BillingPeriod);
            $billingPeriod    = $product->fieldLabel("BillingPeriod{$billingPeriodUCF}");
            if ($product->HasConsequentialCosts) {
                $priceNice = $this->owner
                        ->customise([
                            'Once'                    => $product->fieldLabel('Once'),
                            'Then'                    => $product->fieldLabel('Then'),
                            'BillingPeriodNice'       => $billingPeriod,
                            'ContextPrice'            => $this->owner->getPrice((bool) $forSingleProduct),
                            'PriceConsequentialCosts' => $this->owner->getPriceConsequentialCosts((bool) $forSingleProduct),
                        ])
                        ->renderWith(self::class . 'Price_ShoppingCartHasConsequentialCosts');
            } else {
                $priceNice = $this->owner
                        ->customise([
                            'BillingPeriodNice' => $billingPeriod,
                            'ContextPrice'      => $this->owner->getPrice((bool) $forSingleProduct),
                        ])
                        ->renderWith(self::class . 'Price_ShoppingCart');
            }
        }
    }
    
    /**
     * Updates the positions PriceNice property.
     * 
     * @param DBHTMLText $priceNice Price to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updateSinglePriceNice(DBHTMLText &$priceNice) : void
    {
        $this->updatePriceNice($priceNice, true);
    }

    /**
     * price sum of this position
     *
     * @param bool $forSingleProduct Indicates wether the price for the total
     *                               quantity of products should be returned
     *                               or for one product only.
     * @param bool $priceType        'gross' or 'net'. If undefined it'll be automatically chosen.
     * 
     * @return DBMoney
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function getPriceConsequentialCosts(bool $forSingleProduct = false, bool $priceType = false) : DBMoney
    {
        $product = $this->owner->Product();
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

        $priceObj = DBMoney::create();
        $priceObj->setAmount($price);
        $priceObj->setCurrency(Config::DefaultCurrency());

        return $priceObj;
    }

    /**
     * Single price of this position
     * 
     * @return DBMoney
     */
    public function getSinglePriceConsequentialCosts() : DBMoney
    {
        return $this->getPriceConsequentialCosts(true);
    }
    
    /**
     * Returns the tax amount of the position's consequential costs.
     *
     * @param bool $forSingleProduct Indicates wether the price for the total
     *                               quantity of products should be returned
     *                               or for one product only.
     * 
     * @return float
     */
    public function getTaxAmountConsequentialCosts(bool $forSingleProduct = false) : float
    {
        if (Config::PriceType() == 'gross') {
            $taxRate = $this->owner->getPriceConsequentialCosts($forSingleProduct)->getAmount() -
                       ($this->owner->getPriceConsequentialCosts($forSingleProduct)->getAmount() /
                        (100 + $this->owner->Product()->getTaxRate()) * 100); 
        } else {
            $taxRate = $this->owner->getPriceConsequentialCosts($forSingleProduct)->getAmount() *
                       ($this->owner->Product()->getTaxRate() / 100);
        }
        return $taxRate;
    }
    
    /**
     * Returns whether this shopping cart position reflects a product with 
     * subscription.
     * 
     * @return bool
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function isSubscription() : bool
    {
        return (bool) $this->owner->Product()->IsSubscription;
    }
    
    /**
     * Returns whether this shopping cart position reflects a product with 
     * consequential costs.
     * 
     * @return bool
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function hasConsequentialCosts() : bool
    {
        return (bool) $this->owner->Product()->HasConsequentialCosts;
    }
    
    /**
     * Returns the billing period for the current display context.
     * 
     * @return string
     */
    public function getDisplayContextBillingPeriod() : string
    {
        if (!array_key_exists($this->owner->ID, $this->displayContextBillingPeriod)) {
            $this->displayContextBillingPeriod[$this->owner->ID] = '';
        }
        return $this->displayContextBillingPeriod[$this->owner->ID];
    }
    
    /**
     * Sets the billing period for the current display context.
     * 
     * @param string $billingPeriod Billing period
     * 
     * @return ShoppingCartPosition
     */
    public function setDisplayContextBillingPeriod(string $billingPeriod) : ShoppingCartPosition
    {
        $this->displayContextBillingPeriod[$this->owner->ID] = $billingPeriod;
        return $this->owner;
    }
}