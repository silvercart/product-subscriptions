<?php

namespace SilverCart\Subscriptions\Extensions;

use DataExtension;
use FieldList;
use LabelField;
use LiteralField;
use SilvercartAlertInfoField;
use SilvercartConfig;
use SilvercartCustomer;
use SilvercartFieldGroup;
use SilvercartTools;

/**
 * Extension for SilverCart Product.
 * 
 * @package SilverCart
 * @subpackage Subscriptions\Extensions
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 23.11.2018
 * @copyright 2018 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class ProductExtension extends DataExtension
{
    /**
     * DB attributes.
     *
     * @var array
     */
    private static $db = [
        'IsSubscription'               => 'Boolean(0)',
        'BillingPeriod'                => 'Enum(",monthly,quarterly,yearly","")',
        'HasConsequentialCosts'        => 'Boolean(0)',
        'PriceGrossConsequentialCosts' => 'SilvercartMoney',
        'PriceNetConsequentialCosts'   => 'SilvercartMoney',
        'SubscriptionDurationValue'    => 'Int',
        'SubscriptionDurationPeriod'   => 'Enum(",months,years","")',
    ];
    /**
     * Defaults for DB attributes.
     *
     * @var array
     */
    private static $defaults = [
        'IsSubscription'        => false,
        'HasConsequentialCosts' => false,
    ];
    
    /**
     * Updates the field labels.
     * 
     * @param array &$labels Labels to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updateFieldLabels(&$labels)
    {
        $labels = array_merge(
                $labels,
                SilvercartTools::field_labels_for(self::class),
                [
                    'BillingPeriodMonthly'             => _t(self::class . ".BillingPeriodMonthly", "monthly"),
                    'BillingPeriodQuarterly'           => _t(self::class . ".BillingPeriodQuarterly", "quarterly"),
                    'BillingPeriodYearly'              => _t(self::class . ".BillingPeriodYearly", "yearly"),
                    'Once'                             => _t(self::class . ".Once", "once"),
                    'SubscriptionDuration'             => _t(self::class . ".SubscriptionDuration", "Subscription Duration"),
                    'SubscriptionDurationPeriodMonths' => _t(self::class . ".SubscriptionDurationPeriodMonths", "Months"),
                    'SubscriptionDurationPeriodYears'  => _t(self::class . ".SubscriptionDurationPeriodYears", "Years"),
                    'Then'                             => _t(self::class . ".Then", "then"),
                ]
        );
    }
    
    /**
     * Updates the field group for the product price settings.
     * 
     * @param SilvercartFieldGroup $pricesGroup Price field group
     * @param FieldList            $fields      CMS fields
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updateFieldsForPrices(SilvercartFieldGroup $pricesGroup, FieldList $fields)
    {
        $subscriptionDurationPeriodSource = [
            ''       => '',
            'months' => $this->owner->fieldLabel('SubscriptionDurationPeriodMonths'),
            'years'  => $this->owner->fieldLabel('SubscriptionDurationPeriodYears'),
        ];
        $billingPeriodSource = [
            ''          => '',
            'monthly'   => $this->owner->fieldLabel('BillingPeriodMonthly'),
            'quarterly' => $this->owner->fieldLabel('BillingPeriodQuarterly'),
            'yearly'    => $this->owner->fieldLabel('BillingPeriodYearly'),
        ];
        $fields->dataFieldByName('BillingPeriod')->setSource($billingPeriodSource);
        
        $info = SilvercartAlertInfoField::create('SubscriptionInfo1', "{$this->owner->fieldLabel('IsSubscriptionDesc')}<br/>{$this->owner->fieldLabel('HasConsequentialCostsDesc')}");
        
        $label       = LabelField::create('SubscriptionDurationLabel', $this->owner->fieldLabel('SubscriptionDuration'));
        $valueField  = $fields->dataFieldByName('SubscriptionDurationValue');
        $periodField = $fields->dataFieldByName('SubscriptionDurationPeriod');
        $valueField->setAttribute('style', 'width:39%')
                ->setValue($this->owner->SubscriptionDurationValue);
        $periodField->setAttribute('style', 'width:59%')
                ->setValue($this->owner->SubscriptionDurationPeriod)
                ->addExtraClass('has-chzn')
                ->setSource($subscriptionDurationPeriodSource);
        $subscriptionDurationFieldContent = "<div class=\"fieldholder-small\" style=\"width: 250px\">{$label->Field()}{$valueField->Field()} {$periodField->Field()}</div>";
        $subscriptionDurationField        = LiteralField::create('SubscriptionDuration', $subscriptionDurationFieldContent);
        
        $pricesGroup->breakAndPush($fields->dataFieldByName('IsSubscription'));
        $pricesGroup->push($fields->dataFieldByName('BillingPeriod'));
        $pricesGroup->push($subscriptionDurationField);
        $pricesGroup->breakAndPush($fields->dataFieldByName('HasConsequentialCosts'));
        $pricesGroup->push($fields->dataFieldByName('PriceGrossConsequentialCosts'));
        $pricesGroup->push($fields->dataFieldByName('PriceNetConsequentialCosts'));
        $pricesGroup->breakAndPush($info);
        
        $fields->removeByName('SubscriptionDurationValue');
        $fields->removeByName('SubscriptionDurationPeriod');
    }
    
    /**
     * Sets the subscription duration value and period if needed.
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (array_key_exists('SubscriptionDurationValue', $_POST)
         && array_key_exists('SubscriptionDurationPeriod', $_POST)
         && $this->owner->canEdit()) {
            $this->owner->SubscriptionDurationValue  = $_POST['SubscriptionDurationValue'];
            $this->owner->SubscriptionDurationPeriod = $_POST['SubscriptionDurationPeriod'];
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
    public function updatePriceNice(&$priceNice)
    {
        if ($this->owner->IsSubscription) {
            if ($this->owner->HasConsequentialCosts) {
                $priceNice = $this->owner
                        ->customise([
                            'Once'              => $this->owner->fieldLabel('Once'),
                            'Then'              => $this->owner->fieldLabel('Then'),
                            'BillingPeriodNice' => $this->owner->getBillingPeriodNice(),
                        ])
                        ->renderWith('SilvercartSubscriptionsPrice_HasConsequentialCosts');
            } else {
                $priceNice = $this->owner
                        ->customise([
                            'BillingPeriodNice' => $this->owner->getBillingPeriodNice(),
                        ])
                        ->renderWith('SilvercartSubscriptionsPrice');
            }
        }
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

    /**
     * Getter for product consequential costs price.
     *
     * @param string $priceType          Set to 'gross' or 'net' to get the desired prices.
     *                                   If not given the price type will be automatically determined.
     * @param bool   $ignoreTaxExemption Determines whether to ignore tax exemption or not.
     *
     * @return Money
     *
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function getPriceConsequentialCosts($priceType = '', $ignoreTaxExemption = false)
    {
        if (empty($priceType)) {
            $priceType = SilvercartConfig::PriceType();
        }
        
        if ($priceType == "net") {
            $price = clone $this->owner->PriceNetConsequentialCosts;
        } elseif ($priceType == "gross") {
            $price = clone $this->owner->PriceGrossConsequentialCosts;
        } else {
            $price = clone $this->owner->PriceGrossConsequentialCosts;
        }
        
        $member = SilvercartCustomer::currentUser();
        if (!$ignoreTaxExemption
         && !$this->owner->ignoreTaxExemption
         && $member instanceof Member
         && $member->doesNotHaveToPayTaxes()
         && $priceType != "net"
        ) {
            $this->owner->ignoreTaxExemption = true;
            $price->setAmount($price->getAmount() - $this->owner->getTaxAmount());
            $this->owner->ignoreTaxExemption = false;
        }

        $price->setAmount(round($price->getAmount(), 2));

        if ($price->getAmount() < 0) {
            $price->setAmount(0);
        }
        
        return $price;
    }

    /**
     * Returns the tax amount for the consequential costs price.
     *
     * @return float
     */
    public function getTaxAmountForConsequentialCosts()
    {
        $showPricesGross = false;
        $member          = SilvercartCustomer::currentUser();

        if ($member) {
            if ($member->showPricesGross(true)) {
                $showPricesGross = true;
            }
        } else {
            $defaultPriceType = SilvercartConfig::DefaultPriceType();

            if ($defaultPriceType == 'gross') {
                $showPricesGross = true;
            }
        }

        if ($showPricesGross) {
            $taxRate = $this->owner->getPriceConsequentialCosts()->getAmount() - ($this->owner->getPriceConsequentialCosts()->getAmount() / (100 + $this->owner->getTaxRate()) * 100);
        } else {
            $taxRate = $this->owner->getPriceConsequentialCosts()->getAmount() * ($this->owner->getTaxRate() / 100);
        }
        return $taxRate;
    }
}