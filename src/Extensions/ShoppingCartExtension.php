<?php

namespace SilverCart\Subscriptions\Extensions;

use SilverCart\Admin\Model\Config;
use SilverCart\ORM\FieldType\DBMoney;
use SilverCart\Subscriptions\Extensions\ProductExtension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\ArrayData;

/**
 * Extension for SilverCart ShoppingCart.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 23.11.2018
 * @copyright 2018 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class ShoppingCartExtension extends DataExtension
{
    /**
     * Updates the field labels.
     * 
     * @param array &$labels Labels to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updateFieldLabels(&$labels)
    {
        $labels = array_merge(
                $labels,
                [
                    'Once' => _t(ProductExtension::class . ".Once", "once"),
                ]
        );
    }
    
    /**
     * Updates the taxable amount gross without modules.
     * 
     * @param float                      &$amount   Amount to update
     * @param \SilverStripe\ORM\DataList $positions Positions
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function updateTaxableAmountGrossWithoutModules(&$amount, $positions)
    {
        $this->updateTaxableAmount($amount, $positions, 'gross');
    }
    
    /**
     * Updates the taxable amount net without modules.
     * 
     * @param float                      &$amount   Amount to update
     * @param \SilverStripe\ORM\DataList $positions Positions
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function updateTaxableAmountNetWithoutModules(&$amount, $positions)
    {
        $this->updateTaxableAmount($amount, $positions, 'net');
    }
    
    /**
     * Updates the taxable amount gross without fees and charges.
     * 
     * @param float                      &$amount   Amount to update
     * @param \SilverStripe\ORM\DataList $positions Positions
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function updateTaxableAmountGrossWithoutFeesAndCharges(&$amount, $positions)
    {
        $this->updateTaxableAmount($amount, $positions, 'gross');
    }
    
    /**
     * Updates the taxable amount net without fees and charges.
     * 
     * @param float                      &$amount   Amount to update
     * @param \SilverStripe\ORM\DataList $positions Positions
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function updateTaxableAmountNetWithoutFeesAndCharges(&$amount, $positions)
    {
        $this->updateTaxableAmount($amount, $positions, 'net');
    }
    
    /**
     * Updates the taxable amount with the given context.
     * 
     * @param float     &$amount   Amount to update
     * @param ArrayList $positions Positions
     * @param string    $priceType Price type net or gross
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function updateTaxableAmount(&$amount, $positions, $priceType = null)
    {
        if (is_null($priceType)) {
            $priceType = Config::DefaultPriceType();
        }
        $amount = 0;
        foreach ($positions as $position) {
            /* @var $position \SilverCart\Model\Order\ShoppingCartPosition */
            if ($position->hasMethod('Product')
             && $position->hasMethod('isSubscription')
             && $position->isSubscription()
             && (!$position->hasConsequentialCosts()
              || ($position->hasConsequentialCosts()
               && !empty($position->Product()->BillingPeriod)))
            ) {
                continue;
            }
            $amount += (float) $position->getPrice(false, $priceType)->getAmount();
        }
    }
    
    /**
     * Overwrites the shopping cart position tax rates to use for calculation.
     * 
     * @param ArrayList                      &$taxRates  Tax rate list to overwrite
     * @param \SilverStripe\ORM\RelationList &$positions Positions to overwrite
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function overwritePositionTaxRates(&$taxRates, &$positions)
    {
        $newPositions = ArrayList::create();
        foreach ($positions as $position) {
            /* @var $position \SilverCart\Model\Order\ShoppingCartPosition */
            if ($position->hasMethod('Product')
             && $position->hasMethod('isSubscription')
             && $position->isSubscription()
             && (!$position->hasConsequentialCosts()
              || ($position->hasConsequentialCosts()
               && !empty($position->Product()->BillingPeriod)))
            ) {
                continue;
            }
            $newPositions->push($position);
        }
        $positions = $newPositions;
    }
    
    /**
     * Returns whether the shopping cart contains products with subscriptions.
     * 
     * @return boolean
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function hasSubscriptions()
    {
        $hasSubscriptions = false;
        foreach ($this->owner->ShoppingCartPositions() as $position) {
            if ($position->isSubscription()) {
                $hasSubscriptions = true;
                break;
            }
        }
        return $hasSubscriptions;
    }
    
    /**
     * Returns the positions with subscription.
     * 
     * @return ArrayList
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function PositionsWithSubscription()
    {
        $positionsWithSubscription = ArrayList::create();
        foreach ($this->owner->ShoppingCartPositions() as $position) {
            if ($position->Product()->IsSubscription) {
                $positionsWithSubscription->push($position);
            }
        }
        return $positionsWithSubscription;
    }
    
    /**
     * Returns the positions with a one time price (includes positions having 
     * both, a one time price and consequential costs).
     * 
     * @return ArrayList
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.12.2018
     */
    public function PositionsWithOneTimePrice()
    {
        $positionsWithOneTimePrice = ArrayList::create();
        foreach ($this->owner->ShoppingCartPositions() as $position) {
            if (!$position->Product()->IsSubscription
             || ($position->Product()->IsSubscription
              && $position->Product()->HasConsequentialCosts
              && empty($position->Product()->BillingPeriod))
            ) {
                $positionsWithOneTimePrice->push($position);
            }
        }
        return $positionsWithOneTimePrice;
    }
    
    /**
     * Returns the subscription positions grouped by billing period.
     * 
     * @return ArrayList
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 27.11.2018
     */
    public function getBillingPeriods()
    {
        $billingPeriodsArray = [];
        $billingPeriodsList  = ArrayList::create();
        $taxRates            = $this->owner->getBillingPeriodTaxRates();
        foreach ($this->owner->PositionsWithSubscription() as $position) {
            /* @var $position \SilverCart\Model\Order\ShoppingCartPosition */
            $product = $position->Product();
            if ($product->HasConsequentialCosts
             && empty($product->BillingPeriod)
            ) {
                $amount = $product->getPriceConsequentialCosts()->getAmount() * $position->Quantity;
                $billingPeriod = $product->BillingPeriodConsequentialCosts;
                $billingPeriodNice = $product->BillingPeriodConsequentialCostsNice;
            } else {
                $amount = $product->getPrice()->getAmount() * $position->Quantity;
                $billingPeriod = $product->BillingPeriod;
                $billingPeriodNice = $product->BillingPeriodNice;
            }
            $position->extend('updatePriceAmountForBillingPeriods', $amount);
            if (!array_key_exists($billingPeriod, $billingPeriodsArray)) {
                $billingPeriodsArray[$billingPeriod] = [
                    'BillingPeriod'     => $billingPeriod,
                    'BillingPeriodNice' => $billingPeriodNice,
                    'AmountTotal'       => 0,
                    'QuantityTotal'     => 0,
                    'Positions'         => ArrayList::create(),
                ];
            }
            $billingPeriodsArray[$billingPeriod]['Positions']->push($position);
            $billingPeriodsArray[$billingPeriod]['AmountTotal'] += $amount;
            $billingPeriodsArray[$billingPeriod]['QuantityTotal'] += $position->Quantity;
        }
        foreach ($billingPeriodsArray as $billingPeriod) {
            $taxTotal                = 0;
            $taxRatesByBillingPeriod = ArrayList::create();
            if (array_key_exists($billingPeriod['BillingPeriod'], $taxRates)) {
                $taxRatesByBillingPeriod = $taxRates[$billingPeriod['BillingPeriod']];
            }
            foreach ($taxRatesByBillingPeriod as $taxRate) {
                $taxTotal += $taxRate->Amount->getAmount();
            }
            $amountTotal = DBMoney::create();
            $amountTotal->setAmount($billingPeriod['AmountTotal']);
            $amountTotalWithTax = $amountTotal;
            if (Config::PriceType() === 'net') {
                $amountTotalWithTax = DBMoney::create();
                $amountTotalWithTax->setAmount($amountTotal->getAmount() + $taxTotal);
            }
            $billingPeriodsList->push(ArrayData::create([
                'BillingPeriod'      => $billingPeriod['BillingPeriod'],
                'BillingPeriodNice'  => $billingPeriod['BillingPeriodNice'],
                'AmountTotal'        => $amountTotal,
                'AmountTotalWithTax' => $amountTotalWithTax,
                'QuantityTotal'      => $billingPeriod['QuantityTotal'],
                'Positions'          => $billingPeriod['Positions'],
                'TaxRates'           => $taxRatesByBillingPeriod,
                'ShoppingCart'       => $this->owner,
            ]));
            
        }
        return $billingPeriodsList;
    }
    
    /**
     * Returns the given $billingPeriod context label with the given $key.
     * 
     * @param string $billingPeriod Billing period
     * @param string $key           i18n key
     * 
     * @return string
     */
    public function BillingPeriodLabel(string $billingPeriod, string $key) : string
    {
        return _t(self::class . ".{$billingPeriod}{$key}", "{$key} {$billingPeriod}");
    }
    
    /**
     * Returns the tax rates for subscriptions grouped by billing period.
     * 
     * @return array
     */
    public function getBillingPeriodTaxRates()
    {
        $taxesByBillingPeriod = [];
        foreach ($this->owner->PositionsWithSubscription() as $position) {
            $product = $position->Product();
            if ($product->HasConsequentialCosts
             && empty($product->BillingPeriod)
            ) {
                $amount        = $position->getTaxAmountConsequentialCosts();
                $billingPeriod = $product->BillingPeriodConsequentialCosts;
            } else {
                $amount        = $position->getTaxAmount();
                $billingPeriod = $product->BillingPeriod;
            }
            if (!array_key_exists($billingPeriod, $taxesByBillingPeriod)) {
                $taxesByBillingPeriod[$billingPeriod] = ArrayList::create();
            }
            $taxRate         = $position->Product()->getTaxRate();
            $originalTaxRate = $position->Product()->getTaxRate(true);
            if (!$taxesByBillingPeriod[$billingPeriod]->find('Rate', $taxRate)) {
                $taxesByBillingPeriod[$billingPeriod]->push(ArrayData::create([
                    'Rate'         => $taxRate,
                    'OriginalRate' => $originalTaxRate,
                    'AmountRaw'    => 0.0,
                ]));
            }
            $taxSection             = $taxesByBillingPeriod[$billingPeriod]->find('Rate', $taxRate);
            $taxSection->AmountRaw += $amount;
        }
        foreach ($taxesByBillingPeriod as $taxes) {
            foreach ($taxes as $tax) {
                $taxObj = DBMoney::create();
                $taxObj->setAmount($tax->AmountRaw);
                $tax->Amount = $taxObj;
            }
        }
        return $taxesByBillingPeriod;
    }
}