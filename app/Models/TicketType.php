<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Model\{CouponDiscount};

class TicketType extends Model
{
    use HasFactory;

    protected $table = "ticket_types";

    // public function couponDiscounts(){
    //     return $this->hasMany(CouponDiscount::class, "", "")
    // }
}