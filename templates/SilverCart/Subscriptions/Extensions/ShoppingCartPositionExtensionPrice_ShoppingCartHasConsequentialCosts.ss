<% with $Product %>
{$Up.ContextPrice.Nice} <small>{$BillingPeriodNice}{$BillingPeriodAddition}</small><br/>
<small>{$fieldLabel('Then')} {$Up.PriceConsequentialCosts.Nice} {$BillingPeriodConsequentialCostsNice}</small>
<% end_with %>