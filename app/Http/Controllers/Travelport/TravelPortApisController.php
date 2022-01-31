<?php

namespace App\Http\Controllers\Travelport;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\TravelPort;
use App\Helpers\TravelHitit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting;


class TravelPortApisController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    //Function to get All Data from Travelport API...
    public function one_way_trip_filter(Request $request) {

        //Form Validation Rules...
        $rules = [
            'from_date' => 'required',
            'from' => 'required',
            'to' => 'required',
            'adult' => 'required',
            'infant' => 'required',
            'children' => 'required',
        ];
        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'result' => []
            );
            return response()->json($return);
        }

        //Logic applied to get one way search data from Hitit API...
//        try {
        $flightType = $this->checkFlightType($request->from, $request->to);
        $data = array();
        if ($request->ticket_class == 'First') {
            $ticket_class = 'BUSINESS';
        } else {
            $ticket_class = strtoupper($request->ticket_class);
        }

        $hititReq = new Request([
            'trip' => "ONE_WAY",
            'origion_name' => "",
            'origion_code' => $request->from,
            'distination_name' => "",
            'distination_code' => $request->to,
            'depart_date' => db_format_date($request->from_date),
            'adult' => $request->adult,
            'child' => $request->children,
            'infant' => $request->infant,
            'ticket_class' => $ticket_class,
        ]);
        $hitit = new TravelHitit();
        $oneway_resp = $hitit->oneway_search($hititReq);
        $oneway_res = array();
        if (!(isset($oneway_resp['Body']['Fault']))) {
            if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability'])) {
                if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList'])) {
                    if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList'])) {
                        if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList'])) {
                            if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList']['originDestinationOptionList'])) {
                                $oneway_r = $oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList']['originDestinationOptionList'];
                                if (!(array_key_exists(0, $oneway_r))) {
                                    $oneway_res[0] = $oneway_r;
                                } else {
                                    $oneway_res = $oneway_r;
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $oneway_res = array();
        }
        if (!empty($oneway_res)) {
            $hititRSPNEw = $this->getOneWayHititNeatResponse($oneway_res,$flightType);
        } else {
            $hititRSPNEw = array();
        }
        if (!$this->checkCountryPakistan($request->from) || !$this->checkCountryPakistan($request->to)) {
            if ($request->input('ticket_class') == null) {
                $ticket_class = null;
            } else {
                $ticket_class = $request->input('ticket_class');
            }
            $from_date = $request->from_date;
            $travelPort = new TravelPort();
            $one_way_xml = $travelPort->one_way_trip_req($request->trip, $request->from, $request->to, $from_date, $request->adult, $request->children, $request->infant, null, $ticket_class);
            $data = $travelPort->roundTripOutputAirSearch($one_way_xml, $request->adult, $request->child, $request->infant);
            if ($data == 'false') {
                $data = array();
            }
        }



        if (is_array($data) && isset($data['status']) && $data['status'] == '4000') {
            $data = array();
        }
        if (!empty($data) || !empty($hititRSPNEw)) {
            $dataObj['api_type'] = 'one_way_trip';
            $dataObj['flight_type'] = $flightType;
            $obj = new \stdClass();
            $dataObj1 = $data !== 'false' ? array_values($data) : $obj;
            foreach ($hititRSPNEw as $hit) {
                array_push($dataObj1, $hit);
            }
            $dataObj['flights'] = $dataObj1;
            $return = array(
                'status' => '200',
                'message' => 'One way search list',
                'result' => $dataObj
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Flights not found.',
                'result' => new \stdClass
            );
        }
//        } catch (\Exception $e) {
//            $return = array(
//                'status' => '400',
//                'message' => $e->getMessage(),
//                'result' => new \stdClass
//            );
//        }

        return response()->json($return);
    }

    public function checkFlightType($from, $to) {
        $tp = new TravelPort();
        $fromData = $tp->getCity($from);
        $toData = $tp->getCity($to);

        $fromArray = explode(',', $fromData);
        $toArray = explode(',', $toData);
        //print_r($toArray); die();
        $fromCountry = trim($fromArray['1']);
        if ($toArray[0] && $toArray[0] != "") {
            $toCountry = trim($toArray['1']);
        } else {
            return 'unknown';
        }

        if (strpos($toCountry, $fromCountry) === false && strpos($fromCountry, $toCountry) === false) {
            return 'international';
        } else {
            return 'domestic';
        }
    }

    public function checkCountryPakistan($name) {
        $tp = new TravelPort();
        $cityName = $tp->getCity($name);
        if (str_contains(strtolower($cityName), 'pakistan')) {
            return True;
        } else {
            return FALSE;
        }
    }

    //This function returns the error message from the travelport API response...
    public function getErrorMessage($data) {
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

    //This function returns the error message from the travelport API response...
    public function getErrorMessageForPricing() {
        $error = "";
        $flights = Storage::get('AirPriceRsp.xml');
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

    public function getErrorMessageForBooking() {
        $error = "";
        $flights = Storage::get('BookingRsp.xml');
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

    public function round_trip_filter(Request $request) {

        // ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
        //Form Validation Rules...
        $rules = [
            'ticket_class' => 'required',
            'from_date' => 'required',
            'to_date' => 'required',
            'from' => 'required',
            'to' => 'required',
            'adult' => 'required',
            'infant' => 'required',
            'children' => 'required',
        ];
        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'result' => []
            );
            return response()->json($return);
        }


        // -----------------------------------------Hitit work -----------------------------
        if ($request->ticket_class == 'First') {
            $ticket_class = 'BUSINESS';
        } else {
            $ticket_class = strtoupper($request->ticket_class);
        }
        $flightType = $this->checkFlightType($request->from, $request->to);
        $hititReq = new Request([
            'trip' => "ROUND_TRIP",
            'origion_name' => "",
            'origion_code' => $request->from,
            'distination_name' => "",
            'distination_code' => $request->to,
            'depart_date' => db_format_date($request->from_date),
            'return_date' => db_format_date($request->to_date),
            'adult' => $request->adult,
            'child' => $request->children,
            'infant' => $request->infant,
            'ticket_class' => $ticket_class,
        ]);

        $hitit = new TravelHitit();
        $oneway_resp = $hitit->round_search($hititReq);
        $oneway_res = array();
        if (!(isset($oneway_resp['Body']['Fault']))) {
            if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability'])) {
                if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList'])) {
                    if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList'])) {
                        if (!empty($oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList'])) {
                            $oneway_r = $oneway_resp['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList']['originDestinationOptionList'];

                            if (!(array_key_exists(0, $oneway_r)))
                                $oneway_res[0] = $oneway_r;
                            else
                                $oneway_res = $oneway_r;
                        }
                    }
                }
            }
        }

        if (!empty($oneway_res)) {
            $hititRSPNEw = $this->getOneWayHititNeatResponse($oneway_res,$flightType);
        } else {
            $hititRSPNEw = array();
        }




        if ($request->input('ticket_class') == null) {
            $ticket_class = null;
        } else {
            $ticket_class = $request->input('ticket_class');
        }
        $travelPort = new TravelPort();
        $round_trip_xml = $travelPort->round_trip_req('round-trip', $request->from, $request->to, $request->from_date, $request->to_date, $request->adult, $request->child, $request->infant, null, $ticket_class);
        $data = $travelPort->roundTripOutputAirSearch($round_trip_xml);
        if ($data == 'false') {
            $data = array();
        }

        if (is_array($data) && isset($data['status']) && $data['status'] == '4000') {
            $data = array();
        }
        if (!empty($data) || !empty($hititRSPNEw)) {
            $dataObj['api_type'] = 'one_way_trip';
            $dataObj['flight_type'] = $flightType;
            $obj = new \stdClass();
            $dataObj1 = $data !== 'false' ? array_values($data) : $obj;
            foreach ($hititRSPNEw as $hit) {
                array_push($dataObj1, $hit);
            }
            $dataObj['flights'] = $dataObj1;
            $return = array(
                'status' => '200',
                'message' => 'Round Trip search list',
                'result' => $dataObj
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Flights not found.',
                'result' => new \stdClass
            );
        }
        return response()->json($return);
    }

    public function multi_trip_search(Request $request) {
        ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
        //Form Validation Rules...
        $rules = [
            'ticket_class' => 'required',
            'from_date' => 'required',
            'from' => 'required',
            'to' => 'required',
            'adult' => 'required',
            'infant' => 'required',
            'children' => 'required',
        ];
        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'result' => []
            );
            return response()->json($return);
        }

        $travelPort = new TravelPort();
        $multitrip_xml = $travelPort->multi_city_trip(json_decode($request->from), json_decode($request->to), json_decode($request->from_date), $request->adult, $request->children, $request->infant, null, json_decode($request->ticket_class));
        $data = $travelPort->roundTripOutputAirSearch($multitrip_xml);

        if ($data == 'false') {
            $error = $this->getErrorMessage($multitrip_xml);
            $return = array(
                'status' => '400',
                'message' => $error,
                'result' => new \stdClass()
            );
        } else {
            $dataObj['api_type'] = 'multi_trip';
            $dataObj['flights'] = $this->paginate(array_values($data));
            $return = array(
                'status' => '200',
                'message' => 'Multi Trip search list',
                'result' => $dataObj
            );
        }
        return response()->json($return);
    }

    protected function paginate($items) {
        $collection = $items instanceof Collection ? $items : Collection::make($items);
        $rules = [
            'per_page' => 'integer|min:2|max:50',
        ];

        Validator::validate(request()->all(), $rules);

        $page = LengthAwarePaginator::resolveCurrentPage();

        $perPage = 15;

        if (request()->has('per_page')) {
            $perPage = (int) request()->per_page;
        }

        $results = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator($results, $collection->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        $paginated->appends(request()->all());
        return $paginated;
    }

    public function strReplace($data) {
        return str_replace(' ', '+', $data);
    }

    public function getFareRules(Request $request) {
        $segmentsData = $request->segmentsData;
        $fareRuleKey = $segmentsData['fareRulesData']['FareRuleKey'];
        $fareInfoRef = $segmentsData['fareRulesData']['FareInfoRef'];
        $pricing = new TravelPort();
        $pricingResxml = $pricing->air_pricing_req($segmentsData['segments'], $request->adult, $request->child, $request->infant);
        $PricingInfoData = $pricing->get_air_segments_pricingRequest($pricingResxml);
        $infoData = $pricing->get_data_from_pricing_rsp($pricingResxml);
        if ($infoData == 'false') {
            $error = $this->getErrorMessageForPricing();
            $return = array(
                'status' => '400',
                'message' => $error,
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
        $pricingInfo = $infoData;
        $fareRules = $pricing->fareRuleReq($fareInfoRef, $fareRuleKey);
        $returnData['flightInformation'] = $pricingInfo;
        $returnData['priceInformation'] = $PricingInfoData['pricingSol'];
        $returnData['FareRules'] = $fareRules;
        $return = array(
            'status' => '200',
            'message' => 'Fare Rules Detail.',
            'data' => $returnData
        );
        return response()->json($return);
    }

    function contains_array($array) {
        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        return false;
    }

    public function getOneWayHititNeatResponse($oneway_res,$flightType) {
        $flightArray = array();
        foreach ($oneway_res as $hititrsp) {
            $flightSegmentArr = array();
            $flightSegmentArr['provider_type'] = 'hitit';
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            $key = substr(str_shuffle($permitted_chars), 0, 10);
            $flightSegmentArr['key'] = $key;
            $flightSegmentArr['is_featured'] = 'true';
            foreach ($hititrsp['fareComponentGroupList'] as $key => $fareComponent) {
                if ($key == 'boundList') {
                    if (!isset($fareComponent[0])) {
                        $fareComponent = array($fareComponent);
                    }
                    $boundLIstHere = array();
                    foreach ($fareComponent as $fComponent) {
                        $flightLoopData = $fComponent['availFlightSegmentList'];
                        if (count($flightLoopData) > 0 && is_numeric(array_keys($flightLoopData)[0])) {

                            $flightLoopData1 = $flightLoopData;
                        } else {
                            $flightLoopData1 = array($flightLoopData);
                        }
                        $FlightDataget = array();
                        $segmentHere = array();
                        foreach ($flightLoopData1 as $flightSegment) {
                            if (isset($flightSegment['bookingClassList'])) {
                                $FlightDataget['bookingClassList'] = isset($flightSegment['bookingClassList'][0]) ? $flightSegment['bookingClassList'] : array($flightSegment['bookingClassList']);
                            } else {
                                $FlightDataget['bookingClassList'] = array();
                            }
                            $tp = new TravelPort();
                            $segment = $flightSegment['flightSegment'];
                            $segmentArr['Carrier'] = $segment['airline']['code'];
                            $segmentArr['companyFullName'] = $segment['airline']['companyFullName'];
                            $segmentArr['companyShortName'] = $segment['airline']['companyShortName'];
                            $segmentArr['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['airline']['code'] . '.png';
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
                            $segmentArr['departureDateTimeUTC'] = $segment['departureDateTimeUTC'];
                            $segmentArr['ArrivalTime'] = $segment['arrivalDateTime'];
                            $segmentArr['arrivalDateTimeUTC'] = $segment['arrivalDateTimeUTC'];
                            $segmentArr['FlightTime'] = $this->hititTimeSet($segment['journeyDuration']);
                            $segmentArr['FlightTime1'] = $segment['journeyDuration'];
                            $segmentArr['ondControlled'] = $segment['ondControlled'];
                            $segmentArr['codeshare'] = $segment['codeshare'];
                            $segmentArr['distance'] = $segment['distance'];
                            $segmentArr['Equipment'] = $segment['equipment'];
                            $segmentArr['FlightNotes'] = empty($segment['flightNotes']) ? array() : $segment['flightNotes'];
                            $segmentArr['flownMileageQty'] = $segment['flownMileageQty'];
                            $segmentArr['onTimeRate'] = $segment['onTimeRate'];
                            $segmentArr['secureFlightDataRequired'] = $segment['secureFlightDataRequired'];
                            $segmentArr['stopQuantity'] = $segment['stopQuantity'];
                            $FlightDataget['segment_data'] = $segmentArr;
                            $segmentHere[] = $FlightDataget;
                        }
                        $boundLIstHere[$fComponent['boundCode']] = $segmentHere;
                    }
                    $flightSegmentArr['segments'] = $boundLIstHere;
                }
            }
            if ($key == 'fareComponentList') {
                $pricingHere = isset($fareComponent[0]) ? $fareComponent[0] : $fareComponent;
                $pricingSeg = $this->pricingBreakDownHitit($pricingHere,$flightType);
                $flightSegmentArr['price_info'] = $pricingSeg;
            }
            $flightArray[] = $flightSegmentArr;
        }
        return ($flightArray);
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

    public function pricingBreakDownHitit($pricingHere,$flightType) {
        $pricingObject = array();
        $fareInfoList = $pricingHere['passengerFareInfoList'];
        $passengerFareInfoList = isset($fareInfoList[0]) ? $fareInfoList : array($fareInfoList);
        $fareInfoCompleteList = array();
        foreach ($passengerFareInfoList as $fiList) {
            $fareInfoCompleteList = $fiList['fareInfoList'];
        }
        $pricingObject['fareInfoList'] = isset($fareInfoCompleteList[0]) ? $fareInfoCompleteList : array($fareInfoCompleteList);
        $pricingObject['pricingOverview'] = $pricingHere['pricingOverview'];
        if (!empty($pricingObject['pricingOverview'])) {
            $totalPrice = $pricingObject['pricingOverview']['totalAmount']['value'];
            $totalTax = $pricingObject['pricingOverview']['totalTax']['value'];
            $totalBasePrice = $totalPrice - $totalTax;
            $setting = new Setting();
            $commsionValue = 0;
            $commsionValueWH = 0;
            $settingCode = $flightType == 'domestic'?"hitit-commission-domestic":"hitit-commission-international";
            $hititSetting = $setting->getSettingByCode($settingCode);
            if ($hititSetting && $hititSetting->setting_value > 0) {
                $commsionValue = getCommissionValue($totalBasePrice, $hititSetting->setting_value);
            }
            
            //For Applying Withholding
            $settingCodeWH = $flightType == 'domestic'?"hitit-withholding-domestic":"hitit-withholding-international";
            $hititSettingWH = $setting->getSettingByCode($settingCodeWH);
            if ($hititSettingWH && $hititSettingWH->setting_value > 0) {
                $commsionValueWH = getCommissionValue($commsionValue, $hititSettingWH->setting_value);
            }
            
//            print_r('Commission: '.$commsionValue.' commWH: '.$commsionValueWH); die();
            
            $pricingObject['pricingOverview']['TotalPriceWithCommission'] = round($commsionValue + $totalPrice);
        }
        return ($pricingObject);
    }

    public function getRoundHititNeatResponse($oneway_res) {
        $flightArray = array();
        $flightsArrayData = array();
        $flightSegmentArray = array();
        foreach ($oneway_res as $hititrsp) {
            $boundList = $hititrsp['fareComponentGroupList']['boundList'];
            foreach ($boundList as $seg) {
                $bookingClassData = $seg['availFlightSegmentList']['bookingClassList'];
                $flightArray['bookingClassList'] = is_array($bookingClassData) ? $bookingClassData : array($bookingClassData);
                $flightArray['provider_type'] = 'hitit';
                $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                $key = substr(str_shuffle($permitted_chars), 0, 10);
                $flightArray['key'] = $key;
                $tp = new TravelPort();
                $segment = $seg['availFlightSegmentList']['flightSegment'];
                $segmentArr['Carrier'] = $segment['airline']['code'];
                $segmentArr['companyFullName'] = $segment['airline']['companyFullName'];
                $segmentArr['companyShortName'] = $segment['airline']['companyShortName'];
                $segmentArr['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['airline']['code'] . '.png';
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
                $segmentArr['departureDateTimeUTC'] = $segment['departureDateTimeUTC'];
                $segmentArr['ArrivalTime'] = $segment['arrivalDateTime'];
                $segmentArr['arrivalDateTimeUTC'] = $segment['arrivalDateTimeUTC'];
                $segmentArr['FlightTime'] = $segment['journeyDuration'];
                $segmentArr['ondControlled'] = $segment['ondControlled'];
                $segmentArr['codeshare'] = $segment['codeshare'];
                $segmentArr['distance'] = $segment['distance'];
                $segmentArr['Equipment'] = $segment['equipment'];
                $segmentArr['FlightNotes'] = empty($segment['flightNotes']) ? array() : $segment['flightNotes'];
                $segmentArr['flownMileageQty'] = $segment['flownMileageQty'];
                $segmentArr['onTimeRate'] = $segment['onTimeRate'];
                $segmentArr['secureFlightDataRequired'] = $segment['secureFlightDataRequired'];
                $segmentArr['stopQuantity'] = $segment['stopQuantity'];
                $flightSegmentArray[] = $segmentArr;
            }
            $flightArray['segments'] = $flightSegmentArray;
            $flightArray['price_info'] = $hititrsp['fareComponentGroupList']['fareComponentList'];
            $flightsArrayData[] = $flightArray;
        }
        return $flightsArrayData;
    }

    

}
