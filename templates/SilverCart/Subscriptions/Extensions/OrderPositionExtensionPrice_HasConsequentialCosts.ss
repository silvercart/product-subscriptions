<% if $WithTax %>
{$BeforePriceNiceContent}{$Price.Nice} <small>{$BillingPeriodNice}</small><br/>
    <% if $Order.IsPriceTypeNet %>
<small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxMoney.Nice}</small>
    <% end_if %>
<small>{$BillingPeriodAddition}</small><br/>
<small>{$Then} {$PriceConsequentialCosts.Nice} {$BillingPeriodConsequentialCostsNice}</small>
    <% if $Order.IsPriceTypeNet %>
<br/><small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxConsequentialCostsMoney.Nice}</small>
    <% end_if %>
{$AfterPriceNiceContent}
<% else %>
{$BeforePriceNiceContent}{$Price.Nice} <small>{$BillingPeriodNice}{$BillingPeriodAddition}</small><br/>
<small>{$Then} {$PriceConsequentialCosts.Nice} {$BillingPeriodConsequentialCostsNice}</small>{$AfterPriceNiceContent}
<% end_if %>