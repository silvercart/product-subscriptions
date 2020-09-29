<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Dev\Tools;
use SilverCart\Model\Order\ShoppingCart;
use SilverCart\Model\Order\ShoppingCartPositionNotice;
use SilverCart\Model\Pages\CartPageController;
use SilverCart\Model\Pages\CheckoutStepController;
use SilverCart\Model\Product\Product;
use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverCart\Voucher\Model\Voucher;
use SilverCart\Voucher\View\VoucherPrice;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBMoney;

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
 * @property \SilverCart\Voucher\Model\AbsoluteRebateVoucher $owner Owner
 */
class RelativeRebateVoucherExtension extends DataExtension
{
    /**
     * DB attributes
     *
     * @var array
     */
    private static $db = [
        'PeriodCount' => 'Int(1)',
    ];
    
    /**
     * Updates the CMS fields.
     * 
     * @param FieldList $fields Original fields to update
     * 
     * @return void
     */
    public function updateCMSFields(FieldList $fields) : void
    {
        $periodCountField = $fields->dataFieldByName('PeriodCount');
        $fields->insertAfter('IsSubscriptionVoucher', $periodCountField);
        $periodCountField->setDescription($this->owner->fieldLabel('PeriodCountDesc'));
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
            $subscriptionPosition = $this->owner->getSubscriptionPosition();
            $product              = $this->owner->getSubscriptionProduct();
            if (!($product instanceof Product)) {
                $position = null;
                return;
            }
            $discountLine = '';
            if ($this->owner->PeriodCount > 0) {
                $billingPeriod = ucfirst($product->BillingPeriod);
                $discountLine  = _t("SilverCart.DiscountRelative{$billingPeriod}", "The first billing period is discounted by {amount}%.|The first {count} billing periods are discounted by {amount}%.", ['count' => $this->owner->PeriodCount, 'amount' => $this->owner->valueInPercent]);
            } else {
                
            }
            $originalPrice = $subscriptionPosition->getPrice();
            $title         = $this->owner->renderWith(Voucher\RelativeRebateVoucher::class . '_subscription_title');
            $description   = $this->owner->renderWith(Voucher\RelativeRebateVoucher::class . '_subscription_description', [
                'DiscountInfo'    => $discountLine,
                'PriceOriginal'   => $originalPrice,
                'PriceDiscounted' => DBMoney::create()->setAmount($originalPrice->getAmount() - ($originalPrice->getAmount() * ((int) $this->owner->valueInPercent / 100)))->setCurrency($position->Currency),
            ]);
            $notice = $this->owner->renderWith(Voucher\RelativeRebateVoucher::class . '_subscription', [
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
                $voucherShoppingCartPosition->SubscriptionTitle       = (string) $title;
                $voucherShoppingCartPosition->SubscriptionDescription = (string) $description;
                $voucherShoppingCartPosition->SubscriptionPositionID  = $subscriptionPosition->ID;
                $voucherShoppingCartPosition->write();
            }
        }
    }
}