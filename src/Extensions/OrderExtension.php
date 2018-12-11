<?php

namespace SilverCart\Subscriptions\Extensions;

use ArrayData;
use ArrayList;
use DataExtension;
use Money;
use SilvercartConfig;

/**
 * Extension for SilverCart Order.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 10.12.2018
 * @copyright 2018 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class OrderExtension extends DataExtension
{
    /**
     * Called before a single shopping cart position is converted into / saved as
     * a order position.
     * 
     * @param \SilvercartShoppingCartPosition &$shoppingCartPosition Shopping cart position
     * @param \SilvercartOrderPosition        &$orderPosition        Order position
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.12.2018
     */
    public function onBeforeConvertSingleShoppingCartPositionToOrderPosition(&$shoppingCartPosition, &$orderPosition)
    {
        $orderPosition->IsSubscription = $shoppingCartPosition->isSubscription();
        if ($shoppingCartPosition->isSubscription()) {
            if ($shoppingCartPosition->HasConsequentialCosts()) {
                $orderPosition->PriceConsequentialCosts->setAmount($shoppingCartPosition->getPriceConsequentialCosts(true)->getAmount());
                $orderPosition->PriceTotalConsequentialCosts->setAmount($shoppingCartPosition->getPriceConsequentialCosts()->getAmount());
                $orderPosition->TaxConsequentialCosts      = $shoppingCartPosition->getTaxAmountConsequentialCosts(true);
                $orderPosition->TaxTotalConsequentialCosts = $shoppingCartPosition->getTaxAmountConsequentialCosts();
            } else {
                $orderPosition->Price->setAmount(0);
                $orderPosition->PriceTotal->setAmount(0);
                $orderPosition->Tax      = 0;
                $orderPosition->TaxTotal = 0;
                $orderPosition->PriceConsequentialCosts->setAmount($shoppingCartPosition->getPrice(true)->getAmount());
                $orderPosition->PriceTotalConsequentialCosts->setAmount($shoppingCartPosition->getPrice()->getAmount());
                $orderPosition->TaxConsequentialCosts      = $shoppingCartPosition->getTaxAmount(true);
                $orderPosition->TaxTotalConsequentialCosts = $shoppingCartPosition->getTaxAmount();
            }
            $product                                   = $shoppingCartPosition->SilvercartProduct();
            $orderPosition->BillingPeriod              = $product->BillingPeriod;
            $orderPosition->SubscriptionDurationValue  = $product->SubscriptionDurationValue;
            $orderPosition->SubscriptionDurationPeriod = $product->SubscriptionDurationPeriod;
        }
    }
    
    /**
     * Returns whether the order contains products with subscriptions.
     * 
     * @return boolean
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function hasSubscriptions()
    {
        $hasSubscriptions = false;
        foreach ($this->owner->SilvercartOrderPositions() as $position) {
            if ($position->IsSubscription) {
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
     * @since 10.12.2018
     */
    public function PositionsWithSubscription()
    {
        $positionsWithSubscription = ArrayList::create();
        foreach ($this->owner->SilvercartOrderPositions() as $position) {
            if ($position->IsSubscription) {
                $positionsWithSubscription->push($position);
            }
        }
        return $positionsWithSubscription;
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
            if (!array_key_exists($position->BillingPeriod, $billingPeriodsArray)) {
                $billingPeriodsArray[$position->BillingPeriod] = [
                    'BillingPeriod'     => $position->BillingPeriod,
                    'BillingPeriodNice' => $position->getBillingPeriodNice(),
                    'AmountTotal'       => 0,
                    'QuantityTotal'     => 0,
                    'Positions'         => ArrayList::create(),
                ];
            }
            $amount = $position->PriceTotalConsequentialCosts->getAmount();
            $billingPeriodsArray[$position->BillingPeriod]['Positions']->push($position);
            $billingPeriodsArray[$position->BillingPeriod]['AmountTotal']   += $amount;
            $billingPeriodsArray[$position->BillingPeriod]['QuantityTotal'] += $position->Quantity;
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
            $amountTotal = Money::create();
            $amountTotal->setAmount($billingPeriod['AmountTotal']);
            $amountTotalWithTax = $amountTotal;
            if (SilvercartConfig::PriceType() === 'net') {
                $amountTotalWithTax = Money::create();
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
            ]));
            
        }
        return $billingPeriodsList;
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
            
            if (!array_key_exists($position->BillingPeriod, $taxesByBillingPeriod)) {
                $taxesByBillingPeriod[$position->BillingPeriod] = ArrayList::create();
            }
            
            $taxRate = $position->TaxRate;

            if (!$taxesByBillingPeriod[$position->BillingPeriod]->find('Rate', $taxRate)) {
                $taxesByBillingPeriod[$position->BillingPeriod]->push(ArrayData::create([
                    'Rate'      => $taxRate,
                    'AmountRaw' => 0.0,
                ]));
            }
            $taxSection = $taxesByBillingPeriod[$position->BillingPeriod]->find('Rate', $taxRate);
            $taxSection->AmountRaw += $position->TaxTotalConsequentialCosts;
        }
        foreach ($taxesByBillingPeriod as $taxes) {
            foreach ($taxes as $tax) {
                $taxObj = Money::create();
                $taxObj->setAmount($tax->AmountRaw);
                $tax->Amount = $taxObj;
            }
        }
        return $taxesByBillingPeriod;
    }
}