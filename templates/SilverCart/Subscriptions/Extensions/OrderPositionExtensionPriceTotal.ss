{$BeforePriceTotalNiceContent}{$PriceTotal.Nice} <small>{$BillingPeriodNice}</small>{$AfterPriceTotalNiceContent}
<% if $WithTax %>
    <% if $Order.IsPriceTypeNet %>
<br/><small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxTotalMoney.Nice}</small>
    <% end_if %>
<% end_if %>