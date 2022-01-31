<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use App\Models\Setting;

/*
 * For Travel AirSial
 */

class TravelAirSial {

    protected $link = '';
    protected $username = '';
    protected $password = '';
    protected $credential = '';
    protected $auth = '';
    protected $APITYPE = '';

    public function __construct() {
        // Travel AirSial Credentials Dynamic

        $this->APITYPE = 'preproduction';
        $set = new Setting();
        $settingData = $set->getSettingByCode('flights-api-type');
        if($settingData !== null){
            $this->APITYPE = $settingData->setting_value;
        }

        if ($this->APITYPE == 'production') {
            $this->link = getConstant('AIRSIAL_LINK');
            $this->username = getConstant('AIRSIAL_USERNAME');
            $this->password = getConstant('AIRSIAL_PASSWORD');
        } elseif ($this->APITYPE == 'preproduction') {
            $this->link = getConstant('AIRSIAL_LINK_DEMO');
            $this->username = getConstant('AIRSIAL_USERNAME_DEMO');
            $this->password = getConstant('AIRSIAL_PASSWORD_DEMO');
        }
    }

    function curl_action($method, $url, $data = false) {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }
        // Optional Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }

    //Function for login and to get token from AirSial
    public function login() {
        $request_message = array(
            "Caller" => "login",
            "Username" => $this->username,
            "Password" => $this->password
        );

        $this->message = json_encode(array($request_message));
        $dataJson = $this->curl_action('POST', $this->link, $this->message);
        $data = json_decode($dataJson);

        Storage::put('AirSial_Token.txt', $data->Response->Data->token);
        return TRUE;
    }

    //Function to get city name from its json file
    public function getCity($search) {
        $json = file_get_contents(storage_path() . "/cities.json");
        $cities = json_decode($json);
        foreach ($cities as $city) {
            if ($city->code == $search) {
                return $city->city_name;
            }
        }
    }

    //Function to get AirLine name from its json file
    public function getAirline($search) {
        $json = file_get_contents(storage_path() . "/airlines.json");
        $airlines = json_decode($json);
        foreach ($airlines as $airline) {
            if ($airline->code == $search) {
                return $airline->name;
            }
        }
    }

    //Request to fetch flights from AirSial
    public function search_request($search_type, $inputData) {
        $this->login();
        $token = Storage::get('AirSial_Token.txt');
        $request_message = array(
            "Caller" => "getSingleflight",
            "token" => $token,
            "DepartingOn" => date('d-m-Y', strtotime($inputData['from_date'])),
            "LocationDep" => $inputData['from'],
            "LocationArr" => $inputData['to'],
            "Return" => $search_type == 'round' ? true : false,
            "ReturningOn" => isset($inputData['to_date']) ? date('d-m-Y', strtotime($inputData['to_date'])) : "",
            "AdultNo" => $inputData['adult'],
            "ChildNo" => $inputData['infant'],
            "InfantNo" => $inputData['children']
        );
        $this->message = json_encode(array($request_message));
        $dataJson = $this->curl_action('POST', $this->link, $this->message);
        $data = json_decode($dataJson);
        return $data;
    }

