<?php

namespace App\Http\Controllers\TravelAirSial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\TravelAirSial;
use App\Helpers\TravelPort;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TravelAirSialController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //Login API for AirSial
    //One way search filter function for AirBlue
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
        $flightType = $this->checkFlightType($request->from, $request->to);
        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
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


        //try {
            $airsial = new TravelAirSial();
            $oneway_resp = $airsial->search_request('one_way',$request->input());
            //print_r($oneway_resp); die();
            $passengerQtyInfo = array(
                'adult' => $request->adult,
                'infant' => $request->infant,
                'children' => $request->children,
            );
            $search_data = $airsial->OutputAirSearch($oneway_resp,$passengerQtyInfo);
            //print_r($search_data); die();
            if ($search_data['status'] == 'true') {
                $data = $search_data['data'];
                $dataObj['api_type'] = 'one_way_trip';
                $dataObj['flight_type'] = $flightType;
                $obj = new \stdClass();
                $dataObj1 = $data !== 'false' ? ($data) : $obj;
                $dataObj['flights'] = $dataObj1;
                $return = array(
                    'status' => '200',
                    'message' => 'One way search list AirSial',
                    'result' => $dataObj
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => $search_data['message'],
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
    
    
    public function round_trip_filter(Request $request) {

        //Form Validation Rules...
        $rules = [
            'from_date' => 'required',
            'to_date' => 'required',
            'from' => 'required',
            'to' => 'required',
            'adult' => 'required',
            'infant' => 'required',
            'children' => 'required',
        ];
        $flightType = $this->checkFlightType($request->from, $request->to);
        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
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


        try {
            $airsial = new TravelAirSial();
            $oneway_resp = $airsial->search_request('round',$request->input());
            $passengerQtyInfo = array(
                'adult' => $request->adult,
                'ifant' => $request->infant,
                'childrent' => $request->children,
            );
            $search_data = $airsial->OutputAirSearch($oneway_resp,$passengerQtyInfo);
            //print_r($search_data); die();
            if ($search_data['status'] == 'true') {
                $data = $search_data['data'];
                $dataObj['api_type'] = 'round_trip';
                $dataObj['flight_type'] = $flightType;
                $obj = new \stdClass();
                $dataObj1 = $data !== 'false' ? ($data) : $obj;
                $dataObj['flights'] = $dataObj1;
                $return = array(
                    'status' => '200',
                    'message' => 'Round Trip search list AirSial',
                    'result' => $dataObj
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => $search_data['message'],
                    'result' => new \stdClass
                );
            }
        } catch (\Exception $e) {
            $return = array(
                'status' => '400',
                'message' => $e->getMessage(),
                'result' => new \stdClass
            );
        }

        return response()->json($return);
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

    public function index() {
        //
    }

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
