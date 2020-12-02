<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Admin\Model\Config;
use SilverCart\Dev\Tools;
use SilverCart\Model\Customer\Customer;
use SilverCart\Model\Order\ShoppingCart;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Model\Order\ShoppingCartPositionNotice;
use SilverCart\Model\Pages\CartPageController;
use SilverCart\Model\Pages\CheckoutStepController;
use SilverCart\Model\Product\Product;
use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverCart\Voucher\Model\Voucher;
use SilverCart\Voucher\View\VoucherPrice;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\Security\Member;

/**
 * Extension for the SilverCart AbsoluteRebateVoucher.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions\Model\Vouchers
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 16.09.2020
 * @copyright 2020 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property Voucher\RelativeRebateVoucher $owner Owner
 */
class RelativeRebateVoucherExtension extends DataExtension
{
    /**
     * DB attributes
     *
     * @var array
     */
    private static $db = [
        'PeriodCount'                   => 'Int(1)',
        'CanBeUsedForMultiplePositions' => 'Boolean',
    ];
    /**
     * Subscription Positions. An array containing an ArrayList per Voucher.
     * Each ArrayList contains the affected ShoppingCartPositions.
     *
     * @var ArrayList[]
     */
    protected $subscriptionPositions = [];
    
    /**
     * Updates the CMS fields.
     * 
     * @param FieldList $fields Original fields to update
     * 
     * @return void
     */
    public function updateCMSFields(FieldList $fields) : void
    {
        $periodCountField       = $fields->dataFieldByName('PeriodCount');
        $multiplePositionsField = $fields->dataFieldByName('CanBeUsedForMultiplePositions');
        $fields->insertAfter('IsSubscriptionVoucher', $periodCountField);
        $fields->insertAfter('IsSubscriptionVoucher', $multiplePositionsField);
        $periodCountField->setDescription($this->owner->fieldLabel('PeriodCountDesc'));
        $multiplePositionsField->setDescription($this->owner->fieldLabel('CanBeUsedForMultiplePositionsDesc'));
        if ($periodCountField->Value() === '') {
            $periodCountField->setValue(1);
        }
    }
    
    /**
     * Updates the field labels.
     * 
     * @param array &$labels Original labels to update
     * 
     * @return void
     */
    public function updateFieldLabels(&$labels) : void
    {
        $labels = array_merge($labels, Tools::field_labels_for(self::class));
    }
    
    /**
     * Updates the shopping cart position.
     * 
     * @param VoucherPrice $position     Voucher shopping cart position price data
     * @param ShoppingCart $shoppingCart Shopping cart
     * 
     * @return void
     */
    public function updateShoppingCartPosition(VoucherPrice &$position, ShoppingCart $shoppingCart) : void
    {
        if ($this->owner->IsSubscriptionVoucher
         && (int) $this->owner->valueInPercent > 0
        ) {
            foreach ($this->getSubscriptionPositions() as $subscriptionPosition) {
                /* @var $subscriptionPosition ShoppingCartPosition */
                $product = $subscriptionPosition->Product();
                if (!($product instanceof Product)) {
                    $position = null;
                    return;
                }
                $title       = $this->owner->renderWith(Voucher\RelativeRebateVoucher::class . '_subscription_title');
                $description = $this->getVoucherDescription($subscriptionPosition);
                $notice      = $this->owner->renderWith(Voucher\RelativeRebateVoucher::class . '_subscription', [
                    'Position'           => $position,
                    'VoucherTitle'       => $title,
                    'VoucherDescription' => $description,
                ]);
                if (Controller::curr() instanceof CartPageController
                 || Controller::curr() instanceof CheckoutStepController
                ) {
                    ShoppingCartPositionNotice::addAllowedNotice("subscription-voucher-{$this->owner->ID}-{$subscriptionPosition->ID}", $notice, ShoppingCartPositionNotice::NOTICE_TYPE_SUCCESS);
                    ShoppingCartPositionNotice::setNotice($subscriptionPosition->ID, "subscription-voucher-{$this->owner->ID}-{$subscriptionPosition->ID}");
                    $voucherShoppingCartPosition = VoucherShoppingCartPosition::getVoucherShoppingCartPosition($shoppingCart->ID, $this->owner->ID);
                    $voucherShoppingCartPosition->SubscriptionTitle       = (string) $title;
                    $voucherShoppingCartPosition->SubscriptionDescription = (string) $description;
                    $voucherShoppingCartPosition->SubscriptionPositionID  = $subscriptionPosition->ID;
                    $voucherShoppingCartPosition->write();
                }   
            }
            $position = null;
        }
    }
    
