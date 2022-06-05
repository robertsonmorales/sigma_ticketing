<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\{Ticket};
class TicketController extends Controller
{

    protected $ticket;
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->getTicketPrice(1, 1, 1); // sample IDs
    }

    public function getTicketPrice(
        $ticketTypeId, 
        $couponId, 
        $upgradeCouponId
    ){
        $ticket = $this->ticket->where(array(
            'tickets_ticket_type_id' => $ticketTypeId,
            'tickets_coupon_id' => $couponId,
            'tickets_upgrade_coupon_id' => $upgradeCouponId
        ))->first();

        return (int) $ticket->ticketType->ticket_types_price; // * cast to integer
    }
}
