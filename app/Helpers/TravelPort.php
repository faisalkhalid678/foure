<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Setting;

class TravelPort {

    protected $TARGETBRANCH = '';
    protected $CREDENTIALS = '';
    protected $auth = '';
    protected $Provider = '';
    protected $message = '';
    protected $link = '';
    protected $APITYPE = '';

    function __construct() {
        $this->APITYPE = 'preproduction';
        $set = new Setting();
        $settingData = $set->getSettingByCode('flights-api-type');
        if($settingData !== null){
            $this->APITYPE = $settingData->setting_value;
        }
        if ($this->APITYPE == 'production') {
            //BTS credietials producaiton key 7J47
            $this->TARGETBRANCH = "P3220788";
            $this->CREDENTIALS = 'Universal API/uAPI8653485365-a1381b7e:E$p5!i7J8R';
            $this->auth = base64_encode("$this->CREDENTIALS");
            $this->Provider = '1G';
            $this->link = ("https://emea.universal-api.travelport.com/B2BGateway/connect/uAPI/AirService");
        } elseif ($this->APITYPE == 'preproduction') {
            //BTS Pre Production credietials 7J47
            $this->TARGETBRANCH = "P7120835";
            $this->CREDENTIALS = 'Universal API/uAPI5318013958-52be9b0d:3o%L!Ng8b{';
            $this->auth = base64_encode("$this->CREDENTIALS");
            $this->Provider = '1G';
            $this->link = ("https://emea.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/AirService");
        }
    }