    function OutputAirSearch($flightData, $passengerQtyInfo) {
//print_r($flightData); die();
        try {
            if ($flightData->Success) {
                $flightOriginalDataObject = array($flightData->Response->Data);
                $flightData = $segmentBoundData = array();
                $segmentBoundDataPricing = array();
                $flightArray = array();
                foreach ($flightOriginalDataObject as $flightOriginalData) {

                    if (isset($flightOriginalData->outbound)) {
                        $outbound = $flightOriginalData->outbound;

                        $mainFlightArray = array();

                        foreach ($outbound as $segmentOutbound) {
                            $OutboundBASIC_FARE = 0;
                            $OutboundSURCHARGE = 0;
                            $OutboundFEES = 0;
                            $OutboundTAX = 0;
                            $OutboundTOTAL = 0;
                            $segmentBoundDatasegmentout['outbound'] = array();
                            $segmentBoundData['provider_type'] = 'airsial';
                            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                            $key = substr(str_shuffle($permitted_chars), 0, 10);
                            $segmentBoundData['key'] = $key;
                            $segmentBoundData['is_featured'] = 'true';
                            $segmentBoundDatasegmentout['outbound'][] = $this->listSegments($segmentOutbound);
                            $pricingBoundData = $this->getPricingByBound($segmentOutbound, $passengerQtyInfo);
                            $OutboundBASIC_FARE = $OutboundBASIC_FARE + $pricingBoundData['BASIC_FARE'];
                            $OutboundSURCHARGE = $OutboundSURCHARGE + $pricingBoundData['SURCHARGE'];
                            $OutboundFEES = $OutboundFEES + $pricingBoundData['FEES'];
                            $OutboundTAX = $OutboundTAX + $pricingBoundData['TAX'];
                            $OutboundTOTAL = $OutboundTOTAL + $pricingBoundData['TOTAL'];
                            $segmentBoundDataPricing = array(
                                'BASIC_FARE' => $OutboundBASIC_FARE,
                                'SURCHARGE' => $OutboundSURCHARGE,
                                'FEES' => $OutboundFEES,
                                'TAX' => $OutboundTAX,
                                'TOTAL' => $OutboundTOTAL,
                            );
                            $segmentBoundData['segments'] = $segmentBoundDatasegmentout;
                            $setting = new Setting();
                            $commsionValue = 0;
                            $airsialSetting = $setting->getSettingByCode('airsial-commission-domestic');
                            if ($airsialSetting && $airsialSetting->setting_value > 0) {

                                $commsionValue = getCommissionValue($segmentBoundDataPricing['BASIC_FARE'], $airsialSetting->setting_value);
                            }

                            $segmentBoundDataPricing['TotalPriceWithCommission'] = round($segmentBoundDataPricing['TOTAL'] + $commsionValue);
                            $segmentBoundData['pricing_info'] = $segmentBoundDataPricing;
                            $segmentBoundData['availableFareTypes'] = $flightOriginalData->availableFareTypes;
                            $mainFlightArray[] = $segmentBoundData;
                        }
                    }

                    if (isset($flightOriginalData->inbound)) {
                        $inbound = $flightOriginalData->inbound;
                        $InboundBASIC_FARE = 0;
                        $InboundSURCHARGE = 0;
                        $InboundFEES = 0;
                        $InboundTAX = 0;
                        $InboundTOTAL = 0;
                        foreach ($inbound as $segmentInbound) {
                            $segmentBoundDatasegmentin['inbound'] = array();
                            $segmentBoundDatasegmentin['inbound'][] = $this->listSegments($segmentInbound);
                            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                            $key = substr(str_shuffle($permitted_chars), 0, 10);
                            $segmentBoundData['key'] = $key;
                            $segmentBoundData['is_featured'] = 'true';
                            $pricingBoundData = $this->getPricingByBound($segmentInbound, $passengerQtyInfo);
                            $InboundBASIC_FARE = $pricingBoundData['BASIC_FARE'];
                            $InboundSURCHARGE = $pricingBoundData['SURCHARGE'];
                            $InboundFEES = $pricingBoundData['FEES'];
                            $InboundTAX = $pricingBoundData['TAX'];
                            $InboundTOTAL = $pricingBoundData['TOTAL'];
                            $segmentBoundDataPricing = array(
                                'BASIC_FARE' => $InboundBASIC_FARE,
                                'SURCHARGE' => $InboundSURCHARGE,
                                'FEES' => $InboundFEES,
                                'TAX' => $InboundTAX,
                                'TOTAL' => $InboundTOTAL,
                            );

                            $segmentBoundData['segments'] = $segmentBoundDatasegmentin;
                            $setting = new Setting();
                            $commsionValue = 0;
                            $airsialSetting = $setting->getSettingByCode('airsial-commission-domestic');
                            if ($airsialSetting && $airsialSetting->setting_value > 0) {

                                $commsionValue = getCommissionValue($segmentBoundDataPricing['BASIC_FARE'], $airsialSetting->setting_value);
                            }

                            $segmentBoundDataPricing['TotalPriceWithCommission'] = round($segmentBoundDataPricing['TOTAL'] + $commsionValue);
                            $segmentBoundData['pricing_info'] = $segmentBoundDataPricing;
                            $segmentBoundData['availableFareTypes'] = $flightOriginalData->availableFareTypes;

                            $mainFlightArray[] = $segmentBoundData;
                        }
                    }


                    $flightArray = $mainFlightArray;
                }
                return array(
                    'status' => 'true',
                    'message' => 'successfull data',
                    'data' => $flightArray
                );
            } else {
                return $return = array(
                    'status' => '4000',
                    'message' => $flightData->Response->message
                );
            }
        } catch (\Exception $e) {
            return $return = array(
                'status' => '4000',
                'message' => $e->getMessage()
            );
        }
    }

