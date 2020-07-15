{$BeforePriceNiceContent}{$Price.Nice} <small>{$BillingPeriodNice}</small>{$AfterPriceNiceContent}
<% if $WithTax %>
    <% if $Order.IsPriceTypeNet %>
<br/><small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxMoney.Nice}</small>
    <% end_if %>
<% end_if %>