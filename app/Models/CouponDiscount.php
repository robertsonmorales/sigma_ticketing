<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Model\{TicketType};

class CouponDiscount extends Model
{
    use HasFactory;

    public function ticketType(){
        return $this->belongsTo(TicketType::class, 'coupon_discounts_ticket_type_id', 'ticket_type_id');
    }
}
