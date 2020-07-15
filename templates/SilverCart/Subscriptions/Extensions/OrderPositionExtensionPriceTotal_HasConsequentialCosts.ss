<% if $WithTax %>
{$BeforePriceTotalNiceContent}{$PriceTotal.Nice} <small>{$BillingPeriodNice}</small><br/>
    <% if $Order.IsPriceTypeNet %>
<small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxTotalMoney.Nice}</small>
    <% end_if %>
<small>{$BillingPeriodAddition}</small><br/>
<small>{$Then} {$PriceTotalConsequentialCosts.Nice} {$BillingPeriodConsequentialCostsNice}</small>
    <% if $Order.IsPriceTypeNet %>
<br/><small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxTotalConsequentialCostsMoney.Nice}</small>
    <% end_if %>
{$AfterPriceTotalNiceContent}
<% else %>
{$BeforePriceTotalNiceContent}{$PriceTotal.Nice} <small>{$BillingPeriodNice}{$BillingPeriodAddition}</small><br/>
<small>{$Then} {$PriceTotalConsequentialCosts.Nice} {$BillingPeriodConsequentialCostsNice}</small>{$AfterPriceNiceContent}
<% end_if %>