<?php

namespace App\Http\Controllers\Booking;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Ft_booking;
use App\Ft_booking_response;
use App\Ft_booking_detail;
use App\Helpers\TravelPort;
use App\Helpers\TravelHitit;
use App\Helpers\TravelAirBlue;
use App\Helpers\TravelAirSial;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use DB;
use App\Models\Cancel_booking_request;
use App\Models\Notification;
use App\Events\BookingEvent;
use App\Events\NotificationEvent;
use App\Models\Setting;

class BookingController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        
    }

    public function reserveFlight(Request $request) {
        //Form Validation Rules...
        $rules = [
            'booking_data' => 'required',
            'booking_detail' => 'required',
            'segmentsData' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }
        try {


        $segmentsData = $request->segmentsData;
        $bookingData = $request->booking_data;
        //For AirBlue Round Trip getting Segments
        if (isset($segmentsData[0]) && $segmentsData[0]['provider_type'] == 'airblue') {
            $segmentArrange = array();
            $totalAmount = 0;
            $totalAmountWithCommission = 0;
            $fareBreakdownArry = array();
            foreach ($segmentsData as $segData) {
                $segmentArrange['provider_type'] = $segData['provider_type'];
                $segmentArrange['segments'][] = $segData['segments'];
                foreach ($segData['PTC_FareBreakdowns'] as $fbdown) {
                    $fareBreakdownArry[] = $fbdown;
                }
                $segmentArrange['PTC_FareBreakdowns'] = $fareBreakdownArry;
                $totalAmount = $totalAmount + $segData['pricing_info']['TotalPrice']['Amount'];
                $totalAmountWithCommission = $totalAmountWithCommission + $segData['pricing_info']['TotalPriceWithCommission'];
                $segmentArrange['pricing_info']['TotalPrice']['Amount'] = $totalAmount;
                $segmentArrange['pricing_info']['TotalPriceWithCommission'] = $totalAmountWithCommission;
            }
            $segmentsData = $segmentArrange;
        }

        //For AirSial Round Trip getting Segments
        if (isset($segmentsData[0]) && $segmentsData[0]['provider_type'] == 'airsial') {
            $segmentArrange = array();
            $totalAmount = 0;
            foreach ($segmentsData as $segData) {
                $segmentArrange['provider_type'] = $segData['provider_type'];
                foreach ($segData['segments'] as $key => $seg) {
                    $segmentArrange['segments'][$key] = $seg;
                }

                $totalAmount = $totalAmount + $segData['pricing_info']['TOTAL'];
                $totalAmountWithCom = $totalAmount + $segData['pricing_info']['TotalPriceWithCommission'];
                $segmentArrange['pricing_info']['TOTAL'] = $totalAmount;
                $segmentArrange['pricing_info']['TotalPriceWithCommission'] = $totalAmountWithCom;
            }
            $segmentsData = $segmentArrange;
        }


        if ($segmentsData['provider_type'] == 'travelport') {
            $pricingOBj = new TravelPort();
            $pricingRes = $pricingOBj->air_pricing_req($segmentsData['segments'], $bookingData['adult'], $bookingData['child'], $bookingData['infant']);

            $pricing = new TravelPort();
            $PricingInfoData = $pricing->get_air_segments_pricingRequest($pricingRes);

            $totalAmount = 0;
            if ($PricingInfoData['pricingSol']) {
                $totalAmount = str_replace('PKR', '', $PricingInfoData['pricingSol']['TotalPrice']);
                $setting = new Setting();
                $travelPortSetting = $setting->getSettingByCode('travelport-commission');
                if ($travelPortSetting && $travelPortSetting->setting_value > 0) {
                    $commsionValue = getCommissionValue(str_replace('PKR', '', $PricingInfoData['pricingSol']['ApproximateBasePrice']), $travelPortSetting->setting_value);
                }
                $TotalPriceWithCommission = round($commsionValue + str_replace('PKR', '', $PricingInfoData['pricingSol']['TotalPrice']));
            }
        } elseif ($segmentsData['provider_type'] == 'hitit') {
            $hititData = $request->segmentsData;
            $totalAmount = $hititData['price_info']['pricingOverview']['totalAmount']['value'];
            $TotalPriceWithCommission = $hititData['price_info']['pricingOverview']['TotalPriceWithCommission'];
        } elseif ($segmentsData['provider_type'] == 'airblue') {
            $totalAmount = ($segmentsData['pricing_info']['TotalPrice']['Amount']);
            $TotalPriceWithCommission = ($segmentsData['pricing_info']['TotalPriceWithCommission']);
        } elseif ($segmentsData['provider_type'] == 'airsial') {
            $totalAmount = $segmentsData['pricing_info']['TOTAL'];
            $TotalPriceWithCommission = $segmentsData['pricing_info']['TotalPriceWithCommission'];
        }
        $bookingDetail = $request->booking_detail;


        $booking = new Ft_booking;

        if ($segmentsData['provider_type'] == 'travelport') {
            //Booking Request To TravelPort API...
            $book2 = new TravelPort();
            $bookingRspXml = $book2->air_reservation_req_new($request->input(), $request->input('shipping_detail'), $PricingInfoData, $pricingRes);

            if ($bookingRspXml === null) {
                $return = array(
                    'status' => '400',
                    'message' => 'There is some errors in response of booking',
                    'data' => new \stdClass()
                );
                return response()->json($return);
            }
            $data = $book2->booking_res($bookingRspXml);

            if ($data == 'false') {
                $error = $this->getErrorMessageForBooking($bookingRspXml);
                $return = array(
                    'status' => '400',
                    'message' => $error,
                    'data' => new \stdClass()
                );
                return response()->json($return);
            } else {
                $bookingId = $this->addBookingToDB($bookingDetail, $bookingData, $segmentsData, $totalAmount, $TotalPriceWithCommission);
                if ($segmentsData['provider_type'] == 'travelport' && $data['galilo_pnr'] && $bookingId) {
                    $updateData = array(
                        'pnr' => $data['galilo_pnr']
                    );
                    $booking->where('id', $bookingId)->update($updateData);
                }

                $data['passenger_detail'] = $bookingDetail;
                $data['provider_type'] = $segmentsData['provider_type'];
                //Save Data of Response to Booking Response Table of Database...
                $bookingResponse = new Ft_booking_response();
                $bookingResponse->booking_id = $bookingId;
                $bookingResponse->response = json_encode($data);
                $bookingResponse->save();
                $return = array(
                    'status' => '200',
                    'message' => 'Booking Successfull!',
                    'data' => $data
                );
                if ($bookingId) {
                    $getLatestBooking = $this->getLatestBooking($bookingId);
                        event(new BookingEvent('New Booking Received'));
                }
                return response()->json($return);
            }
        } else if ($segmentsData['provider_type'] == 'hitit') {
            $hitit = new TravelHitit();
            $flight_book_resp = $hitit->flight_booking($request->input());
            if (isset($flight_book_resp['Body']['Fault'])) {
                $message = $flight_book_resp['Body']['Fault']['detail']['CraneFault']['message'];
                $return = array(
                    'status' => '400',
                    'message' => $message,
                    'data' => new \stdClass()
                );
            } else {
                $booking = $flight_book_resp['Body']['CreateBookingResponse']['AirBookingResponse']['airBookingList'];
                $flightt = $booking['airReservation']['airItinerary']['bookOriginDestinationOptions']['bookOriginDestinationOptionList'];

                $flight = array_exists($flightt);

                $pnr_ref_id = $booking['airReservation']['bookingReferenceIDList'];

                $total_amount = $booking['ticketInfo']['totalAmount'];
                $segmentss = $booking['ticketInfo']['ticketItemList'];
                if (count($flightt) > 1) {
                    foreach ($flightt as $flt) {
                        $bookingClassData = isset($flt['bookFlightSegmentList'][0]) ? $flt['bookFlightSegmentList'][0]['bookingClass'] : $flt['bookFlightSegmentList']['bookingClass'];
                        $data['cabinClass'] = isset($bookingClassData[0]) ? $bookingClassData : array($bookingClassData);
                    }
                } else {
                    $bookingClassData = isset($flightt['bookFlightSegmentList'][0]) ? $flightt['bookFlightSegmentList'][0]['bookingClass'] : $flightt['bookFlightSegmentList']['bookingClass'];
                    $data['cabinClass'] = isset($bookingClassData[0]) ? $bookingClassData : array($bookingClassData);
                }
                $segments = array_exists($segmentss);
//                    $flightSegments = $this->getHititSegments($segments, 'main_booking');
                $flightSegments = $this->getHititSegmentsForBooking($flight);
                $pricing_info = array(
                    'total_amount' => (int) $total_amount['value'],
                    'currency_code' => $total_amount['currency']['code'],
                    'TotalPriceWithCommission' => $TotalPriceWithCommission,
                );
                $data['provider_type'] = $segmentsData['provider_type'];
                $data['LocatorCode'] = $pnr_ref_id['referenceID'];
                $data['pricing_info'] = $pricing_info;
                $data['segments'] = $flightSegments;
                $data['passenger_detail'] = $bookingDetail;
                $data['used_for_ticket_reservation_code'] = $pnr_ref_id['referenceID'];
                $data['pnr'] = $pnr_ref_id['ID'];
                $data['ticket_time_limt'] = date('d F Y h:i A', strtotime($booking['airReservation']['ticketTimeLimit']));


                $bookingId = $this->addBookingToDB($bookingDetail, $bookingData, $segmentsData, $totalAmount, $TotalPriceWithCommission);
                if ($segmentsData['provider_type'] == 'hitit' && $pnr_ref_id['ID'] && $bookingId) {
                    $updateData = array(
                        'pnr' => $pnr_ref_id['ID']
                    );
                    $bookingobj = new Ft_booking;
                    $bookingobj->where('id', $bookingId)->update($updateData);
                }

                //Save Data of Response to Booking Response Table of Database...
                $bookingResponse = new Ft_booking_response();
                $bookingResponse->booking_id = $bookingId;
                $bookingResponse->response = json_encode($data);
                $bookingResponse->save();
                $return = array(
                    'status' => '200',
                    'message' => 'Booking Successfull!',
                    'data' => $data
                );

                if ($bookingId) {
                    $getLatestBooking = $this->getLatestBooking($bookingId);
                        event(new BookingEvent('New Booking Received'));
                }

                return response()->json($return);
            }
        } else if ($segmentsData['provider_type'] == 'airblue') {

            $airblue = new TravelAirBlue();
            $completeRequestData = $request->input();
            $completeRequestData['segmentsData'] = $segmentsData;
            unset($completeRequestData['segmentsData']['pricing_info']['TotalPriceWithCommission']);
//            print_r($completeRequestData); die();
            $bookingAirblueXml = $airblue->booking_request($completeRequestData);

            $bookingAirblueData = $airblue->Booking_Data($bookingAirblueXml);

            //$bookingAirblueData['data']['passenger_detail'] = $bookingDetail;
            if ($bookingAirblueData['status'] == 'true') {
                $bookingId = $this->addBookingToDB($bookingDetail, $bookingData, $segmentsData, $totalAmount, $TotalPriceWithCommission);
                if ($segmentsData['provider_type'] == 'airblue' && $bookingAirblueData['data']['BookingReferenceID']['ID'] && $bookingId) {
                    $updateData = array(
                        'pnr' => $bookingAirblueData['data']['BookingReferenceID']['ID']
                    );
                    $bookingobj = new Ft_booking;
                    $bookingobj->where('id', $bookingId)->update($updateData);
                }

                $bookingAirblueData['data']['pricing_info']['TotalPriceWithCommission'] = $TotalPriceWithCommission;
                //Save Data of Response to Booking Response Table of Database...
                $bookingResponse = new Ft_booking_response();
                $bookingResponse->booking_id = $bookingId;
                $bookingResponse->response = json_encode($bookingAirblueData['data']);
                $bookingResponse->save();

                $return = array(
                    'status' => '200',
                    'message' => 'Booking Successful!',
                    'data' => $bookingAirblueData['data']
                );
                $getLatestBooking = $this->getLatestBooking($bookingId);
                if ($bookingId) {
                    $getLatestBooking = $this->getLatestBooking($bookingId);
//                    event(new BookingEvent('New Booking Received'));
                }
            } else {
                $return = array(
                    'status' => '400',
                    'message' => $bookingAirblueData['message'],
                    'data' => new \stdClass()
                );
            }

            return response()->json($return);
        } else if ($segmentsData['provider_type'] == 'airsial') {
            $airsial = new TravelAirSial();
            $completeRequestData = $request->input();
            $completeRequestData['segmentsData'] = $segmentsData;
            $bookingAirsialRes = $airsial->booking_request($completeRequestData);

            $bookingAirsialData['provider_type'] = $segmentsData['provider_type'];
            $bookingAirsialData['segments'] = $completeRequestData['segmentsData']['segments'];
            $bookingAirsialData['passenger_detail'] = $bookingDetail;
            if ($bookingAirsialRes['status'] == '200') {
                $bookingAirsialData['pnr'] = $bookingAirsialRes['message']->Response->Data->PNR;
                $bookingId = $this->addBookingToDB($bookingDetail, $bookingData, $segmentsData, $totalAmount, $TotalPriceWithCommission);
                if ($segmentsData['provider_type'] == 'airsial' && $bookingAirsialData['pnr'] && $bookingId) {
                    $updateData = array(
                        'pnr' => $bookingAirsialData['pnr']
                    );
                    $bookingobj = new Ft_booking;
                    $bookingobj->where('id', $bookingId)->update($updateData);
                }
                $bookingAirsialData['validTill'] = $bookingAirsialRes['message']->Response->Data->validTill;
                $bookingAirsialData['total_amount'] = $totalAmount;
                $bookingAirsialData['TotalPriceWithCommission'] = $TotalPriceWithCommission;

                //Save Data of Response to Booking Response Table of Database...
                $bookingResponse = new Ft_booking_response();
                $bookingResponse->booking_id = $bookingId;
                $bookingResponse->response = json_encode($bookingAirsialData);
                $bookingResponse->save();

                $return = array(
                    'status' => '200',
                    'message' => 'Booking Successful!',
                    'data' => $bookingAirsialData
                );
                $getLatestBooking = $this->getLatestBooking($bookingId);
                if ($bookingId) {
                    $getLatestBooking = $this->getLatestBooking($bookingId);
                    event(new BookingEvent('New Booking Received'));
                }
            } else {

                $return = array(
                    'status' => '400',
                    'message' => $bookingAirsialRes['message'],
                    'data' => new \stdClass()
                );
            }

            return response()->json($return);
        }
        } catch (\Exception $ex) {
            $obj = new \stdClass();
            $return = array(
                'status' => '400',
                'message' => $ex->getMessage(),
                'data' => $obj
            );
            return response()->json($return);
        }
    }

    public function getLatestBooking($bookingId) {
        $booking = new Ft_booking();
        $bookingData = $booking->where('id', $bookingId)->first()->toArray();
        if ($bookingData && !empty($bookingData)) {

            unset($bookingData['title']);
            unset($bookingData['f_name']);
            unset($bookingData['l_name']);

            $bookingDetailObj = new Ft_booking_detail();
            $bookingRespObj = new Ft_booking_response();
            $bookingDetail = $bookingDetailObj->where('booking_id', $bookingData['id'])->get()->toArray();
            $bookingResponse = $bookingRespObj->where('booking_id', $bookingData['id'])->first();
            $bkRsp = array();
            if ($bookingResponse !== null) {
                $bkRsp = json_decode($bookingResponse->response);
            }
            $bookingData['booking_detail'] = $bookingDetail;
            $bookingData['booking_response'] = $bkRsp;
        }
        return ($bookingData);
    }

    public function addBookingToDB($bookingDetail, $bookingData, $segmentsData, $totalAmount, $TotalPriceWithCommission) {
        $booking = new Ft_booking;
        $booking->email = $bookingData['email'];
        $booking->phone_number = $bookingData['phone_number'];
        $booking->total_amount = isset($totalAmount) ? $totalAmount : 0;
        $booking->total_amount_with_commission = isset($TotalPriceWithCommission) ? $TotalPriceWithCommission : 0;
        $booking->payment_method = $bookingData['payment_method'];
        $booking->api_type = $segmentsData['provider_type'];
        $booking->save();

        $bookingId = $booking->id;
        if ($bookingId) {
            $bookingCode = getConstant('BOOKING_CODE') . $bookingId;
            $updateData = array(
                'booking_code' => $bookingCode
            );
            $booking->where('id', $bookingId)->update($updateData);
            $this->addBookingDetail($bookingDetail, $bookingId);
        }
        return $bookingId;
    }

    public function getErrorMessageForBooking($bookingRspXml) {
        $error = "";
        $flights = $bookingRspXml;
        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);

        foreach ($Results->children('SOAP', true) as $fault) {
            if (strcmp($fault->getName(), 'Fault') == 0) {
                foreach ($fault->children() as $message) {
                    if (strcmp($message->getName(), 'faultstring') == 0) {
                        $error = (string) $message;
                    }
                }
            }
        }
        return $error;
    }

    public function getErrorMessageForBookingCancel($data) {

        $error = "";
        $flights = $data;
        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);

        foreach ($Results->children('SOAP', true) as $fault) {
            if (strcmp($fault->getName(), 'Fault') == 0) {
                foreach ($fault->children() as $message) {
                    if (strcmp($message->getName(), 'faultstring') == 0) {
                        $error = (string) $message;
                    }
                }
            }
        }
        return $error;
    }

    public function addBookingDetail($data, $booking_id) {
        if (!empty($data)) {
            foreach ($data as $bk_detail) {
                $bookingDetail = new Ft_booking_detail();
                $bookingDetail->booking_id = $booking_id;
                $bookingDetail->title = $bk_detail['title'];
                $bookingDetail->f_name = $bk_detail['firstName'];
                $bookingDetail->l_name = $bk_detail['lastName'];
                $bookingDetail->nationality = $bk_detail['nationality'];
                $bookingDetail->dob = date('Y-m-d', strtotime($bk_detail['dob_day'] . '-' . $bk_detail['dob_month'] . '-' . $bk_detail['dob_year']));
                $bookingDetail->passenger_type = $bk_detail['passenger_type'];
                $bookingDetail->passport_number = isset($bk_detail['passport_number']) ? $bk_detail['passport_number'] : "";
                $bookingDetail->passport_type = isset($bk_detail['passport_type']) ? $bk_detail['passport_type'] : "";
                //$bookingDetail->passport_expiry_date = $bk_detail['expiry_date'] ? $bk_detail['expiry_date'] : "";
                $bookingDetail->passport_expiry_date = isset($bk_detail['exp_day']) ? date('Y-m-d', strtotime($bk_detail['exp_day'] . '-' . $bk_detail['exp_month'] . '-' . $bk_detail['exp_year'])) : "";
                $bookingDetail->save();
            }
        }
        return TRUE;
    }

    public function issueTicket(Request $request) {
        $rules = [
            'pnr' => 'required',
            'locator_code' => 'required',
            'pricing_info' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }
        $reservation_code = $request->locator_code;
        $pnr = $request->pnr;
        $tp = new TravelPort();
        $ticketData = $tp->ticket_req($reservation_code, $request->pricing_info);
        if ($ticketData['status'] !== 'false') {
            //update ticket number in database in response table
            $booking = new Ft_booking();
            $bookingRsp = new Ft_booking_response();
            $bookingData = $booking->where('pnr', $pnr)->first();
            if ($bookingData !== null) {
                $bookingResponse = $bookingRsp->where('booking_id', $bookingData->id)->first();
                if ($bookingResponse !== null) {
                    $bookingRspArr = json_decode($bookingResponse->response);
                    $bookingRspArray = (array) $bookingRspArr;
                    $bookingRspArray['ticket_numbers'] = $ticketData['data'];
                    $update = array(
                        'response' => $bookingRspArray
                    );
                    $updateBooking = array(
                        'booking_status' => 'Completed'
                    );
                    if ($request->has('payment')) {
                        $updateData = array(
                            'payment_status' => 'Completed'
                        );
                        $booking->where('id', $bookingData->id)->update($updateData);
                    }
                    $booking->where('id', $bookingData->id)->update($updateBooking);
                    $bookingRsp->where('booking_id', $bookingData->id)->update($update);
                    event(new BookingEvent('Ticket Issued Successfully'));
                }
            }
            $return = array(
                'status' => '200',
                'message' => 'Ticket Issued Successfully',
                'data' => new \stdClass()
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => $ticketData['message'],
                'data' => new \stdClass()
            );
        }

        return response()->json($return);
    }

    public function issueTicketHitit(Request $request) {
        $hitit = new TravelHitit();
        $resp = $hitit->ticket_issue($request->pnr, $request->reference_id, $request->price);
        if ($resp && !empty($resp)) {
            if (isset($resp['Body']['Fault'])) {
                $message = $resp['Body']['Fault']['detail']['CraneFault']['message'];

                $return = array(
                    'status' => '400',
                    'message' => $message,
                    'data' => new \stdClass()
                );
                return response()->json($return);
            } else {
                $pnr = $request->pnr;
                if (isset($resp['Body']['TicketReservationResponse']['AirTicketReservationResponse']['airBookingList'])) {
                    $booking = $resp['Body']['TicketReservationResponse']['AirTicketReservationResponse']['airBookingList'];
                    if (isset($booking['airReservation']['airItinerary']['bookOriginDestinationOptions']['bookOriginDestinationOptionList'])) {
                        $flightt = $booking['airReservation']['airItinerary']['bookOriginDestinationOptions']['bookOriginDestinationOptionList'];
                        $pnr_ref_id = $booking['airReservation']['bookingReferenceIDList'];
                        $ticketArray = array();
                        if (isset($booking['ticketInfo']) && isset($booking['ticketInfo']['ticketItemList'])) {
                            $ticketInfo = isset($booking['ticketInfo']['ticketItemList'][0]) ? $booking['ticketInfo']['ticketItemList'] : array($booking['ticketInfo']['ticketItemList']);

                            foreach ($ticketInfo as $ticketkey => $ticket) {
                                $ticketArr['ticket_number'] = $ticket['ticketDocumentNbr'];
                                $ticketArray[] = $ticketArr;
                            }
                        }

                        $booking = new Ft_booking();
                        $bookingRsp = new Ft_booking_response();
                        $bookingData = $booking->where('pnr', $pnr)->first();

                        if ($bookingData !== null) {

                            $bookingResponse = $bookingRsp->where('booking_id', $bookingData->id)->first();
                            if ($bookingResponse !== null) {
                                $bookingRspArr = json_decode($bookingResponse->response);
                                $bookingRspArray = (array) $bookingRspArr;
                                $bookingRspArray['tickets'] = $ticketArray;

                                $update = array(
                                    'response' => $bookingRspArray
                                );
                                $updateBooking = array(
                                    'booking_status' => 'Completed'
                                );
                                if ($request->has('payment')) {
                                    $updateData = array(
                                        'payment_status' => 'Completed'
                                    );
                                    $booking->where('id', $bookingData->id)->update($updateData);
                                }
                                $booking->where('id', $bookingData->id)->update($updateBooking);
                                $bookingRsp->where('booking_id', $bookingData->id)->update($update);
                                event(new BookingEvent('Ticket Issued Successfully'));
                            }
                        }
                    }
                }

                $return = array(
                    'status' => '200',
                    'message' => 'Ticket Generation Successfull!',
                    'data' => new \stdClass()
                );
                return response()->json($return);
            }
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Ticket Generation Failed',
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
    }

    public function issueTicketAirblue(Request $request) {
        $rules = [
            'pnr' => 'required',
            'instance' => 'required',
            'total_amount' => 'required'
        ];

        //Check validation for the inputs and return response in case error *occured*
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }
        $pnr = $request->pnr;
        $instance = $request->instance;
        $totalAmount = $request->total_amount;
        $airblue = new TravelAirBlue();
        $ticketData = $airblue->ticket_generate($pnr, $instance, $totalAmount);
        $ticketArray = $airblue->getTicketArray($ticketData);
        if ($ticketArray['status'] == 'true') {
            $booking = new Ft_booking();
            $bookingRsp = new Ft_booking_response();
            $bookingData = $booking->where('pnr', $pnr)->first();

            if ($bookingData !== null) {

                $bookingResponse = $bookingRsp->where('booking_id', $bookingData->id)->first();
                if ($bookingResponse !== null) {
                    $bookingRspArr = json_decode($bookingResponse->response);
                    $bookingRspArray = (array) $bookingRspArr;
                    $bookingRspArray['ticketing'] = $ticketArray['data'];

                    $update = array(
                        'response' => $bookingRspArray
                    );
                    $updateBooking = array(
                        'booking_status' => 'Completed'
                    );
                    if ($request->has('payment')) {
                        $updateData = array(
                            'payment_status' => 'Completed'
                        );
                        $booking->where('id', $bookingData->id)->update($updateData);
                    }
                    $booking->where('id', $bookingData->id)->update($updateBooking);
                    $bookingRsp->where('booking_id', $bookingData->id)->update($update);
                    event(new BookingEvent('Ticket Issued Successfully'));
                }
            }
            $return = array(
                'status' => '200',
                'message' => 'Ticket Generated Successfully',
                'data' => new \stdClass()
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => $ticketArray['message'],
                'data' => new \stdClass()
            );
        }

        return response()->json($return);
    }

    public function issueTicketAirSial(Request $request) {
        $rules = [
            'pnr' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }
        $pnr = $request->pnr;

        $airsial = new TravelAirSial();
        $ticketData = $airsial->ticket_generate($pnr);
        if ($ticketData['status'] == 'true') {

            $airsial = new TravelAirSial();
            $airSialBookingData = $airsial->getBookingByPNR($pnr);
            if ($airSialBookingData['status'] == '200') {
                $airsialData = $airsial->Booking_Data($airSialBookingData['data']);
                $booking = new Ft_booking();
                $bookingRsp = new Ft_booking_response();
                $bookingData = $booking->where('pnr', $pnr)->first();

                if ($bookingData !== null) {
                    $bookingResponse = $bookingRsp->where('booking_id', $bookingData->id)->first();
                    if ($bookingResponse !== null) {
                        $bookingRspArr = json_decode($bookingResponse->response);
                        $bookingRspArray = (array) $bookingRspArr;
                        $bookingRspArray['passenger_detail'] = $airsialData['passenger_detail'];
                        $update = array(
                            'response' => $bookingRspArray
                        );
                        $updateBooking = array(
                            'booking_status' => 'Completed'
                        );
                        if ($request->has('payment')) {
                            $updateData = array(
                                'payment_status' => 'Completed'
                            );
                            $booking->where('id', $bookingData->id)->update($updateData);
                        }
                        $booking->where('id', $bookingData->id)->update($updateBooking);
                        $bookingRsp->where('booking_id', $bookingData->id)->update($update);
                        event(new BookingEvent('Ticket Issued Successfully'));
                    }
                }
            }

            $return = array(
                'status' => '200',
                'message' => $ticketData['message'],
                'data' => new \stdClass()
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => $ticketData['message'],
                'data' => new \stdClass()
            );
        }

        return response()->json($return);
    }

    //Function to cancel Reservation of Flight using reservation code...
    public function cancelBooking(Request $request) {
        $rules = [
            'reservation_code' => 'required',
            'provider_type' => 'required',
            'pnr' => 'required'
        ];

        $pnr = $request->pnr;
        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }
        $reservation_code = $request->reservation_code;

        if ($request->provider_type == 'travelport') {
            $travelport = new TravelPort();
            $cancel = $travelport->cancel_booking_req($reservation_code);
            if (isset($cancel['status']) && $cancel['status'] == 'false') {
                $error = $this->getErrorMessageForBookingCancel($cancel['xml_data']);
                $return = array(
                    'status' => '400',
                    'message' => $error,
                    'data' => new \stdClass()
                );
                return response()->json($return);
            } else {
                if ($cancel['Body']['UniversalRecordCancelRsp']['ProviderReservationStatus']['@attributes']['Cancelled'] == TRUE) {
                    $this->updateCancelRequestStatus($pnr);
                    $return = array(
                        'status' => '200',
                        'message' => 'Reservations Cancelled Successfully',
                        'data' => new \stdClass()
                    );
                    return response()->json($return);
                } else {
                    $return = array(
                        'status' => '400',
                        'message' => 'Your Request to cancel reservation was rejected.',
                        'data' => new \stdClass()
                    );
                    return response()->json($return);
                }
            }
        } else if ($request->provider_type == 'hitit') {
            if (!$request->has('pnr')) {
                $return = array(
                    'status' => '400',
                    'message' => 'pnr is required for cancel booking from hitit',
                    'data' => new \stdClass()
                );
                return response()->json($return);
            }
            $pnr = $request->input('pnr');
            $hitit = new TravelHitit();
            $cancel = $hitit->cancel_booking_hitit_req($reservation_code, $pnr);
            if (!empty($cancel)) {
                if (!(isset($cancel['Body']['Fault']))) {
                    $this->updateCancelRequestStatus($pnr);
                    $return = array(
                        'status' => '200',
                        'message' => 'Booking Cancelled Successfully',
                        'data' => new \stdClass()
                    );
                    return response()->json($return);
                }
            } else {
                $return = array(
                    'status' => '400',
                    'message' => 'Booking Cancelled Failed',
                    'data' => new \stdClass()
                );
                return response()->json($return);
            }
        } else if ($request->provider_type == 'airblue') {
            $this->updateCancelRequestStatus($pnr);
            $return = array(
                'status' => '200',
                'message' => 'Reservations Cancelled Successfully',
                'data' => new \stdClass()
            );
            return response()->json($return);
        } else if ($request->provider_type == 'airsial') {
            $this->updateCancelRequestStatus($pnr);
            $return = array(
                'status' => '200',
                'message' => 'Reservations Cancelled Successfully',
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
    }

    public function updateCancelRequestStatus($pnr) {
        $crObj = new Cancel_booking_request();
        $bkObj = new Ft_booking();
        $update = array(
            'status' => 'Cancelled'
        );
        $update1 = array(
            'booking_status' => 'Cancelled'
        );
        $updated = $crObj->where('pnr', $pnr)->update($update);
        $updated = $bkObj->where('pnr', $pnr)->update($update1);
        return True;
    }

    public function getBookingByPnr(Request $request) {
        $rules = [
            'pnr' => 'required',
            'last_name' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
        try {
            //Get Data from POST Request and store in Variable
            $pnr = $request->pnr;
            $lastName = $request->last_name;


            //Get Booking Data of TravelPort Using PNR.
            $book = new TravelPort();
            $result = $book->pnr_retrive_req($pnr);
            $data = $book->booking_res($result);
            if ($data !== 'false') {
                $data['provider_type'] = 'travelport';
                if (strtolower($data['passenger_detail'][0]['lastName']) != strtolower($lastName)) {
                    $return = array(
                        'status' => '400',
                        'message' => 'Last Name Does Not Match',
                        'data' => new \stdClass()
                    );
                } else {
                    $return = array(
                        'status' => '200',
                        'message' => 'Booking Retrieved Successfully',
                        'data' => $data
                    );
                }
                return response()->json($return);
            }
            $data = array();


            //Get Booking By PNR of HITIT
            $hitit = new TravelHitit();
            $flight_book_resp = $hitit->read_booking($pnr);
            if (!isset($flight_book_resp['Body']['Fault'])) {
                if (isset($flight_book_resp['Body']['ReadBookingResponse']['AirBookingResponse']['airBookingList'])) {
                    $booking = $flight_book_resp['Body']['ReadBookingResponse']['AirBookingResponse']['airBookingList'];


                    if (isset($booking['airReservation']['airItinerary']['bookOriginDestinationOptions']['bookOriginDestinationOptionList'])) {
                        $flightt = $booking['airReservation']['airItinerary']['bookOriginDestinationOptions']['bookOriginDestinationOptionList'];
                        $pnr_ref_id = $booking['airReservation']['bookingReferenceIDList'];

                        $segments = isset($flightt[0]) ? $flightt : array($flightt);

                        $segmentsArr = array();
                        foreach ($segments as $segment) {

                            $segmentsArr[] = $segment['bookFlightSegmentList']['flightSegment'];
                            $bookingClassData = $segment['bookFlightSegmentList']['bookingClass'];
                            $data['cabinClass'] = isset($bookingClassData[0]) ? $bookingClassData : array($bookingClassData);
                        }
                        $flightSegments = $this->getHititSegments($segmentsArr, 'by_pnr');
                        $passengerDetail = array();
                        if (isset($booking['airReservation']['airTravelerList']) && !empty($booking['airReservation']['airTravelerList'])) {
                            $travelerData = $booking['airReservation']['airTravelerList'];
                            $passengerDetail = $this->getPassengerDataHitit($travelerData);
                        }

                        $ticketArray = array();

                        if (isset($booking['ticketInfo']) && isset($booking['ticketInfo']['ticketItemList'])) {
                            $ticketInfo = isset($booking['ticketInfo']['ticketItemList'][0]) ? $booking['ticketInfo']['ticketItemList'] : array($booking['ticketInfo']['ticketItemList']);

                            foreach ($ticketInfo as $ticketkey => $ticket) {
                                $ticketArr['ticket_number'] = $ticket['ticketDocumentNbr'];
                                $ticketArray[] = $ticketArr;
                            }
                        }

                        $data['passenger_detail'] = $passengerDetail;
                        $data['provider_type'] = 'hitit';
                        $data['LocatorCode'] = $pnr_ref_id['referenceID'];
                        $data['segments'] = $flightSegments;
                        $data['used_for_ticket_reservation_code'] = $pnr_ref_id['referenceID'];
                        $data['pnr'] = $pnr_ref_id['ID'];
                        $data['tickets'] = $ticketArray;

                        //Save Data of Response to Booking Response Table of Database...

                        if (strtolower($data['passenger_detail'][0]['lastName']) != strtolower($lastName)) {
                            $return = array(
                                'status' => '400',
                                'message' => 'Last Name Does Not Match',
                                'data' => new \stdClass()
                            );
                        } else {
                            $return = array(
                                'status' => '200',
                                'message' => 'Booking Retrieved Successfully',
                                'data' => $data
                            );
                        }
                    } else {
                        $return = array(
                            'status' => '400',
                            'message' => 'PNR Expired',
                            'data' => new \stdClass()
                        );
                    }

                    return response()->json($return);
                }
            }

            //Get Booking Data of Airblue using PNR..
            $airblue = new TravelAirBlue();
            $airblueXml = $airblue->getBookingByPNR($pnr);
            $bookingAirblueData = $airblue->Booking_Data($airblueXml);

            if ($bookingAirblueData['status'] == 'true') {

                if (strtolower($bookingAirblueData['data']['passenger_detail'][0]['lastName']) != strtolower($lastName)) {
                    $return = array(
                        'status' => '400',
                        'message' => 'Last Name Does Not Match',
                        'data' => new \stdClass()
                    );
                } else {
                    $return = array(
                        'status' => '200',
                        'message' => 'Booking Retrieved Successfully',
                        'data' => $bookingAirblueData['data']
                    );
                }
//            $return = array(
//                'status' => '200',
//                'message' => 'Booking Retrieved Successfully!',
//                'data' => $bookingAirblueData['data']
//            );
                return response()->json($return);
            }


            $airsial = new TravelAirSial();
            $airSialBookingData = $airsial->getBookingByPNR($pnr);
            if ($airSialBookingData['status'] == '200') {
                $airsialData = $airsial->Booking_Data($airSialBookingData['data']);
                $airsialData['pnr'] = $pnr;

                if (strpos(strtolower($airsialData['passenger_detail'][0]['name']), strtolower($lastName)) === false) {
                    $return = array(
                        'status' => '400',
                        'message' => 'Last Name Does Not Match',
                        'data' => new \stdClass()
                    );
                } else {
                    $return = array(
                        'status' => '200',
                        'message' => 'Booking Retrieved Successfully',
                        'data' => $airsialData
                    );
                }



                return response()->json($return);
            }
            //REturn Error Message if PNR does not match with any of the provider detail
            $return = array(
                'status' => '400',
                'message' => 'PNR Does not Exist!',
                'data' => new \stdClass()
            );
            return response()->json($return);
        } catch (\Exception $ex) {
            $return = array(
                'status' => '400',
                'message' => $ex->getMessage(),
                'result' => new \stdClass
            );
            return response()->json($return);
        }
    }

    public function cancelrequest(Request $request) {
        $rules = [
            'pnr' => 'required',
            'provider_type' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => new \stdClass()
            );
            return response()->json($return);
        }

        $cancelReqObj = new Cancel_booking_request();
        $isExist = $cancelReqObj->where(['pnr' => $request->pnr, 'status' => 'Pending'])->first();
        if ($isExist == null) {
            $cancelReqObj->pnr = $request->pnr;
            $cancelReqObj->ticket_reservation_code = $request->ticket_reservation_code;
            $cancelReqObj->provider_type = $request->provider_type;
            $cancelReqObj->created_at = date('Y-m-d H:i:s');
            $cancelReqObj->updated_at = date('Y-m-d H:i:s');
            if ($cancelReqObj->save()) {
                $notification = new Notification();
                $notification->notification_text = 'Booking Cancel Request From PNR: ' . $request->pnr;
                $notification->notification_type = 'cancel_booking';
                $notification->notification_status = 'Pending';
                $notification->created_at = date('Y-m-d H:i:s');
                $notification->updated_at = date('Y-m-d H:i:s');
                $notification->save();

                event(new NotificationEvent('Cancel Request from PNR ' . $request->pnr));
                $return = array(
                    'status' => '200',
                    'message' => 'Booking Cancel Request Successful',
                    'data' => new \stdClass()
                );
            }
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Booking Cancel Request Already Exist!',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

    public function getBookingByPnrUsingDB(Request $request) {
        $pnr = $request->pnr;
        if ($pnr == '') {
            $return = array(['status' => '400', 'message' => 'PNR Fields required', 'data' => new \stdClass]);
            return response()->json($return);
        }
        $booking = new Ft_booking();
        $bookingData = $booking->where('pnr', $pnr)->first();
        if ($bookingData !== null) {
            $bookingRsp = new Ft_booking_response();
            $bookingRspData = $bookingRsp->where('booking_id', $bookingData->id)->first();
            if ($bookingRspData !== null) {
                $data = json_decode($bookingRspData->response);
                $return = array(
                    'status' => '200',
                    'message' => 'Booking Successfully Retrieved',
                    'data' => $data
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => 'Booking Against This PNR not Exist',
                    'data' => new \stdClass()
                );
            }
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Booking Against This PNR not Exist',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

    public function getPassengerDataHitit($travelerData) {
        $TravelerArray = array();
        $travelerData = isset($travelerData[0]) ? $travelerData : array($travelerData);
        foreach ($travelerData as $traveler) {
            $travelerArr['title'] = $traveler['personName']['nameTitle'];
            $travelerArr['firstName'] = $traveler['personName']['givenName'];
            $travelerArr['lastName'] = $traveler['personName']['surname'];
            if (isset($traveler['documentInfoList'])) {
                if (isset($traveler['documentInfoList']['docHolderNationality']) && isset($traveler['documentInfoList']['docExpireDate'])) {
                    $travelerArr['nationality'] = $traveler['documentInfoList']['docHolderNationality'];
                    $travelerArr['passport_number'] = $traveler['documentInfoList']['docID'];
                    $travelerArr['exp_date'] = $traveler['documentInfoList']['docExpireDate'];
                } else if (isset($traveler['documentInfoList']['docID'])) {
                    $travelerArr['cnic'] = $traveler['documentInfoList']['docID'];
                }
            }
            $TravelerArray[] = $travelerArr;
        }
        return $TravelerArray;
    }

    //This Function checks the form validation and if fails return validation errors...
    public function checkValidation($request, $rules) {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return $errors;
        } else {
            return False;
        }
    }

    public function getHititSegments($segments, $request_type) {


        $flightArray = array();

        if (!empty($segments)) {
            foreach ($segments as $segment) {
                //$segment = isset($segment[0])?$segment:array($segment);



                if ($request_type != "by_pnr") {
                    $segmentsCoupon = isset($segment['couponInfoList'][0]) ? $segment['couponInfoList'] : array($segment['couponInfoList']);
                } else {
                    $segmentsCoupon = isset($segment[0]) ? $segment : array($segment);
                }



                foreach ($segmentsCoupon as $segmentsd) {
                    if ($request_type != "by_pnr") {
                        $segment = $segmentsd['couponFlightSegment']['flightSegment'];
                    }
                    $segmentArr['Carrier'] = $segment['airline']['code'];
//            $segmentArr['companyFullName'] = $segment['airline']['companyFullName'];
//            $segmentArr['companyShortName'] = $segment['airline']['companyShortName'];
                    $segmentArr['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['airline']['code'] . '.png';
                    $tp = new TravelPort();
                    $segmentArr['airline_name'] = $tp->getAirline($segment['airline']['code']);
                    $segmentArr['FlightNumber'] = $segment['flightNumber'];
                    $segmentArr['Origin'] = $segment['departureAirport']['cityInfo']['city']['locationCode'];
                    $segmentArr['Origin_city'] = $segment['departureAirport']['cityInfo']['city']['locationName'];
                    $segmentArr['Origin_city_language'] = $segment['departureAirport']['cityInfo']['city']['locationNameLanguage'];
                    $segmentArr['origin_city_name'] = $tp->getCity($segment['departureAirport']['cityInfo']['city']['locationCode']);

                    $segmentArr['Origin_country_code'] = $segment['departureAirport']['cityInfo']['country']['locationCode'];
                    $segmentArr['Origin_country_name'] = $segment['departureAirport']['cityInfo']['country']['locationName'];
                    $segmentArr['Origin_country_language'] = $segment['departureAirport']['cityInfo']['country']['locationNameLanguage'];
                    $segmentArr['Origin_country_currency'] = $segment['departureAirport']['cityInfo']['country']['currency'];
                    $segmentArr['Origin_codeContext'] = $segment['departureAirport']['codeContext'];
                    $segmentArr['Origin_language'] = $segment['departureAirport']['language'];
                    $segmentArr['Origin_locationCode'] = $segment['departureAirport']['locationCode'];
                    $segmentArr['Origin_locationName'] = $segment['departureAirport']['locationName'];

                    $segmentArr['Destination'] = $segment['arrivalAirport']['cityInfo']['city']['locationCode'];
                    $segmentArr['Destination_city'] = $segment['arrivalAirport']['cityInfo']['city']['locationName'];
                    $segmentArr['Destination_language'] = $segment['arrivalAirport']['cityInfo']['city']['locationNameLanguage'];

                    $segmentArr['Destination_country_code'] = $segment['arrivalAirport']['cityInfo']['country']['locationCode'];
                    $segmentArr['Destination_country'] = $segment['arrivalAirport']['cityInfo']['country']['locationName'];
                    $segmentArr['Destination_country_language'] = $segment['arrivalAirport']['cityInfo']['country']['locationNameLanguage'];
                    $segmentArr['Destination_currency'] = $segment['arrivalAirport']['cityInfo']['country']['currency'];
                    $segmentArr['Destination_codeContext'] = $segment['arrivalAirport']['codeContext'];
                    $segmentArr['Destination_main_language'] = $segment['arrivalAirport']['language'];
                    $segmentArr['Destination_locationCode'] = $segment['arrivalAirport']['locationCode'];
                    $segmentArr['Destination_locationName'] = $segment['arrivalAirport']['locationName'];


                    $segmentArr['destination_city_name'] = $tp->getCity($segment['arrivalAirport']['cityInfo']['city']['locationCode']);
                    $segmentArr['DepartureTime'] = $segment['departureDateTime'];
                    //$segmentArr['departureDateTimeUTC'] = $segment['departureDateTimeUTC'];
                    $segmentArr['ArrivalTime'] = $segment['arrivalDateTime'];
                    //$segmentArr['arrivalDateTimeUTC'] = $segment['arrivalDateTimeUTC'];
                    $segmentArr['FlightTime'] = $this->hititTimeSet($segment['journeyDuration']);
                    $segmentArr['ondControlled'] = $segment['ondControlled'];
                    $segmentArr['codeshare'] = $segment['codeshare'];
                    $segmentArr['distance'] = $segment['distance'];
                    $segmentArr['Equipment'] = $segment['equipment'];
                    $segmentArr['FlightNotes'] = empty($segment['flightNotes']) ? array() : $segment['flightNotes'];
                    $segmentArr['flownMileageQty'] = $segment['flownMileageQty'];
                    $segmentArr['onTimeRate'] = $segment['onTimeRate'];
                    $segmentArr['secureFlightDataRequired'] = $segment['secureFlightDataRequired'];
                    $segmentArr['stopQuantity'] = $segment['stopQuantity'];


                    $flightArray[] = $segmentArr;
                }
            }
        }
        return $flightArray;
    }

    public function getHititSegmentsForBooking($flight) {


        $flightArray = array();

        if (!empty($flight)) {
            foreach ($flight as $segmentsd) {
                $segment = $segmentsd['bookFlightSegmentList'];
                $segmentArr['Carrier'] = $segment['flightSegment']['airline']['code'];
                $segmentArr['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['flightSegment']['airline']['code'] . '.png';
                $tp = new TravelPort();
                $segmentArr['airline_name'] = $tp->getAirline($segment['flightSegment']['airline']['code']);
                $segmentArr['FlightNumber'] = $segment['flightSegment']['flightNumber'];
                $segmentArr['Origin'] = $segment['flightSegment']['departureAirport']['cityInfo']['city']['locationCode'];
                $segmentArr['Origin_city'] = $segment['flightSegment']['departureAirport']['cityInfo']['city']['locationName'];
                $segmentArr['Origin_city_language'] = $segment['flightSegment']['departureAirport']['cityInfo']['city']['locationNameLanguage'];
                $segmentArr['origin_city_name'] = $tp->getCity($segment['flightSegment']['departureAirport']['cityInfo']['city']['locationCode']);

                $segmentArr['Origin_country_code'] = $segment['flightSegment']['departureAirport']['cityInfo']['country']['locationCode'];
                $segmentArr['Origin_country_name'] = $segment['flightSegment']['departureAirport']['cityInfo']['country']['locationName'];
                $segmentArr['Origin_country_language'] = $segment['flightSegment']['departureAirport']['cityInfo']['country']['locationNameLanguage'];
                $segmentArr['Origin_country_currency'] = $segment['flightSegment']['departureAirport']['cityInfo']['country']['currency'];
                $segmentArr['Origin_codeContext'] = $segment['flightSegment']['departureAirport']['codeContext'];
                $segmentArr['Origin_language'] = $segment['flightSegment']['departureAirport']['language'];
                $segmentArr['Origin_locationCode'] = $segment['flightSegment']['departureAirport']['locationCode'];
                $segmentArr['Origin_locationName'] = $segment['flightSegment']['departureAirport']['locationName'];

                $segmentArr['Destination'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['city']['locationCode'];
                $segmentArr['Destination_city'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['city']['locationName'];
                $segmentArr['Destination_language'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['city']['locationNameLanguage'];

                $segmentArr['Destination_country_code'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['country']['locationCode'];
                $segmentArr['Destination_country'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['country']['locationName'];
                $segmentArr['Destination_country_language'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['country']['locationNameLanguage'];
                $segmentArr['Destination_currency'] = $segment['flightSegment']['arrivalAirport']['cityInfo']['country']['currency'];
                $segmentArr['Destination_codeContext'] = $segment['flightSegment']['arrivalAirport']['codeContext'];
                $segmentArr['Destination_main_language'] = $segment['flightSegment']['arrivalAirport']['language'];
                $segmentArr['Destination_locationCode'] = $segment['flightSegment']['arrivalAirport']['locationCode'];
                $segmentArr['Destination_locationName'] = $segment['flightSegment']['arrivalAirport']['locationName'];


                $segmentArr['destination_city_name'] = $tp->getCity($segment['flightSegment']['arrivalAirport']['cityInfo']['city']['locationCode']);
                $segmentArr['DepartureTime'] = $segment['flightSegment']['departureDateTime'];
                //$segmentArr['departureDateTimeUTC'] = $segment['departureDateTimeUTC'];
                $segmentArr['ArrivalTime'] = $segment['flightSegment']['arrivalDateTime'];
                //$segmentArr['arrivalDateTimeUTC'] = $segment['arrivalDateTimeUTC'];
                $segmentArr['FlightTime'] = $this->hititTimeSet($segment['flightSegment']['journeyDuration']);
                $segmentArr['ondControlled'] = $segment['flightSegment']['ondControlled'];
                $segmentArr['codeshare'] = $segment['flightSegment']['codeshare'];
                $segmentArr['distance'] = $segment['flightSegment']['distance'];
                $segmentArr['Equipment'] = $segment['flightSegment']['equipment'];
                $segmentArr['FlightNotes'] = empty($segment['flightSegment']['flightNotes']) ? array() : $segment['flightSegment']['flightNotes'];
                $segmentArr['flownMileageQty'] = $segment['flightSegment']['flownMileageQty'];
                $segmentArr['onTimeRate'] = $segment['flightSegment']['onTimeRate'];
                $segmentArr['secureFlightDataRequired'] = $segment['flightSegment']['secureFlightDataRequired'];
                $segmentArr['stopQuantity'] = $segment['flightSegment']['stopQuantity'];


                $flightArray[] = $segmentArr;
            }
        }
        return $flightArray;
    }

    public function hititTimeSet($time) {
        $flightTime = str_replace('PT', '', $time);
        $flightTime = str_replace('H', ',', $flightTime);
        $flightTime = str_replace('M', '', $flightTime);

        $timePart = explode(',', $flightTime);

        $hoursToMinutes = $timePart[0] * 60;
        $min = (isset($timePart[1]) && is_numeric($timePart[1])) ? $timePart[1] : 0;
        return $totalMinutes = $hoursToMinutes + $min;
    }

//    public function sendemail($to_name, $to_email, $data) {
//        $to_name = 'Faisal';
//        $to_email = 'backenddeveloper0022@gmail.com';
//        $data = array('name' =>"Foure Flights", "body" => "A test mail");
//        Mail::send('mail', $data, function($message) use ($to_name, $to_email) {
//            $message->to($to_email, $to_name)
//            ->subject('Foure Flight Booking Successful');
//            $message->from(env('MAIL_USERNAME'),'Foure Flight Booking');
//        });
//        
//        return True;
//    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }

}
