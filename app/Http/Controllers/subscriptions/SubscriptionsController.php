<?php

namespace App\Http\Controllers\subscriptions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ft_subscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class SubscriptionsController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $rules = [
            'email' => 'required|email',
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

        $subscribe = new Ft_subscription();
        $alreadySubscribed = $subscribe->where('email', $request->email)->first();
        if (!$alreadySubscribed) {
            $subscribe->email = $request->email;
            $subscribe->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $subscribed = $subscribe->save();
            if ($subscribed) {
                $return = array(
                    'status' => '200',
                    'message' => 'User Subscribed Successfully.',
                    'data' => new \stdClass()
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => 'Unable to Subscribe. There are some issues!',
                    'data' => new \stdClass()
                );
            }
        } else {
            $return = array(
                'status' => '200',
                'message' => 'User Already Subscribed.',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

    public function sendemail() {
        $subject = 'Foure Booking';
        $toEmail = 'backenddeveloper0022@gmail.com';
        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: <info@foureflights.com>' . "\r\n";
        $message = view("mail");
        
        mail($toEmail, $subject, $message,$headers);

        die('email sent yar');
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
