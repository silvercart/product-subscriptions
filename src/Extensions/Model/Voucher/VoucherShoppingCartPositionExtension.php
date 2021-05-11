<?php

namespace SilverCart\Subscriptions\Extensions\Model\Vouchers;

use SilverCart\Model\Order\ShoppingCartPosition;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for the SilverCart Voucher ShoppingCartPosition.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions\Model\Vouchers
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 16.09.2020
 * @copyright 2020 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property \SilverCart\Voucher\Model\ShoppingCartPosition $owner Owner
 */
class VoucherShoppingCartPositionExtension extends DataExtension
{
    /**
     * DB attributes.
     *
     * @var array
     */
    private static $db = [
        'SubscriptionTitle'        => 'Varchar',
        'SubscriptionDescription'  => 'Text',
        'SubscriptionVoucherValue' => 'Float',
    ];
    /**
     * Has one relations.
     *
     * @var array
     */
    private static $has_one = [
        'SubscriptionPosition' => ShoppingCartPosition::class,
    ];
}