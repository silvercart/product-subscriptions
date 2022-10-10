<?php

namespace SilverCart\Subscriptions\Extensions;

use Moo\HasOneSelector\Form\Field as HasOneSelector;
use SilverCart\Dev\Tools;
use SilverCart\Model\Order\OrderPosition;
use SilverCart\ORM\FieldType\DBMoney;
use SilverCart\Subscriptions\Extensions\ProductExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBInt;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

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
        'IsSubscription'                               => 'Boolean(0)',
        'IsSubscriptionVoucher'                        => 'Boolean(0)',
        'BillingPeriod'                                => 'Enum(",monthly,quarterly,yearly","")',
        'BillingPeriodConsequentialCosts'              => 'Enum(",monthly,quarterly,yearly","")',
        'PriceConsequentialCosts'                      => DBMoney::class,
        'PriceTotalConsequentialCosts'                 => DBMoney::class,
        'TaxConsequentialCosts'                        => DBFloat::class,
        'TaxTotalConsequentialCosts'                   => DBFloat::class,
        'SubscriptionDurationValue'                    => DBInt::class,
        'SubscriptionDurationValueConsequentialCosts'  => DBInt::class,
        'SubscriptionDurationPeriod'                   => 'Enum(",months,years","")',
        'SubscriptionDurationPeriodConsequentialCosts' => 'Enum(",months,years","")',
    ];
    /**
     * Has one relations.
     *
     * @var type 
     */
    private static $has_one = [
        'SubscriptionPosition' => OrderPosition::class,
    ];
    /**
     * Has one back side relations (1:1).
     *
     * @var array
     */
    private static $belongs_to = [
        'SubscriptionVoucherPosition' => OrderPosition::class . '.SubscriptionPosition',
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
     * Updates order positions with the old format to the new format.
     * 
     * @return void
     */
    public function requireDefaultRecords() : void
    {
        $oldPositions = OrderPosition::get()
                ->exclude([
                    'BillingPeriod'       => '',
                    'Created:GreaterThan' => '2020-10-01',
                ])
                ->filter([
                    'BillingPeriodConsequentialCosts'           => NULL,
                    'PriceAmount'                               => 0,
                    'PriceConsequentialCostsAmount:GreaterThan' => 0,
                ]);
        foreach ($oldPositions as $position) {
            $position->PriceAmount                        = $position->PriceConsequentialCostsAmount;
            $position->PriceTotalAmount                   = $position->PriceTotalConsequentialCostsAmount;
            $position->Tax                                = $position->TaxConsequentialCosts;
            $position->TaxTotal                           = $position->TaxTotalConsequentialCosts;
            $position->PriceConsequentialCostsAmount      = 0;
            $position->PriceTotalConsequentialCostsAmount = 0;
            $position->TaxConsequentialCosts              = 0;
            $position->TaxTotalConsequentialCosts         = 0;
            $position->write();
        }
        $oldPositionsWithOneTimePrice = OrderPosition::get()
                ->exclude([
                    'BillingPeriod'       => '',
                    'Created:GreaterThan' => '2020-10-01',
                ])
                ->filter([
                    'BillingPeriodConsequentialCosts'           => NULL,
                    'PriceAmount:GreaterThan'                   => 0,
                    'PriceConsequentialCostsAmount:GreaterThan' => 0,
                ]);
        foreach ($oldPositionsWithOneTimePrice as $positionWithOneTimePrice) {
            $positionWithOneTimePrice->BillingPeriodConsequentialCosts              = $positionWithOneTimePrice->BillingPeriod;
            $positionWithOneTimePrice->SubscriptionDurationValueConsequentialCosts  = $positionWithOneTimePrice->SubscriptionDurationValue;
            $positionWithOneTimePrice->SubscriptionDurationPeriodConsequentialCosts = $positionWithOneTimePrice->SubscriptionDurationPeriod;
            $positionWithOneTimePrice->BillingPeriod                                = null;
            $positionWithOneTimePrice->SubscriptionDurationValue                    = 0;
            $positionWithOneTimePrice->SubscriptionDurationPeriod                   = null;
            $positionWithOneTimePrice->write();
        }
    }
    
    /**
     * Updates the CMS fields.
     * 
     * @param FieldList $fields Fields to update
     * 
     * @return void
     */
    public function updateCMSFields(FieldList $fields) : void
    {
        if (class_exists(HasOneSelector::class)) {
            $spField = HasOneSelector::create('SubscriptionPosition', $this->owner->fieldLabel('SubscriptionPosition'), $this->owner, OrderPosition::class)
                    ->setLeftTitle($this->owner->fieldLabel('SubscriptionPosition'))
                    ->removeLinkable()
                    ->setDescription($this->owner->fieldLabel('SubscriptionPositionDesc'));
            $spField->getConfig()->removeComponentsByType(GridFieldDeleteAction::class);
            if ($this->owner->SubscriptionPosition()->exists()) {
                $spField->removeAddable();
                $spField->getConfig()->addComponent(new GridFieldTitleHeader());
            }
            $fields->replaceField('SubscriptionPositionID', $spField);
        }
    }
    
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
                Tools::field_labels_for(self::class),
                Tools::field_labels_for(ProductExtension::class),
                [
                    'BillingPeriodOnce'                => _t(ProductExtension::class . ".BillingPeriodOnce", "once"),
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
     * Updates the products PriceNice property.
     * 
     * @param string $priceNice Price to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updatePriceNice(&$priceNice, bool $withTax = null, string $template = null) : void
    {
        if ($this->owner->IsSubscriptionVoucher) {
            $priceNice = '';
            return;
        }
        if ($template === null) {
            $template = 'Price';
        }
        if ($this->owner->IsSubscription) {
            if ($this->owner->PriceTotalConsequentialCosts->getAmount() > 0) {
                $priceNice = $this->owner
                        ->customise([
                            'WithTax'           => (bool) $withTax,
                            'Once'              => $this->owner->fieldLabel('Once'),
                            'Then'              => $this->owner->fieldLabel('Then'),
                            'BillingPeriodNice' => $this->owner->getBillingPeriodNice(),
                        ])
                        ->renderWith(self::class . "{$template}_HasConsequentialCosts");
            } else {
                $priceNice = $this->owner
                        ->customise([
                            'WithTax'           => (bool) $withTax,
                            'BillingPeriodNice' => $this->owner->getBillingPeriodNice(),
                        ])
                        ->renderWith(self::class . $template);
            }
        }
    }
    
    /**
     * Updates the products PriceNice property.
     * 
     * @param string $priceNice Price to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updatePriceTotalNice(&$priceNice, bool $withTax = null) : void
    {
        $this->updatePriceNice($priceNice, $withTax, 'PriceTotal');
    }
    
    /**
     * Skips this position if it is a subscription without a one time purchase.
     * 
     * @return bool
     */
    public function skipCalculateAmountTotal() : bool
    {
        return $this->owner->IsSubscription
            && !empty($this->owner->BillingPeriod);
    }

    /**
     * returns the orders total amount as string incl. currency.
     *
     * @return string
     */
    public function getPriceNiceWithTax() : DBHTMLText
    {
        $priceNice = $this->owner->renderWith(self::class . '_PriceNiceWithTax');
        $withTax   = true;
        $this->owner->extend('updatePriceNice', $priceNice, $withTax);
        return DBHTMLText::create()->setValue($priceNice);
    }

    /**
     * returns the orders total amount as string incl. currency.
     *
     * @return string
     */
    public function getPriceTotalNiceWithTax() : DBHTMLText
    {
        $priceNice = $this->owner->renderWith(self::class . '_PriceTotalNiceWithTax');
        $withTax   = true;
        $this->owner->extend('updatePriceTotalNice', $priceNice, $withTax);
        return DBHTMLText::create()->setValue($priceNice);
    }
    
    /**
     * Returns the billing period i18n.
     * 
     * @return string
     */
    public function getBillingPeriodNice()
    {
        $billingPeriod = ucfirst($this->owner->BillingPeriod);
        if (empty($billingPeriod)) {
            $billingPeriod = 'Once';
        }
        return $this->owner->fieldLabel("BillingPeriod{$billingPeriod}");
    }
    
    /**
     * Returns the billing period consequential costs i18n.
     * 
     * @return string
     */
    public function getBillingPeriodConsequentialCostsNice()
    {
        $billingPeriod = ucfirst($this->owner->BillingPeriodConsequentialCosts);
        if (empty($billingPeriod)) {
            $billingPeriod = 'Once';
        }
        return $this->owner->fieldLabel("BillingPeriod{$billingPeriod}");
    }
    
    /**
     * Returns the addition text for the billing period if necessary.
     * 
     * @return DBHTMLText
     */
    public function getBillingPeriodAddition() : DBHTMLText
    {
        $addition       = '';
        $billingPeriod  = ucfirst($this->owner->BillingPeriod);
        $durationPeriod = ucfirst($this->owner->SubscriptionDurationPeriod);
        if (!empty($billingPeriod)
         && $this->owner->PriceTotalConsequentialCosts->getAmount() > 0
        ) {
            if ((int) $this->owner->SubscriptionDurationValue === 1) {
                $addition = '<br/>' . _t(ProductExtension::class . '.BillingPeriodAdditionSingular', 'in the first {period}', [
                    'period'   => $this->owner->Product()->fieldLabel("DurationPeriodAddSingular{$durationPeriod}"),
                ]) . ',';
            } else {
                $addition = '<br/>' . _t(ProductExtension::class . '.BillingPeriodAdditionPlural', 'in the first {duration} {period}', [
                    'duration' => $this->owner->SubscriptionDurationValue,
                    'period'   => $this->owner->Product()->fieldLabel("DurationPeriodAddPlural{$durationPeriod}"),
                ]) . ',';
            }
        }
        return DBHTMLText::create()->setValue($addition);
    }
    
    /**
     * Returns the TaxConsequentialCosts as a DBMoney object.
     * 
     * @return DBMoney
     */
    public function getTaxConsequentialCostsMoney() : DBMoney
    {
        return DBMoney::create()->setAmount($this->owner->TaxConsequentialCosts)->setCurrency($this->owner->PriceConsequentialCosts->getCurrency());
    }
    
    /**
     * Returns the TaxTotalConsequentialCosts as a DBMoney object.
     * 
     * @return DBMoney
     */
    public function getTaxTotalConsequentialCostsMoney() : DBMoney
    {
        return DBMoney::create()->setAmount($this->owner->TaxTotalConsequentialCosts)->setCurrency($this->owner->PriceTotalConsequentialCosts->getCurrency());
    }
}