<?php

namespace App\Helpers;

use App\Models\Setting;

/*
 * For Travel Hitit
 */

class TravelAirBlue {

    // protected $message = '';
    protected $link = '';
    protected $credential = '';
    protected $readCredential = '';
    protected $auth = '';
    protected $APITYPE = '';

    public function __construct() {
        // Travel Hitit Credentials Dynamic

        $this->APITYPE = 'preproduction';
        $set = new Setting();
        $settingData = $set->getSettingByCode('flights-api-type');
        if($settingData !== null){
            $this->APITYPE = $settingData->setting_value;
        }

        if ($this->APITYPE == 'production') {
            $this->link = 'https://ota.zapways.com/v2.0/OTAAPI.asmx';

            $UserId = '1935/A0B7DEB2C6E1BBA5972768B1DFFEEF310D';
            $agentType = '29';
            $agentId = 'BukhariOTA';
            $password = 'dhtTXL5PE27YvmQZ';
            $target = 'Production';
            $version = '1.04';
        } elseif ($this->APITYPE == 'preproduction') {
            $this->link = 'https://otatest.zapways.com/v2.0/OTAAPI.asmx';

            $UserId = '1926/EB541B20EB54C90CFB5686AE64381C191B';
            $agentType = '29';
            $agentId = 'BukhariOTA';
            $password = 'R0kAi1kkZ5uREOGz';
            $target = 'Test';
            $version = '1.04';
        }


        $this->credential = '<airLowFareSearchRQ EchoToken="-8586704355136787339" Target="' . $target . '"
                        Version="' . $version . '" xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source ERSP_UserID="' . $UserId . '">
                        <RequestorID Type="' . $agentType . '" ID="' . $agentId . '" MessagePassword="' . $password . '" />
                        </Source>
                        </POS>';
        $this->credentialBooking = '<airBookRQ Target="' . $target . '"
                        Version="' . $version . '" xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source ERSP_UserID="' . $UserId . '">
                        <RequestorID Type="' . $agentType . '" ID="' . $agentId . '" MessagePassword="' . $password . '" />
                        </Source>
                        </POS>';
        $this->readCredential = '<readRQ Target="' . $target . '" Version="' . $version . '" xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source ERSP_UserID="' . $UserId . '">
                        <RequestorID Type="' . $agentType . '" ID="' . $agentId . '" MessagePassword="' . $password . '" />
                        </Source>
                        </POS>
                        ';
        $this->ticketGenerate = '<airDemandTicketRQ Target="' . $target . '" Version="' . $version . '" xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source ERSP_UserID="' . $UserId . '">
                        <RequestorID Type="' . $agentType . '" ID="' . $agentId . '" MessagePassword="' . $password . '" />
                        </Source>
                        </POS>
                        ';
        $this->auth = base64_encode("$this->credential");
    }

