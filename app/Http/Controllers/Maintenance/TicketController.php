<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\{Ticket, TicketType, Coupon, CouponDiscount};

class TicketController extends Controller
{

    protected $ticket, $type, $coupon, $discount;
    public function __construct(Ticket $ticket, TicketType $type, Coupon $coupon, CouponDiscount $discount)
    {
        $this->ticket = $ticket;
        $this->type = $type;
        $this->coupon = $coupon;
        $this->discount = $discount;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->getTicketPrice(2, 2, 2); // sample IDs
    }

    public function getTicketPrice(int $ticketTypeId, int $couponId = null, int $upgradeCouponId = null){
        $getTicketTypeById = $this->type->where('ticket_type_id', $ticketTypeId)->first();
        if(!empty($getTicketTypeById)){
            $ticketTypeName = $getTicketTypeById->ticket_types_name;
            $originTicketPrice = $getTicketTypeById->ticket_types_price; // * the original ticket price

            // * discount rules
            $presentCoupon = (!is_null($couponId) && !is_null($upgradeCouponId));
            $eitherPresentCoupon = (!is_null($couponId) || !is_null($upgradeCouponId));
            $noPresentCoupon = (is_null($couponId) && is_null($upgradeCouponId));

            $initialCouponIsValid = $this->isGranted($couponId, $ticketTypeId, "Initial");
            $upgradeCouponIsValid = $this->isGranted($upgradeCouponId, $ticketTypeId, "Upgrade");

            $noValidCoupon = ($initialCouponIsValid['is_true'] == false && $upgradeCouponIsValid['is_true'] == false);
            $notValid = ($initialCouponIsValid['is_true'] == false && $upgradeCouponIsValid['is_true'] == false);
            $valid = !$notValid;
            // * ends here

            // * If both initial and upgrade coupon are present and valid, first the initial discount is granted, then the upgrade discount is imposed on the resulting price
            if($presentCoupon){
                $initialPrice = $this->newTicketPrice($originTicketPrice, $couponId);
                $upgradePrice = $this->newTicketPrice($initialPrice, $upgradeCouponId);

                return $this->discountRulesOutput(
                    "both present and valid, initial discount is granted, then upgrade discount is imposed",
                    $ticketTypeName,
                    $upgradePrice
                );
            }

            // * If both coupons are present and only the upgrade coupon is valid, only apply the upgrade coupon’s discount 
            if($presentCoupon && $initialCouponIsValid['is_true'] == false && $upgradeCouponIsValid['is_true']){
                return $this->discountRulesOutput(
                    "both present and only upgrade is valid",
                    $ticketTypeName,
                    $this->newTicketPrice($originTicketPrice, $upgradeCouponId)
                );
            }
            // * ends here

            // * If both coupons are present and only the initial coupon is valid, only apply the initial coupon’s discount 
            if($presentCoupon && $initialCouponIsValid['is_true'] && $upgradeCouponIsValid['is_true'] == false){
                return $this->discountRulesOutput(
                    "both present and only initial is valid",
                    $ticketTypeName,
                    $this->newTicketPrice($originTicketPrice, $couponId)
                );
            }
            // * ends here

            // * If both coupons are present and no coupon is valid, charge the original price
            if($presentCoupon && $noValidCoupon){
                return $this->discountRulesOutput(
                    "both present and no coupon is valid",
                    $ticketTypeName,
                    number_format($originTicketPrice, 2)
                );
            }
            // * ends here

            // * If either coupon is present but not valid, charge the original price
            if($eitherPresentCoupon && $notValid){
                return $this->discountRulesOutput(
                    "either present but not valid",
                    $ticketTypeName,
                    number_format($originTicketPrice, 2)
                );
            }
            // * ends here

            // * If either coupon is present and valid, apply the corresponding discount
            if($eitherPresentCoupon && $valid){
                if($initialCouponIsValid['is_true']){
                    return $this->discountRulesOutput(
                        "either is present and valid (initial)",
                        $ticketTypeName,
                        $this->newTicketPrice($originTicketPrice, $couponId)
                    );
                }
                
                if($upgradeCouponIsValid['is_true']){
                    return $this->discountRulesOutput(
                        "either is present and valid (upgrade)",
                        $ticketTypeName,
                        $this->newTicketPrice($originTicketPrice, $upgradeCouponId)
                    );
                }
            }
            // * ends here

            // * If no coupon is present at all, of course charge the original price
            if($noPresentCoupon){
                return $this->discountRulesOutput(
                    "no coupon is present at all",
                    $ticketTypeName,
                    number_format($originTicketPrice, 2)
                );
            }
        }else{
            return $this->output("Ticket Type Id not found", false);
        }
    }

    public function newTicketPrice(float $ticketPrice, int $couponId){
        $getDiscountMode = $this->discountMode($couponId); // ? applying discount happens here
        $newTicketPrice = $this->calculateTicketPriceWithDiscount($getDiscountMode, $ticketPrice); // ? calculation happens here

        return $newTicketPrice;
    }

