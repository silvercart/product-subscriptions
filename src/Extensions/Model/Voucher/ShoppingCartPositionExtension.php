<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBMoney;

/**
 * Extension for the SilverCart ShoppingCartPosition.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions\Model\Vouchers
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 11.05.2021
 * @copyright 2021 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property \SilverCart\Model\Order\ShoppingCartPosition $owner Owner
 */
class ShoppingCartPositionExtension extends DataExtension
{
    /**
     * Has one relations.
     *
     * @var array
     */
    private static $belongs_to = [
        'SubscriptionVoucherPosition' => VoucherShoppingCartPosition::class,
    ];
    
    /**
     * Returns whether this position is a fully discounted subscription (without
     * period count limitation).
     * 
     * @return bool
     */
    public function IsDiscountedSubscription() : bool
    {
        $is       = false;
        $position = $this->owner->SubscriptionVoucherPosition();
        if ($position->exists()
         && $position->Voucher()->exists()
         && (int) $position->Voucher()->PeriodCount === 0
        ) {
            $is = true;
        }
        return $is;
    }
    
    /**
     * Returns whether this position is a temporarily discounted subscription 
     * (with period count limitation).
     * 
     * @return bool
     */
    public function IsTemporarilyDiscountedSubscription() : bool
    {
        $is       = false;
        $position = $this->owner->SubscriptionVoucherPosition();
        if ($position->exists()
         && $position->Voucher()->exists()
         && (int) $position->Voucher()->PeriodCount > 0
        ) {
            $is = true;
        }
        return $is;
    }
    
    /**
     * Returns the original subscription price.
     * 
     * @return DBMoney
     */
    public function getSubscriptionPriceOriginal() : DBMoney
    {
        return $this->owner->Product()->getPrice();
    }
    
    /**
     * Returns the discounted subscription price.
     * 
     * @return DBMoney
     */
    public function getSubscriptionPriceDiscounted() : DBMoney
    {
        $voucher       = $this->owner->SubscriptionVoucherPosition()->Voucher();
        $originalPrice = $this->getSubscriptionPriceOriginal();
        return DBMoney::create()->setAmount($originalPrice->getAmount() - ($originalPrice->getAmount() * ((int) $voucher->valueInPercent / 100)))->setCurrency($originalPrice->getCurrency());
    }
    
    /**
     * Updates the billing period context price to display.
     * 
     * @param DBMoney $price Original price
     * 
     * @return void
     */
    public function updateDisplayContextBillingPeriodPrice(DBMoney $price) : void
    {
        if ($this->owner->IsDiscountedSubscription()) {
            $price->setAmount($this->owner->getSubscriptionPriceDiscounted()->getAmount());
        }
    }
    
    /**
     * Updates the price.
     * 
     * @param DBMoney $priceObj         Original price
     * @param bool    $forSingleProduct For single product or total?
     * 
     * @return void
     */
    public function updatePrice(DBMoney $priceObj, bool $forSingleProduct) : void
    {
        if ($this->owner->IsDiscountedSubscription()) {
            $quantity = $forSingleProduct ? 1 : $this->owner->Quantity;
            $priceObj->setAmount($this->owner->getSubscriptionPriceDiscounted()->getAmount() * $quantity);
        } elseif ($this->IsTemporarilyDiscountedSubscription()) {
            $quantity = $forSingleProduct ? 1 : $this->owner->Quantity;
            $priceObj->setAmount($this->owner->getSubscriptionPriceDiscounted()->getAmount() * $quantity);
        }
    }
    
    /**
     * Updates the consequential costs.
     * 
     * @param DBMoney $priceObj         Original price
     * @param bool    $forSingleProduct For single product?
     * @param string  $priceType        Price type
     */
    public function updatePriceConsequentialCosts(DBMoney $priceObj, bool $forSingleProduct, string $priceType)
    {
        if ($this->IsTemporarilyDiscountedSubscription()) {
            $priceObj->setAmount($this->getSubscriptionPriceOriginal()->getAmount());
        }
    }
    
    /**
     * Updates whether this position has consequential costs.
     * 
     * @param bool &$has Has?
     * 
     * @return void
     */
    public function updateHasConsequentialCosts(bool &$has) : void
    {
        if ($this->IsTemporarilyDiscountedSubscription()) {
            $has = true;
        }
    }
    
    /**
     * Updates the subscription duration value.
     * 
     * @param int &$value Original value
     * 
     * @return void
     */
    public function updateSubscriptionDurationValue(int &$value) : void
    {
        if ($this->IsTemporarilyDiscountedSubscription()) {
            $value = (int) $this->owner->SubscriptionVoucherPosition()->Voucher()->PeriodCount;
        }
    }
}