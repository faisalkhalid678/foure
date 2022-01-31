<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Ft_booking;

class PaymentController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function checkValidation($request, $rules) {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return $errors;
        } else {
            return False;
        }
    }

    public function index(Request $request) {
        $rules = [
            'pnr' => 'required',
            'account_number' => 'required',
            'account_type' => 'required',
            'total_amount' => 'required',
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


        $booking = new Ft_booking();

        $pnrExist = $booking->where('pnr', $request->pnr)->first();
        if ($pnrExist !== null ) {
            if($pnrExist->payment_status == "Completed"){
               $return = array(
                    'status' => '400',
                    'message' => 'Payment already Received',
                    'data' => new \stdClass()
                ); 
               return response()->json($return);
            }
            $updateData = array(
                'payment_status' => 'Completed'
            );
            $updatenow = $booking->where('id', $pnrExist->id)->update($updateData);
            if ($updatenow) {
                $return = array(
                    'status' => '200',
                    'message' => 'Payment Received Successfully',
                    'data' => new \stdClass()
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => 'Payment Received Successfully',
                    'data' => new \stdClass()
                );
            }
        } else {
            $return = array(
                'status' => '400',
                'message' => 'PNR Does Not Exist',
                'data' => new \stdClass()
            );
        }

        return response()->json($return);
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
