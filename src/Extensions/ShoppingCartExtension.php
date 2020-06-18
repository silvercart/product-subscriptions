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
             && !$position->hasConsequentialCosts()
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
             && !$position->hasConsequentialCosts()
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
              && $position->Product()->HasConsequentialCosts)
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
            $product = $position->Product();
            if (!array_key_exists($product->BillingPeriod, $billingPeriodsArray)) {
                $billingPeriodsArray[$product->BillingPeriod] = [
                    'BillingPeriod'     => $product->BillingPeriod,
                    'BillingPeriodNice' => $product->getBillingPeriodNice(),
                    'AmountTotal'       => 0,
                    'QuantityTotal'     => 0,
                    'Positions'         => ArrayList::create(),
                ];
            }
            if ($product->HasConsequentialCosts) {
                $amount = $product->getPriceConsequentialCosts()->getAmount() * $position->Quantity;
            } else {
                $amount = $product->getPrice()->getAmount() * $position->Quantity;
            }
            $position->setDisplayContextBillingPeriod($product->BillingPeriod);
            $billingPeriodsArray[$product->BillingPeriod]['Positions']->push($position);
            $billingPeriodsArray[$product->BillingPeriod]['AmountTotal'] += $amount;
            $billingPeriodsArray[$product->BillingPeriod]['QuantityTotal'] += $position->Quantity;
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
            if (!array_key_exists($product->BillingPeriod, $taxesByBillingPeriod)) {
                $taxesByBillingPeriod[$product->BillingPeriod] = ArrayList::create();
            }
            
            
            $taxRate         = $position->Product()->getTaxRate();
            $originalTaxRate = $position->Product()->getTaxRate(true);

            if (!$taxesByBillingPeriod[$product->BillingPeriod]->find('Rate', $taxRate)) {
                $taxesByBillingPeriod[$product->BillingPeriod]->push(ArrayData::create([
                    'Rate'         => $taxRate,
                    'OriginalRate' => $originalTaxRate,
                    'AmountRaw'    => 0.0,
                ]));
            }
            $taxSection = $taxesByBillingPeriod[$product->BillingPeriod]->find('Rate', $taxRate);
            if ($position->hasConsequentialCosts()) {
                $taxSection->AmountRaw += $position->getTaxAmountConsequentialCosts();
            } else {
                $taxSection->AmountRaw += $position->getTaxAmount();
            }
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