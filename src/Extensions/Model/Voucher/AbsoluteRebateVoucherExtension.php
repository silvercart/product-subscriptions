<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Admin\Model\Config;
use SilverCart\Model\Customer\Customer;
use SilverCart\Model\Order\ShoppingCart;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Model\Order\ShoppingCartPositionNotice;
use SilverCart\Model\Pages\CartPageController;
use SilverCart\Model\Pages\CheckoutStepController;
use SilverCart\Model\Pages\Page;
use SilverCart\Model\Product\Product;
use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverCart\Voucher\Model\Voucher;
use SilverCart\Voucher\View\VoucherPrice;
use SilverStripe\Control\Controller;
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
 * @property Voucher\AbsoluteRebateVoucher $owner Owner
 */
class AbsoluteRebateVoucherExtension extends DataExtension
{
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
        if ($this->owner->IsSubscriptionVoucher) {
            $subscriptionPosition = $this->owner->getSubscriptionPosition();
            $product              = $this->owner->getSubscriptionProduct();
            if (!($product instanceof Product)) {
                $position = null;
                return;
            }
            $position->setPriceGrossAmount($this->owner->value->getAmount() * -1);
            $voucherValue = $position->getPrice()->getAmount() * -1;
            if ($product->Price->getAmount() > $voucherValue) {
                $plus = Config::PriceType() === Config::PRICE_TYPE_NET ? ' ' . _t(Page::class . '.EXCLUDING_TAX', 'plus VAT') : '';
            } else {
                $plus = Config::PriceType() === Config::PRICE_TYPE_NET ? ' ' . _t(Page::class . '.EXCLUDING_TAX', 'plus VAT') : '';
            }
            $title = $this->owner->renderWith(Voucher\AbsoluteRebateVoucher::class . '_subscription_title', [
                'Value'    => DBMoney::create()->setAmount($voucherValue)->setCurrency($position->Currency),
                'plus'     => $plus,
            ]);
            $description = $this->getVoucherDescription($subscriptionPosition, $voucherValue);
            $notice = $this->owner->renderWith(Voucher\AbsoluteRebateVoucher::class . '_subscription', [
                'Position'           => $position,
                'VoucherTitle'       => $title,
                'VoucherDescription' => $description,
            ]);
            if (Controller::curr() instanceof CartPageController
             || Controller::curr() instanceof CheckoutStepController
            ) {
                ShoppingCartPositionNotice::addAllowedNotice("subscription-voucher-{$this->owner->ID}", $notice, ShoppingCartPositionNotice::NOTICE_TYPE_SUCCESS);
                ShoppingCartPositionNotice::setNotice($subscriptionPosition->ID, "subscription-voucher-{$this->owner->ID}");
                $position = null;
                $voucherShoppingCartPosition = VoucherShoppingCartPosition::getVoucherShoppingCartPosition($shoppingCart->ID, $this->owner->ID);
                $voucherShoppingCartPosition->SubscriptionTitle        = (string) $title;
                $voucherShoppingCartPosition->SubscriptionDescription  = (string) $description;
                $voucherShoppingCartPosition->SubscriptionPositionID   = $subscriptionPosition->ID;
                $voucherShoppingCartPosition->SubscriptionVoucherValue = $voucherValue;
                $voucherShoppingCartPosition->write();
            }
        }
    }
    
    /**
     * Returns the voucher description respecting the $subscriptionPosition and
     * $voucherPrice context.
     * 
     * @param ShoppingCartPosition $subscriptionPosition Subscription shopping cart position
     * @param float                $voucherValue         Voucher value
     * 
     * @return DBHTMLText
     */
    public function getVoucherDescription(ShoppingCartPosition $subscriptionPosition, float $voucherValue = null) : DBHTMLText
    {
        return $this->getVoucherDescriptionForProduct($subscriptionPosition->Product(), $voucherValue);
    }
    
    /**
     * Returns the voucher description respecting the $subscriptionPosition and
     * $voucherPrice context.
     * 
     * @param Product $product      Subscription product
     * @param float   $voucherValue Voucher value
     * 
     * @return DBHTMLText
     */
    public function getVoucherDescriptionForProduct(Product $product, float $voucherValue = null) : DBHTMLText
    {
        if ($voucherValue === null) {
            $voucherValue = 0;
            $member       = Customer::currentUser();
            if ($member instanceof Member) {
                $voucherShoppingCartPosition = VoucherShoppingCartPosition::getVoucherShoppingCartPosition($member->ShoppingCart()->ID, $this->owner->ID);
                /* @var $voucherPrice VoucherPrice */
                if ($voucherShoppingCartPosition !== null) {
                    $voucherValue = $voucherShoppingCartPosition->SubscriptionVoucherValue;
                }
            }
        }
        $money = DBMoney::create()
                ->setAmount(round($voucherValue, 2))
                ->setCurrency($product->getPrice()->getCurrency());
        $voucherValue = $money->getAmount();
        $periods      = 0;
        $remainder    = 0;
        $firstPeriod  = 0;
        $discounted   = DBMoney::create()->setAmount($product->Price->getAmount())->setCurrency($product->Price->getCurrency());
        if ($product->Price->getAmount() > $voucherValue) {
            $discounted->setAmount($product->Price->getAmount() - $voucherValue);
            $billingPeriod = ucfirst($product->BillingPeriod);
            $discountLine  = _t("SilverCart.Discount{$billingPeriod}First", 'Billing period {count} is discounted to {price}.', ['count' => 1, 'price' => $discounted->Nice()]);
        } else {
            if ($product->Price->getAmount() < $voucherValue) {
                $periods   = floor((int) ($voucherValue * 100) / (int) ($product->Price->getAmount() * 100));
                $remainder = (int) ($voucherValue * 100) - ($periods * (int) ($product->Price->getAmount() * 100));
                if ($remainder > 0) {
                    $firstPeriod = $periods + 1;
                    $discounted->setAmount($product->Price->getAmount() - $remainder);
                }
            }
            $billingPeriod = ucfirst($product->BillingPeriod);
            $discountLine  = _t("SilverCart.Discount{$billingPeriod}", "The first billing period is for free.|The first {count} billing periods are for free.", ['count' => $periods]);
            if ($firstPeriod > 0) {
                $discountLine .= ' ' . _t("SilverCart.Discount{$billingPeriod}First", 'Billing period {count} is discounted to {price}.', ['count' => $firstPeriod, 'price' => $discounted->Nice()]);
            }
        }
        $description = $this->owner->renderWith(Voucher\AbsoluteRebateVoucher::class . '_subscription_description', [
            'SubscriptionProduct' => $product,
            'DiscountInfo'        => $discountLine,
        ]);
        return $description;
    }
    
    /**
     * Updates the remaining amount to 0 if this is a subscription voucher.
     * 
     * @param float &$remainingAmount Original remaining amount to update
     * 
     * @return void
     */
    public function updateRemainingAmount(float &$remainingAmount) : void
    {
        if ($remainingAmount > 0
         && $this->owner->IsSubscriptionVoucher
        ) {
            $remainingAmount = 0;
        }
    }
}