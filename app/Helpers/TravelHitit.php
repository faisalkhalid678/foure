<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use App\Models\Setting;

/*
 * For Travel Hitit
 */

class TravelHitit {

    // protected $message = '';
    protected $link = '';
    protected $credential = '';
    protected $APITYPE = '';

    public function __construct() {
        
        $this->APITYPE = 'preproduction';
        $set = new Setting();
        $settingData = $set->getSettingByCode('flights-api-type');
        if($settingData !== null){
            $this->APITYPE = $settingData->setting_value;
        }

        if ($this->APITYPE == 'production') {
            //BTS credietials producaiton Hitit
            $this->link = "https://app.crane.aero/craneota/CraneOTAService";
            $this->credential = "<clientInformation>
         		               <clientIP>182.191.83.168</clientIP>
         		               <member>false</member>
         		               <password>BTS@2020</password>
         		               <userName>A618ZV30</userName>
         		               <preferredCurrency>PKR</preferredCurrency>
         		            </clientInformation>";
        } elseif ($this->APITYPE == 'preproduction') {
            //BTS Pre Production credietials For Hitit
            $this->link = "https://app-stage.crane.aero/craneota/CraneOTAService";
            $this->credential = "<clientInformation>
                                        <clientIP>129.0.0.1</clientIP>
                                        <member>false</member>
                                        <password>Test1234</password>
                                        <userName>PSA2746344</userName>
                                        <preferredCurrency>PKR</preferredCurrency>
                                     </clientInformation>";
        }
        
        
    }

    public function oneway_search($request) {


        //dd($data->child);
        try {
            $child = '';
            if ($request->child != '') {
                $child = '<passengerTypeQuantityList>
	                  <hasStrecher/>
	                  <passengerType>
	                     <code>CHLD</code>
	                  </passengerType>
	                  <quantity>' . $request->child . '</quantity>
	               </passengerTypeQuantityList>';
            }
            $infant = '';
            if ($request->infant != '') {
                $infant = '<passengerTypeQuantityList>
	                  <hasStrecher/>
	                  <passengerType>
	                     <code>INFT</code>
	                  </passengerType>
	                  <quantity>' . $request->infant . '</quantity>
	               </passengerTypeQuantityList>';
            }

            $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:GetAvailability>
			         <AirAvailabilityRequest>
			            ' . $this->credential . '
			            <originDestinationInformationList>
										 <prefferedCabinClass>' . strtoupper($request->ticket_class) . '</prefferedCabinClass>
			               <dateOffset>0</dateOffset>
			               <departureDateTime>' . $request->depart_date . '</departureDateTime>
			               <destinationLocation>
			                  <locationCode>' . $request->distination_code . '</locationCode>
			               </destinationLocation>
			               <flexibleFaresOnly>false</flexibleFaresOnly>
			               <includeInterlineFlights>false</includeInterlineFlights>
			               <openFlight>false</openFlight>
			               <originLocation>
			                  <locationCode>' . $request->origion_code . '</locationCode>
			               </originLocation>
			            </originDestinationInformationList>
			            <travelerInformation>
			               <passengerTypeQuantityList>
			                  <hasStrecher/>
			                  <passengerType>
			                     <code>ADLT</code>
			                  </passengerType>
			                  <quantity>' . $request->adult . '</quantity>
			               </passengerTypeQuantityList>
			               ' . $child . $infant . '
			            </travelerInformation>
			            <tripType>' . $request->trip . '</tripType>
			         </AirAvailabilityRequest>
			      </impl:GetAvailability>
			   <impl:GetAirAvailability/></soapenv:Body>
			</soapenv:Envelope>';

            $this->message = $this->prettyPrint($message);
            $return = $this->curl_action();
            $return = $this->prettyPrint($return);
            $return = $this->removeNamespaceFromXML($return);
            $xml = simplexml_load_string($return);
            $array = json_decode(json_encode((array) $xml), true);
            return $array;
        } catch (\Exception $e) {
            return array();
        }
    }

    function outputAirSearch() {

        $flights = Storage::get('hitit/oneway_flight_resp.xml');
        //print_r($flights); die();
        $count = 0;
        $jurArray = [];
        $jur = [];
        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);

        $Results = $xml->children('SOAP', true);

        foreach ($Results->children('SOAP', true) as $fault) {
            if (strcmp($fault->getName(), 'Fault') == 0) {
                foreach ($fault->children() as $message) {
                    if (strcmp($message->getName(), 'faultstring') == 0) {
                        return 'false';
                    }
                }
            }
        }

