<?php

namespace SilverCart\Subscriptions\Extensions;

use SilverCart\Admin\Forms\AlertInfoField;
use SilverCart\Admin\Model\Config;
use SilverCart\Dev\Tools;
use SilverCart\Forms\FormFields\FieldGroup;
use SilverCart\Model\Customer\Customer;
use SilverCart\ORM\FieldType\DBMoney;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBInt;

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
        'IsSubscription'                               => 'Boolean(0)',
        'BillingPeriod'                                => 'Enum(",monthly,quarterly,yearly","")',
        'BillingPeriodConsequentialCosts'              => 'Enum(",monthly,quarterly,yearly","")',
        'HasConsequentialCosts'                        => 'Boolean(0)',
        'PriceGrossConsequentialCosts'                 => DBMoney::class,
        'PriceNetConsequentialCosts'                   => DBMoney::class,
        'SubscriptionDurationValue'                    => DBInt::class,
        'SubscriptionDurationValueConsequentialCosts'  => DBInt::class,
        'SubscriptionDurationPeriod'                   => 'Enum(",months,years","")',
        'SubscriptionDurationPeriodConsequentialCosts' => 'Enum(",months,years","")',
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
                Tools::field_labels_for(self::class),
                [
                    'BillingPeriodConsequentialCosts'        => _t(self::class . ".BillingPeriod", "Billing period"),
                    'BillingPeriodOnce'                      => _t(self::class . ".BillingPeriodOnce", "once"),
                    'BillingPeriodMonthly'                   => _t(self::class . ".BillingPeriodMonthly", "monthly"),
                    'BillingPeriodQuarterly'                 => _t(self::class . ".BillingPeriodQuarterly", "quarterly"),
                    'BillingPeriodYearly'                    => _t(self::class . ".BillingPeriodYearly", "yearly"),
                    'DurationPeriodAddPluralMonths'          => _t(self::class . ".DurationPeriodAddPluralMonths", "months"),
                    'DurationPeriodAddPluralYears'           => _t(self::class . ".DurationPeriodAddPluralYears", "years"),
                    'DurationPeriodAddSingularMonths'        => _t(self::class . ".DurationPeriodAddSingularMonths", "month"),
                    'DurationPeriodAddSingularYears'         => _t(self::class . ".DurationPeriodAddSingularYears", "year"),
                    'Once'                                   => _t(self::class . ".Once", "once"),
                    'SubscriptionDuration'                   => _t(self::class . ".SubscriptionDuration", "Subscription Duration"),
                    'SubscriptionDurationPeriodMonths'       => _t(self::class . ".SubscriptionDurationPeriodMonths", "Months"),
                    'SubscriptionDurationPeriodYears'        => _t(self::class . ".SubscriptionDurationPeriodYears", "Years"),
                    'SubscriptionDurationConsequentialCosts' => _t(self::class . ".SubscriptionDuration", "Subscription Duration"),
                    'Then'                                   => _t(self::class . ".Then", "then"),
                ]
        );
    }
    
    /**
     * Updates the field group for the product price settings.
     * 
     * @param FieldGroup $pricesGroup Price field group
     * @param FieldList            $fields      CMS fields
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function updateFieldsForPrices(FieldGroup $pricesGroup, FieldList $fields) : void
    {
        $billingPeriodSource = [
            ''          => '',
            'monthly'   => $this->owner->fieldLabel('BillingPeriodMonthly'),
            'quarterly' => $this->owner->fieldLabel('BillingPeriodQuarterly'),
            'yearly'    => $this->owner->fieldLabel('BillingPeriodYearly'),
        ];
        $fields->dataFieldByName('BillingPeriod')->setSource($billingPeriodSource);
        $fields->dataFieldByName('BillingPeriodConsequentialCosts')->setSource($billingPeriodSource);
        
        $info = AlertInfoField::create('SubscriptionInfo1', Tools::string2html("{$this->owner->fieldLabel('IsSubscriptionDesc')}<br/>{$this->owner->fieldLabel('HasConsequentialCostsDesc')}"));
        
        $subscriptionDurationField                   = $this->getSubscriptionDurationField($fields);
        $subscriptionDurationConsequentialCostsField = $this->getSubscriptionDurationField($fields, 'ConsequentialCosts');
        
        $pricesGroup->breakAndPush($fields->dataFieldByName('IsSubscription'));
        $pricesGroup->push($fields->dataFieldByName('BillingPeriod'));
        $pricesGroup->push($subscriptionDurationField);
        $pricesGroup->breakAndPush($fields->dataFieldByName('HasConsequentialCosts'));
        $pricesGroup->push($fields->dataFieldByName('PriceGrossConsequentialCosts'));
        $pricesGroup->push($fields->dataFieldByName('PriceNetConsequentialCosts'));
        $pricesGroup->breakAndPush(LiteralField::create('Dummy', '&nbsp;'));
        $pricesGroup->push($fields->dataFieldByName('BillingPeriodConsequentialCosts'));
        $pricesGroup->push($subscriptionDurationConsequentialCostsField);
        $pricesGroup->breakAndPush($info);
        
        $fields->removeByName('SubscriptionDurationValue');
        $fields->removeByName('SubscriptionDurationPeriod');
        $fields->removeByName('SubscriptionDurationValueConsequentialCosts');
        $fields->removeByName('SubscriptionDurationPeriodConsequentialCosts');
    }
    
    /**
     * Returns the subscription fields for the given $suffix.
     * 
     * @param FieldList $fields Fields to expand
     * @param string    $suffix Suffix to use for the context DB fields
     * 
     * @return LiteralField
     */
    public function getSubscriptionDurationField(FieldList $fields, string $suffix = '') : LiteralField
    {
        $fieldName                        = "SubscriptionDuration{$suffix}";
        $labelName                        = "SubscriptionDurationLabel{$suffix}";
        $valueFieldName                   = "SubscriptionDurationValue{$suffix}";
        $periodFieldName                  = "SubscriptionDurationPeriod{$suffix}";
        $subscriptionDurationPeriodSource = [
            ''       => '',
            'months' => $this->owner->fieldLabel('SubscriptionDurationPeriodMonths'),
            'years'  => $this->owner->fieldLabel('SubscriptionDurationPeriodYears'),
        ];
        $label       = LiteralField::create($labelName, "<label class=\"form__fieldgroup-label\">{$this->owner->fieldLabel($fieldName)}</label>");
        $valueField  = $fields->dataFieldByName($valueFieldName);
        $periodField = $fields->dataFieldByName($periodFieldName);
        $valueField->setAttribute('style', 'width:39%; float:left;')
                ->setValue($this->owner->{$valueFieldName});
        $periodField->setAttribute('style', 'width:59%; float:right;')
                ->setValue($this->owner->{$periodFieldName})
                ->addExtraClass('has-chzn')
                ->setSource($subscriptionDurationPeriodSource);
        $subscriptionDurationFieldContent = "<div class=\"fieldholder-small\" style=\"width: 250px\">{$label->Field()}{$valueField->Field()} {$periodField->Field()}</div>";
        return LiteralField::create($fieldName, $subscriptionDurationFieldContent);
    }
    
    /**
     * Sets the subscription duration value and period if needed.
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 23.11.2018
     */
    public function onBeforeWrite() : void
    {
        parent::onBeforeWrite();
        if (array_key_exists('SubscriptionDurationValue', $_POST)
         && array_key_exists('SubscriptionDurationPeriod', $_POST)
         && array_key_exists('SubscriptionDurationValueConsequentialCosts', $_POST)
         && array_key_exists('SubscriptionDurationPeriodConsequentialCosts', $_POST)
         && $this->owner->canEdit()
        ) {
            $this->owner->SubscriptionDurationValue  = $_POST['SubscriptionDurationValue'];
            $this->owner->SubscriptionDurationPeriod = $_POST['SubscriptionDurationPeriod'];
            $this->owner->SubscriptionDurationValueConsequentialCosts  = $_POST['SubscriptionDurationValueConsequentialCosts'];
            $this->owner->SubscriptionDurationPeriodConsequentialCosts = $_POST['SubscriptionDurationPeriodConsequentialCosts'];
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
                        ->renderWith(self::class . 'Price_HasConsequentialCosts');
            } else {
                $priceNice = $this->owner
                        ->customise([
                            'BillingPeriodNice' => $this->owner->getBillingPeriodNice(),
                        ])
                        ->renderWith(self::class . 'Price');
            }
        }
    }
    
    /**
     * Returns the billing period i18n.
     * 
     * @return string
     */
    public function getBillingPeriodNice() : string
    {
        $billingPeriod = ucfirst($this->owner->BillingPeriod);
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
         && $this->owner->HasConsequentialCosts
        ) {
            if ((int) $this->owner->SubscriptionDurationValue === 1) {
                $addition = '<br/>' . _t(self::class . '.BillingPeriodAdditionSingular', 'in the first {period}', [
                    'period'   => $this->owner->fieldLabel("DurationPeriodAddSingular{$durationPeriod}"),
                ]) . ',';
            } else {
                $addition = '<br/>' . _t(self::class . '.BillingPeriodAdditionPlural', 'in the first {duration} {period}', [
                    'duration' => $this->owner->SubscriptionDurationValue,
                    'period'   => $this->owner->fieldLabel("DurationPeriodAddPlural{$durationPeriod}"),
                ]) . ',';
            }
        }
        return DBHTMLText::create()->setValue($addition);
    }
    
    /**
     * Returns the billing period for the consequential costs i18n.
     * 
     * @return string
     */
    public function getBillingPeriodConsequentialCostsNice() : string
    {
        $billingPeriod = ucfirst($this->owner->BillingPeriodConsequentialCosts);
        if (empty($billingPeriod)) {
            $billingPeriod = 'Once';
        }
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
            $priceType = Config::PriceType();
        }
        
        if ($priceType == "net") {
            $price = clone $this->owner->PriceNetConsequentialCosts;
        } elseif ($priceType == "gross") {
            $price = clone $this->owner->PriceGrossConsequentialCosts;
        } else {
            $price = clone $this->owner->PriceGrossConsequentialCosts;
        }
        
        $member = Customer::currentUser();
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
        $member          = Customer::currentUser();

        if ($member) {
            if ($member->showPricesGross(true)) {
                $showPricesGross = true;
            }
        } else {
            $defaultPriceType = Config::DefaultPriceType();

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