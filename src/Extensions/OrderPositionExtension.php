<?php

namespace SilverCart\Subscriptions\Extensions;

use DataExtension;
use SilvercartTools;
use SilverCart\Subscriptions\Extensions\ProductExtension;

/**
 * Extension for SilverCart OrderPosition.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 10.12.2018
 * @copyright 2018 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class OrderPositionExtension extends DataExtension
{
    /**
     * DB attributes.
     *
     * @var array
     */
    private static $db = [
        'IsSubscription'               => 'Boolean(0)',
        'BillingPeriod'                => 'Enum(",monthly,quarterly,yearly","")',
        'PriceConsequentialCosts'      => 'SilvercartMoney',
        'PriceTotalConsequentialCosts' => 'SilvercartMoney',
        'TaxConsequentialCosts'        => 'Float',
        'TaxTotalConsequentialCosts'   => 'Float',
        'SubscriptionDurationValue'    => 'Int',
        'SubscriptionDurationPeriod'   => 'Enum(",months,years","")',
    ];
    /**
     * Defaults for DB attributes.
     *
     * @var array
     */
    private static $defaults = [
        'IsSubscription' => false,
    ];
    
    /**
     * Updates the field labels.
     * 
     * @param array &$labels Labels to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.12.2018
     */
    public function updateFieldLabels(&$labels)
    {
        $labels = array_merge(
                $labels,
                SilvercartTools::field_labels_for(self::class),
                SilvercartTools::field_labels_for(ProductExtension::class),
                [
                    'BillingPeriodMonthly'             => _t(ProductExtension::class . ".BillingPeriodMonthly", "monthly"),
                    'BillingPeriodQuarterly'           => _t(ProductExtension::class . ".BillingPeriodQuarterly", "quarterly"),
                    'BillingPeriodYearly'              => _t(ProductExtension::class . ".BillingPeriodYearly", "yearly"),
                    'Once'                             => _t(ProductExtension::class . ".Once", "once"),
                    'SubscriptionDuration'             => _t(ProductExtension::class . ".SubscriptionDuration", "Subscription Duration"),
                    'SubscriptionDurationPeriodMonths' => _t(ProductExtension::class . ".SubscriptionDurationPeriodMonths", "Months"),
                    'SubscriptionDurationPeriodYears'  => _t(ProductExtension::class . ".SubscriptionDurationPeriodYears", "Years"),
                    'Then'                             => _t(ProductExtension::class . ".Then", "then"),
                ]
        );
    }
    
    /**
     * Returns the billing period i18n.
     * 
     * @return string
     */
    public function getBillingPeriodNice()
    {
        $billingPeriod = ucfirst($this->owner->BillingPeriod);
        return $this->owner->fieldLabel("BillingPeriod{$billingPeriod}");
    }
}