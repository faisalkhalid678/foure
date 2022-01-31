<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Ft_booking;
use App\Ft_booking_detail;
use App\Ft_booking_response;
use App\Models\Cancel_booking_request;
use App\Models\Notification;
use App\Events\NotificationEvent;

class BookingController extends Controller {

    public function getBookings(Request $request) {
        $booking = new Ft_booking();

        $payment_status = "";
        if ($request->has('payment_status')) {
            $payment_status = $request->payment_status;
        }
        $booking_status = "";
        if ($request->has('booking_status')) {
            $booking_status = $request->booking_status;
        }
        $query = $booking->whereRAW('pnr <> ""');
        if ($payment_status !== "") {
            $query->where('payment_status', $payment_status);
        }
        if ($booking_status !== "") {
            $query->where('booking_status', $booking_status);
        }
        $allBookings = $query->orderBy('created_at', 'DESC')->get()->toArray();
        if (!empty($allBookings)) {
            $BookingArr = array();
            foreach ($allBookings as $bking) {
                unset($bking['title']);
                unset($bking['f_name']);
                unset($bking['l_name']);

                $bookingDetailObj = new Ft_booking_detail();
                $bookingRespObj = new Ft_booking_response();
                $bookingDetail = $bookingDetailObj->where('booking_id', $bking['id'])->get()->toArray();
                $bookingResponse = $bookingRespObj->where('booking_id', $bking['id'])->first();
                $bkRsp = array();
                if ($bookingResponse !== null) {
                    $bkRsp = json_decode($bookingResponse->response);
                }
                $bking['booking_detail'] = $bookingDetail;
                $bking['booking_response'] = $bkRsp;
                $BookingArr[] = $bking;
            }
            $return = array(
                'status' => '200',
                'message' => 'Bookings List',
                'data' => $BookingArr
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'No Booking Found',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
    
    
    public function getCancelRequests() {
        $cancelRequest = new Cancel_booking_request();        
        $allRequests = $cancelRequest->orderBy('created_at', 'DESC')->get()->toArray();
        if (!empty($allRequests)) {
            $return = array(
                'status' => '200',
                'message' => 'Bookings List',
                'data' => $allRequests
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'No Requests Found',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
    
    public function getNotifications(){
        $notification = new Notification();        
        $allNotifications = $notification->where('notification_status','Pending')->orderBy('created_at', 'DESC')->get()->toArray();
        if (!empty($allNotifications)) {
            $return = array(
                'status' => '200',
                'message' => 'Notifications List',
                'data' => $allNotifications
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'No Notifications Found',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
    
    public function markNotificationsRead(Request $request){
        
        if (!$request->has('id')) {
            $return = array(
                'status' => '400',
                'message' => 'Id Field is Required',
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
        $notification = new Notification();   
        $update = array(
            'notification_status' => 'Read'
        );
        $allNotifications = $notification->where('id',$request->id)->update($update);
        event(new NotificationEvent('Cancel Request from PNR ' . $request->pnr));
        if (!empty($allNotifications)) {
            $return = array(
                'status' => '200',
                'message' => 'Notifications Marked as Read Successfully',
                'data' => $allNotifications
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Notification not marked as read',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

}