    function OutputAirSearch1($flightData, $passengerQtyInfo) {
        try {

            if ($flightData->Success) {
                $flightOriginalDataObject = array($flightData->Response->Data);
                $flightData = $segmentBoundData = array();
                $segmentBoundDataPricing = array();
                $flightArray = array();
                foreach ($flightOriginalDataObject as $flightOriginalData) {
                    $mainFlightArray['provider_type'] = 'airsial';
                    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                    $key = substr(str_shuffle($permitted_chars), 0, 10);
                    $mainFlightArray['key'] = $key;
                    $mainFlightArray['is_featured'] = 'true';
                    if (isset($flightOriginalData->outbound)) {
                        $outbound = $flightOriginalData->outbound;
                        $OutboundBASIC_FARE = 0;
                        $OutboundSURCHARGE = 0;
                        $OutboundFEES = 0;
                        $OutboundTAX = 0;
                        $OutboundTOTAL = 0;
                        foreach ($outbound as $segmentOutbound) {
                            $segmentBoundData['outbound'][] = $this->listSegments($segmentOutbound);
                            $pricingBoundData = $this->getPricingByBound($segmentOutbound, $passengerQtyInfo);
                            $OutboundBASIC_FARE = $OutboundBASIC_FARE + $pricingBoundData['BASIC_FARE'];
                            $OutboundSURCHARGE = $OutboundSURCHARGE + $pricingBoundData['SURCHARGE'];
                            $OutboundFEES = $OutboundFEES + $pricingBoundData['FEES'];
                            $OutboundTAX = $OutboundTAX + $pricingBoundData['TAX'];
                            $OutboundTOTAL = $OutboundTOTAL + $pricingBoundData['TOTAL'];
                            $segmentBoundDataPricing = array(
                                'BASIC_FARE' => $OutboundBASIC_FARE,
                                'SURCHARGE' => $OutboundSURCHARGE,
                                'FEES' => $OutboundFEES,
                                'TAX' => $OutboundTAX,
                                'TOTAL' => $OutboundTOTAL,
                            );
                        }
                    }

                    if (isset($flightOriginalData->inbound)) {
                        $outboundpricingArr = $segmentBoundDataPricing;
                        $inbound = $flightOriginalData->inbound;
                        $InboundBASIC_FARE = 0;
                        $InboundSURCHARGE = 0;
                        $InboundFEES = 0;
                        $InboundTAX = 0;
                        $InboundTOTAL = 0;
                        foreach ($inbound as $segmentInbound) {
                            $segmentBoundData['inbound'][] = $this->listSegments($segmentInbound);
                            $pricingBoundData = $this->getPricingByBound($segmentInbound, $passengerQtyInfo);
                            $InboundBASIC_FARE = $InboundBASIC_FARE + $pricingBoundData['BASIC_FARE'];
                            $InboundSURCHARGE = $InboundSURCHARGE + $pricingBoundData['SURCHARGE'];
                            $InboundFEES = $InboundFEES + $pricingBoundData['FEES'];
                            $InboundTAX = $InboundTAX + $pricingBoundData['TAX'];
                            $InboundTOTAL = $InboundTOTAL + $pricingBoundData['TOTAL'];
                            $segmentBoundDataPricing = array(
                                'BASIC_FARE' => $OutboundBASIC_FARE + $outboundpricingArr['BASIC_FARE'],
                                'SURCHARGE' => $OutboundSURCHARGE + $outboundpricingArr['SURCHARGE'],
                                'FEES' => $OutboundFEES + $outboundpricingArr['FEES'],
                                'TAX' => $OutboundTAX + $outboundpricingArr['TAX'],
                                'TOTAL' => $OutboundTOTAL + $outboundpricingArr['TOTAL'],
                            );
                        }
                    }
                    $mainFlightArray['segments'] = $segmentBoundData;
                    $mainFlightArray['pricing_info'] = $segmentBoundDataPricing;
                    $mainFlightArray['availableFareTypes'] = $flightOriginalData->availableFareTypes;
                    $flightArray[] = $mainFlightArray;
                }
                return array(
                    'status' => 'true',
                    'message' => 'successfull data',
                    'data' => $flightArray
                );
            } else {
                return $return = array(
                    'status' => '4000',
                    'message' => $flightData->Response->message
                );
            }
        } catch (\Exception $e) {
            return $return = array(
                'status' => '4000',
                'message' => $e->getMessage()
            );
        }
    }