        //print_r($Results->children('air', true)); die();
        foreach ($Results->children('air', true) as $lowFare) {
            foreach ($lowFare->children('air', true) as $airPriceSol) {
                //print_r(strcmp($airPriceSol->getName(), 'AirPricingSolution')); die();
                if (strcmp($airPriceSol->getName(), 'AirPricingSolution') == 0) {
                    $airpricing = [];
                    foreach ($airPriceSol->attributes() as $e => $f) {
                        if (strcmp($e, "ApproximateBasePrice") == 0)
                            $airpricing['basePrice'] = (string) $f;

                        if (strcmp($e, "TotalPrice") == 0) {
                            $airpricing['totalPrice'] = (string) $f;
                        }
                        if (strcmp($e, "Taxes") == 0) {
                            $airpricing['taxes'] = (string) $f;
                        }
                        if (strcmp($e, "PlatingCarrier") == 0) {
                            $airpricing['platingCarrier'] = (string) $f;
                        }
                    }
                    $jur['pricing_info'] = $airpricing;

                    $jindex = 0;
                    foreach ($airPriceSol->children('air', true) as $journey) {
                        if (strcmp($journey->getName(), 'Journey') == 0) {
                            $jindex++;
                            //$jur[$count][$jindex] = ['journey' . $jindex];
                            // $j=0;
                            $temp = [];
                            $t = 0;
                            foreach ($journey->children('air', true) as $segmentRef) {
                                if (strcmp($segmentRef->getName(), 'AirSegmentRef') == 0) {

                                    foreach ($segmentRef->attributes() as $a => $b) {
                                        $segment = $this->listAirSegments($b, $lowFare);

                                        // $details = $this->listAirSDetails($segment);
                                        $trip = [];

                                        foreach ($segment->attributes() as $c => $d) {
                                            $trip[$c] = (string) $d;
                                        }

                                        foreach ($segment->children('air', true) as $subChild) {
                                            if (strcmp($subChild->getName(), 'FlightDetailsRef') == 0) {
                                                foreach ($subChild->attributes() as $c => $d) {

                                                    $xml->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v45_0');
                                                    $x = json_decode(json_encode($xml->xpath("//air:FlightDetails[@Key='" . (string) $d . "']")), TRUE);
                                                    $trip['details'] = $x[0]['@attributes'];
                                                }
                                            }

                                            $trip[$c] = (string) $d;
                                        }
                                        $fareInfoRef = "0RVOhcqxnDKAfzMChAAAAA==";
                                        $fareRuleKey = "gws-eJxNTssOgzAM+xjku1OtMG7deAg2qWOlO3Dh/z9jSWHSIiV24shJCMHRCcVJ+I8Ke/VOiJ8OiHCa85pxrUmINhtIXpAfy3OR9MLpUasUi3yglMW+7bwCRo7+kCywlTqvd/MqzooCuwyjOsCPDJPSeOtzGuj1sm22copsoK9+Ada7K+I=";
                                        $fareRules = $this->fareRuleReq($fareInfoRef, $fareRuleKey);
                                        //print_r($fareRules); die();
                                        $baseUrl = url('/');
                                        //print_r($baseUrl); die();
                                        $trip['airline_logo'] = $baseUrl . '/public/airline_logo/' . $trip['Carrier'] . '.png';

                                        $trip['origin_city_name'] = $this->getCity($trip['Origin']);
                                        $trip['destination_city_name'] = $this->getCity($trip['Destination']);
                                        $temp[$t] = $trip;
                                    }
                                    $t++;
                                }
                            }

                            $jur['segments'] = $temp;
                        }


                        $pass_price = [];
                        foreach ($airPriceSol->children('air', true) as $priceInfo) {
                            // getting air pricing info attributes for passengers
                            if (strcmp($priceInfo->getName(), 'AirPricingInfo') == 0) {
                                $pass_single_price = [];
                                foreach ($priceInfo->attributes() as $e => $f) {
                                    if (strcmp($e, "TotalPrice") == 0)
                                        $pass_single_price['TotalPrice'] = (string) $f;
                                }

                                // getting cabin class and code e.g Economy L etc
                                $class = [];
                                $cabin = [];
                                foreach ($priceInfo->children('air', true) as $bookingInfo) {
                                    if (strcmp($bookingInfo->getName(), 'BookingInfo') == 0) {
                                        foreach ($bookingInfo->attributes() as $e => $f) {
                                            if (strcmp($e, "CabinClass") == 0)
                                                $cabin['CabinClass'] = (string) $f;

                                            if (strcmp($e, "BookingCode") == 0)
                                                $cabin['BookingCode'] = (string) $f;
                                        }
                                        $class[] = $cabin;
                                    }

                                    // getting passenger type e.g Adult , child , infant
                                    if (strcmp($bookingInfo->getName(), 'PassengerType') == 0) {
                                        foreach ($bookingInfo->attributes() as $e => $f) {
                                            if (strcmp($e, "Code") == 0)
                                                $pass_single_price['PassengerType'] = (string) $f;
                                        }
                                    }
                                }
                                $jur['cabin'] = $class;
                                $pass_price[] = $pass_single_price;
                            }
                        }
                        $jur['pass_price'] = $pass_price;
                        $jurArray[] = $jur;
                        // file_put_contents($fileName,"\r\n", FILE_APPEND);
                    }

                    $count++;
                }
            }
            return $jurArray;
        }
    }

    public function round_search($request) {
        $child = '';
        if ($request->child != '') {
            $child = '<passengerTypeQuantityList>
		                  <hasStrecher/>
		                  <passengerType>
		                     <code>CHLD</code>
		                  </passengerType>
		                  <quantity>' . $request->child . '</quantity>
		               </passengerTypeQuantityList>';
        }
        $infant = '';
        if ($request->infant != '') {
            $infant = '<passengerTypeQuantityList>
		                  <hasStrecher/>
		                  <passengerType>
		                     <code>INFT</code>
		                  </passengerType>
		                  <quantity>' . $request->infant . '</quantity>
		               </passengerTypeQuantityList>';
        }
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:GetAvailability>
			         <AirAvailabilityRequest>
			            ' . $this->credential . '
			            <originDestinationInformationList>
										 <prefferedCabinClass>' . strtoupper($request->ticket_class) . '</prefferedCabinClass>
			               <dateOffset>0</dateOffset>
			               <departureDateTime>' . $request->depart_date . '</departureDateTime>
			               <destinationLocation>
			                  <locationCode>' . $request->distination_code . '</locationCode>
			               </destinationLocation>
			               <flexibleFaresOnly>false</flexibleFaresOnly>
			               <includeInterlineFlights>false</includeInterlineFlights>
			               <openFlight>false</openFlight>
			               <originLocation>
			                  <locationCode>' . $request->origion_code . '</locationCode>
			               </originLocation>
			            </originDestinationInformationList>
			            <originDestinationInformationList>
										 <prefferedCabinClass>' . strtoupper($request->ticket_class) . '</prefferedCabinClass>
			               <dateOffset>0</dateOffset>
			               <departureDateTime>' . $request->return_date . '</departureDateTime>
			               <destinationLocation>
			                  <locationCode>' . $request->origion_code . '</locationCode>
			               </destinationLocation>
			               <flexibleFaresOnly>false</flexibleFaresOnly>
			               <includeInterlineFlights>false</includeInterlineFlights>
			               <openFlight>false</openFlight>
			               <originLocation>
			                  <locationCode>' . $request->distination_code . '</locationCode>
			               </originLocation>
			            </originDestinationInformationList>
			            <travelerInformation>
			               <passengerTypeQuantityList>
			                  <hasStrecher/>
			                  <passengerType>
			                     <code>ADLT</code>
			                  </passengerType>
			                  <quantity>' . $request->adult . '</quantity>
			               </passengerTypeQuantityList>
			               ' . $child . $infant . '
			            </travelerInformation>
			            <tripType>' . $request->trip . '</tripType>
			         </AirAvailabilityRequest>
			      </impl:GetAvailability>
			   <impl:GetAirAvailability/></soapenv:Body>
			</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        $return = $this->curl_action();
        $return = $this->prettyPrint($return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    public function multi_search($request) {
        // dd($request->all());
        $child = '';
        if ($request->child != '') {
            $child = '<passengerTypeQuantityList>
	                  <hasStrecher/>
	                  <passengerType>
	                     <code>CHLD</code>
	                  </passengerType>
	                  <quantity>' . $request->child . '</quantity>
	               </passengerTypeQuantityList>';
        }
        $infant = '';
        if ($request->infant != '') {
            $infant = '<passengerTypeQuantityList>
	                  <hasStrecher/>
	                  <passengerType>
	                     <code>INFT</code>
	                  </passengerType>
	                  <quantity>' . $request->infant . '</quantity>
	               </passengerTypeQuantityList>';
        }
        $o_code = $request->multi_origion_code;
        $originDest = '';
        for ($i = 0; $i < sizeof($o_code); $i++) {
            if ($request->ticket_class[$i] == 'First') {
                $tick_class = 'BUSINESS';
            } else {
                $tick_class = strtoupper($request->ticket_class[$i]);
            }

            $originDest .= '<originDestinationInformationList>
          		 <prefferedCabinClass>' . $tick_class . '</prefferedCabinClass>
               <dateOffset>0</dateOffset>
               <departureDateTime>' . db_format_date($request->multi_depart_date[$i]) . '</departureDateTime>
               <destinationLocation>
                  <locationCode>' . $request->multi_distination_code[$i] . '</locationCode>
               </destinationLocation>
               <flexibleFaresOnly>false</flexibleFaresOnly>
               <includeInterlineFlights>false</includeInterlineFlights>
               <openFlight>false</openFlight>
               <originLocation>
                  <locationCode>' . $request->multi_origion_code[$i] . '</locationCode>
               </originLocation>
            </originDestinationInformationList>';
        }

        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
		   <soapenv:Header/>
		   <soapenv:Body>
		      <impl:GetAvailability>
		         <AirAvailabilityRequest>
		            ' . $this->credential . $originDest . '
		            <travelerInformation>
		               <passengerTypeQuantityList>
		                  <hasStrecher/>
		                  <passengerType>
		                     <code>ADLT</code>
		                  </passengerType>
		                  <quantity>' . $request->adult . '</quantity>
		               </passengerTypeQuantityList>
		               ' . $child . $infant . '
		            </travelerInformation>
		            <tripType>MULTI_DIRECTIONAL</tripType>
		         </AirAvailabilityRequest>
		      </impl:GetAvailability>
		   </soapenv:Body>
		</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        Storage::put('hitit/multi_flight_req.xml', $this->message);
        $return = $this->curl_action();
        // $return = Storage::get('multi_flight_resp.xml');
        // dd($return);
        $return = $this->prettyPrint($return);
        Storage::put('hitit/multi_flight_resp.xml', $return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    public function makeBookingDetailArray($booking_detail) {
        $titles = array();
        $fnames = array();
        $lnames = array();
        $cnics = array();
        $nationalities = array();
        $dobs = array();
        $passenger_types = array();
        $passport_numbers = array();
        $passport_types = array();
        $expiry_dates = array();
        $issue_countrys = array();
        if (!empty($booking_detail)) {
            foreach ($booking_detail as $key => $detail) {
                $titles[] = $detail['title'];
                $fnames[] = $detail['firstName'];
                $lnames[] = $detail['lastName'];
                $cnics[] = isset($detail['cnic'])?$detail['cnic']:"";
                $nationalities[] = isset($detail['nationality'])?$detail['nationality']:"";
                $dobs[] = date('Y-m-d', strtotime($detail['dob_day'] . '-' . $detail['dob_month'] . '-' . $detail['dob_year']));
                if ($detail['passenger_type'] == 'INF') {
                    $pass_t = 'INFT';
                } else if ($detail['passenger_type'] == 'CNN') {
                    $pass_t = 'CHLD';
                } else if ($detail['passenger_type'] == 'ADT') {
                    $pass_t = 'ADLT';
                } else {
                    $pass_t = 'ADLT';
                }
                $passenger_types[] = $pass_t;
                $passport_numbers[] = isset($detail['passport_number'])?$detail['passport_number']:"";
                $passport_types[] = isset($detail['passport_type'])?$detail['passport_type']:"";
                $expiry_dates[] = date('Y-m-d', strtotime($detail['exp_day'] . '-' . $detail['exp_month'] . '-' . $detail['exp_year']));
                $issue_countrys[] = $detail['issue_country'];
            }
        }
        return $returnArray = array(
            'title' => $titles,
            'f_name' => $fnames,
            'l_name' => $lnames,
            'cnic' => $cnics,
            'nationality' => $nationalities,
            'dob' => $dobs,
            'passenger_type' => $passenger_types,
            'passport_number' => $passport_numbers,
            'passport_type' => $passport_types,
            'expiry_date' => $expiry_dates,
            'issue_country' => $issue_countrys,
        );
    }

    public function makeSegmentsXml($data) {

        $segment = '<flightSegment>
                                    <airline>
                                       <code>' . $data['Carrier'] . '</code>
                                       <companyFullName>' . $data['companyFullName'] . '</companyFullName>
                                       <companyShortName>' . $data['companyShortName'] . '</companyShortName>
                                    </airline>
                                    <arrivalAirport>
                                       <cityInfo>
                                          <city>
                                             <locationCode>' . $data['Destination'] . '</locationCode>
                                             <locationName>' . $data['Destination_city'] . '</locationName>
                                             <locationNameLanguage>' . $data['Destination_language'] . '</locationNameLanguage>
                                          </city>
                                          <country>
                                             <locationCode>' . $data['Destination_country_code'] . '</locationCode>
                                             <locationName>' . $data['Destination_country'] . '</locationName>
                                             <locationNameLanguage>' . $data['Destination_country_language'] . '</locationNameLanguage>
                                             <currency>
                                                <code>' . $data['Destination_currency']['code'] . '</code>
                                             </currency>
                                          </country>
                                       </cityInfo>
                                       <codeContext>' . $data['Destination_codeContext'] . '</codeContext>
                                       <language>' . $data['Destination_main_language'] . '</language>
                                       <locationCode>' . $data['Destination_locationCode'] . '</locationCode>
                                       <locationName>' . $data['Destination_locationName'] . '</locationName>
                                       <terminal/>
                                    </arrivalAirport>
                                    <arrivalDateTime>' . $data['ArrivalTime'] . '</arrivalDateTime>
                                    <arrivalDateTimeUTC>' . $data['arrivalDateTimeUTC'] . '</arrivalDateTimeUTC>
                                    <departureAirport>
                                       <cityInfo>
                                          <city>
                                             <locationCode>' . $data['Origin'] . '</locationCode>
                                             <locationName>' . $data['Origin_city'] . '</locationName>
                                             <locationNameLanguage>' . $data['Origin_city_language'] . '</locationNameLanguage>
                                          </city>
                                          <country>
                                             <locationCode>' . $data['Origin_country_code'] . '</locationCode>
                                             <locationName>' . $data['Origin_country_name'] . '</locationName>
                                             <locationNameLanguage>' . $data['Origin_country_language'] . '</locationNameLanguage>
                                             <currency>
                                                <code>' . $data['Origin_country_currency']['code'] . '</code>
                                             </currency>
                                          </country>
                                       </cityInfo>
                                       <codeContext>' . $data['Origin_codeContext'] . '</codeContext>
                                       <language>' . $data['Origin_language'] . '</language>
                                       <locationCode>' . $data['Origin_locationCode'] . '</locationCode>
                                       <locationName>' . $data['Origin_locationName'] . '</locationName>
                                       <terminal/>
                                    </departureAirport>
                                    <departureDateTime>' . $data['DepartureTime'] . '</departureDateTime>
                                    <departureDateTimeUTC>' . $data['departureDateTimeUTC'] . '</departureDateTimeUTC>
                                    <flightNumber>' . $data['FlightNumber'] . '</flightNumber>
                                    <ondControlled>' . $data['ondControlled'] . '</ondControlled>
                                    <codeshare>' . $data['codeshare'] . '</codeshare>
                                    <distance>' . $data['distance'] . '</distance>
                                    <equipment>
                                       <airEquipType>' . $data['Equipment']['airEquipType'] . '</airEquipType>
                                       <airEquipTypeModel>' . $data['Equipment']['airEquipTypeModel'] . '</airEquipTypeModel>
                                       <changeofGauge>' . $data['Equipment']['changeofGauge'] . '</changeofGauge>
                                    </equipment>
                                    <flightNotes>';
//                                      if(!empty($data['FlightNotes'])){
//                                         
//                                          foreach($data['FlightNotes'] as $FlightNote){
//                                              $segment .='<deiCode>'.$FlightNote['deiCode'].'</deiCode>
//                                       <explanation>'.$FlightNote['explanation'].'</explanation>
//                                       <note>'.$FlightNote['note'].'</note>';
//                                          }
//                                      } 
        $segment .= '</flightNotes>
                                    <flownMileageQty>' . $data['flownMileageQty'] . '</flownMileageQty>
                                    <journeyDuration>' . $data['distance'] . '</journeyDuration>
                                    <onTimeRate>' . $data['onTimeRate'] . '</onTimeRate>
                                    <secureFlightDataRequired>' . $data['secureFlightDataRequired'] . '</secureFlightDataRequired>
                                    <stopQuantity>' . $data['FlightTime'] . '</stopQuantity>
                                 </flightSegment>';
        return ($segment);
    }

    /* ========================================
      =            Flight bookings             =
      ======================================== */

    public function flight_booking($request) {

        //$segmentClass = $request['segmentsData']['bookingClassList'];
        $flightSegment = $request['segmentsData']['segments'];
        //print_r($flightSegment); die();
        $fareInfo = $request['segmentsData']['price_info'];
        //print_r($fareInfo); die();
        //Passenger and Booking Data
        $booking_detail = $request['booking_detail'];
        $allInputs = $this->makeBookingDetailArray($booking_detail);
        $pass_type = $allInputs['passenger_type']; // INF /CNN / ADT
        $title = $allInputs['title'];
        $first_name = $allInputs['f_name'];
        $last_name = $allInputs['l_name'];
        $nationality = $allInputs['nationality'];
        $cnic = $allInputs['cnic'];
        $birth_date = $allInputs['dob'];
        $ptype = $allInputs['passport_type'];
        $passport_no = $allInputs['passport_number'];
        $issue_country = $allInputs['issue_country'];
        $expiration_data = $allInputs['expiry_date'];

//        $street = $mainData['street'] ? $mainData['street'] : "";
//        $city = $mainData['city'] ? $mainData['city'] : "";
//        $state = $mainData['state'] ? $mainData['state'] : "";
//        $postal_code = $mainData['postal_code'] ? $mainData['postal_code'] : "";
//        $country = $mainData['country'] ? $mainData['country'] : "";
//        $data = $PricingInfoData;

        $email = 'info@foureflights.com';

        $segClass = '';
        $flight = '';
        $fareInfoList = '';
        $originDestinationList = '';
        $xmlVers = '<?xml version="1.0"?>';
        $originDestinationListcombine = "";

        // print_r($flightSegment); die();
        $count = 0;
        foreach ($flightSegment as $key => $fsegment) {

            foreach ($fsegment as $key22 => $segg) {
                $key22 = $count + $key22;
                $segmentClass = $segg['bookingClassList'];

                //foreach($segmentClass as $sclass){
                $segClass .= str_replace($xmlVers, '', $this->combArrToXML($segmentClass[0], "bookingClass"));
                //}


                $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><flightSegment></flightSegment>");
                $fliSeg = $segg['segment_data'];
                $this->array_to_xml($fliSeg, $xml);
                $flight = $this->makeSegmentsXml($fliSeg);
                $xml1 = new SimpleXMLElement("<?xml version=\"1.0\"?><fareInfo></fareInfo>");
                $this->array_to_xml($fareInfo['fareInfoList'][$key22], $xml1);
                $fareInfoList = str_replace($xmlVers, '', $xml1->asXML());

                $originDestinationList .= '
                <bookOriginDestinationOptionList>
                <bookFlightSegmentList>
					                        <actionCode>NN</actionCode>
					                        <addOnSegment/>
					                           ' . $segClass . $fareInfoList . $flight . '
					                        <sequenceNumber/>
					                     </bookFlightSegmentList>
                                                             </bookOriginDestinationOptionList>
                
                                                          ';
            }
//        $originDestinationList .= '<bookOriginDestinationOptionList>
//					            '.$originDestinationListcombine.'         
//					                  </bookOriginDestinationOptionList>';
            $count++;
        }

        $quantity = array_count_values($pass_type);
        $passInfo = '';
        $inf = '';
        $chld = '';
        $user = explode(' ', 'Test User');
        for ($i = 0; $i < sizeof($pass_type); $i++) {
            if ($title[$i] == 'MR' || $title[$i] == 'Mr' || $title[$i] == 'mr' || $title[$i] == 'MR.' || $title[$i] == 'mr.') {
                $gender = 'M';
            } else {
                $gender = 'F';
            }

            if ($pass_type[$i] != "INFT") {
                // pr(db_format_date($request->dob[$i]));
                $passInfo .= '<airTravelerList>
	               <gender>' . $gender . '</gender>
	               <accompaniedByInfant/>
	               <birthDate>' . db_format_date($birth_date[$i]) . '</birthDate>
	               <hasStrecher/><parentSequence/>
	               <passengerTypeCode>' . $pass_type[$i] . '</passengerTypeCode>
	               <personName>
	                  <givenName>' . $first_name[$i] . '</givenName>
	                  <shareMarketInd/>
	                  <surname>' . $last_name[$i] . '</surname>
	                  <passportno>' . $passport_no[$i] . '</passportno>
	                  <expdate>' . $expiration_data[$i] . '</expdate>
	               </personName>
	               <contactPerson>
	                  <email>
	                     <email>' . $email . '</email>
	                     <shareMarketInd>true</shareMarketInd>
	                  </email>
	                  <markedForSendingRezInfo>true</markedForSendingRezInfo>
	                  <personName>
	                     <givenName>' . $user[0] . '</givenName>
	                     <surname>' . $user[1] . '</surname>
	                  </personName>
	                  <phoneNumber>
	                     <areaCode>344</areaCode>
	                     <countryCode>+92</countryCode>
	                     <markedForSendingRezInfo>true</markedForSendingRezInfo>
	                     <subscriberNumber>' . substr('03001234567', 1) . '</subscriberNumber>
	                  </phoneNumber>
	               </contactPerson>
	               <requestedSeatCount>1</requestedSeatCount>
	               <shareMarketInd/><unaccompaniedMinor/>
                       <documentInfoList>

                        <birthDate>'.$birth_date[$i].'</birthDate>';

                if(isset($passport_no[$i]) && $passport_no[$i] !== "" &&  $cnic[$i] == ""){
                    $passInfo .= '<docExpireDate>'.$expiration_data[$i].'</docExpireDate>

                        <docHolderFormattedName>

                           <givenName>'.$first_name[$i].'</givenName>

                           <shareMarketInd>false</shareMarketInd>

                           <surname>'.$last_name[$i].'</surname>

                        </docHolderFormattedName>

                        <docHolderNationality>'.$nationality[$i].'</docHolderNationality>

                        <docID>'.$passport_no[$i].'</docID> 

                        <docType>PASSPORT</docType> ';
                }else if(isset($cnic[$i]) && $cnic[$i] != ""){
                    $passInfo .= '
                        <docHolderFormattedName>

                           <givenName>'.$first_name[$i].'</givenName>

                           <shareMarketInd>false</shareMarketInd>

                           <surname>'.$last_name[$i].'</surname>

                        </docHolderFormattedName>

                     

                        <docID>'.$cnic[$i].'</docID> 

                        <docType>PASSPORT</docType> ';
                }
                        

                        $passInfo .='<gender>M</gender> 

                     </documentInfoList>
	            </airTravelerList>';
            }
            if ($pass_type[$i] == "INFT") {
                
                for ($k = 0; $k < sizeof($flightSegment); $k++) {
                    $segNo = $k + 1;
                    $data_of_birth = hitit__date_fomrat($birth_date[$i]);
                    $inf .= '<specialServiceRequestList>
		                  <airTravelerSequence>' . $quantity['INFT'] . '</airTravelerSequence>
		                  <flightSegmentSequence>' . $segNo . '</flightSegmentSequence>
		                  <SSR>
		                     <code>' . $pass_type[$i] . '</code>
		                     <explanation>' .
                            $last_name[$i] . '/' . $first_name[$i] . ' ' . euro_date($data_of_birth)
                            . '</explanation>
		                  </SSR>
		                  <serviceQuantity>1</serviceQuantity>
		                  <status>NN</status>
		                  <ticketed/>
		               </specialServiceRequestList>';
                }
            }
            $childSequence = $i + 1;
            if ($pass_type[$i] == "CHLD") {
                $data_of_birth = hitit__date_fomrat($birth_date[$i]);
                $chld .= '<specialServiceRequestList>
		                  <airTravelerSequence>' . $childSequence . '</airTravelerSequence>
		                  <flightSegmentSequence>0</flightSegmentSequence>
		                  <SSR>
		                     <code>' . $pass_type[$i] . '</code>
		                     <explanation>' . euro_date($data_of_birth) . '</explanation>
		                  </SSR>
		                  <serviceQuantity>1</serviceQuantity>
		                  <status>NN</status>
		                  <ticketed/>
		               </specialServiceRequestList>';
            }
        }
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:CreateBooking>
			         <AirBookingRequest>
			            ' . $this->credential . '
			            <airItinerary>
			               <bookOriginDestinationOptions>
			                  ' . $originDestinationList . '
			               </bookOriginDestinationOptions>
			            </airItinerary>
			            ' . $passInfo . '
			            <requestPurpose>MODIFY_PERMANENTLY_AND_CALC</requestPurpose>
			         	<specialRequestDetails>
			         	' . $chld . $inf . '
			         	</specialRequestDetails>
			         </AirBookingRequest>
			      </impl:CreateBooking>
			   </soapenv:Body>
			</soapenv:Envelope>';
        $this->message = $this->prettyPrint($message);

        $return = $this->curl_action();
        $return = $this->prettyPrint($return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    /* =====  End of Flight bookings   ====== */
    /* ======================================
      =            View Ticketing            =
      ====================================== */

    public function ticket_issue($pnr, $ref_id, $price) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:TicketReservation>
			         <AirTicketReservationRequest>
			            ' . $this->credential . '
			            <bookingReferenceID>
				            <companyName>
				               <cityCode>SDT</cityCode>
				               <code>PK</code>
				               <codeContext>CRANE</codeContext>
				               <companyFullName>Mingora Travels PSA</companyFullName>
				               <companyShortName>Mingora Travels PSA</companyShortName>
				               <countryCode>PK</countryCode>
				            </companyName>
				            <ID>' . $pnr . '</ID>
				            <referenceID>' . $ref_id . '</referenceID>
			          	</bookingReferenceID>
			            <fullfillment>
			               <paymentDetails>
			                  <paymentDetailList>
			                     <miscChargeOrder>
			                        <avsEnabled/><capturePaymentToolNumber>false</capturePaymentToolNumber>
			                        <paymentCode>INV</paymentCode>
			                        <threeDomainSecurityEligible>false</threeDomainSecurityEligible>
			                        <transactionFeeApplies/>
			                        <MCONumber>101359</MCONumber>
			                     </miscChargeOrder>
			                     <payLater/><paymentAmount>
			                        <currency>
			                           <code>PKR</code>
			                        </currency>
			                        <value>' . $price . '</value>
			                     </paymentAmount>
			                     <paymentType>MISC_CHARGE_ORDER</paymentType>
			                     <primaryPayment>true</primaryPayment>
			                  </paymentDetailList>
			               </paymentDetails>
			            </fullfillment>
			            <requestPurpose>COMMIT</requestPurpose>
			         </AirTicketReservationRequest>
			      </impl:TicketReservation>
			   </soapenv:Body>
			</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        $return = $this->curl_action();
        $return = $this->prettyPrint($return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    /* =====  End of View Ticketing  ====== */

    /* ====================================
      =            Read Booking            =
      ==================================== */

    public function read_booking($pnr) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:ReadBooking>
			         <AirBookingReadRequest>
			            ' . $this->credential . '
			            <bookingReferenceID>
			               <ID>' . $pnr . '</ID>
			            </bookingReferenceID>
			         </AirBookingReadRequest>
			      </impl:ReadBooking>
			   </soapenv:Body>
			</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        Storage::put('hitit/read_booking_req.xml', $this->message);
        $return = $this->curl_action();
        $return = $this->prettyPrint($return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    /* =====  End of Read Booking  ====== */

    public function view_only($pnr, $ref_no) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:TicketReservation>
			         <AirTicketReservationRequest>
			           ' . $this->credential . '
			            <bookingReferenceID>
			               <companyName>
			                  <cityCode>SDT</cityCode>
			                  <code>PK</code>
			                  <codeContext>CRANE</codeContext>
			                  <companyFullName>Mingora Travels PSA</companyFullName>
			                  <companyShortName>Mingora Travels PSA</companyShortName>
			                  <countryCode>PK</countryCode>
			               </companyName>
			               <ID>' . $pnr . '</ID>
			               <referenceID>' . $ref_no . '</referenceID>
			            </bookingReferenceID>
			            <requestPurpose>VIEW_ONLY</requestPurpose>
			         </AirTicketReservationRequest>
			      </impl:TicketReservation>
			   </soapenv:Body>
			</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        Storage::put('hitit/TicketReservation_view_only_req.xml', $this->message);
        $return = $this->curl_action();
        // $return = Storage::get('TicketReservation_view_only_resp.xml');
        $return = $this->prettyPrint($return);
        Storage::put('hitit/TicketReservation_view_only_resp.xml', $return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    /* =====  End of Read Booking  ====== */

    public function combArrToXML($arrC = array(), $element = "element") {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;

        $b = $doc->createElement($element);
        $doc->appendChild($b);
        foreach ($arrC as $key => $val) {
            // print_r($val);
            $$key = $doc->createElement($key);
            if (is_array($val)) {
                foreach ($val as $key1 => $val1) {
                    print_r($val1);
                    $$key1 = $doc->createElement($key1);
                    $$key1->appendChild(
                            $doc->createTextNode($val1)
                    );
                    $b->appendChild($$key1);
                }
            }
            $$key->appendChild(
                    $doc->createTextNode($val)
            );
            $b->appendChild($$key);
        }

        return $doc->saveXML();
    }

    public function array_to_xml($array, &$xml) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild("$key");
                    $this->array_to_xml($value, $subnode);
                } else {
                    $this->array_to_xml($value, $xml);
                }
            } else {
                $xml->addChild("$key", "$value");
            }
        }
    }

    /* ===================================================================
      =            Api Curl Xml to String and remove namespace            =
      =================================================================== */

    public function prettyPrint($result) {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($result);
        $dom->formatOutput = true;
        // dd($dom->saveXML());
        return $dom->saveXML();
    }

    function curl_action() {

        $header = array(
            "Content-Type: text/xml;charset=UTF-8",
            "Accept: gzip,deflate",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"\"",
            "Content-length: " . strlen($this->message),
        );
        $soap_do = curl_init($this->link);

        //print_r($this->link); die();
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap_do, CURLOPT_POST, true);
        curl_setopt($soap_do, CURLOPT_POSTFIELDS, $this->message);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, $header);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        $return = curl_exec($soap_do);

        curl_close($soap_do);

        return $return;
    }

    function removeNamespaceFromXML($xml) {
        $toRemove = ['S', 'ns2'];
        // $toRemove = ['air', 'turss', 'crim];
        // This is part of a regex I will use to remove the namespace declaration from string
        $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';
        // Cycle through each namespace and remove it from the XML string
        foreach ($toRemove as $remove) {
            // First remove the namespace from the opening of the tag
            $xml = str_replace('<' . $remove . ':', '<', $xml);
            // Now remove the namespace from the closing of the tag
            $xml = str_replace('</' . $remove . ':', '</', $xml);
            // This XML uses the name space with CommentText, so remove that too
            // $xml = str_replace($remove . ':BookingTraveler', 'BookingTraveler', $xml);
            $pattern = "/xmlns:{$remove}{$nameSpaceDefRegEx}/";
            // Remove the actual namespace declaration using the Pattern
            $xml = preg_replace($pattern, '', $xml, 1);
        }
        // Return sanitized and cleaned up XML with no namespaces
        return $xml;
    }

    /* =====  End of Api Curl Xml to String and remove namespace  ====== */

    public function airport_codes() {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
			   <soapenv:Header/>
			   <soapenv:Body>
			      <impl:GetAirPortMatrix>
			         <AirPortMatrixRequest>
			            ' . $this->credential . '
			         </AirPortMatrixRequest>
			      </impl:GetAirPortMatrix>
			   </soapenv:Body>
			</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        Storage::put('hitit/airport_matrix_req.xml', $this->message);
        $return = $this->curl_action();
        // $return = Storage::get('airport_matrix_resp.xml');
        $return = $this->prettyPrint($return);
        Storage::put('hitit/airport_matrix_resp.xml', $return);
        $return = $this->removeNamespaceFromXML($return);

        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        // return $xml;
        dd($array);
    }

    function createXMLFile($empData) {
        $title = $empData['title'];
        $totalEmployee = count($empData['employee']);
        $xmlDocument = new DOMDocument();
        $root = $xmlDocument->appendChild($xmlDocument->createElement("employee_details"));
        $root->appendChild($xmlDocument->createElement("title", $title));
        $root->appendChild($xmlDocument->createElement("totalEmployee", $totalEmployee));
        $empRecords = $root->appendChild($xmlDocument->createElement('emp_records'));
        foreach ($empData['employee'] as $employee) {
            if (!empty($employee)) {
                $empRecord = $empRecords->appendChild($xmlDocument->createElement('employee'));
                foreach ($employee as $key => $val) {
                    $empRecord->appendChild($xmlDocument->createElement($key, $val));
                }
            }
        }
        $fileName = str_replace(' ', '_', $title) . '_' . time() . '.xml';
        $xmlDocument->formatOutput = true;
        $xmlDocument->save("XML_FILE/" . $fileName);
    }

    public function cancel_booking_hitit_req($referenceId, $pnr) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
                <soapenv:Header/>
                <soapenv:Body>
                  <impl:CancelBooking>
                     <AirCancelBookingRequest>
                ' . $this->credential . '
                        <bookingReferenceID>
                        <companyName>
                          <cityCode>ISB</cityCode>
                          <code>PK</code>
                          <codeContext>CRANE</codeContext>
                          <companyFullName>Ramada International</companyFullName>
                          <companyShortName>Ramada International</companyShortName>
                          <countryCode>PK</countryCode>
                        </companyName>
                        <ID>' . $pnr . '</ID>
                        <referenceID>' . $referenceId . '</referenceID>
                        </bookingReferenceID>
                        <requestPurpose>COMMIT</requestPurpose>
                     </AirCancelBookingRequest>
                  </impl:CancelBooking>
                </soapenv:Body>
        </soapenv:Envelope>';
        $this->message = $this->prettyPrint($message);
        $return = $this->curl_action();
        $return = $this->prettyPrint($return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    public function pnr_retrive_hitit_req($pnr, $vendor_locator) {
        // $message ='<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
        // 					  <soapenv:Header/>
        // 					  <soapenv:Body>
        // 				     	<impl:ReadBooking>
        // 			         	<AirBookingReadRequest>
        // 	           			'.$this->credential.'
        // 			            <bookingReferenceID>
        // 										<companyName>
        // 											<cityCode>ISB</cityCode>
        // 											<code>PK</code>
        // 											<codeContext>CRANE</codeContext>
        // 										  <companyFullName>Ramada International</companyFullName>
        // 										  <companyShortName>Ramada International</companyShortName>
        // 											<countryCode>PK</countryCode>
        // 										</companyName>
        // 										<ID>'.$vendor_locator.'</ID>
        // 										<referenceID>'.$pnr.'</referenceID>
        // 			            </bookingReferenceID>
        // 			         	</AirBookingReadRequest>
        // 				      </impl:ReadBooking>
        // 					  </soapenv:Body>
        // 					</soapenv:Envelope>';


        $message = '	<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
						   <soapenv:Header/>
						   <soapenv:Body>
						      <impl:TicketReservation>
						         <AirTicketReservationRequest>
			           			' . $this->credential . '
						            <bookingReferenceID>
													<companyName>
													  <cityCode>ISB</cityCode>
													  <code>PK</code>
													  <codeContext>CRANE</codeContext>
													<companyFullName>Ramada International</companyFullName>
													<companyShortName>Ramada International</companyShortName>
													  <countryCode>PK</countryCode>
													</companyName>
													<ID>' . $vendor_locator . '</ID>
						            </bookingReferenceID>
						            <requestPurpose>VIEW_ONLY</requestPurpose>
						         </AirTicketReservationRequest>
						      </impl:TicketReservation>
						   </soapenv:Body>
						</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        Storage::put('hitit/ReadBooking_TK_req.xml', $this->message);
        $return = $this->curl_action();
        // $return = Storage::get('TicketReservation_view_only_resp.xml');
        $return = $this->prettyPrint($return);
        Storage::put('hitit/ReadBooking_TK_resp.xml', $return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

    public function ticket_resveration_commit($pnr = '', $supplier_paybill = '') {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">
					  <soapenv:Header/>
					  <soapenv:Body>
					    <impl:TicketReservation>
					      <AirTicketReservationRequest>
			           	' . $this->credential . '
					        <bookingReferenceID>
										<companyName>
										  <cityCode>ISB</cityCode>
										  <code>PK</code>
										  <codeContext>CRANE</codeContext>
										<companyFullName>Ramada International</companyFullName>
										<companyShortName>Ramada International</companyShortName>
										  <countryCode>PK</countryCode>
										</companyName>
					          <ID>' . $pnr . '</ID>
					        </bookingReferenceID>
					        <fullfillment>
					          <paymentDetails>
					            <paymentDetailList>
					              <miscChargeOrder>
					                <avsEnabled/>
					                <capturePaymentToolNumber>false</capturePaymentToolNumber>
					                <paymentCode>INV</paymentCode>
					                <threeDomainSecurityEligible>false</threeDomainSecurityEligible>
					                <transactionFeeApplies/>
					                <MCONumber>4000007387</MCONumber>
					              </miscChargeOrder>
					              <payLater/>
					              <paymentAmount>
					                <currency>
					                  <code>PKR</code>
					                </currency>
					                <value>' . $supplier_paybill . '</value>
					              </paymentAmount>
					              <paymentType>MISC_CHARGE_ORDER</paymentType>
					              <primaryPayment>true</primaryPayment>
					            </paymentDetailList>
					          </paymentDetails>
					        </fullfillment>
					        <requestPurpose>COMMIT</requestPurpose>
					      </AirTicketReservationRequest>
					    </impl:TicketReservation>
					  </soapenv:Body>
					</soapenv:Envelope>';

        $this->message = $this->prettyPrint($message);
        Storage::put('hitit/ticket_issue_req.xml', $this->message);
        $return = $this->curl_action();
        // $return = Storage::get('TicketReservation_view_only_resp.xml');
        $this->prettyPrint($return);
        Storage::put('hitit/ticket_issue_resp.xml', $return);
        $return = $this->removeNamespaceFromXML($return);
        $xml = simplexml_load_string($return);
        $array = json_decode(json_encode((array) $xml), true);
        return $array;
    }

}
