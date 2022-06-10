<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\{CouponDiscount};
class Coupon extends Model
{
    use HasFactory;

    protected $table = "coupons";

    // public function couponDiscounts(){
    //     return $this->hasOne(CouponDiscount::class, 'coupon_discounts_coupon_id', 'coupon_discount_id');
    // }
}