    public function listSegments($segmentsData) {
        $segment = array();
        if (!empty($segmentsData)) {
            $segment['Carrier'] = getConstant('AIRSIAL_IATA_CODE');
            $segment['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['Carrier'] . '.png';
            $segment['airline_name'] = $this->getAirline($segment['Carrier']);
            $segment['Origin'] = $segmentsData->ORGN;
            $segment['origin_city_name'] = $this->getCity($segment['Origin']);
            $segment['Destination'] = $segmentsData->DEST;
            $segment['Destination_city_name'] = $this->getCity($segment['Destination']);
            $segment['FlightNumber'] = $segmentsData->FLIGHT_NO;
            $segment['DEPARTURE_DATE'] = date('d-F-Y', strtotime($segmentsData->DEPARTURE_DATE));
            $segment['DEPARTURE_TIME'] = date('H:i A', strtotime($segmentsData->DEPARTURE_TIME));
            $segment['ARRIVAL_TIME'] = date('H:i A', strtotime($segmentsData->ARRIVAL_TIME));
            $segment['JOURNEY_CODE'] = $segmentsData->JOURNEY_CODE;
            $segment['CLASS_CODE'] = $segmentsData->CLASS_CODE;
            $segment['CURRENCY'] = $segmentsData->CURRENCY;
            $segment['FlightTime'] = $this->setFlightTime($segmentsData->DURATION);
            $segment['BAGGAGE_FARE'] = isset($segmentsData->BAGGAGE_FARE) ? $segmentsData->BAGGAGE_FARE : array();

            return $segment;
        }
    }

    public function listSegmentsBooking($segmentsData) {

        $segment = array();
        if (!empty($segmentsData)) {
            $segment['Carrier'] = getConstant('AIRSIAL_IATA_CODE');
            $segment['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['Carrier'] . '.png';
            $segment['airline_name'] = $this->getAirline($segment['Carrier']);
            $segment['Origin'] = $segmentsData->orig;
            $segment['origin_city_name'] = $this->getCity($segment['Origin']);
            $segment['Destination'] = $segmentsData->dest;
            $segment['Destination_city_name'] = $this->getCity($segment['Destination']);
            $segment['FlightNumber'] = $segmentsData->flno;
            $segment['DEPARTURE_DATE'] = date('d-F-Y', strtotime($segmentsData->dpdate));
            $segment['DEPARTURE_TIME'] = date('h:i A', strtotime($segmentsData->departureTime));
            $segment['ARRIVAL_TIME'] = date('h:i A', strtotime($segmentsData->arrivalTime));
            $segment['FlightTime'] = $this->getTimeDiff($segment['DEPARTURE_TIME'], $segment['ARRIVAL_TIME']);
//            $segment['JOURNEY_CODE'] = $segmentsData->JOURNEY_CODE;
            $segment['CLASS_CODE'] = $segmentsData->class;
//            $segment['CURRENCY'] = $segmentsData->CURRENCY;
//            $segment['FlightTime'] = $this->setFlightTime($segmentsData->DURATION);
//            $segment['BAGGAGE_FARE'] = $segmentsData->BAGGAGE_FARE;


            return $segment;
        }
    }

    public function getTimeDiff($dept, $arriv) {
        $start = strtotime($dept);
        $end = strtotime($arriv);
        $mins = ($end - $start) / 60;
        return $mins;
    }

    public function setFlightTime($time) {
        $hRemoved = str_replace('h', '', $time);
        $mRemoved = str_replace('m', '', $hRemoved);
        $timeSegments = explode(' ', $mRemoved);
        $totalMinutes = ($timeSegments[0] * 60) + $timeSegments[1];
        return $totalMinutes;
    }

    public function getPricingByBound($segmentOutbound, $passengerQtyInfo) {
        $BASIC_FARE = 0;
        $SURCHARGE = 0;
        $FEES = 0;
        $TAX = 0;
        $TOTAL = 0;
        if (isset($segmentOutbound->BAGGAGE_FARE[0]->FARE_PAX_WISE)) {
            $FARE_PAX_WISE = $segmentOutbound->BAGGAGE_FARE[0]->FARE_PAX_WISE;
            if ($passengerQtyInfo['adult'] && $passengerQtyInfo['adult'] > 0) {
                $adultPricingOriginal = $FARE_PAX_WISE->ADULT;
                $BASIC_FARE = $adultPricingOriginal->BASIC_FARE * $passengerQtyInfo['adult'];
                $SURCHARGE = $adultPricingOriginal->SURCHARGE * $passengerQtyInfo['adult'];
                $FEES = $adultPricingOriginal->FEES * $passengerQtyInfo['adult'];
                $TAX = $adultPricingOriginal->TAX * $passengerQtyInfo['adult'];
                $TOTAL = $adultPricingOriginal->TOTAL * $passengerQtyInfo['adult'];
            }

            if (isset($passengerQtyInfo['infant']) && $passengerQtyInfo['infant'] > 0) {
                $adultPricingOriginal = $FARE_PAX_WISE->INFANT;
                $BASIC_FARE = $BASIC_FARE + $adultPricingOriginal->BASIC_FARE * $passengerQtyInfo['infant'];
                $SURCHARGE = $SURCHARGE + $adultPricingOriginal->SURCHARGE * $passengerQtyInfo['infant'];
                $FEES = $FEES + $adultPricingOriginal->FEES * $passengerQtyInfo['infant'];
                $TAX = $TAX + $adultPricingOriginal->TAX * $passengerQtyInfo['infant'];
                $TOTAL = $TOTAL + $adultPricingOriginal->TOTAL * $passengerQtyInfo['infant'];
            }

            if (isset($passengerQtyInfo['children']) && $passengerQtyInfo['children'] > 0) {
                $adultPricingOriginal = $FARE_PAX_WISE->CHILD;
                $BASIC_FARE = $BASIC_FARE + $adultPricingOriginal->BASIC_FARE * $passengerQtyInfo['children'];
                $SURCHARGE = $SURCHARGE + $adultPricingOriginal->SURCHARGE * $passengerQtyInfo['children'];
                $FEES = $FEES + $adultPricingOriginal->FEES * $passengerQtyInfo['children'];
                $TAX = $TAX + $adultPricingOriginal->TAX * $passengerQtyInfo['children'];
                $TOTAL = $TOTAL + $adultPricingOriginal->TOTAL * $passengerQtyInfo['children'];
            }
        }

        return $pricing_array = array(
            'BASIC_FARE' => $BASIC_FARE,
            'SURCHARGE' => $SURCHARGE,
            'FEES' => $FEES,
            'TAX' => $TAX,
            'TOTAL' => $TOTAL,
        );
    }

    //Booking section of AirSial...
    public function booking_request($inputData) {
        $this->login();
        $token = Storage::get('AirSial_Token.txt');
        $request_message = array(
            "Caller" => "bookSeat",
            "token" => $token,
            "Return" => isset($inputData['segmentsData']['segments']['inbound']) ? true : false,
            "DepartureJourney" => $inputData['segmentsData']['segments']['outbound'][0]['JOURNEY_CODE'],
            "DepartureFareType" => 2,
            "DepartureClass" => $inputData['segmentsData']['segments']['outbound'][0]['CLASS_CODE'],
            "DepartureFlight" => $inputData['segmentsData']['segments']['outbound'][0]['FlightNumber'],
            "ReturningJourney" => isset($inputData['segmentsData']['segments']['inbound']) ? $inputData['segmentsData']['segments']['inbound'][0]['JOURNEY_CODE'] : "",
            "ReturningClass" => isset($inputData['segmentsData']['segments']['inbound']) ? $inputData['segmentsData']['segments']['inbound'][0]['CLASS_CODE'] : "",
            "ReturningFlight" => isset($inputData['segmentsData']['segments']['inbound']) ? $inputData['segmentsData']['segments']['inbound'][0]['FlightNumber'] : "",
            "ReturningFareType" => isset($inputData['segmentsData']['segments']['inbound']) ? 2 : "",
            "LocationDep" => $inputData['segmentsData']['segments']['outbound'][0]['Origin'],
            "LocationArr" => $inputData['segmentsData']['segments']['outbound'][0]['Destination'],
            "ftype" => 'dom',
            "TotalSeats" => $inputData['booking_data']['adult'] + $inputData['booking_data']['infant'] + $inputData['booking_data']['child'],
            "totalAdult" => $inputData['booking_data']['adult'],
            "totalChild" => $inputData['booking_data']['infant'],
            "totalInfant" => $inputData['booking_data']['child']
        );
        $this->message = json_encode(array($request_message));
        $dataJson = $this->curl_action('POST', $this->link, $this->message);
        $data = json_decode($dataJson);
        if ($data->Success) {
            $pnr = $data->Response->Data;
            $resp = $this->passenger_detail_insertion($inputData, $pnr);
            if ($resp->Success) {
                return array(
                    'status' => '200',
                    'message' => $resp
                );
            } else {
                return array(
                    'status' => '4000',
                    'message' => $resp->Response->message
                );
            }
        } else {
            return array(
                'status' => '4000',
                'message' => $data->Response->message
            );
        }
    }

    public function passenger_detail_insertion($inputData, $pnr) {

        $token = Storage::get('AirSial_Token.txt');
        $adult = array();
        $child = array();
        $infant = array();
        foreach ($inputData['booking_detail'] as $passenger_detail) {
            $passengerArr['Title'] = strtoupper($passenger_detail['title']);
            $passengerArr['WheelChair'] = 'N';
            $passengerArr['FullName'] = $passenger_detail['firstName'] . ' ' . $passenger_detail['lastName'];
            $passengerArr['Firstname'] = $passenger_detail['firstName'];
            $passengerArr['Lastname'] = $passenger_detail['lastName'];
            if ($passenger_detail['passenger_type'] == 'ADT') {
                $adult[] = $passengerArr;
            }
            if ($passenger_detail['passenger_type'] == 'CNN') {
                $passengerArr['Title'] = strtolower($passengerArr['Title']) == "ms" ? "MISS" : $passengerArr['Title'];
                $child[] = $passengerArr;
            }
            if ($passenger_detail['passenger_type'] == 'INF') {
                $passengerArr['Title'] = strtolower($passengerArr['Title']) == "ms" ? "MISS" : $passengerArr['Title'];
                $infant[] = $passengerArr;
            }
        }

        $request_message = array(
            "Caller" => "passengerInsertion",
            "token" => $token,
            "PNR" => $pnr,
            'adult' => ($adult),
            "PrimaryCell" => $inputData['booking_data']['phone_number'],
            "SecondaryCell" => "+92",
            "EmailAddress" => $inputData['booking_data']['email'],
            "CNIC" => isset($inputData['booking_detail'][0]['cnic']) ? $inputData['booking_detail'][0]['cnic'] : "",
            "Comments" => "",
            "ft" => "dom",
        );

        if (!empty($child)) {
            $request_message['child'] = $child;
        }
        if (!empty($infant)) {
            $request_message['infant'] = $infant;
        }

        $this->message = json_encode(array($request_message));
        $dataJson = $this->curl_action('POST', $this->link, $this->message);
        $data = json_decode($dataJson);

        return $data;
    }

    public function getBookingByPNR($pnr) {
        $this->login();
        $token = Storage::get('AirSial_Token.txt');
        $request_message = array(
            "Caller" => "viewTicket",
            "token" => $token,
            "PNR" => $pnr,
        );
        $this->message = json_encode(array($request_message));
        $dataJson = $this->curl_action('POST', $this->link, $this->message);
        $data = json_decode($dataJson);

        if ($data && !empty($data) && $data->Success) {
            return array(
                'status' => '200',
                'message' => 'Data Retrieved Successfully!',
                'data' => $data->Response->Data
            );
        } else {

            return array(
                'status' => '4000',
                'message' => $data->Response->message
            );
        }
    }

    public function Booking_Data($airSialBookingData) {
        try {

            $segmentBoundData = array();
            $mainFlightArray['provider_type'] = 'airsial';
            if (isset($airSialBookingData->outbound)) {
                $outbound = $airSialBookingData->outbound;

                foreach ($outbound as $keySeg => $segmentOutbound) {
                    if ($keySeg == 'flightInfo') {
                        $segmentBoundData['outbound'][] = $this->listSegmentsBooking($segmentOutbound);
                    }
                }
            }
            if (isset($airSialBookingData->inbound)) {
                $inbound = $airSialBookingData->inbound;
                foreach ($inbound as $keySeg1 => $segmentInbound) {
                    if ($keySeg1 == 'flightInfo') {
                        $segmentBoundData['inbound'][] = $this->listSegmentsBooking($segmentInbound);
                    }
                }
            }
            $mainFlightArray['segments'] = $segmentBoundData;
            if (isset($airSialBookingData->pnrNames)) {
                $pnrNames = $airSialBookingData->pnrNames;
                $pnrNamesArray = $this->getPnrNamesArray($pnrNames);
                $mainFlightArray['passenger_detail'] = $pnrNamesArray;
            }




            return $flightArray[] = $mainFlightArray;
        } catch (\Exception $e) {
            return $return = array(
                'status' => '4000',
                'message' => $e->getMessage()
            );
        }
    }

    public function getPnrNamesArray($pnrNames) {

        $pnrNamesArray = array();
        if (!empty($pnrNames)) {
            if (isset($pnrNames->adult)) {
                foreach ($pnrNames->adult as $adult) {
                    $adultArr['title'] = $adult->title;
                    $adultArr['name'] = $adult->name;
                    $adultArr['cnic'] = $adult->nic;
                    $adultArr['passenger_type'] = 'adult';
                    $adultArr['ticket_number'] = $adult->tkt_no;
                    $pnrNamesArray[] = $adultArr;
                }
            }
            if (isset($pnrNames->child)) {
                foreach ($pnrNames->child as $adult) {
                    $childArr['title'] = $adult->title;
                    $childArr['name'] = $adult->name;
                    $childArr['cnic'] = $adult->nic;
                    $childArr['passenger_type'] = 'child';
                    $childArr['ticket_number'] = $adult->tkt_no;
                    $pnrNamesArray[] = $childArr;
                }
            }
            if (isset($pnrNames->infant)) {
                foreach ($pnrNames->infant as $adult) {
                    $infantArr['title'] = $adult->title;
                    $infantArr['name'] = $adult->name;
                    $infantArr['cnic'] = $adult->nic;
                    $infantArr['passenger_type'] = 'child';
                    $infantArr['ticket_number'] = $adult->tkt_no;
                    $pnrNamesArray[] = $infantArr;
                }
            }
        }
        return $pnrNamesArray;
    }

    public function ticket_generate($pnr) {
        $this->login();
        $token = Storage::get('AirSial_Token.txt');
        $request_message = array(
            "Caller" => "makePayment",
            "token" => $token,
            "PNR" => $pnr,
        );
        $this->message = json_encode(array($request_message));
        $dataJson = $this->curl_action('POST', $this->link, $this->message);
        $data = json_decode($dataJson);
        if ($data->Success) {

            return array(
                'status' => 'true',
                'message' => $data->Response->message
            );
        } else {

            return array(
                'status' => '4000',
                'message' => $data->Response->message
            );
        }
    }

}
