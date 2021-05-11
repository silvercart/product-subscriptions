<?php

namespace SilverCart\Subscriptions\Extensions;

use SilverCart\Admin\Model\Config;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBMoney;

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
            if ($product->HasConsequentialCosts
             && empty($product->BillingPeriod)
            ) {
                $priceNice = $this->owner
                        ->customise([
                            'Once'                    => $product->fieldLabel('Once'),
                            'Then'                    => $product->fieldLabel('Then'),
                            'BillingPeriodNice'       => $product->BillingPeriodConsequentialCostsNice,
                            'ContextPrice'            => $this->owner->getPrice((bool) $forSingleProduct),
                            'PriceConsequentialCosts' => $this->owner->getPriceConsequentialCosts((bool) $forSingleProduct),
                        ])
                        ->renderWith(self::class . 'Price_ShoppingCartHasConsequentialCostsOnce');
            } elseif ($product->HasConsequentialCosts) {
                $priceNice = $this->owner
                        ->customise([
                            'ContextPrice'            => $this->owner->getPrice((bool) $forSingleProduct),
                            'PriceConsequentialCosts' => $this->owner->getPriceConsequentialCosts((bool) $forSingleProduct),
                        ])
                        ->renderWith(self::class . 'Price_ShoppingCartHasConsequentialCosts');
            } else {
                $priceNice = $this->owner
                        ->customise([
                            'BillingPeriodNice' => $product->BillingPeriodNice,
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
     * @param bool   $forSingleProduct Indicates wether the price for the total
     *                                 quantity of products should be returned
     *                                 or for one product only.
     * @param string $priceType        'gross' or 'net'. If undefined it'll be automatically chosen.
     * 
     * @return DBMoney
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function getPriceConsequentialCosts(bool $forSingleProduct = false, string $priceType = '') : DBMoney
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
        $this->owner->extend('updatePriceConsequentialCosts', $priceObj, $forSingleProduct, $priceType);
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
        $has = (bool) $this->owner->Product()->HasConsequentialCosts;
        $this->owner->extend('updateHasConsequentialCosts', $has);
        return $has;
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
     * @return void
     */
    public function setDisplayContextBillingPeriod(string $billingPeriod) : void
    {
        $this->displayContextBillingPeriod[$this->owner->ID] = $billingPeriod;
    }
    
    /**
     * Returns the price for the current billing period display context.
     * 
     * @return DBMoney
     */
    public function getDisplayContextBillingPeriodPrice() : DBMoney
    {
        $price   = DBMoney::create();
        $context = $this->getDisplayContextBillingPeriod();
        if ((string) $this->owner->Product()->BillingPeriod === (string) $context) {
            $price = $this->owner->getSinglePrice();
        } else {
            $price = $this->owner->getSinglePriceConsequentialCosts();
        }
        $this->owner->extend('updateDisplayContextBillingPeriodPrice', $price);
        return $price;
    }
    
    /**
     * Returns the price addition for the current billing period display context.
     * 
     * @return DBHTMLText
     */
    public function DisplayContextBillingPeriodPriceAddition() : DBHTMLText
    {
        $addition       = '';
        $billingPeriod  = ucfirst($this->getDisplayContextBillingPeriod());
        if (!empty($billingPeriod)
         && !empty($this->owner->Product()->BillingPeriod)
         && $this->owner->hasConsequentialCosts()
        ) {
            if ($this->getSubscriptionDurationValue() === 1) {
                $addition = '<br/>' . _t(self::class . '.BillingPeriodAdditionSingular', 'in the first {period}, then {price}', [
                    'period'   => $this->getDurationPeriodLabel(),
                    'price'    => $this->owner->getSinglePriceConsequentialCosts()->Nice(),
                ]);
            } else {
                $addition = '<br/>' . _t(self::class . '.BillingPeriodAdditionPlural', 'in the first {duration} {period}, then {price}', [
                    'duration' => $this->getSubscriptionDurationValue(),
                    'period'   => $this->getDurationPeriodLabel(),
                    'price'    => $this->owner->getSinglePriceConsequentialCosts()->Nice(),
                ]);
            }
        }
        return DBHTMLText::create()->setValue($addition);
    }
    
    /**
     * Returns the subscription duration value.
     * 
     * @return int
     */
    public function getSubscriptionDurationValue() : int
    {
        $value = (int) $this->owner->Product()->SubscriptionDurationValue;
        $this->owner->extend('updateSubscriptionDurationValue', $value);
        return (int) $value;
    }
    
    /**
     * Returns the duration period label.
     * 
     * @return string
     */
    public function getDurationPeriodLabel() : string
    {
        $label          = '';
        $durationPeriod = ucfirst($this->getSubscriptionDurationPeriod());
        $product        = $this->owner->Product();
        if ($this->getSubscriptionDurationValue() === 1) {
            $label = $product->fieldLabel("DurationPeriodAddSingular{$durationPeriod}");
        } else {
            $label = $product->fieldLabel("DurationPeriodAddPlural{$durationPeriod}");
        }
        return $label;
    }
    
    /**
     * Returns the subscription duration period.
     * 
     * @return string
     */
    public function getSubscriptionDurationPeriod() : string
    {
        $period = (string) $this->owner->Product()->SubscriptionDurationPeriod;
        $this->owner->extend('updateSubscriptionDurationPeriod', $period);
        return (string) $period;
    }
}