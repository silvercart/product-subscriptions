<?php

use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Voucher\Model\ShoppingCartPosition as VoucherShoppingCartPosition;
use SilverCart\Voucher\Model\Voucher;
use SilverCart\Voucher\Model\Voucher\AbsoluteRebateVoucher;
use SilverCart\Voucher\Model\Voucher\RelativeRebateVoucher;
use SilverCart\Subscriptions\Extensions\Model\Vouchers\ShoppingCartPositionExtension;
use SilverCart\Subscriptions\Extensions\Model\Vouchers\VoucherExtension;
use SilverCart\Subscriptions\Extensions\Model\Vouchers\VoucherShoppingCartPositionExtension;
use SilverCart\Subscriptions\Extensions\Model\Vouchers\AbsoluteRebateVoucherExtension;
use SilverCart\Subscriptions\Extensions\Model\Vouchers\RelativeRebateVoucherExtension;

if (class_exists(Voucher::class)) {
    Voucher::add_extension(VoucherExtension::class);
    AbsoluteRebateVoucher::add_extension(AbsoluteRebateVoucherExtension::class);
    RelativeRebateVoucher::add_extension(RelativeRebateVoucherExtension::class);
    ShoppingCartPosition::add_extension(ShoppingCartPositionExtension::class);
    VoucherShoppingCartPosition::add_extension(VoucherShoppingCartPositionExtension::class);
}