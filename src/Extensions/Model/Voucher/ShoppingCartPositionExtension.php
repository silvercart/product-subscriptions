<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverCart\Voucher\Model\Voucher\RelativeRebateVoucher;
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
     * Set to true to skip this extensions @see $this->updatePrice() when calling
     * @see $this->owner->getPrice().
     * 
     * @var bool
     */
    protected static $skipPriceUpdate = false;
    /**
     * List of already requested original prices.
     * 
     * @var DBMoney[]
     */
    protected $originalPrices = [];

    /**
     * Returns whether this position is a fully discounted subscription (without
     * period count limitation).
     * 
     * @return bool
     */
    public function IsDiscountedSubscription() : bool
    {
        $is = false;
        if ($this->owner->isSubscription()
         && $this->owner->getOriginalPrice()->getAmount() > 0
        ) {
            $position = $this->owner->SubscriptionVoucherPosition();
            if ($position->exists()
             && $position->Voucher()->exists()
             && (int) $position->Voucher()->PeriodCount === 0
            ) {
                $is = true;
            }
            $multiPosition = $this->owner->MultipleSubscriptionVoucherPosition();
            if ($multiPosition !== null
             && $multiPosition->exists()
             && $multiPosition->Voucher()->exists()
             && (int) $multiPosition->Voucher()->PeriodCount === 0
            ) {
                $is = true;
            }
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
        $is = false;
        if ($this->owner->isSubscription()
         && $this->owner->getOriginalPrice()->getAmount() > 0
        ) {
            $position = $this->owner->SubscriptionVoucherPosition();
            if ($position->exists()
             && $position->Voucher()->exists()
             && (int) $position->Voucher()->PeriodCount > 0
            ) {
                $is = true;
            }
            $multiPosition = $this->owner->MultipleSubscriptionVoucherPosition();
            if ($multiPosition !== null
             && $multiPosition->exists()
             && $multiPosition->Voucher()->exists()
             && (int) $multiPosition->Voucher()->PeriodCount > 0
            ) {
                $is = true;
            }
            if (!$this->owner->Product()->HasConsequentialCosts) {
                $this->owner->Product()->BillingPeriodConsequentialCosts = $this->owner->Product()->BillingPeriod;
            }
        }
        return $is;
    }

    /**
     * Returns the owners price without calling this extensions updatePrice.
     * 
     * @return DBMoney
     */
    public function getOriginalPrice() : DBMoney
    {
        if (!array_key_exists($this->owner->ID, $this->originalPrices)) {
            self::$skipPriceUpdate = true;
            $this->originalPrices[$this->owner->ID] = $this->owner->getPrice();
            self::$skipPriceUpdate = false;
        }
        return $this->originalPrices[$this->owner->ID];
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
        $voucher       = $this->owner->ContextSubscriptionVoucherPosition()->Voucher();
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
        if (self::$skipPriceUpdate) {
            return;
        }
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
            $value = (int) $this->owner->ContextSubscriptionVoucherPosition()->Voucher()->PeriodCount;
        }
    }
    
    /**
     * Returns the related voucher position with the CanBeUsedForMultiplePositions 
     * setting.
     * 
     * @return VoucherShoppingCartPosition|null
     */
    public function MultipleSubscriptionVoucherPosition() : ?VoucherShoppingCartPosition
    {
        $voucherTable  = RelativeRebateVoucher::config()->table_name;
        $positionTable = VoucherShoppingCartPosition::config()->table_name;
        return VoucherShoppingCartPosition::get()
                ->leftJoin($voucherTable, "{$voucherTable}.ID = {$positionTable}.VoucherID")
                ->filter([
                    'ShoppingCartID' => $this->owner->ShoppingCartID,
                    'CanBeUsedForMultiplePositions' => true,
                ])->first();
    }
    
    /**
     * Returns either @see $this->owner->SubscriptionVoucherPosition() or
     * @see $this->owner->MultipleSubscriptionVoucherPosition().
     * 
     * @return VoucherShoppingCartPosition|null
     */
    public function ContextSubscriptionVoucherPosition() : ?VoucherShoppingCartPosition
    {
        $position = $this->owner->SubscriptionVoucherPosition();
        if (!$position->exists()
         || !$position->Voucher()->exists()
        ) {
            $position = $this->owner->MultipleSubscriptionVoucherPosition();
        }
        return $position;
    }
}