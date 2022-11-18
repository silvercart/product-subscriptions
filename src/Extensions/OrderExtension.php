<?php

namespace SilverCart\Subscriptions\Extensions;

use SilverCart\Admin\Model\Config;
use SilverCart\Model\Order\OrderPosition;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\View\ArrayData;

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
     * @param ShoppingCartPosition &$shoppingCartPosition Shopping cart position
     * @param OrderPosition        &$orderPosition        Order position
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.12.2018
     */
    public function onBeforeConvertSingleShoppingCartPositionToOrderPosition(ShoppingCartPosition &$shoppingCartPosition, OrderPosition &$orderPosition) : void
    {
        $orderPosition->IsSubscription = $shoppingCartPosition->isSubscription();
        if ($shoppingCartPosition->isSubscription()) {
            $product                                   = $shoppingCartPosition->Product();
            $orderPosition->BillingPeriod              = $product->BillingPeriod;
            $orderPosition->SubscriptionDurationValue  = $shoppingCartPosition->getSubscriptionDurationValue();
            $orderPosition->SubscriptionDurationPeriod = $shoppingCartPosition->getSubscriptionDurationPeriod();
            if ($shoppingCartPosition->HasConsequentialCosts()) {
                $orderPosition->PriceConsequentialCosts->setAmount($shoppingCartPosition->getPriceConsequentialCosts(true)->getAmount());
                $orderPosition->PriceTotalConsequentialCosts->setAmount($shoppingCartPosition->getPriceConsequentialCosts()->getAmount());
                $orderPosition->TaxConsequentialCosts                        = $shoppingCartPosition->getTaxAmountConsequentialCosts(true);
                $orderPosition->TaxTotalConsequentialCosts                   = $shoppingCartPosition->getTaxAmountConsequentialCosts();
                $orderPosition->BillingPeriodConsequentialCosts              = $product->BillingPeriodConsequentialCosts;
                $orderPosition->SubscriptionDurationValueConsequentialCosts  = $product->SubscriptionDurationValueConsequentialCosts;
                $orderPosition->SubscriptionDurationPeriodConsequentialCosts = $product->SubscriptionDurationPeriodConsequentialCosts;
            }
        }
    }
    
    /**
     * Checks the converted shopping cart position for a subscription voucher.
     * 
     * @param ShoppingCartPosition $shoppingCartPosition Shopping cart position
     * @param OrderPosition        $orderPosition        Converted order position
     * 
     * @return void
     */
    public function onAfterConvertSingleShoppingCartPositionToOrderPosition(ShoppingCartPosition &$shoppingCartPosition, OrderPosition &$orderPosition) : void
    {
        $voucherPositions = VoucherShoppingCartPosition::get()->filter('SubscriptionPositionID', $shoppingCartPosition->ID);
        if ($voucherPositions->exists()) {
            foreach ($voucherPositions as $voucherPosition) {
                /* @var $voucherPosition VoucherShoppingCartPosition */
                $this->convertVoucherPositionToOrderPosition($voucherPosition);
            }
        }
    }
    
    /**
     * Converts the subscription voucher position to an order position.
     * 
     * @param VoucherShoppingCartPosition $voucherPosition Voucher position to convert data for
     * 
     * @return void
     */
    public function convertVoucherPositionToOrderPosition(VoucherShoppingCartPosition $voucherPosition) : void
    {
        $voucher = $voucherPosition->Voucher();
        if ($voucher->exists()
         && $voucher->IsSubscriptionVoucher
        ) {
            $currency = Config::DefaultCurrency();
            if ($voucher->hasMethod('getSubscriptionPositions')) {
                $subscriptionPositions = $voucher->getSubscriptionPositions();
            } else {
                $subscriptionPositions = ArrayList::create();
                $subscriptionPositions->push($voucher->getSubscriptionPosition());
            }
            foreach ($subscriptionPositions as $subscriptionPosition) {
                if ($subscriptionPosition !== null) {
                    $currency = $subscriptionPosition->getPrice()->getCurrency();
                }
                $orderPosition = OrderPosition::create();
                $orderPosition->ProductNumber         = $voucher->ProductNumber;
                $orderPosition->VoucherCode           = $voucher->code;
                $orderPosition->IsSubscriptionVoucher = true;
                $orderPosition->Title                 = $voucherPosition->SubscriptionTitle;
                $orderPosition->ProductDescription    = (string) $voucher->getVoucherDescription($subscriptionPosition);//$voucherPosition->SubscriptionDescription;
                $orderPosition->TaxRate               = $voucher->Tax()->Rate;
                $orderPosition->Price->setAmount(0);
                $orderPosition->Price->setCurrency($currency);
                $orderPosition->PriceTotal->setAmount(0);
                $orderPosition->PriceTotal->setCurrency($currency);
                $orderPosition->Tax                   = 0;
                $orderPosition->TaxTotal              = 0;
                $orderPosition->Quantity              = 1;
                $orderPosition->OrderID               = $this->owner->ID;
                $subscriptionOrderPosition = OrderPosition::get()
                        ->filter([
                            'OrderID'       => $this->owner->ID,
                            'Quantity'      => $subscriptionPosition->Quantity,
                            'ProductNumber' => $subscriptionPosition->getProductNumberShop(),
                        ])
                        ->first();
                if ($subscriptionOrderPosition !== null) {
                    $orderPosition->SubscriptionPositionID = $subscriptionOrderPosition->ID;
                }
                $orderPosition->write();
                unset($orderPosition);
            }
        }
    }
    
    /**
     * Returns whether the order contains products with subscriptions.
     * 
     * @return bool
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function hasSubscriptions() : bool
    {
        $hasSubscriptions = false;
        foreach ($this->owner->OrderPositions() as $position) {
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
    public function PositionsWithSubscription() : ArrayList
    {
        $positionsWithSubscription = ArrayList::create();
        foreach ($this->owner->OrderPositions() as $position) {
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
    public function getBillingPeriods() : ArrayList
    {
        $billingPeriodsArray = [];
        $billingPeriodsList  = ArrayList::create();
        $taxRates            = $this->owner->getBillingPeriodTaxRates();
        foreach ($this->owner->PositionsWithSubscription() as $position) {
            if (empty($position->BillingPeriod)) {
                $amount            = $position->PriceTotalConsequentialCosts->getAmount();
                $billingPeriod     = $position->BillingPeriodConsequentialCosts;
                $billingPeriodNice = $position->BillingPeriodConsequentialCostsNice;
            } else {
                $amount            = $position->PriceTotal->getAmount();
                $billingPeriod     = $position->BillingPeriod;
                $billingPeriodNice = $position->BillingPeriodNice;
            }
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
            $billingPeriodsArray[$billingPeriod]['AmountTotal']   += $amount;
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
            ]));
            
        }
        return $billingPeriodsList;
    }
    
    /**
     * Returns the tax rates for subscriptions grouped by billing period.
     * 
     * @return array
     */
    public function getBillingPeriodTaxRates() : array
    {
        $taxesByBillingPeriod = [];
        foreach ($this->owner->PositionsWithSubscription() as $position) {
            if (empty($position->BillingPeriod)) {
                $amount        = $position->TaxTotalConsequentialCosts;
                $billingPeriod = $position->BillingPeriodConsequentialCosts;
            } else {
                $amount        = $position->TaxTotal;
                $billingPeriod = $position->BillingPeriod;
            }
            if (!array_key_exists($billingPeriod, $taxesByBillingPeriod)) {
                $taxesByBillingPeriod[$billingPeriod] = ArrayList::create();
            }
            $taxRate = $position->TaxRate;
            if (!$taxesByBillingPeriod[$billingPeriod]->find('Rate', $taxRate)) {
                $taxesByBillingPeriod[$billingPeriod]->push(ArrayData::create([
                    'Rate'      => $taxRate,
                    'AmountRaw' => 0.0,
                ]));
            }
            $taxSection = $taxesByBillingPeriod[$billingPeriod]->find('Rate', $taxRate);
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