    /**
     * Returns the voucher description respecting the $subscriptionPosition context.
     * 
     * @param ShoppingCartPosition $subscriptionPosition Subscription shopping cart position
     * 
     * @return DBHTMLText
     */
    public function getVoucherDescription(ShoppingCartPosition $subscriptionPosition) : DBHTMLText
    {
        $product      = $subscriptionPosition->Product();
        $discountLine = '';
        if ($this->owner->PeriodCount > 0) {
            $billingPeriod = ucfirst($product->BillingPeriod);
            $discountLine  = _t("SilverCart.DiscountRelative{$billingPeriod}", "The first billing period is discounted by {amount}%.|The first {count} billing periods are discounted by {amount}%.", ['count' => $this->owner->PeriodCount, 'amount' => $this->owner->valueInPercent]);
        }
        $originalPrice = $subscriptionPosition->getPrice();
        $description   = $this->owner->renderWith(Voucher\RelativeRebateVoucher::class . '_subscription_description', [
            'SubscriptionProduct' => $product,
            'DiscountInfo'        => $discountLine,
            'PriceOriginal'       => $originalPrice,
            'PriceDiscounted'     => DBMoney::create()->setAmount($originalPrice->getAmount() - ($originalPrice->getAmount() * ((int) $this->owner->valueInPercent / 100)))->setCurrency($originalPrice->getCurrency()),
        ]);
        return $description;
    }
    
    /**
     * Returns the list of subscription positions.
     * 
     * @return ArrayList
     */
    public function getSubscriptionPositions() : ArrayList
    {
        if (array_key_exists($this->owner->ID, $this->subscriptionPositions)) {
            return $this->subscriptionPositions[$this->owner->ID];
        }
        $positions = ArrayList::create();
        if ($this->owner->CanBeUsedForMultiplePositions) {
            $customer = Customer::currentUser();
            if ($customer instanceof Member) {
                $shoppingCart = $customer->getCart();
                /* @var $shoppingCart \SilverCart\Model\Order\ShoppingCart */
                $voucherPosition = VoucherShoppingCartPosition::getVoucherShoppingCartPosition($shoppingCart->ID, $this->owner->ID);
                if ($voucherPosition instanceof VoucherShoppingCartPosition) {
                    $priceType            = ucfirst(strtolower(Config::Pricetype()));
                    $subscriptionProducts = $this->owner->RestrictToProducts()->filter([
                        'IsSubscription'                      => true,
                        "Price{$priceType}Amount:GreaterThan" => 0,
                    ]);
                    if ($subscriptionProducts->exists()) {
                        $productIDs       = $subscriptionProducts->map('ID', 'ID')->toArray();
                        $matchingProducts = $shoppingCart->ShoppingCartPositions()->filter('ProductID', $productIDs);
                        if ($matchingProducts->exists()) {
                            $positions->merge($matchingProducts);
                        }
                    } else {
                        foreach ($shoppingCart->ShoppingCartPositions() as $shoppingCartPosition) {
                            /* @var $shoppingCartPosition ShoppingCartPosition */ 
                            if ($shoppingCartPosition->isSubscription()
                             && $shoppingCartPosition->Product()->getPrice()->getAmount() > 0
                            ) {
                                $positions->push($shoppingCartPosition);
                            }
                        }
                    }
                }
            }
        } else {
            $subscriptionPosition = $this->owner->getSubscriptionPosition();
            if ($subscriptionPosition instanceof ShoppingCartPosition) {
                $positions->push($subscriptionPosition);
            }
        }
        $this->subscriptionPositions[$this->owner->ID] = $positions;
        return $this->subscriptionPositions[$this->owner->ID];
    }
}