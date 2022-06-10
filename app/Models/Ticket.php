<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\{TicketType, Coupon};

class Ticket extends Model
{
    use HasFactory;

    protected $table = "tickets";

    public function ticketType() : \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(
            TicketType::class, 'tickets_ticket_type_id', 'ticket_type_id'
        );
    }
    
    public function coupon() : \Illuminate\Database\Eloquent\Relations\BelongsTo{
        return $this->belongsTo(
            Coupon::class, 'tickets_coupon_id', 'coupon_id'
        );
    }
}