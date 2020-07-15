{$BeforePriceTotalNiceContent}{$PriceTotal.Nice}{$AfterPriceTotalNiceContent}
<% if $Order.IsPriceTypeNet %>
<br/><small class="text-muted">{$TaxRate}% {$fieldLabel('Tax')}: {$TaxTotalMoney.Nice}</small>
<% end_if %>