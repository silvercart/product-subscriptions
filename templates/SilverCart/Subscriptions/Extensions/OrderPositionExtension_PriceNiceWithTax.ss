{$BeforePriceNiceContent}{$Price.Nice}{$AfterPriceNiceContent}
<% if $Order.IsPriceTypeNet %>
<br/><small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxMoney.Nice}</small>
<% end_if %>