    function prettyPrint($result) {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($result);
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    function parseOutput($content) {
        $xml = simplexml_load_String("$content", null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);
        foreach ($Results->children('SOAP', true) as $fault) {
            if (strcmp($fault->getName(), 'Fault') == 0) {
                return $total_rec = 'Error';
            }
        }
        $count = 0;
        $total_rec = false;
        foreach ($Results->children('util', true) as $nodes) {
            foreach ($nodes->children('util', true) as $hsr) {
                if (strcmp($hsr->getName(), 'Airport') == 0) {
                    $count = $count + 1;
                    foreach ($hsr->attributes() as $a => $b) {
                        if (strcmp($a, 'Code') == 0) {
                            $total_rec[$count]['Code'] = (string) $b;
                        } else if (strcmp($a, 'Name') == 0) {
                            $total_rec[$count]['Name'] = (string) $b;
                        } else if (strcmp($a, 'CountryCode') == 0) {
                            $total_rec[$count]['CountryCode'] = (string) $b;
                        }
                    }
                }
            }
        }
        return $total_rec;
    }

    function setMessage($message) {
        $this->message = $message;
    }

    function soapMainLink($link = null) {
        if (!$link) {
            $this->link = $link;
        }
    }

    function curl_action() {
        $header = array(
            "Content-Type: text/xml;charset=UTF-8",
            "Accept: gzip,deflate",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"\"",
            "Authorization: Basic " . $this->auth,
            "Content-length: " . strlen($this->message),
        );
        $soap_do = curl_init($this->link);
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

    // function for setting one way trip api request xml
    public function one_way_trip_req($trip, $origion = null, $destination = null, $fromDate = null, $adult = null, $child = null, $infant = null, $stop = null, $ticket_class = null) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
				   <soapenv:Header/>
				   <soapenv:Body>
				      <air:LowFareSearchReq TraceId="trace" AuthorizedBy="user" SolutionResult="true" TargetBranch="' . $this->TARGETBRANCH . '" xmlns:air="http://www.travelport.com/schema/air_v45_0" xmlns:com="http://www.travelport.com/schema/common_v45_0">
				         <com:BillingPointOfSaleInfo OriginApplication="UAPI"/>';
        $message .= '<air:SearchAirLeg>';
        if ($origion != null) {
            $mix_orgion = explode(" - ", $origion);
            $origion = $mix_orgion[0];
            $message .= '<air:SearchOrigin>
				   	<com:CityOrAirport Code="' . $origion . '" PreferCity="true"/>
				   </air:SearchOrigin>';
        }
        // if destination is set
        if ($destination != null) {
            $mix_destinationn = explode(" - ", $destination);
            $destination = $mix_destinationn[0];
            $message .= '<air:SearchDestination>
				        <com:CityOrAirport Code="' . $destination . '" PreferCity="true" />
				   </air:SearchDestination>';
        }
        //if fromDate is set
        if ($fromDate != null) {
            $newDate = date("Y-m-d", strtotime(str_replace('/', '-', $fromDate)));
            $message .= '<air:SearchDepTime PreferredTime="' . $newDate . '"></air:SearchDepTime>';
        }
        // --------------------------air leg modifier for stops and class type --------------------------------------
        $message .= '<air:AirLegModifiers AllowDirectAccess="true" >';
        if ($ticket_class != null) {
            $message .= '<air:PreferredCabins>
			                <CabinClass xmlns="http://www.travelport.com/schema/common_v45_0" Type="' . $ticket_class . '" />
			              </air:PreferredCabins>';
        }
        $message .= '</air:AirLegModifiers>';
        // --------------------------end air leg modifier for stops and class type -----------------
        $message .= '</air:SearchAirLeg>';
        $message .= '<air:AirSearchModifiers>
                        <air:PreferredProviders>
                           <com:Provider Code="' . $this->Provider . '"/>
                        </air:PreferredProviders>';
        $message .= '<air:ProhibitedCarriers>
                    <com:Carrier Code="PK" />
                  </air:ProhibitedCarriers>';
        if ($stop != null) {
            if ($stop == 0)
                $message .= '<air:FlightType NonStopDirects="true" />';

            if ($stop == 1)
                $message .= '<air:FlightType MaxStops="' . $stop . '"/>';
        }
        $message .= '</air:AirSearchModifiers>';
        if ($adult != null) {
            for ($i = 1; $i <= $adult; $i++) {
                $message .= '<com:SearchPassenger  Code="ADT" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }
        if ($child != null) {
            for ($i = 1; $i <= $child; $i++) {

                $chBirth = date('Y-m-d', strtotime('-10 years'));
                $message .= '<com:SearchPassenger Code="CNN" Age="10" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }
        if ($infant != null) {
            for ($i = 1; $i <= $infant; $i++) {

                $chBirth = date('Y-m-d', strtotime('-1 years'));
                $message .= '<com:SearchPassenger Code="INF" Age="1" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }
        $message .= ' <air:AirPricingModifiers FaresIndicator="PublicAndPrivateFares" >

						  </air:AirPricingModifiers>';
        $message .= '</air:LowFareSearchReq>
				   </soapenv:Body>
				</soapenv:Envelope>';
        $this->message = $message;
        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        return $content;
    }

    // Low Fare Search finding listAirSigmant tag in xml response
    function listAirSegments($key, $lowFare) {
        foreach ($lowFare->children('air', true) as $airSegmentList) {
            if (strcmp($airSegmentList->getName(), 'AirSegmentList') == 0) {
                foreach ($airSegmentList->children('air', true) as $airSegment) {
                    if (strcmp($airSegment->getName(), 'AirSegment') == 0) {
                        foreach ($airSegment->attributes() as $a => $b) {

                            if (strcmp($a, 'Key') == 0) {
                                if (strcmp($b, $key) == 0) {

                                    return $airSegment;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    function listFareInfo($key, $lowFare, $jindex) {
        $infoCount = 0;
        foreach ($lowFare->children('air', true) as $airSegmentList) {
            if (strcmp($airSegmentList->getName(), 'FareInfoList') == 0) {
                foreach ($airSegmentList->children('air', true) as $airSegment) {
                    if (strcmp($airSegment->getName(), 'FareInfo') == 0) {
                        $infoCount++;
                        foreach ($airSegment->attributes() as $a => $b) {
                            if ($infoCount == $jindex) {
                                return $airSegment;
                            }
                        }
                    }
                }
            }
        }
    }

    public function getCity($search) {
        $json = file_get_contents(storage_path() . "/cities.json");
        $cities = json_decode($json);
        foreach ($cities as $city) {
            if ($city->code == $search) {
                return $city->city_name;
            }
        }
    }

    public function getAirline($search) {
        $json = file_get_contents(storage_path() . "/airlines.json");
        $airlines = json_decode($json);
        foreach ($airlines as $airline) {
            if ($airline->code == $search) {
                return $airline->name;
            }
        }
    }

    function roundTripOutputAirSearch($xmlData) {
//        try {
        $flights = $xmlData;
        $count = 0;
        $i = 0;
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
        foreach ($Results->children('air', true) as $lowFare) {
            foreach ($lowFare->children('air', true) as $airPriceSol) {
                if (strcmp($airPriceSol->getName(), 'AirPricingSolution') == 0) {
                    $count = $count + 1;
                    $jindex = 0;
                    foreach ($airPriceSol->children('air', true) as $journey) {
                        if (strcmp($journey->getName(), 'Journey') == 0) {
                            $jindex++;
                            $temp = [];
                            $t = 0;
                            foreach ($journey->children('air', true) as $segmentRef) {
                                if (strcmp($segmentRef->getName(), 'AirSegmentRef') == 0) {
                                    $t++;
                                    foreach ($segmentRef->attributes() as $a => $b) {
                                        $fareRuleData = array();
                                        $segment = $this->listAirSegments($b, $lowFare);
                                        $fareinfo = $this->listFareInfo($b, $lowFare, $count);
                                        if ($fareinfo) {
                                            foreach ($fareinfo->attributes() as $f => $g) {
                                                $fareRuleDataBg = array();
                                                foreach ($fareinfo->children('air', true) as $key111 => $subChild11) {
                                                    if (strcmp($subChild11->getName(), 'BaggageAllowance') == 0) {
                                                        foreach ($subChild11->children('air', true) as $baggage) {
                                                            foreach ($baggage->attributes() as $bgi => $bgj) {

                                                                $fareRuleDataBg[$bgi] = (string) $bgj;
                                                            }
                                                        }
                                                    }
                                                    if (strcmp($subChild11->getName(), 'FareRuleKey') == 0) {
                                                        $fareRuleData['FareRuleKey'] = (string) $subChild11;
                                                        foreach ($subChild11->attributes() as $ii => $jj) {
                                                            $fareRuleData[$ii] = (string) $jj;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $jur[$count]['provider_type'] = 'travelport';
                                        $jur[$count]['is_featured'] = 'true';
                                        $jur[$count]['BaggageInfo'] = $fareRuleDataBg;
                                        $jur[$count]['fareRulesData'] = $fareRuleData;
                                        $trip = [];
                                        foreach ($segment->attributes() as $c => $d) {
                                            foreach ($airPriceSol->attributes() as $airPricingAttrName => $airPricingAttrValue) {
                                                if (strcmp($airPricingAttrName, "Key") == 0) {
                                                    $trip['Key'] = (string) $airPricingAttrValue;
                                                }
                                            }
                                            if (strcmp($c, "Group") == 0) {
                                                $trip['Group'] = (string) $d;
                                            }
                                            if (strcmp($c, "Carrier") == 0) {
                                                $trip['Carrier'] = (string) $d;
                                                $trip['airline_logo'] = url('/') . '/public/airline_logo/' . $trip['Carrier'] . '.png';
                                                $trip['airline_name'] = $this->getAirline($trip['Carrier']);
                                            }
                                            if (strcmp($c, "FlightNumber") == 0) {
                                                $trip['FlightNumber'] = (string) $d;
                                            }
                                            if (strcmp($c, "PassengerTypeCode") == 0) {
                                                $trip['PassengerTypeCode'] = (string) $d;
                                            }
                                            if (strcmp($c, "Origin") == 0) {
                                                $trip['Origin'] = (string) $d;
                                                $trip['origin_city_name'] = $this->getCity($trip['Origin']);
                                            }
                                            if (strcmp($c, "Destination") == 0) {
                                                $trip['Destination'] = (string) $d;
                                                $trip['destination_city_name'] = $this->getCity($trip['Destination']);
                                            }
                                            if (strcmp($c, "DepartureTime") == 0) {
                                                $trip['DepartureTime'] = (string) $d;
                                            }
                                            if (strcmp($c, "ArrivalTime") == 0) {
                                                $trip['ArrivalTime'] = (string) $d;
                                            }
                                            if (strcmp($c, "FlightTime") == 0) {
                                                $trip['FlightTime'] = (string) $d;
                                            }
                                            if (strcmp($c, "Distance") == 0) {
                                                $trip['Distance'] = (string) $d;
                                            }
                                            if (strcmp($c, "ETicketability") == 0) {
                                                $trip['ETicketability'] = (string) $d;
                                            }
                                            if (strcmp($c, "Equipment") == 0) {
                                                $trip['Equipment'] = (string) $d;
                                            }
                                            if (strcmp($c, "ChangeOfPlane") == 0) {
                                                $trip['ChangeOfPlane'] = (string) $d;
                                            }
                                            if (strcmp($c, "ParticipantLevel") == 0) {
                                                $trip['ParticipantLevel'] = (string) $d;
                                            }
                                            if (strcmp($c, "LinkAvailability") == 0) {
                                                $trip['LinkAvailability'] = (string) $d;
                                            }
                                            if (strcmp($c, "PolledAvailabilityOption") == 0) {
                                                $trip['PolledAvailabilityOption'] = (string) $d;
                                            }
                                            if (strcmp($c, "OptionalServicesIndicator") == 0) {
                                                $trip['OptionalServicesIndicator'] = (string) $d;
                                            }
                                            if (strcmp($c, "AvailabilitySource") == 0) {
                                                $trip['AvailabilitySource'] = (string) $d;
                                            }
                                            if (strcmp($c, "AvailabilityDisplayType") == 0) {
                                                $trip['AvailabilityDisplayType'] = (string) $d;
                                            }
                                        }
                                        foreach ($segment->children('air', true) as $subChild) {
                                            if (strcmp($subChild->getName(), 'FlightDetailsRef') == 0) {
                                                foreach ($subChild->attributes() as $c => $d) {
                                                    $xml->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v45_0');
                                                    $x = json_decode(json_encode($xml->xpath("//air:FlightDetails[@Key='" . (string) $d . "']")), TRUE);
                                                    $trip['details'] = $x[0]['@attributes'];
                                                }
                                            }
                                        }
                                        $temp = $trip;
                                    }
                                    // --------------------------canbin and price taking ----------------------
                                    $base_price_arr = [];
                                    foreach ($airPriceSol->children('air', true) as $priceInfoforref) {
                                        foreach ($priceInfoforref->attributes() as $pi => $pinfo) {
                                            if (strcmp($pi, "Refundable") == 0) {
                                                $base_price_arr[$pi] = (string) $pinfo;
                                            }
                                        }
                                    }

                                    foreach ($airPriceSol->attributes() as $e => $f) {

                                        if (strcmp($e, "ApproximateBasePrice") == 0) {
                                            $base_price_arr['ApproximateBasePrice'] = (string) $f;
                                        }
                                        if (strcmp($e, "TotalPrice") == 0) {
                                            $base_price_arr['TotalPrice'] = (string) $f;
                                        }
                                        if (strcmp($e, "Taxes") == 0) {
                                            $base_price_arr['Taxes'] = (string) $f;
                                        }
                                    }
                                    $commsionValue = 0;
                                    $setting = new Setting();
                                    $travelPortSetting = $setting->getSettingByCode('travelport-commission');
                                    if ($travelPortSetting && $travelPortSetting->setting_value > 0) {
                                        $commsionValue = getCommissionValue(str_replace('PKR', '', $base_price_arr['ApproximateBasePrice']), $travelPortSetting->setting_value);
                                    }
                                    $base_price_arr['TotalPriceWithCommission'] = round($commsionValue + str_replace('PKR', '', $base_price_arr['TotalPrice']));
                                    $jur[$count]['price_info'] = $base_price_arr;
                                    foreach ($airPriceSol->children('air', true) as $priceInfo) {
                                        if (strcmp($priceInfo->getName(), 'AirPricingInfo') == 0) {

                                            foreach ($priceInfo->children('air', true) as $bookingInfo) {
                                                if (strcmp($bookingInfo->getName(), 'BookingInfo') == 0) {
                                                    foreach ($bookingInfo->attributes() as $e => $f) {
                                                        if (strcmp($e, "BookingCode") == 0) {
                                                            $cabinArr['BookingCode'] = (string) $f;
                                                        }
                                                        if (strcmp($e, "CabinClass") == 0) {
                                                            $cabinArr['CabinClass'] = (string) $f;
                                                        }
                                                    }
                                                }
                                            }
                                            $temp['cabin'] = $cabinArr;
                                        }
                                    }
                                    // --------------------------canbin and price taking end----------------------
                                }
                                $jur[$count]['segments'][] = $temp;
                            }
                        }
                    }
                }
            }
            return $jur;
        }
//        } catch (\Exception $e) {
//            return $return = array(
//                'status' => '4000',
//                'message' => $e->getMessage()
//            );
//        }
    }

    function pricingOutputAirSearch($adult = null, $child = null, $infant = null) {
        $flights = Storage::get('AirPriceRsp.xml');
        $count = 0;
        $i = 0;
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
        foreach ($Results->children('air', true) as $lowFare) {
            foreach ($lowFare->children('air', true) as $airPriceSol) {
                if (strcmp($airPriceSol->getName(), 'AirPricingSolution') == 0) {
                    $count = $count + 1;
                    $jindex = 0;
                    foreach ($airPriceSol->children('air', true) as $journey) {
                        if (strcmp($journey->getName(), 'Journey') == 0) {
                            $jindex++;
                            $temp = [];
                            $t = 0;
                            foreach ($journey->children('air', true) as $segmentRef) {
                                if (strcmp($segmentRef->getName(), 'AirSegmentRef') == 0) {
                                    $t++;
                                    foreach ($segmentRef->attributes() as $a => $b) {
                                        $segment = $this->listAirSegments($b, $lowFare);
                                        $trip = [];
                                        foreach ($segment->attributes() as $c => $d) {
                                            foreach ($airPriceSol->attributes() as $airPricingAttrName => $airPricingAttrValue) {
                                                if (strcmp($airPricingAttrName, "Key") == 0) {
                                                    $trip['Key'] = (string) $airPricingAttrValue;
                                                }
                                            }
                                            if (strcmp($c, "Group") == 0) {
                                                $trip['Group'] = (string) $d;
                                            }
                                            if (strcmp($c, "Carrier") == 0) {
                                                $trip['Carrier'] = (string) $d;
                                                $trip['airline_logo'] = url('/') . '/public/airline_logo/' . $trip['Carrier'] . '.png';
                                            }
                                            if (strcmp($c, "FlightNumber") == 0) {
                                                $trip['FlightNumber'] = (string) $d;
                                            }
                                            if (strcmp($c, "Origin") == 0) {
                                                $trip['Origin'] = (string) $d;
                                                $trip['origin_city_name'] = $this->getCity($trip['Origin']);
                                            }
                                            if (strcmp($c, "Destination") == 0) {
                                                $trip['Destination'] = (string) $d;
                                                $trip['destination_city_name'] = $this->getCity($trip['Destination']);
                                            }
                                            if (strcmp($c, "DepartureTime") == 0) {
                                                $trip['DepartureTime'] = (string) $d;
                                            }
                                            if (strcmp($c, "ArrivalTime") == 0) {
                                                $trip['ArrivalTime'] = (string) $d;
                                            }
                                            if (strcmp($c, "FlightTime") == 0) {
                                                $trip['FlightTime'] = (string) $d;
                                            }
                                            if (strcmp($c, "Distance") == 0) {
                                                $trip['Distance'] = (string) $d;
                                            }
                                            if (strcmp($c, "ETicketability") == 0) {
                                                $trip['ETicketability'] = (string) $d;
                                            }
                                            if (strcmp($c, "Equipment") == 0) {
                                                $trip['Equipment'] = (string) $d;
                                            }
                                            if (strcmp($c, "ChangeOfPlane") == 0) {
                                                $trip['ChangeOfPlane'] = (string) $d;
                                            }
                                            if (strcmp($c, "ParticipantLevel") == 0) {
                                                $trip['ParticipantLevel'] = (string) $d;
                                            }
                                            if (strcmp($c, "LinkAvailability") == 0) {
                                                $trip['LinkAvailability'] = (string) $d;
                                            }
                                            if (strcmp($c, "PolledAvailabilityOption") == 0) {
                                                $trip['PolledAvailabilityOption'] = (string) $d;
                                            }
                                            if (strcmp($c, "OptionalServicesIndicator") == 0) {
                                                $trip['OptionalServicesIndicator'] = (string) $d;
                                            }
                                            if (strcmp($c, "AvailabilitySource") == 0) {
                                                $trip['AvailabilitySource'] = (string) $d;
                                            }
                                            if (strcmp($c, "AvailabilityDisplayType") == 0) {
                                                $trip['AvailabilityDisplayType'] = (string) $d;
                                            }
                                        }
                                        foreach ($segment->children('air', true) as $subChild) {
                                            if (strcmp($subChild->getName(), 'FlightDetailsRef') == 0) {
                                                foreach ($subChild->attributes() as $c => $d) {
                                                    $xml->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v45_0');
                                                    $x = json_decode(json_encode($xml->xpath("//air:FlightDetails[@Key='" . (string) $d . "']")), TRUE);
                                                    $trip['details'] = $x[0]['@attributes'];
                                                }
                                            }
                                        }
                                        $temp = $trip;
                                    }
                                    // --------------------------canbin and price taking ----------------------
                                    foreach ($airPriceSol->children('air', true) as $priceInfo) {
                                        if (strcmp($priceInfo->getName(), 'AirPricingInfo') == 0) {
                                            // $airpricing=[];
                                            foreach ($priceInfo->attributes() as $e => $f) {
                                                if (strcmp($e, "ApproximateBasePrice") == 0) {
                                                    // $airpricing['ApproximateBasePrice']=(string)$f;
                                                    $base_price_arr['ApproximateBasePrice'] = (string) $f;
                                                }
                                                if (strcmp($e, "TotalPrice") == 0) {
                                                    // $airpricing['TotalPrice']=(string)$f;
                                                    $base_price_arr['TotalPrice'] = (string) $f;
                                                }
                                                if (strcmp($e, "Taxes") == 0) {
                                                    $base_price_arr['Taxes'] = (string) $f;
                                                }
                                            }
                                            $jur[$count]['price_info'] = $base_price_arr;
                                            foreach ($priceInfo->children('air', true) as $bookingInfo) {
                                                if (strcmp($bookingInfo->getName(), 'BookingInfo') == 0) {
                                                    foreach ($bookingInfo->attributes() as $e => $f) {
                                                        if (strcmp($e, "BookingCode") == 0) {
                                                            $cabinArr['BookingCode'] = (string) $f;
                                                        }
                                                        if (strcmp($e, "CabinClass") == 0) {
                                                            $cabinArr['CabinClass'] = (string) $f;
                                                        }
                                                    }
                                                }
                                            }
                                            $temp['cabin'] = $cabinArr;
                                        }
                                    }
                                    // --------------------------canbin and price taking end----------------------
                                }
                                $jur[$count]['segments'][] = $temp;
                            }
                        }
                    }
                }
            }
            return $jur;
        }
    }

    // function for setting round trip api request xml
    public function round_trip_req($trip, $origion = null, $destination = null, $fromDate = null, $toDate = null, $adult = null, $child = null, $infant = null, $stop = null, $ticket_class = null) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
				   <soapenv:Header/>
				   <soapenv:Body>
				      <air:LowFareSearchReq TraceId="trace" AuthorizedBy="user" SolutionResult="true" TargetBranch="' . $this->TARGETBRANCH . '" xmlns:air="http://www.travelport.com/schema/air_v45_0" xmlns:com="http://www.travelport.com/schema/common_v45_0">
				         <com:BillingPointOfSaleInfo OriginApplication="UAPI"/>';

        $message .= '<air:SearchAirLeg>';
        if ($origion != null) {
            $mix_orgion = explode(" - ", $origion);
            $origion = $mix_orgion[0];
            $message .= '<air:SearchOrigin>
				   	<com:CityOrAirport Code="' . $origion . '" PreferCity="true"/>
				   </air:SearchOrigin>';
        }
        // if destination is set
        if ($destination != null) {
            $mix_destinationn = explode(" - ", $destination);
            $destination = $mix_destinationn[0];
            $message .= '<air:SearchDestination>
				        <com:CityOrAirport Code="' . $destination . '" PreferCity="true" />
				   </air:SearchDestination>';
        }
        //if fromDate is set
        if ($fromDate != null) {
            $newDate = date("Y-m-d", strtotime(str_replace('/', '-', $fromDate)));
            $message .= '<air:SearchDepTime PreferredTime="' . $newDate . '">
				            </air:SearchDepTime>';
        }
        // --------------------------air leg modifier for stops and class type --------------------------------------
        $message .= '<air:AirLegModifiers AllowDirectAccess="true" >';
        if ($ticket_class != null) {
            $message .= '<air:PreferredCabins>
                            <CabinClass xmlns="http://www.travelport.com/schema/common_v45_0" Type="' . $ticket_class . '" />
                          </air:PreferredCabins>';
        }
        $message .= '</air:AirLegModifiers>';
        // --------------------------end air leg modifier for stops and class type --------------------------------------
        $message .= '</air:SearchAirLeg>';
        // --------------------------For Round Trip code start  --------------------------------------
        if ($trip == 'round-trip') {
            $message .= '<air:SearchAirLeg>';
            if ($origion != null && $destination != null) {
                $message .= '<air:SearchOrigin>
							   	<com:CityOrAirport Code="' . $destination . '" PreferCity="true"/>
							   </air:SearchOrigin>';
                $message .= '<air:SearchDestination>
							        <com:CityOrAirport Code="' . $origion . '" PreferCity="true" />
								   </air:SearchDestination>';
            }
            if ($toDate != null) {
                $newDate = date("Y-m-d", strtotime(str_replace('/', '-', $toDate)));
                $message .= '<air:SearchDepTime PreferredTime="' . $newDate . '">
							            </air:SearchDepTime>';
            }

            $message .= '<air:AirLegModifiers>';
            if ($ticket_class != null) {
                $message .= '<air:PreferredCabins>
			                            <CabinClass xmlns="http://www.travelport.com/schema/common_v45_0" Type="' . $ticket_class . '" />
			                          </air:PreferredCabins>';
            }

            $message .= '</air:AirLegModifiers>';
            $message .= '</air:SearchAirLeg>';
        }
        // --------------------------For Round Trip code end    --------------------------------------
        $message .= '<air:AirSearchModifiers>
				            <air:PreferredProviders>
				               <com:Provider Code="' . $this->Provider . '"/>
				            </air:PreferredProviders>';
        $message .= '<air:ProhibitedCarriers>
					      <com:Carrier Code="PK" />
					    </air:ProhibitedCarriers>';
        $message .= '<air:FlightType NonStopDirects="false" />';
        $message .= '</air:AirSearchModifiers>';
        if ($adult != null) {
            for ($i = 1; $i <= $adult; $i++) {
                $message .= '<com:SearchPassenger  Code="ADT" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }
        if ($child != null) {
            for ($i = 1; $i <= $child; $i++) {

                $chBirth = date('Y-m-d', strtotime('-10 years'));
                $message .= '<com:SearchPassenger Code="CNN" Age="10" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }
        if ($infant != null) {
            for ($i = 1; $i <= $infant; $i++) {

                $chBirth = date('Y-m-d', strtotime('-1 years'));
                $message .= '<com:SearchPassenger Code="INF" Age="1" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }
        $message .= '<air:AirPricingModifiers FaresIndicator="AllFares"/>';
        $message .= '</air:LowFareSearchReq>
			 </soapenv:Body>
			</soapenv:Envelope>';
        $this->message = $message;
        Storage::put('FlightSearchRequest.xml', $this->prettyPrint($this->message));
        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        return $content;
    }

    function return_itenery_trip_for_pricingRequest($pricingKey) {
        $flights = Storage::get('FlightSearchResponse.xml');
        $count = 0;
        $i = 0;
        $jur = [];
        $pass = [];
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
        foreach ($Results->children('air', true) as $lowFare) {
            foreach ($lowFare->children('air', true) as $airPriceSol) {
                if (strcmp($airPriceSol->getName(), 'AirPricingSolution') == 0) {
                    if ($airPriceSol->attributes()->Key == $pricingKey) {
                        $count = $count + 1;
                        $jindex = 0;
                        foreach ($airPriceSol->children('air', true) as $journey) {
                            if (strcmp($journey->getName(), 'Journey') == 0) {
                                $jindex++;
                                $temp = [];
                                $t = 0;
                                foreach ($journey->children('air', true) as $segmentRef) {
                                    if (strcmp($segmentRef->getName(), 'AirSegmentRef') == 0) {
                                        $t++;
                                        foreach ($segmentRef->attributes() as $a => $b) {
                                            $segment = $this->listAirSegments($b, $lowFare);
                                            $trip = [];
                                            foreach ($segment->attributes() as $c => $d) {

                                                $trip[$c] = (string) $d;
                                                // }
                                            }
                                            $temp = $trip;
                                        }
                                        $pass = [];
                                        foreach ($airPriceSol->children('air', true) as $priceInfo) {
                                            if (strcmp($priceInfo->getName(), 'AirPricingInfo') == 0) {
                                                foreach ($priceInfo->children('air', true) as $bookingInfo) {
                                                    if (strcmp($bookingInfo->getName(), 'BookingInfo') == 0) {
                                                        foreach ($bookingInfo->attributes() as $e => $f) {
                                                            if (strcmp($e, "CabinClass") == 0) {
                                                                $temp['cabin'] = (string) $f;
                                                            }
                                                            if (strcmp($e, "BookingCode") == 0) {
                                                                $temp['BookingCode'] = (string) $f;
                                                            }
                                                        }
                                                    }
                                                    // getting passenger
                                                    if (strcmp($bookingInfo->getName(), 'PassengerType') == 0) {
                                                        foreach ($bookingInfo->attributes() as $e => $f) {
                                                            if (strcmp($e, "Code") == 0) {
                                                                $pass[] = (string) $f;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $jur[$count][] = $temp;
                                }
                            }
                        }
                    }
                }
            }
            $comResult['journey'] = $jur;
            $comResult['passenger'] = $pass;
            return $comResult;
        }
    }

    public function air_pricing_req($segmentsData, $adult = null, $child = null, $infant = null) {
        if (empty($segmentsData)) {
            return 'false';
        }
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
				   <soapenv:Header/>
				   <soapenv:Body>';

        $message .= '<air:AirPriceReq xmlns:air="http://www.travelport.com/schema/air_v45_0" AuthorizedBy="user" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace">
				<com:BillingPointOfSaleInfo xmlns:com="http://www.travelport.com/schema/common_v45_0" OriginApplication="UAPI"/>';

        $message .= '<air:AirItinerary>';
        $segments = sizeof($segmentsData);
        foreach ($segmentsData as $key => $flight) {
            $message .= '<air:AirSegment ';
            foreach ($flight as $segment_key => $segment_data) {

                if ($segment_key == 'Key') {
                    $message .= 'Key="' . $flight['details']['Key'] . '" ';
                }
                if ($segment_key == 'cabin' || $segment_key == 'BookingCode' || $segment_key == 'details' || $segment_key == 'airline_logo' || $segment_key == 'airline_name' || $segment_key == 'origin_city_name' || $segment_key == 'destination_city_name' || $segment_key == 'Key') {
                    continue;
                }
                $message .= $segment_key . '="' . $segment_data . '" ';
            }
            $message .= 'ProviderCode="1G"';
            $message .= '>';
            $message .= '</air:AirSegment>';
        }
        $message .= '	</air:AirItinerary>';
        $message .= ' <air:AirPricingModifiers FaresIndicator="PublicAndPrivateFares"  PlatingCarrier="' . $segmentsData[0]['Carrier'] . '" >
			</air:AirPricingModifiers>';
        if ($adult != null) {
            for ($i = 1; $i <= $adult; $i++) {
                $message .= '<com:SearchPassenger  Code="ADT" xmlns:com="http://www.travelport.com/schema/common_v45_0" BookingTravelerRef="' . strtoupper(Str::random(12)) . '" /> ';
            }
        }
        if ($child != null) {
            for ($i = 1; $i <= $child; $i++) {

                $chBirth = date('Y-m-d', strtotime('-10 years'));
                $message .= '<com:SearchPassenger Code="CNN" Age="10" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0" BookingTravelerRef="' . strtoupper(Str::random(12)) . '"/>';
            }
        }
        if ($infant != null) {
            for ($i = 1; $i <= $infant; $i++) {
                $chBirth = date('Y-m-d', strtotime('-1 years'));
                $message .= '<com:SearchPassenger Code="INF" Age="1" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0" BookingTravelerRef="' . strtoupper(Str::random(12)) . '"/>';
            }
        }
        $message .= '<air:AirPricingCommand>';
        foreach ($segmentsData as $key => $flight) {
            $message .= ' <air:AirSegmentPricingModifiers AirSegmentRef="' . $flight['details']['Key'] . '" CabinClass="' . $flight['cabin']['CabinClass'] . '">
						 <air:PermittedBookingCodes>
				         <air:BookingCode Code="' . $flight['cabin']['BookingCode'] . '" />
				         </air:PermittedBookingCodes>
				    </air:AirSegmentPricingModifiers>';
        }
        $message .= '</air:AirPricingCommand>';

        $message .= '</air:AirPriceReq>
                    </soapenv:Body>
                    </soapenv:Envelope>';
        $this->message = $message;
        Storage::put('AirPriceRequest.xml', $this->prettyPrint($this->message));
        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        return $content;
    }

    function get_data_from_pricing_rsp($pricingResxml) {
        $flights = $pricingResxml;
        $booking_class = [];
        $Group0 = [];
        $Group1 = [];
        $bag = [];
        $data = [];
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
        foreach ($Results->children('air', true) as $priceRsp) {
            foreach ($priceRsp->children('air', true) as $result) {
                //get segment of airline
                if (strcmp($result->getName(), 'AirItinerary') == 0) {
                    foreach ($result->children('air', true) as $segment) {

                        if (strcmp($segment->getName(), 'AirSegment') == 0) {
                            $codesh = [];
                            foreach ($segment->attributes() as $attrName => $attrVal)
                                $codesh[$attrName] = (string) $attrVal;
                            foreach ($segment->children('air', true) as $CodeshareInfo) {
                                if (strcmp($CodeshareInfo->getName(), 'CodeshareInfo') == 0) {
                                    $codesh['fligtName'] = (string) $CodeshareInfo;
                                    foreach ($CodeshareInfo->attributes() as $attrName => $attrVal) {
                                        $codesh[$attrName] = (string) $attrVal;
                                    }
                                }
                                if (strcmp($CodeshareInfo->getName(), 'FlightDetails') == 0) {
                                    foreach ($CodeshareInfo->attributes() as $attrName => $attrVal) {
                                        if (strcmp($attrName, 'Key') == 0)
                                            $codesh['FlightDetailsKey'] = (string) $attrVal;
                                        else
                                            $codesh[$attrName] = (string) $attrVal;
                                    }
                                }
                            }
                            if ($codesh['Group'] == 0)
                                array_push($Group0, $codesh);
                            else
                                array_push($Group1, $codesh);
                        }
                    }
                }
                // get baggege and information about ticket
                if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                    foreach ($result->children('air', true) as $AirPricingSolution) {
                        $check = 0;
                        foreach ($AirPricingSolution->children('air', true) as $AirPricingInfo) {
                            if (strcmp($AirPricingInfo->getName(), 'AirPricingInfo') == 0) {

                                foreach ($AirPricingInfo->children('air', true) as $BaggageAllowances) {
                                    // -----------get booking class and code ------------------------------------
                                    if ($check == 0) {
                                        if (strcmp($BaggageAllowances->getName(), 'BookingInfo') == 0) {
                                            $book = [];
                                            foreach ($BaggageAllowances->attributes() as $attrName => $attrVal)
                                                $book[$attrName] = (string) $attrVal;
                                            $booking_class[] = $book;
                                        }
                                    }
                                    if (strcmp($BaggageAllowances->getName(), 'BaggageAllowances') == 0) {
                                        foreach ($BaggageAllowances->children('air', true) as $BaggageAllowancesSubAir) {

                                            if (strcmp($BaggageAllowancesSubAir->getName(), 'BaggageAllowanceInfo') == 0) {
                                                $bag_alow_info = [];
                                                foreach ($BaggageAllowancesSubAir->attributes() as $attrName => $attrVal)
                                                    $bag_alow_info[$attrName] = (string) $attrVal;

                                                foreach ($BaggageAllowancesSubAir->children('air', true) as $BaggageAllowancesInfoSubAir) {
                                                    if (strcmp($BaggageAllowancesInfoSubAir->getName(), 'URLInfo') == 0) {
                                                        foreach ($BaggageAllowancesInfoSubAir->children('air', true) as $url) {
                                                            $bag_alow_info['url'] = (string) $url;
                                                        }
                                                    }
                                                    if (strcmp($BaggageAllowancesInfoSubAir->getName(), 'TextInfo') == 0) {
                                                        foreach ($BaggageAllowancesInfoSubAir->children('air', true) as $url) {
                                                            $bag_alow_info['weight'] = (string) $url;
                                                            break;
                                                        }
                                                    }
                                                    if (strcmp($BaggageAllowancesInfoSubAir->getName(), 'BagDetails') == 0) {
                                                        foreach ($BaggageAllowancesInfoSubAir->attributes() as $attrName => $attrVal)
                                                            foreach ($BaggageAllowancesInfoSubAir->children('air', true) as $BaggageRestriction) {
                                                                foreach ($BaggageRestriction->children('air', true) as $TextInfo) {
                                                                    foreach ($TextInfo->children('air', true) as $Text) {

                                                                        $bag_alow_info[(string) $attrVal] = (string) $Text;
                                                                    }
                                                                }
                                                            }
                                                    }
                                                }
                                                $bag[] = $bag_alow_info;
                                            }
                                        }
                                    }
                                }


                                $check = 1;
                            }
                        }
                        break;
                    }
                }
                if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                    foreach ($result->children('air', true) as $AirPricingSolution) {
                        $check = 1;
                        foreach ($AirPricingSolution->children('air', true) as $FareNote) {
                            if (strcmp($FareNote->getName(), 'FareNote') == 0) {
                                if ($check == 2) {
                                    $data['FareNote'] = (string) $FareNote;
                                }
                                $check++;
                            }
                        }
                        foreach ($AirPricingSolution->children('air', true) as $airPricingInfo) {
                            if (strcmp($airPricingInfo->getName(), 'AirPricingInfo') == 0) {
                                foreach ($airPricingInfo->children('air', true) as $FareRule) {
                                    if (strcmp($FareRule->getName(), 'FareInfo') == 0) {
                                        foreach ($FareRule->children('air', true) as $FareRuleKey) {
                                            if (strcmp($FareRuleKey->getName(), 'FareRuleKey') == 0) {
                                                foreach ($FareRuleKey->attributes() as $attrName => $attrVal)
                                                    $test[$attrName] = (string) $attrVal;
                                                $test['FareRulekey'] = (string) $FareRuleKey;
                                                $data['fareRule'] = $test;
                                                break;
                                            }
                                            break;
                                        }
                                    }
                                    break;
                                }
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }
        $data['segments'] = $Group0;
        $data['booking_class'] = $booking_class;
        $data['baggage'] = $bag;
        return $data;
    }

    function get_air_segments_pricingRequest($pricingResxml) {
        $flights = $pricingResxml;
        $airsegment = [];
        $codesh = [];
        $price = [];
        $data = [];
        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);
        foreach ($Results->children('air', true) as $priceRsp) {
            foreach ($priceRsp->children('air', true) as $result) {
                if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                    foreach ($result->children('air', true) as $segment) {
                        if (strcmp($segment->getName(), 'AirPricingSolution') == 0) {
                            foreach ($segment->attributes() as $attrName => $attrVal) {
                                if (strcmp($attrName, 'Key') == 0)
                                    $price['pricingSolutionKey'] = (string) $attrVal;
                                else
                                    $price[$attrName] = (string) $attrVal;
                            }
                        }
                        break;
                    }
                }
            }
        }
        foreach ($Results->children('air', true) as $priceRsp) {
            foreach ($priceRsp->children('air', true) as $result) {
                if (strcmp($result->getName(), 'AirItinerary') == 0) {
                    foreach ($result->children('air', true) as $segment) {
                        if (strcmp($segment->getName(), 'AirSegment') == 0) {
                            $codesh = [];
                            foreach ($segment->attributes() as $attrName => $attrVal) {

                                $codesh[$attrName] = (string) $attrVal;
                            }
                            foreach ($segment->children('air', true) as $CodeshareInfo) {
                                if (strcmp($CodeshareInfo->getName(), 'CodeshareInfo') == 0) {
                                    $codesh['fligtName'] = (string) $CodeshareInfo;
                                    foreach ($CodeshareInfo->attributes() as $attrName => $attrVal) {

                                        $codesh[$attrName] = (string) $attrVal;
                                    }
                                }
                                if (strcmp($CodeshareInfo->getName(), 'FlightDetails') == 0) {
                                    foreach ($CodeshareInfo->attributes() as $attrName => $attrVal) {
                                        if (strcmp($attrName, 'Key') == 0)
                                            $codesh['FlightDetailsKey'] = (string) $attrVal;
                                        else
                                            $codesh[$attrName] = (string) $attrVal;
                                    }
                                }
                                if (strcmp($CodeshareInfo->getName(), 'Connection') == 0) {
                                    $codesh['connection'] = 1;
                                }
                            }
                            $airsegment[] = $codesh;
                        }
                    }
                    break;
                }
            }
        }
        $data['segment'] = $airsegment;
        $data['pricingSol'] = $price;
        return $data;
    }

    public function makeBookingDetailArray($booking_detail) {
        $titles = array();
        $fnames = array();
        $lnames = array();
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
                $nationalities[] = $detail['nationality'];
                $dobs[] = date('Y-m-d', strtotime($detail['dob_day'] . '-' . $detail['dob_month'] . '-' . $detail['dob_year']));
                $passenger_types[] = $detail['passenger_type'];
                $passport_numbers[] = $detail['passport_number'];
                $passport_types[] = $detail['passport_type'];
                $expiry_dates[] = date('Y-m-d', strtotime($detail['exp_day'] . '-' . $detail['exp_month'] . '-' . $detail['exp_year']));
                $issue_countrys[] = $detail['issue_country'];
            }
        }
        return $returnArray = array(
            'title' => $titles,
            'f_name' => $fnames,
            'l_name' => $lnames,
            'nationality' => $nationalities,
            'dob' => $dobs,
            'passenger_type' => $passenger_types,
            'passport_number' => $passport_numbers,
            'passport_type' => $passport_types,
            'expiry_date' => $expiry_dates,
            'issue_country' => $issue_countrys,
        );
    }

    public function air_reservation_req_new($request, $mainData, $PricingInfoData, $pricingRes) {
        try {
            $booking_detail = $request['booking_detail'];
            $allInputs = $this->makeBookingDetailArray($booking_detail);
            $pass_type = $allInputs['passenger_type']; // INF /CNN / ADT
            $title = $allInputs['title'];
            $first_name = $allInputs['f_name'];
            $last_name = $allInputs['l_name'];
            $nationality = $allInputs['nationality'];
            $birth_date = $allInputs['dob'];
            $ptype = $allInputs['passport_type'];
            $passport_no = $allInputs['passport_number'];
            $issue_country = $allInputs['issue_country'];
            $expiration_data = $allInputs['expiry_date'];
            $street = $mainData['street'] ? $mainData['street'] : "";
            $city = $mainData['city'] ? $mainData['city'] : "";
            $state = $mainData['state'] ? $mainData['state'] : "";
            $postal_code = $mainData['postal_code'] ? $mainData['postal_code'] : "";
            $country = $mainData['country'] ? $mainData['country'] : "";
            $data = $PricingInfoData;
            $email = 'info@foureflights.com';
            $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
			<soapenv:Header/>
			<soapenv:Body>
			<AirCreateReservationReq xmlns="http://www.travelport.com/schema/universal_v45_0" TraceId="trace" AuthorizedBy="Travelport" TargetBranch="' . $this->TARGETBRANCH . '" ProviderCode="1G" RetainReservation="Both">
                        <BillingPointOfSaleInfo xmlns="http://www.travelport.com/schema/common_v45_0" OriginApplication="UAPI" /> ';
            $randomkey = Str::random(18);
            $newkey = [];
            for ($i = 0; $i < sizeof($pass_type); $i++) {
                $newkey[$i] = $randomkey . '' . $i;
                $newDate = db_format_date($birth_date[$i]);
                $newDateExp = db_format_date($expiration_data[$i]);
                $age_in_year = Carbon::parse($newDate)->age;
                if ($title[$i] == 'MR') {
                    $gender = 'M';
                } else {
                    $gender = 'F';
                }
                if ($pass_type[$i] == 'ADT') {
                    $message .= '<BookingTraveler xmlns="http://www.travelport.com/schema/common_v45_0" Key="' . $newkey[$i] . '" TravelerType="' . $pass_type[$i] . '" Age="' . $age_in_year . '" DOB="' . $newDate . '" Gender="' . $gender . '" Nationality="' . $nationality[$i] . '">
                        <BookingTravelerName Prefix="' . $title[$i] . '" First="' . $first_name[$i] . '" Last="' . $last_name[$i] . '" />';
                } else {
                    $message .= '<BookingTraveler xmlns="http://www.travelport.com/schema/common_v45_0" Key="' . $newkey[$i] . '" TravelerType="' . $pass_type[$i] . '" Age="' . $age_in_year . '" DOB="' . $newDate . '" Gender="' . $gender . '" Nationality="' . $nationality[$i] . '">
                        <BookingTravelerName Prefix="' . $title[$i] . '" First="' . $first_name[$i] . '" Last="' . $last_name[$i] . '" />';
                }
                if ($street && $street != "") {
                    $message .= '<DeliveryInfo>
                            <ShippingAddress Key="' . $newkey[$i] . '">
                              <Street>' . $street . '</Street>
                              <City>' . $city . '</City>
                              <State>' . $state . '</State>
                              <PostalCode>' . $postal_code . '</PostalCode>
                              <Country>' . $country . '</Country>
                            </ShippingAddress>
                          </DeliveryInfo>';
                }
                $message .= '<PhoneNumber AreaCode="051" CountryCode="0092" Location="ISB" Number="2272480"/>';
                $message .= ' <Email EmailID="' . $email . '" Type="office"/>';

                if ($passport_no[$i] == '' && $ptype[$i] == '' && $issue_country[$i] == '') {
                    $message .= '<SSR Type="DOCS" FreeText="' . $nationality[$i] . '/' . $passport_no[$i] . '/' . date("dMy", strtotime($newDate)) . '/' . $gender . '//' . $last_name[$i] . '/' . $first_name[$i] . '"  Carrier="' . $data['segment'][0]['Carrier'] . '" />';
                } else {
                    $message .= '<SSR Type="DOCS" FreeText="' . $ptype[$i] . '/' . $nationality[$i] . '/' . $passport_no[$i] . '/' . $nationality[$i] . '/' . date("dMy", strtotime($newDate)) . '/' . $gender . '/' . date("dMy", strtotime($newDateExp)) . '/' . $last_name[$i] . '/' . $first_name[$i] . '" Carrier="' . $data['segment'][0]['Carrier'] . '" />';
                }
                if ($pass_type[$i] == 'CNN') {
                    $message .= '<NameRemark>
   			     <RemarkData>P-C' . sprintf("%02d", $age_in_year) . '</RemarkData>
  			     </NameRemark>';
                }
                if ($pass_type[$i] == 'INF') {
                    $message .= '<NameRemark>
   			     <RemarkData>' . Carbon::parse($newDate)->format("dMy") . '</RemarkData>
  			     </NameRemark>';
                }
                if ($street && $street != "") {
                    $message .= '<Address>
                            <AddressName>TravelportAddress</AddressName>
                            <Street>' . $street . '</Street>
                            <City>' . $city . '</City>
                            <State>' . $state . '</State>
                            <PostalCode>' . $postal_code . '</PostalCode>
                            <Country>' . $country . '</Country>
                          </Address>';
                }
                $message .= '</BookingTraveler>';
            }
            $message .= '<FormOfPayment xmlns="http://www.travelport.com/schema/common_v45_0" Type="Cash" Key="1"></FormOfPayment>';
            $pricingsolution = '<AirPricingSolution xmlns="http://www.travelport.com/schema/air_v45_0"';
            foreach ($data['pricingSol'] as $key => $value) {
                $pricingsolution .= ' ' . $key . '=' . '"' . $value . '"';
            }
            $pricingsolution .= ' >';
            $pricingsolution = str_replace("pricingSolutionKey", "Key", $pricingsolution);
            $message .= $pricingsolution;
            foreach ($data['segment'] as $key => $airSegment) {
                $equipment = isset($airSegment['Equipment']) ? '" ' . 'Equipment="' . $airSegment['Equipment'] : "";
                $participantLevel = isset($airSegment['ParticipantLevel']) ? '" ' . 'ParticipantLevel="' . $airSegment['ParticipantLevel'] : "";
                $AvailabilitySource = isset($airSegment['AvailabilitySource']) && $airSegment['AvailabilitySource'] !==""?$airSegment['AvailabilitySource']:"";
                $message .= '<AirSegment ArrivalTime="' . $airSegment['ArrivalTime'] . '" AvailabilityDisplayType="' . $airSegment['AvailabilityDisplayType'] . '" AvailabilitySource="'.$AvailabilitySource.'" Carrier="' . $airSegment['Carrier'] . '" ChangeOfPlane="' . $airSegment['ChangeOfPlane'] . '" ClassOfService="' . $airSegment['ClassOfService'] . '" DepartureTime="' . $airSegment['DepartureTime'] . '" Destination="' . $airSegment['Destination'] . '" Distance="' . $airSegment['Distance'] . $equipment . '" FlightNumber="' . $airSegment['FlightNumber'] . '" FlightTime="' . $airSegment['FlightTime'] . '" Group="' . $airSegment['Group'] . '" Key="' . $airSegment['Key'] . '"  OptionalServicesIndicator="' . $airSegment['OptionalServicesIndicator'] . '" Origin="' . $airSegment['Origin'] . $participantLevel . '" PolledAvailabilityOption="' . $airSegment['PolledAvailabilityOption'] . '" ProviderCode="' . $this->Provider . '" TravelTime="' . $airSegment['TravelTime'] . '" ';


                if (array_key_exists("LinkAvailability", $airSegment)) {
                    $message .= ' LinkAvailability ="' . $airSegment['LinkAvailability'] . '" >';
                } else {
                    $message .= ' >';
                }

                $message .= '<FlightDetails ArrivalTime="' . $airSegment['ArrivalTime'] . '" DepartureTime="' . $airSegment['DepartureTime'] . '" Destination="' . $airSegment['Destination'] . '" Distance="' . $airSegment['Distance'] . '" FlightTime="' . $airSegment['FlightTime'] . '" Key="' . $airSegment['FlightDetailsKey'] . '" Origin="' . $airSegment['Origin'] . '" TravelTime="' . $airSegment['TravelTime'] . '"/>';
                if (array_key_exists("connection", $airSegment)) {
                    $message .= '  <Connection/>';
                }
                $message .= '  </AirSegment>';
            }
            $flights = $pricingRes;
            $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
            $Results = $xml->children('SOAP', true);
            foreach ($Results->children('air', true) as $priceRsp) {
                foreach ($priceRsp->children('air', true) as $result) {
                    if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                        foreach ($result->children('air', true) as $segment) {
                            if (strcmp($segment->getName(), 'AirPricingSolution') == 0) {
                                foreach ($segment->children('air', true) as $AirPricingInfo) {
                                    if (strcmp($AirPricingInfo->getName(), 'AirPricingInfo') == 0) {
                                        // get air pricing info attribute
                                        $message .= '<AirPricingInfo ';
                                        foreach ($AirPricingInfo->attributes() as $attrName => $attrVal) {
                                            $message .= '' . $attrName . '="' . (string) $attrVal . '" ';
                                        }
                                        $message .= ' PlatingCarrier="' . $data['segment'][0]['Carrier'] . '" >';
                                        foreach ($AirPricingInfo->children('air', true) as $BookingInfo) {
                                            // taking fare info portion
                                            if (strcmp($BookingInfo->getName(), 'FareInfo') == 0) {
                                                $message .= '<FareInfo ';
                                                foreach ($BookingInfo->attributes() as $attrName3 => $attrVal3) {
                                                    $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                                }
                                                $message .= '>';
                                                foreach ($BookingInfo->children('common_v45_0', true) as $AccountCode) {
                                                    if (strcmp($AccountCode->getName(), 'AccountCode') == 0) {
                                                        $message .= '<AccountCode ';
                                                        foreach ($AccountCode->attributes() as $attrName3 => $attrVal3) {
                                                            $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                                        }
                                                        $message .= '/>';
                                                    }
                                                }
                                                $message .= '</FareInfo>';
                                            }
                                            // taking booking info portion
                                            if (strcmp($BookingInfo->getName(), 'BookingInfo') == 0) {
                                                $message .= '<BookingInfo ';
                                                foreach ($BookingInfo->attributes() as $attrName2 => $attrVal2) {
                                                    $message .= '' . $attrName2 . '="' . (string) $attrVal2 . '" ';
                                                }
                                                $message .= '/>';
                                            }
                                            // taking passenger type portion
                                            if (strcmp($BookingInfo->getName(), 'PassengerType') == 0) {
                                                $message .= '<PassengerType ';

                                                foreach ($BookingInfo->attributes() as $attrName3 => $attrVal3) {
                                                    $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                                }
                                                $message .= 'BookingTravelerRef="' . $newkey[0] . '" ';
                                                array_shift($newkey);
                                                $message .= '/>';
                                            }
                                            // taking tax info portion
                                            if (strcmp($BookingInfo->getName(), 'TaxInfo') == 0) {

                                                $message .= '<TaxInfo ';

                                                foreach ($BookingInfo->attributes() as $attrName3 => $attrVal3) {
                                                    $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                                }
                                                $message .= '/>';
                                            }
                                        }

                                        $message .= '</AirPricingInfo>';
                                    }
                                }
                                foreach ($segment->children('common_v45_0', true) as $index => $common_v45_0) {
                                    if (strcmp($common_v45_0->getName(), 'HostToken') == 0) {
                                        $message .= '<HostToken xmlns="http://www.travelport.com/schema/common_v45_0" ';
                                        foreach ($common_v45_0->attributes() as $attrName => $attrVal) {
                                            $message .= '' . $attrName . '="' . (string) $attrVal . '" ';
                                        }
                                        $message .= '>';
                                        $message .= (string) $common_v45_0;

                                        $message .= '</HostToken>';
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }

            // ------------------------------------------------------------------------------------
            $message .= '</AirPricingSolution>';
            $tkt_date = date("Y-m-d") . 'T' . '23:59:00.000' . '+05:00';
            $message .= '<ActionStatus xmlns="http://www.travelport.com/schema/common_v45_0" ProviderCode="1G" TicketDate="' . $tkt_date . '" Type="TAW"/>';
            $message .= '</AirCreateReservationReq>';
            $message .= '</soapenv:Body>
		         </soapenv:Envelope>';
            $this->message = $message;
//            print_r($this->message); die();
            $return = $this->curl_action();
            $content = $this->prettyPrint($return);
            return $content;
        } catch (\Exception $e) {

            $e->getMessage();
        }
    }

    public function pnr_retrive_req($pnr) {
        if ($this->APITYPE == 'production') {
            $this->link = ("https://emea.universal-api.travelport.com/B2BGateway/connect/uAPI/UniversalRecordService");
        } elseif ($this->APITYPE == 'preproduction') {
            $this->link = ("https://emea.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/UniversalRecordService");
        }
        $this->message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:com="http://www.travelport.com/schema/common_v45_0" xmlns:univ="http://www.travelport.com/schema/universal_v45_0">
			   <soapenv:Body>
			         <univ:UniversalRecordRetrieveReq AuthorizedBy="user" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace">         <com:BillingPointOfSaleInfo OriginApplication="UAPI"/>
			            <univ:ProviderReservationInfo ProviderCode="' . $this->Provider . '" ProviderLocatorCode="' . $pnr . '" />
			         </univ:UniversalRecordRetrieveReq>
			    </soapenv:Body>
		          </soapenv:Envelope>';
        $return = $this->curl_action();
        return $return;
    }

    public function ticket_req($used_code_for_ticket, $pricing_info = []) {
        $msg = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
                <soapenv:Header/>
                <soapenv:Body>
                <air:AirTicketingReq xmlns:air="http://www.travelport.com/schema/air_v45_0" AuthorizedBy="user" BulkTicket="false" ReturnInfoOnFail="true" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace">
                <com:BillingPointOfSaleInfo xmlns:com="http://www.travelport.com/schema/common_v45_0" OriginApplication="UAPI"/>

                <air:AirReservationLocatorCode>' . $used_code_for_ticket . '</air:AirReservationLocatorCode>';
        foreach ($pricing_info as $key => $value) {
            $msg .= '<air:AirPricingInfoRef Key="' . $value['Key'] . '"/>';
        }
        $msg .= '</air:AirTicketingReq>
                    </soapenv:Body>
                    </soapenv:Envelope>';
        $this->message = $msg;
        $return = $this->curl_action();
        $Results = $this->prettyPrint($return);
        $booking = [];
        $xml = simplexml_load_String($Results, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);
        foreach ($Results->children('SOAP', true) as $fault) {
            if (strcmp($fault->getName(), 'Fault') == 0) {
                foreach ($fault->children() as $message) {
                    if (strcmp($message->getName(), 'faultstring') == 0) {
                        return array(
                            'status' => 'false',
                            'message' => (string) $message
                        );
                    }
                }
            }
        }
        $dataTicket = array();
        $ticketArray = array();
        foreach ($Results->children('air', true) as $AirTicketRsp) {
            foreach ($AirTicketRsp->children('air', true) as $ert) {
                if (strcmp($ert->getName(), 'TicketFailureInfo') == 0) {
                    foreach ($ert->attributes() as $keyError => $errorMessage) {
                        if ($keyError == 'Message') {
                            $message = (string) $errorMessage;
                            return array(
                                'status' => 'false',
                                'message' => $message
                            );
                        }
                    }
                }
                if (strcmp($ert->getName(), 'ETR') == 0) {
                    foreach ($ert->children('air', true) as $journey) {
                        foreach ($journey->attributes() as $c => $d) {
                            if ($c === 'TicketNumber') {
                                $dataTicket['ticket_number'] = (string) $d;
                            }
                        }
                    }
                }
                $ticketArray[] = $dataTicket;
            }
        }
        return array(
            'status' => 'true',
            'data' => $ticketArray
        );
    }

    function booking_res($bookingRspXml) {
        $flights = $bookingRspXml;
        $booking = [];
        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);
        foreach ($Results->children('SOAP', true) as $fault) {
            if (strcmp($fault->getName(), 'Fault') == 0) {
                return 'false';
            }
        }
        foreach ($Results->children('universal', true) as $AirCreateReservationRsp) {
            foreach ($AirCreateReservationRsp->children('universal', true) as $UniversalRecord) {
                if (strcmp($UniversalRecord->getName(), 'UniversalRecord') == 0) {
                    foreach ($UniversalRecord->attributes() as $ke => $att) {
                        $booking[$ke] = (string) $att;
                    }
                    //getting segments
                    foreach ($UniversalRecord->children('air', true) as $air) {
                        if (strcmp($air->getName(), 'AirReservation') == 0) {
                            $segmentIndex = 0;
                            $segments = [];
                            $pricing = [];
                            $userTicketData = [];
                            $userTicketDataArr = array();
                            foreach ($air->children('air', true) as $segmentss) {
                                if (strcmp($segmentss->getName(), 'AirSegment') == 0) {
                                    foreach ($segmentss->attributes() as $ke => $att) {
                                        if (strcmp($ke, "Carrier") == 0) {
                                            $ss['airline_logo'] = url('/') . '/public/airline_logo/' . $att . '.png';
                                            $ss['airline_name'] = $this->getAirline($att);
                                        }
                                        if (strcmp($ke, "Origin") == 0) {
                                            $ss['origin_city_name'] = $this->getCity($att);
                                        }
                                        if (strcmp($ke, "Destination") == 0) {
                                            $ss['destination_city_name'] = $this->getCity($att);
                                        }
                                        $ss[$ke] = (string) $att;
                                    }
                                    foreach ($segmentss->children('air', true) as $ssr) {
                                        if (strcmp($ssr->getName(), 'FlightDetails') == 0) {
                                            $ss[$ke] = (string) $att;
                                        }
                                    }
                                    $segments[] = $ss;
                                }
                                // get bagage data
                                if (strcmp($segmentss->getName(), 'AirPricingInfo') == 0) {
                                    $sub_price;
                                    foreach ($segmentss->attributes() as $ke => $att) {
                                        $sub_price[$ke] = (string) $att;
                                    }
                                    
                                    $setting = new Setting();
                                    $travelPortSetting = $setting->getSettingByCode('travelport-commission');
                                    if ($travelPortSetting && $travelPortSetting->setting_value > 0) {
                                        $commsionValue = getCommissionValue(str_replace('PKR', '', $sub_price['ApproximateBasePrice']), $travelPortSetting->setting_value);
                                    }
                                    $sub_price['TotalPriceWithCommission'] = round($commsionValue + str_replace('PKR', '', $sub_price['TotalPrice']));
                                    $pricing[] = $sub_price;
                                    //getting bagage data
                                    $taxes = [];
                                    foreach ($segmentss->children('air', true) as $airpricingchild) {
                                        if (strcmp($airpricingchild->getName(), 'FareInfo') == 0) {
                                            $baggage = [];
                                            foreach ($airpricingchild->children('air', true) as $fairinfochild) {

                                                if (strcmp($fairinfochild->getName(), 'BaggageAllowance') == 0) {
//                                                    print_r($fairinfochild); die();
                                                    foreach ($fairinfochild->children('air', true) as $baggageHere) {
                                                        foreach ($baggageHere->attributes() as $bgi => $bgj) {
                                                            $baggage[$bgi] = (string) $bgj;
                                                        }
                                                    }
                                                }
                                            }


                                            $booking['baggage'] = $baggage;
                                        }
                                        $te = [];
                                        if (strcmp($airpricingchild->getName(), 'TaxInfo') == 0) {
                                            foreach ($airpricingchild->attributes() as $ke => $att) {
                                                if ($ke == 'Category' || $ke == 'Amount')
                                                    $te[$ke] = (string) $att;
                                            }
                                            array_push($taxes, $te);
                                        }
                                    }
                                    $booking['taxes'] = $taxes;
                                }

                                if (strcmp($segmentss->getName(), 'DocumentInfo') == 0) {

                                    foreach ($segmentss->children('air', true) as $ticketInfo) {
                                        //die('ddefd');
                                        foreach ($ticketInfo->children('common_v45_0', true) as $tkt) {
                                            if (strcmp($ticketInfo->getName(), 'TicketInfo') == 0) {
                                                foreach ($ticketInfo->attributes() as $keyTkNum => $TkNum) {
                                                    if ($keyTkNum == 'Number') {
                                                        $userTicketData['ticket_number'] = (string) $TkNum;
                                                    }
                                                }
                                            }
                                        }
                                        $userTicketDataArr[] = $userTicketData;
                                    }
                                }
                            }
                            $booking['ticket_numbers'] = $userTicketDataArr;
                            $booking['pricing'] = $pricing;
                            $booking['segments'] = $segments;
                        }
                        $segmentIndex++;
                    }
                    $travelerData = array();
                    $ssrsu = array();
                    foreach ($UniversalRecord->children('common_v45_0', true) as $booking_travler) {
                        // getting ssr from  bookingTraveler
                        if (strcmp($booking_travler->getName(), 'BookingTraveler') == 0) {
                            $userData = [];
                            $ssrs = array();
                            foreach ($booking_travler->children('common_v45_0', true) as $keySsr => $ssr) {
                                foreach ($booking_travler->attributes() as $traveltypeKey => $traveltype) {
                                    if ($traveltypeKey == 'TravelerType') {
                                        $userData['passenger_type'] = (string) $traveltype;
                                    }
                                }
                                if (strcmp($ssr->getName(), 'BookingTravelerName') == 0) {

                                    foreach ($ssr->attributes() as $btnKey => $btn) {
                                        if ($btnKey == 'Prefix') {
                                            $userData['title'] = (string) $btn;
                                        }
                                        if ($btnKey == 'First') {
                                            $userData['firstName'] = (string) $btn;
                                        }
                                        if ($btnKey == 'Last') {
                                            $userData['lastName'] = (string) $btn;
                                        }
                                    }
                                }
                                if (strcmp($ssr->getName(), 'SSR') == 0) {

                                    foreach ($ssr->attributes() as $ke => $att) {


                                        if ($ke == 'FreeText') {
                                            $ssrs[] = (string) $att;
                                        }
                                    }
                                }
                            }
//                            if (isset($ssrs[count($ssrs) - 1])) {
//                                if (strpos($ssrs[count($ssrs) - 1], '/')) {
//                                    $ssrDataArr = explode('/', $ssrs[count($ssrs) - 1]);
//                                    $userData['nationality'] = $ssrDataArr[1];
//                                    $userData['passport_number'] = $ssrDataArr[2];
//                                    $userData['exp_date'] = $ssrDataArr[6];
//                                }
//                            }
                            $travelerData[] = $userData;
                        }
                        // getting general remarks
                        if (strcmp($booking_travler->getName(), 'GeneralRemark') == 0) {
                            foreach ($booking_travler->children('common_v45_0', true) as $ssr) {
                                if (strcmp($ssr->getName(), 'RemarkData') == 0) {
                                    $remarks[] = (string) $ssr;
                                    $booking['remarks'] = $remarks;
                                }
                            }
                        }
                    }
                    $booking['passenger_detail'] = $travelerData;
                }
                foreach ($UniversalRecord->children('universal', true) as $AirReservation) {
                    if (strcmp($AirReservation->getName(), 'ProviderReservationInfo') == 0) {
                        foreach ($AirReservation->attributes() as $ke => $att) {
                            if ($ke == 'LocatorCode')
                                $booking['galilo_pnr'] = (string) $att;
                        }
                    }
                }
                foreach ($UniversalRecord->children('air', true) as $AirReservation) {
                    if (strcmp($AirReservation->getName(), 'AirReservation') == 0) {
                        foreach ($AirReservation->attributes() as $ke => $att) {
                            if ($ke == 'LocatorCode')
                                $booking['used_for_ticket_reservation_code'] = (string) $att;
                        }
                        foreach ($AirReservation->children('common_v45_0', true) as $SupplierLocator) {
                            if (strcmp($SupplierLocator->getName(), 'SupplierLocator') == 0) {
                                foreach ($SupplierLocator->attributes() as $ke => $att) {
                                    $booking[$ke] = (string) $att;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $booking;
    }

    function removeNamespaceFromXML($xml) {
        $toRemove = ['universal', 'SOAP', 'common_v45_0', 'xmlns', 'air'];
        $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';
        foreach ($toRemove as $remove) {
            // First remove the namespace from the opening of the tag
            $xml = str_replace('<' . $remove . ':', '<', $xml);
            // Now remove the namespace from the closing of the tag
            $xml = str_replace('</' . $remove . ':', '</', $xml);
            // This XML uses the name space with CommentText, so remove that too
            $xml = str_replace($remove . ':UniversalRecord', 'UniversalRecord', $xml);
            // $xml = str_replace($remove . ':BookingTraveler', 'BookingTraveler', $xml);
            $pattern = "/xmlns:{$remove}{$nameSpaceDefRegEx}/";
            // Remove the actual namespace declaration using the Pattern
            $xml = preg_replace($pattern, '', $xml, 1);
        }
        // Return sanitized and cleaned up XML with no namespaces
        return json_encode(simplexml_load_string($xml));
    }

    function cancel_booking_req($code) {
        try {
            if ($this->APITYPE == 'production') {
                $this->link = "https://emea.universal-api.travelport.com/B2BGateway/connect/uAPI/UniversalRecordService";
            } elseif ($this->APITYPE == 'preproduction') {
                $this->link = "https://emea.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/UniversalRecordService";
            }
            $this->message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:com="http://www.travelport.com/schema/common_v45_0" xmlns:univ="http://www.travelport.com/schema/universal_v45_0">
			<soapenv:Header/>
			<soapenv:Body>
			<univ:UniversalRecordCancelReq AuthorizedBy="user" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace" UniversalRecordLocatorCode="' . $code . '" Version="1">
			<com:BillingPointOfSaleInfo OriginApplication="UAPI"/>
			</univ:UniversalRecordCancelReq>
			</soapenv:Body>
			</soapenv:Envelope>';
            $return = $this->curl_action();
            $content = $this->prettyPrint($return);
            $xml = simplexml_load_String($return, null, null, 'SOAP', true);
            $Results = $xml->children('SOAP', true);
            foreach ($Results->children('SOAP', true) as $fault) {
                if (strcmp($fault->getName(), 'Fault') == 0) {
                    foreach ($fault->children() as $message) {
                        if (strcmp($message->getName(), 'faultstring') == 0) {

                            return array('status' => 'false', 'xml_data' => $content);
                        }
                    }
                }
            }

            return json_decode($this->removeNamespaceFromXML($content), true);
            // return json_decode($this->removeNamespaceFromXML($content),true) ;
        } catch (\Exception $ex) {
            return $return = array(
                'status' => '4000',
                'message' => $e->getMessage()
            );
        }
    }

    function void_request($code) {
        $this->link = "https://emea.universal-api.travelport.com/B2BGateway/connect/uAPI/ReferenceDataLookupService";
        $this->message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:air="http://www.travelport.com/schema/air_v36_0" xmlns:com="http://www.travelport.com/schema/common_v36_0">
		<soapenv:Header/>
		<soapenv:Body>
		<air:AirVoidDocumentReq AuthorizedBy="user" BulkTicket="false" ReturnInfoOnFail="true" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace">
		<com:BillingPointOfSaleInfo OriginApplication="UAPI"/>
		<air:AirReservationLocatorCode> ' . $code . ' </air:AirReservationLocatorCode>
		</air:AirVoidDocumentReq>
		</soapenv:Body>
		 </soapenv:Envelope>';



        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        // $content = $this->prettyPrint($return);
        Storage::put('voidResponse.xml', $content);
        return;
    }

    function URModify() {
        $this->link = "https://emea.universal-api.travelport.com/B2BGateway/connect/uAPI/UniversalRecordService";
        $this->message = '<UniversalRecordModifyReq xmlns="http://www.travelport.com/schema/universal_v45_0" TargetBranch="' . $this->TARGETBRANCH . '" ReturnRecord="true" Version="0">
					<BillingPointOfSaleInfo xmlns="http://www.travelport.com/schema/common_v45_0" OriginApplication="UAPI"/>
					<RecordIdentifier UniversalLocatorCode="40MS3H" ProviderCode="1G" ProviderLocatorCode="NB5KHQ"/>
					<UniversalModifyCmd Key="ksldjfklsjdflkd1222">
					<AirAdd ReservationLocatorCode="72DESR" BookingTravelerRef="zG3sXvBAAA/B4cJioSAAAA==">
					<AirPricingTicketingModifiers xmlns="http://www.travelport.com/schema/air_v45_0">
					<AirPricingInfoRef Key="DDbVYvXc1BKAmQ57KSAAAA=="/>
					<TicketingModifiers>
					<Commission xmlns="http://www.travelport.com/schema/common_v45_0" Level="Fare" Type="PercentBase" Percentage="00.00" BookingTravelerRef="zG3sXvBAAA/B4cJioSAAAA=="/>
					</TicketingModifiers>
					</AirPricingTicketingModifiers>
					</AirAdd>
					</UniversalModifyCmd>
					</UniversalRecordModifyReq>';



        $req = $this->prettyPrint($this->message);
        Storage::put('ModifyReq.xml', $req);
        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        Storage::put('ModifyRsp.xml', $content);
        return $content;
    }

    function fareRuleReq($fareInfoRef, $fareRuleKey) {
        $this->message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:air="http://www.travelport.com/schema/air_v36_0" xmlns:com="http://www.travelport.com/schema/common_v36_0">
		<soapenv:Header/>
		<soapenv:Body>
					<air:AirFareRulesReq TargetBranch="' . $this->TARGETBRANCH . '">
					 	<com:BillingPointOfSaleInfo OriginApplication="UAPI"/>
					 	<air:FareRuleKey FareInfoRef="' . $fareInfoRef . '" ProviderCode="1G">' . $fareRuleKey . '</air:FareRuleKey>
					</air:AirFareRulesReq>
		</soapenv:Body>
		 </soapenv:Envelope>';

        // return $this->message;
        $req = $this->prettyPrint($this->message);
        Storage::put('FareRuleReq.xml', $req);
        $return = $this->curl_action();
        $content = $this->prettyPrint($return);

        $flights = $content;

        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);

        foreach ($Results->children('air', true) as $AirFareRulesRsp) {
            foreach ($AirFareRulesRsp->children('air', true) as $FareRule) {
                $fare_text = '';
                foreach ($FareRule->children('air', true) as $FareRuleLong) {
                    foreach ($FareRule->children('air', true) as $FareRuleLong) {
                        foreach ($FareRuleLong->attributes() as $ke => $att) {
                            if ($ke == 'Category')
                                $fare_text .= '<b> CATEGORY ' . $att . '</b><br/>';
                        }

                        $fare_text .= (string) $FareRuleLong;
                        $fare_text .= '</br>';
                    }
                    return $fare_text;
                }
            }
        }
    }

    // multi destination code start from here
    public function multi_city_trip($origion = null, $destination = null, $fromDate = null, $adult = null, $child = null, $infant = null, $stop = null, $ticket_class = null) {
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
				   <soapenv:Header/>
				   <soapenv:Body>
				      <air:LowFareSearchReq TraceId="trace" AuthorizedBy="user" SolutionResult="true" TargetBranch="' . $this->TARGETBRANCH . '" xmlns:air="http://www.travelport.com/schema/air_v45_0" xmlns:com="http://www.travelport.com/schema/common_v45_0">
				         <com:BillingPointOfSaleInfo OriginApplication="UAPI"/>';

        foreach ($origion as $origion_key => $origion_value) {

            $message .= '<air:SearchAirLeg>';
            $message .= '<air:SearchOrigin>
				   	<com:CityOrAirport Code="' . $origion_value . '" PreferCity="true"/>
				   </air:SearchOrigin>';

            $message .= '<air:SearchDestination>
				        <com:CityOrAirport Code="' . $destination[$origion_key] . '" PreferCity="true" />
				   </air:SearchDestination>';

            $newDate = date("Y-m-d", strtotime(str_replace('/', '-', $fromDate[$origion_key])));
            $message .= '<air:SearchDepTime PreferredTime="' . $newDate . '">
				            </air:SearchDepTime>';

            $message .= '<air:AirLegModifiers AllowDirectAccess="true" >';

            if ($ticket_class[$origion_key] != null) {
                $message .= '<air:PreferredCabins>
                            <CabinClass xmlns="http://www.travelport.com/schema/common_v45_0" Type="' . $ticket_class[$origion_key] . '" />
                          </air:PreferredCabins>';
            }

            $message .= '</air:AirLegModifiers>';

            $message .= '</air:SearchAirLeg>';
        }

        $message .= '<air:AirSearchModifiers>
			            <air:PreferredProviders>
			               <com:Provider Code="' . $this->Provider . '"/>
			            </air:PreferredProviders>';

        $message .= '<air:ProhibitedCarriers>
					      <com:Carrier Code="PK" />
					    </air:ProhibitedCarriers>';

        $message .= '<air:FlightType NonStopDirects="false" />';
        $message .= '</air:AirSearchModifiers>';

        if ($adult != null) {
            for ($i = 1; $i <= $adult; $i++) {
                $message .= '<com:SearchPassenger  Code="ADT" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }

        if ($child != null) {
            for ($i = 1; $i <= $child; $i++) {

                $chBirth = date('Y-m-d', strtotime('-10 years'));
                $message .= '<com:SearchPassenger Code="CNN" Age="10" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }

        if ($infant != null) {
            for ($i = 1; $i <= $infant; $i++) {

                $chBirth = date('Y-m-d', strtotime('-1 years'));
                $message .= '<com:SearchPassenger Code="INF" Age="1" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0"/>';
            }
        }

        $message .= '<air:AirPricingModifiers FaresIndicator="AllFares"/>';
        $message .= '</air:LowFareSearchReq>
				   </soapenv:Body>
				</soapenv:Envelope>';

        $this->message = $message;

        // return $this->prettyPrint($this->message);
        Storage::put('FlightSearchRequest.xml', $this->prettyPrint($this->message));

        $return = $this->curl_action();

        $content = $this->prettyPrint($return);
        return $content;
    }

    public function multi_air_pricing_req($pricingKey, $adult = null, $child = null, $infant = null) {
        $result = $this->return_itenery_trip_for_pricingRequest($pricingKey);

        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
				   <soapenv:Header/>
				   <soapenv:Body>';

        $message .= '<air:AirPriceReq xmlns:air="http://www.travelport.com/schema/air_v45_0" AuthorizedBy="user" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace">
				<com:BillingPointOfSaleInfo xmlns:com="http://www.travelport.com/schema/common_v45_0" OriginApplication="UAPI"/>';

        $message .= '<air:AirItinerary>';

        $segments = sizeof($result['journey'][1]);

        $aa = 0;
        foreach ($result['journey'][1] as $key => $flight) {
            $message .= '<air:AirSegment ';
            foreach ($flight as $segment_key => $segment_data) {
                if ($segment_key == 'cabin' || $segment_key == 'BookingCode')
                    continue;

                $message .= $segment_key . '="' . $segment_data . '" ';
            }

            $message .= 'ProviderCode="1G"';
            $message .= '>';

            if (array_key_exists($key + 1, $result['journey'][1])) {
                if ($flight['Group'] == $result['journey'][1][$key + 1]['Group']) {
                    $message .= '<air:Connection/>';
                }
            }

            $message .= '</air:AirSegment>';
            $aa++;
        }

        $message .= '	</air:AirItinerary>';

        $message .= ' <air:AirPricingModifiers FaresIndicator="PublicAndPrivateFares"  PlatingCarrier="' . $result['journey'][1][0]['Carrier'] . '" >
				    <air:AccountCodes>
				      <com:AccountCode xmlns:com="http://www.travelport.com/schema/common_v45_0" Code="BKH0519" />
				    </air:AccountCodes>
				  </air:AirPricingModifiers>';

        if ($adult != null) {
            for ($i = 1; $i <= $adult; $i++) {
                $message .= '<com:SearchPassenger  Code="ADT" xmlns:com="http://www.travelport.com/schema/common_v45_0" BookingTravelerRef="' . strtoupper(str_random(12)) . '" /> ';
            }
        }
        if ($child != null) {
            for ($i = 1; $i <= $child; $i++) {

                $chBirth = date('Y-m-d', strtotime('-10 years'));
                $message .= '<com:SearchPassenger Code="CNN" Age="10" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0" BookingTravelerRef="' . strtoupper(str_random(12)) . '"/>';
            }
        }

        if ($infant != null) {
            for ($i = 1; $i <= $infant; $i++) {

                $chBirth = date('Y-m-d', strtotime('-1 years'));
                $message .= '<com:SearchPassenger Code="INF" Age="1" DOB="' . $chBirth . '" xmlns:com="http://www.travelport.com/schema/common_v45_0" BookingTravelerRef="' . strtoupper(str_random(12)) . '"/>';
            }
        }


        $message .= '<air:AirPricingCommand>';
        foreach ($result['journey'][1] as $key => $flight) {

            $message .= ' <air:AirSegmentPricingModifiers AirSegmentRef="' . $flight['Key'] . '" CabinClass="' . $flight['cabin'] . '">
						 <air:PermittedBookingCodes>
				         <air:BookingCode Code="' . $flight['BookingCode'] . '" />
				         </air:PermittedBookingCodes>
				    </air:AirSegmentPricingModifiers>';
        }



        $message .= '</air:AirPricingCommand>';

        $message .= '</air:AirPriceReq>
				   			</soapenv:Body>
							</soapenv:Envelope>';
        $this->message = $message;

        Storage::put('AirPriceRequest.xml', $this->prettyPrint($this->message));

        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        Storage::put('AirPriceRsp.xml', $content);
        return $content;
    }

    function multi_get_data_from_pricing_rsp() {
        $flights = Storage::get('AirPriceRsp.xml');
        $airsegment = [];
        $booking_class = [];
        $Group0 = [];
        $Group1 = [];
        $Group2 = [];
        $Group3 = [];
        $Group4 = [];
        $Group5 = [];
        $Group6 = [];
        $Group7 = [];

        $bag = [];

        $data = [];
        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);

        foreach ($Results->children('air', true) as $priceRsp) {
            foreach ($priceRsp->children('air', true) as $result) {
                //get segment of airline
                if (strcmp($result->getName(), 'AirItinerary') == 0) {
                    foreach ($result->children('air', true) as $segment) {
                        if (strcmp($segment->getName(), 'AirSegment') == 0) {
                            $codesh = [];
                            foreach ($segment->attributes() as $attrName => $attrVal)
                                $codesh[$attrName] = (string) $attrVal;

                            foreach ($segment->children('air', true) as $CodeshareInfo) {
                                if (strcmp($CodeshareInfo->getName(), 'CodeshareInfo') == 0) {
                                    $codesh['fligtName'] = (string) $CodeshareInfo;
                                    foreach ($CodeshareInfo->attributes() as $attrName => $attrVal) {
                                        $codesh[$attrName] = (string) $attrVal;
                                    }
                                }

                                if (strcmp($CodeshareInfo->getName(), 'FlightDetails') == 0) {
                                    foreach ($CodeshareInfo->attributes() as $attrName => $attrVal) {
                                        if (strcmp($attrName, 'Key') == 0)
                                            $codesh['FlightDetailsKey'] = (string) $attrVal;
                                        else
                                            $codesh[$attrName] = (string) $attrVal;
                                    }
                                }
                            }
                            if ($codesh['Group'] == 0) {
                                array_push($Group0, $codesh);
                            } elseif ($codesh['Group'] == 1) {
                                array_push($Group1, $codesh);
                            } elseif ($codesh['Group'] == 2) {
                                array_push($Group2, $codesh);
                            } elseif ($codesh['Group'] == 3) {
                                array_push($Group3, $codesh);
                            } elseif ($codesh['Group'] == 4) {
                                array_push($Group4, $codesh);
                            } elseif ($codesh['Group'] == 5) {
                                array_push($Group5, $codesh);
                            } elseif ($codesh['Group'] == 6) {
                                array_push($Group6, $codesh);
                            } elseif ($codesh['Group'] == 7) {
                                array_push($Group7, $codesh);
                            }
                        }
                    }
                }
                // get baggege and information about ticket
                if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                    foreach ($result->children('air', true) as $AirPricingSolution) {
                        $check = 0;
                        foreach ($AirPricingSolution->children('air', true) as $AirPricingInfo) {
                            if (strcmp($AirPricingInfo->getName(), 'AirPricingInfo') == 0) {
                                foreach ($AirPricingInfo->children('air', true) as $BaggageAllowances) {
                                    // -----------get booking class and code ---------------
                                    if ($check == 0) {
                                        if (strcmp($BaggageAllowances->getName(), 'BookingInfo') == 0) {
                                            $book = [];
                                            foreach ($BaggageAllowances->attributes() as $attrName => $attrVal)
                                                $book[$attrName] = (string) $attrVal;
                                            $booking_class[] = $book;
                                        }
                                    }

                                    // -------------------------------------------
                                    if (strcmp($BaggageAllowances->getName(), 'BaggageAllowances') == 0) {
                                        foreach ($BaggageAllowances->children('air', true) as $BaggageAllowancesSubAir) {

                                            if (strcmp($BaggageAllowancesSubAir->getName(), 'BaggageAllowanceInfo') == 0) {
                                                $bag_alow_info = [];

                                                //get attribute of baggageAllowances
                                                foreach ($BaggageAllowancesSubAir->attributes() as $attrName => $attrVal)
                                                    $bag_alow_info[$attrName] = (string) $attrVal;

                                                foreach ($BaggageAllowancesSubAir->children('air', true) as $BaggageAllowancesInfoSubAir) {
                                                    if (strcmp($BaggageAllowancesInfoSubAir->getName(), 'URLInfo') == 0) {
                                                        foreach ($BaggageAllowancesInfoSubAir->children('air', true) as $url) {
                                                            $bag_alow_info['url'] = (string) $url;
                                                        }
                                                    }

                                                    if (strcmp($BaggageAllowancesInfoSubAir->getName(), 'TextInfo') == 0) {
                                                        foreach ($BaggageAllowancesInfoSubAir->children('air', true) as $url) {
                                                            $bag_alow_info['weight'] = (string) $url;
                                                            break;
                                                        }
                                                    }

                                                    if (strcmp($BaggageAllowancesInfoSubAir->getName(), 'BagDetails') == 0) {
                                                        foreach ($BaggageAllowancesInfoSubAir->attributes() as $attrName => $attrVal)
                                                        // $bag_alow_info[$attrName]=(string)$attrVal;
                                                            foreach ($BaggageAllowancesInfoSubAir->children('air', true) as $BaggageRestriction) {
                                                                foreach ($BaggageRestriction->children('air', true) as $TextInfo) {
                                                                    foreach ($TextInfo->children('air', true) as $Text) {

                                                                        $bag_alow_info[(string) $attrVal] = (string) $Text;
                                                                    }
                                                                }
                                                            }
                                                    }
                                                }
                                                $bag[] = $bag_alow_info;
                                            }
                                        }
                                    }
                                }
                                $check = 1;
                            }
                        }
                        break;
                    }
                }

                if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                    foreach ($result->children('air', true) as $AirPricingSolution) {
                        //getting ticketing date line
                        $check = 1;
                        foreach ($AirPricingSolution->children('air', true) as $FareNote) {

                            if (strcmp($FareNote->getName(), 'FareNote') == 0) {
                                if ($check == 2) {
                                    $data['FareNote'] = (string) $FareNote;
                                }
                                $check++;
                            }
                        }
                        // -----------------------------------------
                        foreach ($AirPricingSolution->children('air', true) as $airPricingInfo) {

                            if (strcmp($airPricingInfo->getName(), 'AirPricingInfo') == 0) {

                                foreach ($airPricingInfo->children('air', true) as $FareRule) {

                                    if (strcmp($FareRule->getName(), 'FareInfo') == 0) {

                                        foreach ($FareRule->children('air', true) as $FareRuleKey) {
                                            if (strcmp($FareRuleKey->getName(), 'FareRuleKey') == 0) {
                                                foreach ($FareRuleKey->attributes() as $attrName => $attrVal)
                                                    $test[$attrName] = (string) $attrVal;
                                                $test['key'] = (string) $FareRuleKey;
                                                $data['fareRule'] = $test;
                                                break;
                                            }

                                            break;
                                        }
                                    }
                                    break;
                                }
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }

        $data['segment0'] = $Group0;
        $data['segment1'] = $Group1;
        $data['segment2'] = $Group2;
        $data['segment3'] = $Group3;
        $data['segment4'] = $Group4;
        $data['segment5'] = $Group5;
        $data['segment6'] = $Group6;
        $data['segment7'] = $Group7;
        $data['booking_class'] = $booking_class;

        $data['baggage'] = $bag;
        return $data;
    }

    public function multi_air_reservation_req_new(Request $request) {
        $pass_type = $request->input('pass_type'); // INF /CNN / ADT
        $title = $request->input('title');
        $first_name = $request->input('fname');
        $last_name = $request->input('lname');
        $nationality = $request->input('nationality');
        $birth_date = $request->input('dob');
        $type = $request->input('type');
        $passport_no = $request->input('passport_no');
        $issue_country = $request->input('issue_country');
        $expiration_data = $request->input('expiration_date');
        $data = $this->get_air_segments_pricingRequest();
        $email = $request->input('email');
        $message = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
	    <soapenv:Header/>
	    <soapenv:Body>
	    <univ:AirCreateReservationReq xmlns:air="http://www.travelport.com/schema/air_v45_0" xmlns:common_v45_0="http://www.travelport.com/schema/common_v45_0" xmlns:univ="http://www.travelport.com/schema/universal_v45_0" AuthorizedBy="user" RetainReservation="Both" TargetBranch="' . $this->TARGETBRANCH . '" TraceId="trace">
	    <com:BillingPointOfSaleInfo xmlns:com="http://www.travelport.com/schema/common_v45_0" OriginApplication="UAPI"/>
	       ';

        $randomkey = Str::random(18);
        $newkey = [];

        for ($i = 0; $i < sizeof($pass_type); $i++) {
            $newkey[$i] = $randomkey . '' . $i;
            $newDate = db_format_date($birth_date[$i]);
            $newDateExp = db_format_date($expiration_data[$i]);
            $age_in_year = Carbon::parse($newDate)->age;

            if ($title[$i] == 'MR')
                $gender = 'M';
            else
                $gender = 'F';

            if ($pass_type[$i] == 'ADT')
                $message .= '<com:BookingTraveler xmlns:com="http://www.travelport.com/schema/common_v45_0" DOB="' . $newDate . '" Gender="' . $gender . '" Key="' . $newkey[$i] . '" TravelerType="' . $pass_type[$i] . '">
	        <com:BookingTravelerName First="' . $first_name[$i] . '" Last="' . $last_name[$i] . '" Prefix="' . $title[$i] . '"/>';
            else
                $message .= '<com:BookingTraveler xmlns:com="http://www.travelport.com/schema/common_v45_0" Age="' . $age_in_year . '" DOB="' . $newDate . '" Gender="' . $gender . '" Key="' . $newkey[$i] . '" TravelerType="' . $pass_type[$i] . '">
	        <com:BookingTravelerName First="' . $first_name[$i] . '" Last="' . $last_name[$i] . '" Prefix="' . $title[$i] . '"/>';

            $message .= '<com:PhoneNumber AreaCode="051" CountryCode="0092" Location="ISB" Number="3005959967"/>';

            $message .= ' <com:Email EmailID="' . $email . '" Type="office"/>';

            foreach ($data['segment'] as $key => $airSegment) {
                if ($passport_no[$i] == '' && $type[$i] == '' && $issue_country[$i] == '') {
                    $message .= '<com:SSR Carrier="' . $airSegment['Carrier'] . '" FreeText="/' . $issue_country[$i] . '/' . $passport_no[$i] . '//' . date("dMy", strtotime($newDate)) . '/' . $gender . '//' . $last_name[$i] . '/' . $first_name[$i] . '" SegmentRef="' . $airSegment['Key'] . '" Status="HK" Type="DOCS"/>';
                } else {

                    $message .= '<com:SSR Carrier="' . $airSegment['Carrier'] . '" FreeText="' . $type[$i] . '/' . $issue_country[$i] . '/' . $passport_no[$i] . '/' . $nationality[$i] . '/' . date("dMy", strtotime($newDate)) . '/' . $gender . '/' . date("dMy", strtotime($newDateExp)) . '/' . $last_name[$i] . '/' . $first_name[$i] . '" SegmentRef="' . $airSegment['Key'] . '" Status="HK" Type="DOCS"/>';
                }
            }

            if ($pass_type[$i] == 'CNN')
                $message .= '<com:NameRemark>
	                <com:RemarkData>P-C' . sprintf("%02d", $age_in_year) . '</com:RemarkData>
	              </com:NameRemark>';

            if ($pass_type[$i] == 'INF')
                $message .= '<com:NameRemark>
	                <com:RemarkData>' . Carbon::parse($newDate)->format("dMy") . '</com:RemarkData>
	              </com:NameRemark>';

            $message .= '</com:BookingTraveler>';
        }

        $message .= '<ContinuityCheckOverride Key="1T" xmlns="http://www.travelport.com/schema/common_v45_0">true</ContinuityCheckOverride>';

        $message .= '<com:FormOfPayment xmlns:com="http://www.travelport.com/schema/common_v45_0" Type="Cash"/>';

        $pricingsolution = '<air:AirPricingSolution ';
        foreach ($data['pricingSol'] as $key => $value) {
            $pricingsolution .= ' ' . $key . '=' . '"' . $value . '"';
        }

        $pricingsolution .= ' >';
        $pricingsolution = str_replace("pricingSolutionKey", "Key", $pricingsolution);
        $message .= $pricingsolution;

        foreach ($data['segment'] as $key => $airSegment) {
            $message .= '<air:AirSegment ArrivalTime="' . $airSegment['ArrivalTime'] . '" AvailabilityDisplayType="' . $airSegment['AvailabilityDisplayType'] . '" AvailabilitySource="' . $airSegment['AvailabilitySource'] . '" Carrier="' . $airSegment['Carrier'] . '" ChangeOfPlane="' . $airSegment['ChangeOfPlane'] . '" ClassOfService="' . $airSegment['ClassOfService'] . '" DepartureTime="' . $airSegment['DepartureTime'] . '" Destination="' . $airSegment['Destination'] . '" Distance="' . $airSegment['Distance'] . '" Equipment="' . $airSegment['Equipment'] . '" FlightNumber="' . $airSegment['FlightNumber'] . '" FlightTime="' . $airSegment['FlightTime'] . '" Group="' . $airSegment['Group'] . '" Key="' . $airSegment['Key'] . '"  OptionalServicesIndicator="' . $airSegment['OptionalServicesIndicator'] . '" Origin="' . $airSegment['Origin'] . '" ParticipantLevel="' . $airSegment['ParticipantLevel'] . '" PolledAvailabilityOption="' . $airSegment['PolledAvailabilityOption'] . '" ProviderCode="' . $this->Provider . '" TravelTime="' . $airSegment['TravelTime'] . '"';


            if (array_key_exists("LinkAvailability", $airSegment))
                $message .= ' LinkAvailability ="' . $airSegment['LinkAvailability'] . '" >';
            else
                $message .= ' >';

            $message .= '<air:FlightDetails ArrivalTime="' . $airSegment['ArrivalTime'] . '" DepartureTime="' . $airSegment['DepartureTime'] . '" Destination="' . $airSegment['Destination'] . '" Distance="' . $airSegment['Distance'] . '" FlightTime="' . $airSegment['FlightTime'] . '" Key="' . $airSegment['FlightDetailsKey'] . '" Origin="' . $airSegment['Origin'] . '" TravelTime="' . $airSegment['TravelTime'] . '"/>';
            if (array_key_exists($key + 1, $data['segment'])) {
                if ($airSegment['Group'] == $data['segment'][$key + 1]['Group']) {
                    $message .= '<air:Connection/>';
                }
            }

            $message .= '</air:AirSegment>';
        }

        // --------------------------------------------------------------

        $flights = Storage::get('AirPriceRsp.xml');

        $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
        $Results = $xml->children('SOAP', true);

        foreach ($Results->children('air', true) as $priceRsp) {
            foreach ($priceRsp->children('air', true) as $result) {
                if (strcmp($result->getName(), 'AirPriceResult') == 0) {
                    foreach ($result->children('air', true) as $segment) {
                        if (strcmp($segment->getName(), 'AirPricingSolution') == 0) {
                            foreach ($segment->children('air', true) as $AirPricingInfo) {
                                if (strcmp($AirPricingInfo->getName(), 'AirPricingInfo') == 0) {
                                    // get air pricing info attribute
                                    $message .= '<air:AirPricingInfo ';
                                    foreach ($AirPricingInfo->attributes() as $attrName => $attrVal) {
                                        $message .= '' . $attrName . '="' . (string) $attrVal . '" ';
                                    }

                                    $message .= '>';

                                    foreach ($AirPricingInfo->children('air', true) as $BookingInfo) {
                                        // taking fare info portion
                                        if (strcmp($BookingInfo->getName(), 'FareInfo') == 0) {
                                            $message .= '<air:FareInfo ';
                                            foreach ($BookingInfo->attributes() as $attrName3 => $attrVal3) {
                                                $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                            }
                                            $message .= '/>';
                                        }

                                        // taking booking info portion
                                        if (strcmp($BookingInfo->getName(), 'BookingInfo') == 0) {
                                            $message .= '<air:BookingInfo ';
                                            foreach ($BookingInfo->attributes() as $attrName2 => $attrVal2) {
                                                $message .= '' . $attrName2 . '="' . (string) $attrVal2 . '" ';
                                            }
                                            $message .= '/>';
                                        }

                                        // taking passenger type portion
                                        if (strcmp($BookingInfo->getName(), 'PassengerType') == 0) {

                                            $message .= '<air:PassengerType ';

                                            foreach ($BookingInfo->attributes() as $attrName3 => $attrVal3) {
                                                $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                            }

                                            $message .= 'BookingTravelerRef="' . $newkey[0] . '" ';
                                            array_shift($newkey);
                                            $message .= '/>';
                                        }

                                        // taking tax info portion
                                        if (strcmp($BookingInfo->getName(), 'TaxInfo') == 0) {

                                            $message .= '<air:TaxInfo ';

                                            foreach ($BookingInfo->attributes() as $attrName3 => $attrVal3) {
                                                $message .= '' . $attrName3 . '="' . (string) $attrVal3 . '" ';
                                            }
                                            $message .= '/>';
                                        }

                                        // taking ChangePenalty portion
                                        if (strcmp($BookingInfo->getName(), 'ChangePenalty') == 0) {
                                            $message .= '<air:ChangePenalty> ';
                                            foreach ($BookingInfo->children('air', true) as $name => $value) {
                                                $message .= '<air:' . (string) $name . '>' . (string) $value . '</air:' . (string) $name . '> ';
                                            }
                                            $message .= '</air:ChangePenalty>';
                                        }

                                        // taking CancelPenalty portion
                                        if (strcmp($BookingInfo->getName(), 'CancelPenalty') == 0) {
                                            $message .= '<air:CancelPenalty> ';
                                            foreach ($BookingInfo->children('air', true) as $name => $value) {
                                                $message .= '<air:' . (string) $name . '>' . (string) $value . '</air:' . (string) $name . '> ';
                                            }
                                            $message .= '</air:CancelPenalty>';
                                        }
                                    }

                                    $message .= '</air:AirPricingInfo>';
                                }
                            }
                            foreach ($segment->children('common_v45_0', true) as $index => $common_v45_0) {
                                if (strcmp($common_v45_0->getName(), 'HostToken') == 0) {
                                    $message .= '<common_v45_0:HostToken ';
                                    foreach ($common_v45_0->attributes() as $attrName => $attrVal) {
                                        $message .= '' . $attrName . '="' . (string) $attrVal . '" ';
                                    }
                                    $message .= '>';
                                    $message .= (string) $common_v45_0;

                                    $message .= '</common_v45_0:HostToken>';
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }

        // -----------------------------------------------------

        $message .= '</air:AirPricingSolution>';

        $tkt_date = date("Y-m-d") . 'T' . '23:59:00.000' . '+05:00';
        $message .= '<com:ActionStatus xmlns:com="http://www.travelport.com/schema/common_v45_0" ProviderCode="1G" TicketDate="' . $tkt_date . '" Type="TAW"/>';

        $message .= '</univ:AirCreateReservationReq>';

        $message .= '</soapenv:Body>
	          </soapenv:Envelope>';

        $this->message = $message;

        Storage::put('BookingReq.xml', $this->prettyPrint($this->message));

        $return = $this->curl_action();
        $content = $this->prettyPrint($return);
        Storage::put('BookingRsp.xml', $content);
        return;
    }

}
