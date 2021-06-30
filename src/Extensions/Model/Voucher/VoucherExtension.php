<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Admin\Model\Config;
use SilverCart\Model\Customer\Customer;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Model\Order\ShoppingCartPositionNotice;
use SilverCart\Model\Product\Product;
use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Member;

/**
 * Extension for the SilverCart Voucher.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions\Model\Vouchers
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 16.09.2020
 * @copyright 2020 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property \SilverCart\Voucher\Model\Voucher $owner Owner
 */
class VoucherExtension extends DataExtension
{
    /**
     * DB attributes
     *
     * @var array
     */
    private static $db = [
        'IsSubscriptionVoucher' => DBBoolean::class,
    ];
    /**
     * Subscription Position
     *
     * @var ShoppingCartPosition[]
     */
    protected $subscriptionPosition = [];
    /**
     * Subscription Product
     *
     * @var Product[]
     */
    protected $subscriptionProduct = [];

    /**
     * Updates the CMS fields.
     * 
     * @param FieldList $fields Original fields to update
     * 
     * @return void
     */
    public function updateCMSFields(FieldList $fields) : void
    {
        $fields->dataFieldByName('IsSubscriptionVoucher')->setDescription($this->owner->fieldLabel('IsSubscriptionVoucherDesc'));
    }
    
    public function updateFieldLabels(&$labels)
    {
        $labels = array_merge($labels, [
            'CantBeCombinedWith' => _t(self::class . '.CantBeCombinedWith', 'This voucher cannot be combined with the voucher which is already in your cart.'),
        ]);
    }
    
    /**
     * Updates the shopping cart items validity check.
     * 
     * @param bool    &$isValid              Result of the original check
     * @param SS_List $shoppingCartPositions Shopping cart positions to check
     * @param string  &$message              Alternative message to display in cart
     * 
     * @return void
     */
    public function updateIsValidForShoppingCartItems(bool &$isValid, SS_List $shoppingCartPositions, string &$message = null) : void
    {
        if ($isValid) {
            if ($this->owner->IsSubscriptionVoucher) {
                $isValid = false;
                foreach ($shoppingCartPositions as $position) {
                    /* @var $position ShoppingCartPosition */
                    if ($position->isSubscription()
                     && $position->Product()->getPrice()->getAmount() > 0
                    ) {
                        $isValid = true;
                        break;
                    }
                }
                if ($isValid) {
                    $customer = Customer::currentUser();
                    if ($customer instanceof Member) {
                        $cart = $customer->getCart();
                        if ($cart instanceof \SilverCart\Model\Order\ShoppingCart) {
                            foreach ($cart->VoucherPositions() as $voucherPosition) {
                                /* @var $voucherPosition VoucherShoppingCartPosition */
                                if ($voucherPosition->SubscriptionPosition()->exists()
                                 && (int) $voucherPosition->Voucher()->ID !== (int) $this->owner->ID
                                ) {
                                    $isValid = false;
                                    $message = $this->owner->fieldLabel('CantBeCombinedWith');
                                    break;
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($shoppingCartPositions as $position) {
                    /* @var $position ShoppingCartPosition */ 
                    if ($position->isSubscription()) {
                        $isValid = false;
                    } else {
                        $isValid = true;
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Removes subscription voucher notices.
     * 
     * @param Member $member Member
     * 
     * @return void
     */
    public function onBeforeRemoveFromShoppingCart(Member $member) : void
    {
        $subscriptionPosition = $this->getSubscriptionPosition();
        if ($subscriptionPosition === null) {
            return;
        }
        ShoppingCartPositionNotice::unsetNotice($subscriptionPosition->ID, "subscription-voucher-{$this->owner->ID}");
    }
    
    /**
     * Returns the first matching subscription position.
     * 
     * @return ShoppingCartPosition|null
     */
    public function getSubscriptionPosition() : ?ShoppingCartPosition
    {
        if (array_key_exists($this->owner->ID, $this->subscriptionPosition)) {
            return $this->subscriptionPosition[$this->owner->ID];
        }
        $position = null;
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
                    $productIDs = $shoppingCart->ShoppingCartPositions()->map('ID', 'ProductID')->toArray();
                    if (!empty($productIDs)) {
                        $matchingProduct = $subscriptionProducts->filter('ID', $productIDs)->first();
                        if ($matchingProduct instanceof Product) {
                            $position = $shoppingCart->ShoppingCartPositions()->filter('ProductID', $matchingProduct->ID)->first();
                        }
                    }
                }
                if ($position === null) {
                    foreach ($shoppingCart->ShoppingCartPositions() as $shoppingCartPosition) {
                        /* @var $shoppingCartPosition ShoppingCartPosition */ 
                        if ($shoppingCartPosition->isSubscription()
                         && $shoppingCartPosition->Product()->getPrice()->getAmount() > 0
                        ) {
                            $position = $shoppingCartPosition;
                            break;
                        }
                    }
                }
            }
        }
        $this->subscriptionPosition[$this->owner->ID] = $position;
        return $this->subscriptionPosition[$this->owner->ID];
    }
    
    /**
     * Returns the product relation of the fist matching subscription position.
     * 
     * @return Product|null
     */
    public function getSubscriptionProduct() : ?Product
    {
        if (array_key_exists($this->owner->ID, $this->subscriptionProduct)) {
            return $this->subscriptionProduct[$this->owner->ID];
        }
        $product  = null;
        $position = $this->getSubscriptionPosition();
        if ($position instanceof ShoppingCartPosition) {
            $product = $position->Product();
        }
        $this->subscriptionProduct[$this->owner->ID] = $product;
        return $this->subscriptionProduct[$this->owner->ID];
    }
}