    // * A coupon discount can either be fixed (coupon_discounts_discount_type == ‘fixed’, amount in
    // * coupon_discounts_fixed_discount) or gives a certain percentage discount from the ticket price
    // * (coupon_discounts_percent_discount) 
    public function discountMode($couponId)  : array {
        $appliedDiscount = 0;
        $disc = $this->discount->where('coupon_discounts_coupon_id', $couponId)->first();

        if($disc->coupon_discounts_discount_type == "fixed"){ 
            $appliedDiscount = $disc->coupon_discounts_fixed_discount; // * apply fixed discount amount
        }else{
            $appliedDiscount = $disc->coupon_discounts_percent_discount; // * apply discount by percentage
        }

        return array(
            "mode" => $disc->coupon_discounts_discount_type,
            "discount" => $appliedDiscount
        );
    }

    // * Calculate discount coupon
    public function calculateTicketPriceWithDiscount($discount, $ticketPrice){
        $getDiscount = null;

        if($discount['mode'] == "fixed"){
            $getDiscount = $ticketPrice - $discount['discount'];
        }else{
            $getDiscount = (($ticketPrice * $discount['discount']) / 100);
            $getDiscount = $ticketPrice - $getDiscount;
        }

        return number_format($getDiscount, 2);
    }

    // * Find a coupon based on coupon id (initial or upgrade)
    public function getCouponByCouponId($id){
        return $this->coupon->where('coupon_id', $id)->first();
    }

    // * The coupon has not reached its maximum usage (coupons_max_usage >= coupons_times_used)
    public function validateCouponMaxUsage($maxUsage, $timesUsed){
        $checkMaxUsage = ($maxUsage >= $timesUsed);
        if(!$checkMaxUsage){
            return false;
        }else{
            return true;
        }
    }

    // * Both ticket (tickets_status == ‘active’) and coupon (coupons_status == ‘active’) are active 
    public function validateCouponAndTicketTypeStatus(string $ticketTypeStatus, string $couponStatus) : bool {
        $checkStatuses = ($ticketTypeStatus == "active"
            && $couponStatus == "active");

        if(!$checkStatuses){
            return false;   
        }else{
            return true;
        }
    }

    public function validateCouponDiscount(int $couponId, int $ticketTypeId) {
        $ifExist = $this->discount->where(array(
            "coupon_discounts_coupon_id" => $couponId,
            "coupon_discounts_ticket_type_id" => $ticketTypeId
        ))->first();

        if(!$ifExist){
            return false;
        }else{
            return true;
        }
    }

    public function isGranted(int $couponId = null, int $ticketTypeId, string $couponType){
        $getTicketStatus = $this->ticket->where(function($query) use($couponId){
            return $query->where('tickets_coupon_id', $couponId)
                ->orWhere('tickets_upgrade_coupon_id', $couponId);
        })->where('tickets_ticket_type_id', $ticketTypeId)->first();

        if(!empty($getTicketStatus)){
            $getCouponById = @$this->getCouponByCouponId($couponId); // * if not found return null

            if(!empty($getCouponById)){
                $isCouponDiscountValid = $this->validateCouponDiscount($getCouponById->coupon_id, $ticketTypeId);
                $isMaxUsageValid = $this->validateCouponMaxUsage($getCouponById->coupons_max_usage, $getCouponById->coupons_times_used);


                $isStatusesValid = $this->validateCouponAndTicketTypeStatus($getTicketStatus->tickets_status, $getCouponById->coupons_status);

                if($isCouponDiscountValid && $isMaxUsageValid && $isStatusesValid){
                    return $this->output($couponType . " Coupon discount is granted", true);
                }else{
                    return $this->output($couponType . "Coupon discount is not granted", false);
                }
            
            }else{
                return $this->output($couponType . ' Coupon not found', false);
            }
        }else{
            return $this->output('Ticket not found', false);
        }
    }

    public function getTicketByIDs($ticketTypeId, $couponId, $upgradeCouponId){
        $getTicket = $this->ticket;
        $data = [];

        // * Either no coupons at all
        if(is_null($couponId) && is_null($upgradeCouponId)){
            $data = $getTicket->where('tickets_ticket_type_id', $ticketTypeId)->first();
        }
        
        // * Only the initial coupon (tickets_coupon_id)
        if(!is_null($couponId) && is_null($upgradeCouponId)){
            $data = $getTicket->where([
                'tickets_ticket_type_id' => $ticketTypeId,
                'tickets_coupon_id' => $couponId
            ])->first();
        }
        
        // * Both initial and upgrade coupon (tickets_upgrade_coupon_id)
        if(!is_null($couponId) && !is_null($upgradeCouponId)){
            $data = $getTicket->where([
                'tickets_ticket_type_id' => $ticketTypeId,
                'tickets_coupon_id' => $couponId,
                'tickets_upgrade_coupon_id' => $upgradeCouponId
            ])->first();
        }
        
        // * Only the upgrade coupon
        if(is_null($couponId) && !is_null($upgradeCouponId)){
            $data = $getTicket->where([
                'tickets_ticket_type_id' => $ticketTypeId,
                'tickets_upgrade_coupon_id' => $upgradeCouponId
            ])->first();
        }

        return $data;
    }

    // * just for outputs/returns
    public function output(string $text, bool $bool) : array {
        return array(
            "text" => $text,
            "is_true" => $bool
        );
    }

    // * I just added this to help you, what discount rule is applied, ticket type used, and the calculated price
    public function discountRulesOutput(string $ruleApplied, string $ticketTypeName, $price){
        return array(
            "rule_appiled" => $ruleApplied,
            "ticket type" => $ticketTypeName,
            "price" => "$" . number_format($price, 2)
        );
    }
}