    public function prettyPrint($result) {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($result);
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    function curl_action() {

        $RequestURL = $this->link;
        // Relative or Absolute path to Client Certificate.
        $CertFile = storage_path() . "/cert.pem";
        // Relative or Absolute path to Client Key.
        $KeyFile = storage_path() . "/key.pem";

        $header = array(
            "Content-Type: text/xml;charset=UTF-8",
            "Accept: gzip,deflate",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: " . strlen($this->message),
        );
        $CurlHandler = curl_init();
        //curl_easy_setopt(curl,CURLOPT_SSLCERTTYPE,"PEM");
        $Opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLKEY => $KeyFile,
            CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->message,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_URL => $RequestURL,
            CURLOPT_SSLCERT => $CertFile,
        );

        // Set options on the handler.
        @curl_setopt_array($CurlHandler, $Opts);

        // Here's the output.
        $Output = @curl_exec($CurlHandler);
        // Just dump out the output here in plain text for now.
        header('Content-type: text/plain');
        return $Output;
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

    public function getCity($search) {
        $json = file_get_contents(storage_path() . "/cities.json");
        $cities = json_decode($json);
        //print_r($cities); die();
        foreach ($cities as $city) {
            if ($city->code == $search) {
                return $city->city_name;
            }
        }
    }

    public function getAirline($search) {
        $json = file_get_contents(storage_path() . "/airlines.json");
        $airlines = json_decode($json);
        //print_r($cities); die();
        foreach ($airlines as $airline) {
            if ($airline->code == $search) {
                return $airline->name;
            }
        }
    }

    public function search_request($data) {
        $message = '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
                        <Header/>
                        <Body>
                        <AirLowFareSearch xmlns="http://zapways.com/air/ota/2.0" >
                        ' . $this->credential . '
                        <OriginDestinationInformation>
                        <DepartureDateTime>' . db_format_date($data['from_date']) . '</DepartureDateTime>
                        <OriginLocation LocationCode="' . $data['from'] . '"></OriginLocation>
                        <DestinationLocation LocationCode="' . $data['to'] . '"></DestinationLocation>
                        </OriginDestinationInformation>';
        if (isset($data['to_date']) && $data['to_date']) {
            $message .= '<OriginDestinationInformation>
                        <DepartureDateTime>' . db_format_date($data['to_date']) . '</DepartureDateTime>
                        <OriginLocation LocationCode="' . $data['to'] . '"></OriginLocation>
                        <DestinationLocation LocationCode="' . $data['from'] . '"></DestinationLocation>
                        </OriginDestinationInformation>';
        }
        $message .= '<TravelerInfoSummary>
                        <AirTravelerAvail>
                        <PassengerTypeQuantity Code="ADT" Quantity="' . $data['adult'] . '"></PassengerTypeQuantity>';
        if ($data['children'] && $data['children'] > 0) {
            $message .= '<PassengerTypeQuantity Code="CHD" Quantity="' . $data['children'] . '"></PassengerTypeQuantity>';
        }

        if ($data['infant'] && $data['infant'] > 0) {
            $message .= '<PassengerTypeQuantity Code="INF" Quantity="' . $data['infant'] . '"></PassengerTypeQuantity>';
        }

        $message .= '</AirTravelerAvail>
                        </TravelerInfoSummary>
                        </airLowFareSearchRQ>
                        </AirLowFareSearch>
                        </Body>
                        </Envelope>';
        $this->message = $this->prettyPrint($message);
        
        $AirBlueSearchData = $this->curl_action();
        $return = $this->prettyPrint($AirBlueSearchData);
        return $return;
    }

    

    public function simpleXmlToArray($xmlObject) {
        $array = [];
        foreach ($xmlObject->children() as $node) {
            $array[$node->getName()] = is_array($node) ? simplexml_to_array($node) : (string) $node;
        }

        return $array;
    }

    function OutputAirSearch($xmlData, $from, $cabinclass,$flightType) {

        try {
            $flights = $xmlData;
            //print_r($flights); die();
            $flightsArray = array();
            $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
            $Results = $xml->children('soap', true);


            foreach ($Results->children() as $message) {
                foreach ($message->children() as $AirLowFareSearchResult) {

                    foreach ($AirLowFareSearchResult->children() as $key => $war) {

                        if ($key == 'Warnings') {
                            //die('dd');
                            foreach ($war->children() as $warning) {
                                return array(
                                    'status' => 'false',
                                    'message' => (string) $warning
                                );
                            }
                        }
                    }
                }
            }


            foreach ($Results->children() as $message) {
                foreach ($message->children() as $AirLowFareSearchResult) {

                    foreach ($AirLowFareSearchResult->children() as $PricedItineraries) {
                        foreach ($PricedItineraries->children() as $PricedItinerary) {

                            foreach ($PricedItinerary as $flightSegments) {
                                //$availableData = array();
                                if (strcmp($flightSegments->getName(), 'AirItinerary') == 0) {
                                    foreach ($flightSegments->children() as $OriginDestinationOptions) {

                                        foreach ($OriginDestinationOptions->children() as $OriginDestinationOption) {
                                            foreach ($OriginDestinationOption->children() as $flightData) {

                                                if (strcmp($flightData->getName(), 'FlightSegment') == 0) {
                                                    $availableData['provider_type'] = 'airblue';
                                                    $availableData['cabin_class'] = $cabinclass;
                                                    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                                                    $key = substr(str_shuffle($permitted_chars), 0, 10);
                                                    $availableData['key'] = $key;
                                                    $availableData['is_featured'] = 'true';
                                                    $availableData['segments'] = $this->listSegments($flightData, $from);
                                                }
                                            }
                                        }
                                    }
                                }
                                $pricingArray = array();

                                if (strcmp($flightSegments->getName(), 'AirItineraryPricingInfo') == 0) {
                                    $baseFarePrice = array();
                                    $TaxesPrice = array();
                                    $TotalFarePrice = array();

                                    foreach ($flightSegments->children() as $ItinTotalFare) {

                                        if (strcmp($ItinTotalFare->getName(), 'ItinTotalFare') == 0) {
                                            foreach ($ItinTotalFare->children() as $Pricing) {
                                                if (strcmp($Pricing->getName(), 'BaseFare') == 0) {
                                                    foreach ($Pricing->attributes() as $pkey => $baseFare) {
                                                        $baseFarePrice[$pkey] = (string) $baseFare;
                                                    }
                                                }

                                                if (strcmp($Pricing->getName(), 'Taxes') == 0) {

                                                    foreach ($Pricing->attributes() as $Taxkey => $Taxes) {
                                                        $TaxesPrice[$Taxkey] = (string) $Taxes;
                                                    }
                                                }
                                                if (strcmp($Pricing->getName(), 'TotalFare') == 0) {
                                                    foreach ($Pricing->attributes() as $TotalFarekey => $TotalFare) {
                                                        $TotalFarePrice[$TotalFarekey] = (string) $TotalFare;
                                                    }
                                                }

                                                $pricingArray['TotalPrice'] = $TotalFarePrice;
                                                $pricingArray['ApproximateBasePrice'] = $baseFarePrice;
                                                $pricingArray['Taxes'] = $TaxesPrice;
                                            }
                                            $setting = new Setting();
                                                $commsionValue = 0;
                                                $settingCode = $flightType == 'domestic'?"airblue-commission-domestic":"airblue-commission-international";
                                                $airblueSetting = $setting->getSettingByCode($settingCode);
                                                if ($airblueSetting && $airblueSetting->setting_value > 0) {
                                                    
                                                    $commsionValue = getCommissionValue($pricingArray['ApproximateBasePrice']['Amount'], $airblueSetting->setting_value);
                                                }
                                                
                                                $pricingArray['TotalPriceWithCommission'] = round($pricingArray['TotalPrice']['Amount'] + $commsionValue);
                                        }
                                        //$FareBreakdown = array();
                                        if (strcmp($ItinTotalFare->getName(), 'PTC_FareBreakdowns') == 0) {
                                            $PTC_data = $this->MakePTC_FareBD($ItinTotalFare);
                                            $availableData['PTC_FareBreakdowns'] = $PTC_data;
                                        }
                                    }
                                    $availableData['pricing_info'] = $pricingArray;
                                }
                            }
                            $flightsArray[] = $availableData;
                        }
                    }
                }
            }
            return array(
                'status' => 'true',
                'message' => 'successfull data',
                'data' => $flightsArray
            );
            //die();
        } catch (\Exception $e) {
            return $return = array(
                'status' => '4000',
                'message' => $e->getMessage()
            );
        }
    }

    public function MakePTC_FareBD($ItinTotalFare) {
        $bd = array();
        $FareBreakdown = array();
        foreach ($ItinTotalFare->children() as $PTC_FareBreakdown) {
            $count = 0;
            $FareInfoArray2 = [];
            $countar = array();
            $FareBreakdown = [];
            foreach ($PTC_FareBreakdown->children() as $BDKey => $passengerFareBD) {


                //Getting PassengerTypeQuantity
                if (strcmp($passengerFareBD->getName(), 'PassengerTypeQuantity') == 0) {
                    foreach ($passengerFareBD->attributes() as $PTQKey => $PTQ) {
                        $PassengerTypeQuantity[$PTQKey] = (string) $PTQ;
                    }
                    $FareBreakdown['PassengerTypeQuantity'] = $PassengerTypeQuantity;
                }

                $FareBreakdownInternal = [];
                //Getting PassengerFare
                if (strcmp($passengerFareBD->getName(), 'PassengerFare') == 0) {

                    foreach ($passengerFareBD->children() as $kk => $FareInfoData) {
                        $passengerBaseFare = array();
                        if (strcmp($FareInfoData->getName(), 'BaseFare') == 0) {

                            foreach ($FareInfoData->attributes() as $bfKey => $bf) {
                                $passengerBaseFare[$bfKey] = (string) $bf;
                            }
                            $FareBreakdownInternal['BaseFares'] = $passengerBaseFare;
                        }


                        //$passengerTaxes = [];

                        if ($kk == 'Taxes') {

                            foreach ($FareInfoData->attributes() as $pt) {
                                $passengerTaxes['totalTax'] = (string) $pt;
                            }

                            foreach ($FareInfoData->children() as $TaxChild) {
                                $passengerIndividualTaxes = array();
                                if (strcmp($TaxChild->getName(), 'Tax') == 0) {
                                    foreach ($TaxChild->attributes() as $pt1key => $pt1) {
                                        $passengerIndividualTaxes[$pt1key] = (string) $pt1;
                                    }
                                }
                                $passengerTaxes['Tax'][] = $passengerIndividualTaxes;
                            }
                            $FareBreakdownInternal['Taxes'] = $passengerTaxes;
                            //add Taxes here
                        }



                        $passengerFees = array();
                        if (strcmp($FareInfoData->getName(), 'Fees') == 0) {

                            foreach ($FareInfoData->attributes() as $fee) {
                                $passengerFees['totalFees'] = (string) $fee;
                            }

                            foreach ($FareInfoData->children() as $FeeChild) {
                                $passengerIndividualFee = array();
                                if (strcmp($FeeChild->getName(), 'Fee') == 0) {
                                    foreach ($FeeChild->attributes() as $fee1key => $fee1) {
                                        $passengerIndividualFee[$fee1key] = (string) $fee1;
                                    }
                                }
                                $passengerFees['Fee'][] = $passengerIndividualFee;
                            }
                            $FareBreakdownInternal['Fees'] = $passengerFees;
                        }


                        $TotalFarePTC = array();
                        if (strcmp($FareInfoData->getName(), 'TotalFare') == 0) {
                            foreach ($FareInfoData->attributes() as $TKey => $TFare) {
                                $TotalFarePTC[$TKey] = (string) $TFare;
                            }
                            $FareBreakdownInternal['TotalFare'] = $TotalFarePTC;
                        }
                        //print_r($FareBreakdown); die();
                    }

                    $FareBreakdown['PassengerFare'] = $FareBreakdownInternal;
                }


                //Getting FareInfo
                if (strcmp($passengerFareBD->getName(), 'FareInfo') == 0) {

                    foreach ($passengerFareBD->children() as $kk => $FareInfoData) {
                        if (strcmp($FareInfoData->getName(), 'DepartureDate') == 0) {
                            $FareInfoArray['DepartureDate'] = (string) $FareInfoData;
                        }

                        if (strcmp($FareInfoData->getName(), 'DepartureAirport') == 0) {
                            foreach ($FareInfoData->attributes() as $DepartureAirport) {
                                $FareInfoArray['DepartureAirport'] = (string) $DepartureAirport;
                            }
                        }

                        if (strcmp($FareInfoData->getName(), 'ArrivalAirport') == 0) {
                            foreach ($FareInfoData->attributes() as $ArrivalAirport) {
                                $FareInfoArray['ArrivalAirport'] = (string) $ArrivalAirport;
                            }
                        }

                        if (strcmp($FareInfoData->getName(), 'FareInfo') == 0) {
                            foreach ($FareInfoData->attributes() as $FareInfo) {
                                $FareInfoArray['FareInfo'] = (string) $FareInfo;
                            }
                        }

                        if (strcmp($FareInfoData->getName(), 'PassengerFare') == 0) {

                            foreach ($FareInfoData->children() as $FareInfoDataInternal) {
                                $passengerBaseFare = array();
                                if (strcmp($FareInfoDataInternal->getName(), 'BaseFare') == 0) {

                                    foreach ($FareInfoDataInternal->attributes() as $bfKey => $bf) {
                                        $passengerBaseFare[$bfKey] = (string) $bf;
                                    }
                                    $FareBreakdownFI['BaseFares'] = $passengerBaseFare;
                                }



                                $passengerTaxes = array();
                                if (strcmp($FareInfoDataInternal->getName(), 'Taxes') == 0) {

                                    foreach ($FareInfoDataInternal->attributes() as $pt) {
                                        $passengerTaxes['totalTax'] = (string) $pt;
                                    }

                                    foreach ($FareInfoDataInternal->children() as $TaxChild) {
                                        $passengerIndividualTaxes = array();
                                        if (strcmp($TaxChild->getName(), 'Tax') == 0) {
                                            foreach ($TaxChild->attributes() as $pt1key => $pt1) {
                                                $passengerIndividualTaxes[$pt1key] = (string) $pt1;
                                            }
                                        }
                                        $passengerTaxes['Tax'][] = $passengerIndividualTaxes;
                                    }
                                    $FareBreakdownFI['Taxes'] = $passengerTaxes;
                                    //add Taxes here
                                }

                                $TotalFarePTCIn = array();
                                if (strcmp($FareInfoDataInternal->getName(), 'TotalFare') == 0) {
                                    foreach ($FareInfoDataInternal->attributes() as $TKey => $TFare) {
                                        $TotalFarePTCIn[$TKey] = (string) $TFare;
                                    }
                                    $FareBreakdownFI['TotalFare'] = $TotalFarePTCIn;
                                }
                            }
                            $FareInfoArray['PassengerFare'] = $FareBreakdownFI;
                            $FareBreakdown['FareInfo'] = $FareInfoArray;
                        }
                    }
                }

                //Getting FareInfo2
                if ($count == 3 && $BDKey == "FareInfo") {
                    foreach ($passengerFareBD->children() as $kk => $FareInfoData2) {
                        if (strcmp($FareInfoData2->getName(), 'DepartureDate') == 0) {
                            $FareInfoArray2['DepartureDate'] = (string) $FareInfoData2;
                        }
                        if (strcmp($FareInfoData2->getName(), 'DepartureAirport') == 0) {
                            foreach ($FareInfoData2->attributes() as $DepartureAirport) {
                                $FareInfoArray2['DepartureAirport'] = (string) $DepartureAirport;
                            }
                        }
                        if (strcmp($FareInfoData2->getName(), 'ArrivalAirport') == 0) {
                            foreach ($FareInfoData2->attributes() as $ArrivalAirport) {
                                $FareInfoArray2['ArrivalAirport'] = (string) $ArrivalAirport;
                            }
                        }

                        if (strcmp($FareInfoData2->getName(), 'RuleInfo') == 0) {
                            $FareInfoArray2['RuleInfo'] = $this->ArrangeRuleInfo($FareInfoData2);
                        }

                        if (strcmp($FareInfoData2->getName(), 'PassengerFare') == 0) {
                            foreach ($FareInfoData2->children() as $FareInfoDataInternal2) {
                                $passengerBaseFare = array();


                                if (strcmp($FareInfoDataInternal2->getName(), 'FareBaggageAllowance') == 0) {
                                    foreach ($FareInfoDataInternal2->attributes() as $FBAKey => $FBA) {
                                        $passengerBaseFare[$FBAKey] = (string) $FBA;
                                    }
                                    $FareBreakdownFareInfo2['FareBaggageAllowance'] = $passengerBaseFare;
                                }
                            }
                            $FareInfoArray2['PassengerFare'] = $FareBreakdownFareInfo2;
                        }
                        $FareBreakdown['FareInfo2'] = $FareInfoArray2;
                    }
                }

                $count++;
            }


            $bd[] = $FareBreakdown;
        }

        return $bd;
    }

    public function listSegments($segmentsData, $from) {
        $segment = array();
        if (!empty($segmentsData)) {



            foreach ($segmentsData->children() as $otherData) {
                if (strcmp($otherData->getName(), 'OperatingAirline') == 0) {
                    foreach ($otherData->attributes() as $OperatingAirline) {
                        $segment['Carrier'] = (string) $OperatingAirline;
                        $segment['airline_logo'] = url('/') . '/public/airline_logo/' . $segment['Carrier'] . '.png';
                        $segment['airline_name'] = $this->getAirline($segment['Carrier']);
                    }
                }

                if (strcmp($otherData->getName(), 'DepartureAirport') == 0) {
//                    print_r($otherData); die();
                    foreach ($otherData->attributes() as $dakey => $DepartureAirport) {
                        //print_r($DepartureAirport); die();
                        if ($dakey == "LocationCode") {
                            $segment['Origin'] = (string) $DepartureAirport;
                            if ($from != "") {
                                if ($from == $segment['Origin']) {
                                    $segment['boundType'] = 'outbound';
                                } else {
                                    $segment['boundType'] = 'inbound';
                                }
                            }
                            $segment['origin_city_name'] = $this->getCity($segment['Origin']);
                        }
                    }
                }

                if (strcmp($otherData->getName(), 'ArrivalAirport') == 0) {
                    foreach ($otherData->attributes() as $arKey => $ArrivalAirport) {
                        if ($arKey == "LocationCode") {
                            $segment['Destination'] = (string) $ArrivalAirport;
                            $segment['Destination_city_name'] = $this->getCity($segment['Destination']);
                        }
                    }
                }
                if (strcmp($otherData->getName(), 'Equipment') == 0) {
                    foreach ($otherData->attributes() as $Equipment) {
                        $segment['Equipment'] = (string) $Equipment;
                    }
                }
                if (strcmp($otherData->getName(), 'MarketingAirline') == 0) {
                    foreach ($otherData->attributes() as $MarketingAirline) {
                        $segment['MarketingAirline'] = (string) $MarketingAirline;
                    }
                }
            }
            foreach ($segmentsData->attributes() as $key => $seg) {
                $segment[$key] = (string) $seg;
            }
        }
        return $segment;
    }

    public function ArrangeRuleInfo($FareInfoData2) {

        foreach ($FareInfoData2->children() as $FareInfoData) {
            //$ChargesRules = [];
            $voluntaryMainArray = array();
            $voluntaryRefundMainArray = array();
            if (strcmp($FareInfoData->getName(), 'ChargesRules') == 0) {
                foreach ($FareInfoData->children() as $key => $RuleInfo) {

                    if (strcmp($RuleInfo->getName(), 'VoluntaryChanges') == 0) {
                        foreach ($RuleInfo->children() as $voluntaryChanges) {
                            foreach ($voluntaryChanges->attributes() as $voluntryKey => $voluntryrules) {
                                $voluntaryArray[$voluntryKey] = (string) $voluntryrules;
                            }
                            $voluntaryMainArray[] = $voluntaryArray;
                        }
                    }
                    //print_r($voluntaryMainArray); die();
                    $ChargesRules['VoluntaryChanges'] = $voluntaryMainArray;

                    if (strcmp($RuleInfo->getName(), 'VoluntaryRefunds') == 0) {
                        foreach ($RuleInfo->children() as $VoluntaryRefunds) {
                            foreach ($VoluntaryRefunds->attributes() as $voluntryRefundKey => $voluntryRefundrules) {
                                $voluntaryRefundArray[$voluntryRefundKey] = (string) $voluntryRefundrules;
                            }
                            $voluntaryRefundMainArray[] = $voluntaryRefundArray;
                        }
                    }
                    $ChargesRules['VoluntaryRefunds'] = $voluntaryRefundMainArray;
                }
            }
        }
        return ($ChargesRules);
    }

    //Booking section of Airblue...
    public function booking_request($data) {
        $segments = isset($data['segmentsData']['segments'][0]) ? $data['segmentsData']['segments'] : array($data['segmentsData']['segments']);
        $PTC_FareBreakdown = $data['segmentsData']['PTC_FareBreakdowns'];
//        print_r($PTC_FareBreakdown); die();
        $travelerData = $data['booking_detail'];
        $message = '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
	<Header/>
	<Body>
		<AirBook xmlns="http://zapways.com/air/ota/2.0">
			' . $this->credentialBooking . '
				<AirItinerary>
					<OriginDestinationOptions>';
        if (isset($segments) && !empty($segments)) {
            foreach ($segments as $segment) {
                $message .= '<OriginDestinationOption RPH="0-0">
							<FlightSegment DepartureDateTime="' . $segment['DepartureDateTime'] . '" ArrivalDateTime="' . $segment['ArrivalDateTime'] . '"
								StopQuantity="' . $segment['StopQuantity'] . '" RPH="' . $segment['RPH'] . '" FlightNumber="' . $segment['FlightNumber'] . '" ResBookDesigCode="' . $segment['ResBookDesigCode'] . '" Status="' . $segment['Status'] . '">
								<DepartureAirport LocationCode="' . $segment['Origin'] . '"/>
								<ArrivalAirport LocationCode="' . $segment['Destination'] . '"/>
								<OperatingAirline Code="' . $segment['Carrier'] . '"/>
								<Equipment AirEquipType="' . $segment['Equipment'] . '"/>
								<MarketingAirline Code="' . $segment['MarketingAirline'] . '"/>
							</FlightSegment>
						</OriginDestinationOption>';
            }
        }
        $message .= '</OriginDestinationOptions>
				</AirItinerary>
				<PriceInfo>
					<PTC_FareBreakdowns>';

        foreach ($PTC_FareBreakdown as $PTC_FareBreakdowns) {
            //print_r($PTC_FareBreakdowns); die();
            $message .= '<PTC_FareBreakdown>
							<PassengerTypeQuantity Code="' . $PTC_FareBreakdowns['PassengerTypeQuantity']['Code'] . '" Quantity="' . $PTC_FareBreakdowns['PassengerTypeQuantity']['Quantity'] . '"/>
							<PassengerFare>
								<BaseFare CurrencyCode="' . $PTC_FareBreakdowns['PassengerFare']['BaseFares']['CurrencyCode'] . '" Amount="' . $PTC_FareBreakdowns['PassengerFare']['BaseFares']['Amount'] . '" />
								';
            if (isset($PTC_FareBreakdowns['PassengerFare']['Taxes'])) {
                $message .= '<Taxes Amount="' . $PTC_FareBreakdowns['PassengerFare']['Taxes']['totalTax'] . '">';
                foreach ($PTC_FareBreakdowns['PassengerFare']['Taxes']['Tax'] as $tax) {
                    $message .= '<Tax TaxCode="' . $tax['TaxCode'] . '" CurrencyCode="' . $tax['CurrencyCode'] . '" Amount="' . $tax['Amount'] . '" />';
                }


                $message .= '</Taxes>';
            }
            if (isset($PTC_FareBreakdowns['PassengerFare']['Fees']) && !empty($PTC_FareBreakdowns['PassengerFare']['Fees'])) {
                $message .= '<Fees Amount = "' . $PTC_FareBreakdowns['PassengerFare']['Fees']['totalFees'] . '">';

                if (isset($PTC_FareBreakdowns['PassengerFare']['Fees']['Fee'])) {
                    foreach ($PTC_FareBreakdowns['PassengerFare']['Fees']['Fee'] as $fee) {
                        $message .= '<Fee FeeCode = "' . $fee['FeeCode'] . '" CurrencyCode = "' . $fee['CurrencyCode'] . '" Amount = "' . $fee['Amount'] . '" />';
                    }
                }
                $message .= '</Fees>';
            }
            $message .= '
                                                                        <TotalFare CurrencyCode="' . $PTC_FareBreakdowns['PassengerFare']['TotalFare']['CurrencyCode'] . '" Amount="' . $PTC_FareBreakdowns['PassengerFare']['TotalFare']['Amount'] . '"/>                                                            
                                                                        </PassengerFare>
							<FareInfo>
								<DepartureDate>' . $PTC_FareBreakdowns['FareInfo']['DepartureDate'] . '</DepartureDate>
								<DepartureAirport LocationCode="' . $PTC_FareBreakdowns['FareInfo']['DepartureAirport'] . '"/>
								<ArrivalAirport LocationCode="' . $PTC_FareBreakdowns['FareInfo']['ArrivalAirport'] . '"/>';
            if (isset($PTC_FareBreakdowns['FareInfo']['FareInfo'])) {
                $message .= '<FareInfo FareBasisCode="' . $PTC_FareBreakdowns['FareInfo']['FareInfo'] . '"/>';
            } else {
                $message .= '<FareInfo FareBasisCode=""/>';
            }
            $message .= '<PassengerFare>
									<BaseFare CurrencyCode="' . $PTC_FareBreakdowns['FareInfo']['PassengerFare']['BaseFares']['CurrencyCode'] . '" Amount="' . $PTC_FareBreakdowns['FareInfo']['PassengerFare']['BaseFares']['Amount'] . '" />';
            if (isset($PTC_FareBreakdowns['FareInfo']['PassengerFare']['Taxes']) && isset($PTC_FareBreakdowns['PassengerFare']['Taxes'])) {
                $message .= '<Taxes Amount="' . $PTC_FareBreakdowns['FareInfo']['PassengerFare']['Taxes']['totalTax'] . '">';
                foreach ($PTC_FareBreakdowns['FareInfo']['PassengerFare']['Taxes']['Tax'] as $taxInfo) {
                    $message .= '<Tax TaxCode="' . $taxInfo['TaxCode'] . '" CurrencyCode="' . $taxInfo['CurrencyCode'] . '" Amount="' . $taxInfo['Amount'] . '" />';
                }
                $message .= '</Taxes>';
            }
            if (isset($PTC_FareBreakdowns['PassengerFare']['Fees']) && !empty($PTC_FareBreakdowns['PassengerFare']['Fees'])) {
                $message .= '<Fees Amount = "' . $PTC_FareBreakdowns['PassengerFare']['Fees']['totalFees'] . '">';

                if (isset($PTC_FareBreakdowns['PassengerFare']['Fees']['Fee'])) {
                    foreach ($PTC_FareBreakdowns['PassengerFare']['Fees']['Fee'] as $fee) {
                        $message .= '<Fee FeeCode = "' . $fee['FeeCode'] . '" CurrencyCode = "' . $fee['CurrencyCode'] . '" Amount = "' . $fee['Amount'] . '" />';
                    }
                }
                $message .= '</Fees>';
            }

            $message .= '<TotalFare CurrencyCode="' . $PTC_FareBreakdowns['FareInfo']['PassengerFare']['TotalFare']['CurrencyCode'] . '" Amount="' . $PTC_FareBreakdowns['FareInfo']['PassengerFare']['TotalFare']['Amount'] . '" />
								</PassengerFare>
							</FareInfo>';
            if (isset($PTC_FareBreakdowns['FareInfo2']) && !empty($PTC_FareBreakdowns['FareInfo2'])) {
                $message .= '<FareInfo>
								<DepartureDate>' . $PTC_FareBreakdowns['FareInfo2']['DepartureDate'] . '</DepartureDate>';
                if (isset($PTC_FareBreakdowns['FareInfo2']['RuleInfo'])) {
                    $message .= '<RuleInfo>
									<ChargesRules>
										<VoluntaryChanges>';
                    foreach ($PTC_FareBreakdowns['FareInfo2']['RuleInfo']['VoluntaryChanges'] as $vc) {
                        $Vchours = str_replace('<', '&lt;', $vc['HoursBeforeDeparture']);
                        $Vchours = str_replace('>', '&gt;', $Vchours);

                        $message .= '<Penalty HoursBeforeDeparture="' . $Vchours . '"  Amount="' . $vc['Amount'] . '"/>';
                    }
//                                                                     print_r($PTC_FareBreakdowns['FareInfo2']['RuleInfo']); die();                                         
                    $message .= '</VoluntaryChanges>
                                                                                                                                                 <VoluntaryRefunds>';
                    foreach ($PTC_FareBreakdowns['FareInfo2']['RuleInfo']['VoluntaryRefunds'] as $vr) {
                        $Vchours = str_replace('<', '&lt;', $vr['HoursBeforeDeparture']);
                        $Vchours = str_replace('>', '&gt;', $Vchours);

                        $message .= '<Penalty HoursBeforeDeparture="' . $Vchours . '"  Amount="' . $vr['Amount'] . '"/>';
                    }

                    $message .= '</VoluntaryRefunds>
									</ChargesRules>
								</RuleInfo>';
                }
                $message .= '<DepartureAirport LocationCode="' . $PTC_FareBreakdowns['FareInfo2']['DepartureAirport'] . '"/>
								<ArrivalAirport LocationCode="' . $PTC_FareBreakdowns['FareInfo2']['ArrivalAirport'] . '"/>
								<PassengerFare>';
                if (isset($PTC_FareBreakdowns['FareInfo2']['PassengerFare'])) {
                    $message .= '<FareBaggageAllowance UnitOfMeasureQuantity="' . $PTC_FareBreakdowns['FareInfo2']['PassengerFare']['FareBaggageAllowance']['UnitOfMeasureQuantity'] . '" UnitOfMeasure="' . $PTC_FareBreakdowns['FareInfo2']['PassengerFare']['FareBaggageAllowance']['UnitOfMeasure'] . '"/>';
                }
                $message .= '</PassengerFare>
							</FareInfo>';
            }
            $message .= '</PTC_FareBreakdown>';
        }
        $message .= '</PTC_FareBreakdowns>
				</PriceInfo>
				<TravelerInfo>';
        foreach ($travelerData as $traveler) {
            $dobFull = $traveler['dob_day'] . '-' . $traveler["dob_month"] . '-' . $traveler['dob_year'];
            $dob = date('Y-m-d', strtotime($dobFull));
            $title = $traveler["title"] == "mr" ? "MR" : "MRS";
            $passenger_type = $traveler['passenger_type'] == 'CNN' ? 'CHD' : $traveler['passenger_type'];
            $message .= '<AirTraveler BirthDate="' . $dob . '">
						<PersonName>
							<GivenName>' . strtoupper($traveler["firstName"]) . '</GivenName>
							<Surname>' . strtoupper($traveler["lastName"]) . '</Surname>';
            if ($passenger_type != 'INF') {
                if ($passenger_type == 'CHD' && $title = 'MR') {
                    $title = 'MSTR';
                }
                $message .= '<NameTitle>' . $title . '</NameTitle>';
            }
            $message .= '</PersonName>
						<Telephone PhoneLocationType="10" CountryAccessCode="91" PhoneNumber="2223331111" />
						<Email>support@zapways.com</Email>';
            if (isset($traveler['passport_number']) && $traveler['passport_number'] != "") {
                $message .= '<Document DocID="' . $traveler['passport_number'] . '" DocType="2" DocIssueCountry="' . $traveler['nationality'] . '" DocHolderNationality="' . $traveler["nationality"] . '"/>';
            } else if (isset($traveler['cnic'])) {
                $message .= '<Document DocID="' . $traveler['cnic'] . '" DocType="5" DocIssueCountry="' . $traveler['nationality'] . '"/>';
            }
            $message .= '<PassengerTypeQuantity Code="' . $passenger_type . '" Quantity="1" />
						<TravelerRefNumber RPH="1" />
					</AirTraveler>';
        }
        $message .= '</TravelerInfo>
			</airBookRQ>
		 </AirBook>
	     </Body>
        </Envelope>';

        $this->message = $this->prettyPrint($message);
        $AirBlueBookingData = $this->curl_action();
        $return = $this->prettyPrint($AirBlueBookingData);
        return $return;
    }

    public function Booking_Data($bookingAirblueXml) {
        try {
            $flights = $bookingAirblueXml;
            //print_r($flights); die();
            $flightsArray = array();
            $availableData = array();
            $xml = simplexml_load_String($flights, null, null, 'SOAP', true);
            $Results = $xml->children('soap', true);


            foreach ($Results->children() as $message) {
                foreach ($message->children() as $AirLowFareSearchResult) {

                    foreach ($AirLowFareSearchResult->children() as $key => $war) {

                        if ($key == 'Errors') {
                            foreach ($war->children() as $warning) {
                                return array(
                                    'status' => 'false',
                                    'message' => (string) $warning
                                );
                            }
                        }
                    }
                }
            }



            foreach ($Results->children() as $message) {
                foreach ($message as $AirBookResult) {
//                    print_r($AirBookResult); die();
                    foreach ($AirBookResult->children() as $AirReservation) {
                        if (strcmp($AirReservation->getName(), 'AirReservation') == 0) {
                            foreach ($AirReservation->children() as $AirItinerary) {

                                //Getting Segments Data
                                if (strcmp($AirItinerary->getName(), 'AirItinerary') == 0) {
                                    foreach ($AirItinerary->children() as $OriginDestinationOptions) {
                                        foreach ($OriginDestinationOptions->children() as $OriginDestinationOption) {
                                            foreach ($OriginDestinationOption->children() as $flightData) {
                                                if (strcmp($flightData->getName(), 'FlightSegment') == 0) {
                                                    $availableData['provider_type'] = 'airblue';
                                                    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                                                    $key = substr(str_shuffle($permitted_chars), 0, 10);
                                                    $availableData['key'] = $key;
                                                    $availableData['is_featured'] = 'true';
                                                    $from = "";
                                                    $availableData['segments'][] = $this->listSegments($flightData, $from);
                                                }
                                            }
                                        }
                                    }
                                }

                                //Getting Pricing and PTC_Breakdown Data
                                if (strcmp($AirItinerary->getName(), 'PriceInfo') == 0) {
                                    foreach ($AirItinerary->children() as $ItinTotalFare) {

                                        if (strcmp($ItinTotalFare->getName(), 'ItinTotalFare') == 0) {
                                            foreach ($ItinTotalFare->children() as $Pricing) {

                                                foreach ($Pricing->attributes() as $TotalFarekey => $TotalFare) {
                                                    $TotalFarePrice[$TotalFarekey] = (string) $TotalFare;
                                                }
                                                $pricingArray['TotalFare'] = $TotalFarePrice;
                                            }
                                        }


                                        if (strcmp($ItinTotalFare->getName(), 'PTC_FareBreakdowns') == 0) {
                                            $PTC_data = $this->MakePTC_FareBD($ItinTotalFare);
                                            $availableData['PTC_FareBreakdowns'] = $PTC_data;
                                        }
                                    }
                                    $availableData['pricing_info'] = $pricingArray;
                                    $flightData = $availableData;
                                }


                                //$personArray = array();
                                if (strcmp($AirItinerary->getName(), 'TravelerInfo') == 0) {
                                    foreach ($AirItinerary->children() as $TravelerInfo) {

                                        if (strcmp($TravelerInfo->getName(), 'AirTraveler') == 0) {

                                            foreach ($TravelerInfo->children() as $PersonData) {
                                                if (strcmp($PersonData->getName(), 'PersonName') == 0) {
                                                    foreach ($PersonData->children() as $keyP => $passDataHere) {
                                                        if ($keyP == 'NameTitle') {
                                                            $personArr['title'] = (string) $passDataHere;
                                                        }
                                                        if ($keyP == 'GivenName') {
                                                            $personArr['firstName'] = (string) $passDataHere;
                                                        }
                                                        if ($keyP == 'Surname') {
                                                            $personArr['lastName'] = (string) $passDataHere;
                                                        }
                                                    }
                                                }

                                                if (strcmp($PersonData->getName(), 'Document') == 0) {
                                                    foreach ($PersonData->attributes() as $docKey => $doc) {
                                                        if ($docKey == 'DocID') {
                                                            $personArr['passport_number'] = (string) $doc;
                                                        }
                                                        if ($docKey == 'DocHolderNationality') {
                                                            $personArr['nationality'] = (string) $doc;
                                                        }
                                                        if ($docKey == 'DocType') {
                                                            $personArr['document_type'] = (string) $doc;
                                                        }
                                                    }
                                                }
                                            }
                                            if (isset($personArr['document_type']) && $personArr['document_type'] == '5') {
                                                $cnic = $personArr['passport_number'];
                                                $personArr['cnic'] = $cnic;
                                                unset($personArr['passport_number']);
                                                //unset($personArr['document_type'] ) ;
                                            }
                                            unset($personArr['document_type']);
                                            $personArray[] = $personArr;
                                        }
                                    }

                                    $flightData['passenger_detail'] = $personArray;
                                }


                                //Getting Ticketing Data
                                if (strcmp($AirItinerary->getName(), 'Ticketing') == 0) {
                                    foreach ($AirItinerary->attributes() as $ticketKey => $ticket) {
                                        $ticketsArray[$ticketKey] = (string) $ticket;
                                    }
                                    $flightData['ticketing'][] = $ticketsArray;
                                }


                                //Getting Booking Reference Id
                                if (strcmp($AirItinerary->getName(), 'BookingReferenceID') == 0) {
//                                    foreach($AirItinerary->children() as $bookRefChild){
//                                        Print_r($bookRefChild); die();
//                                    }
                                    $BookingRefIDArray = array();
                                    foreach ($AirItinerary->attributes() as $bookKey => $bookId) {
                                        $BookingRefIDArray[$bookKey] = (string) $bookId;
                                    }
                                    $flightData['BookingReferenceID'] = $BookingRefIDArray;
                                }
                            }
                        }
                    }
                }
            }
            //print_r($flightData); die('dddfaisal');
            return array(
                'status' => 'true',
                'message' => 'successfull data',
                'data' => $flightData
            );
            //die();
        } catch (\Exception $e) {
            return $return = array(
                'status' => '4000',
                'message' => $e->getMessage()
            );
        }
    }

    public function getBookingbyPNR($pnr) {
        $request = '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
                    <Header/>
                        <Body>
                           <Read xmlns="http://zapways.com/air/ota/2.0">
                              ' . $this->readCredential . '
                              <UniqueID ID="' . $pnr . '" ></UniqueID>
                               </readRQ>
                           </Read>
                        </Body>
                    </Envelope>';
        $this->message = $this->prettyPrint($request);
        //print_r($this->message); die();
        $AirBlueBookingData = $this->curl_action();
        $return = $this->prettyPrint($AirBlueBookingData);
        return $return;
    }

    public function ticket_generate($pnr, $instance, $totalAmount) {
        $request = '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
                    <Header/>
                        <Body>
                           <AirDemandTicket xmlns="http://zapways.com/air/ota/2.0">
                              ' . $this->ticketGenerate . '
                              <DemandTicketDetail>
                                <BookingReferenceID Instance="' . $instance . '" ID="' . $pnr . '"></BookingReferenceID>
                                <PaymentInfo PaymentType="Cash" CurrencyCode="PKR" Amount="' . $totalAmount . '"></PaymentInfo>
                               </DemandTicketDetail>
                                 </airDemandTicketRQ>
                           </AirDemandTicket>
                        </Body>
                    </Envelope>';
        $this->message = $this->prettyPrint($request);
        // print_r($this->message); die();
        $AirBlueBookingData = $this->curl_action();
        $return = $this->prettyPrint($AirBlueBookingData);
        return $return;
    }

    public function getTicketArray($ticketXml) {
        $xml = simplexml_load_String($ticketXml, null, null, 'SOAP', true);
        $Results = $xml->children('soap', true);

        foreach ($Results->children() as $message) {
            foreach ($message->children() as $AirLowFareSearchResult) {

                foreach ($AirLowFareSearchResult->children() as $key => $war) {

                    if ($key == 'Errors') {
                        foreach ($war->children() as $warning) {
                            return array(
                                'status' => 'false',
                                'message' => (string) $warning
                            );
                        }
                    }
                }
            }
        }

        $passengerDetailArray = array();
        foreach ($Results->children() as $ticketData) {
            //print_r($ticketData); die();
            foreach ($ticketData as $ticketDetail) {
                foreach ($ticketDetail->children() as $PassengerDetail) {
                    if (strcmp($PassengerDetail->getName(), 'TicketItemInfo') == 0) {

                        foreach ($PassengerDetail->children() as $passengerName) {
//                          foreach($passengerName->attributes() as $passType){
//                              $passengerDetail['passenger_type'] = (string) $passType;
//                          }
//                          foreach($passengerName->children() as $nameSegment){
//                             if (strcmp($nameSegment->getName(), 'NameTitle') == 0) {
//                                 $passengerDetail['title'] = ucfirst(strtolower((string) $nameSegment));
//                             }
//                             if (strcmp($nameSegment->getName(), 'GivenName') == 0) {
//                                 $passengerDetail['firstName'] = ucfirst(strtolower((string) $nameSegment));
//                             }
//                             if (strcmp($nameSegment->getName(), 'Surname') == 0) {
//                                 $passengerDetail['lastName'] = ucfirst(strtolower((string) $nameSegment));
//                             }
//                             
//                          }
                        }




                        foreach ($PassengerDetail->attributes() as $passengerTicketData) {
                            $passengerDetail['TicketDocumentNbr'] = (string) $passengerTicketData;
                        }
                        $passengerDetailArray[] = $passengerDetail;
                    }
                }
            }
        }
        return array(
            'status' => 'true',
            'data' => $passengerDetailArray
        );
    }

}
