<%t Voucher.ValidFor 'Valid for' %> <span class="font-italic">{$SubscriptionProduct.ProductNumberShop}</span> <span class="font-weight-bold">{$SubscriptionProduct.Title}</span>.<br/>
<% if $DiscountInfo %>
{$DiscountInfo}<br/>
<% end_if %>
<%t Voucher.YourPrice 'Your price: <span class="font-weight-bold">{discounted}</span> instead of <span class="text-line-through">{original}</span>.' discounted=$PriceDiscounted.Nice original=$PriceOriginal.Nice %>