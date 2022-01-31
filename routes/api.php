<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware([])->group(function(){

Route::post('/converttojson','Converttojson@index');
Route::get('/one-way-search','App\Http\Controllers\Travelport\TravelPortApisController@one_way_trip_filter');
Route::get('/round-trip-search','App\Http\Controllers\Travelport\TravelPortApisController@round_trip_filter');
Route::get('/multi-trip-search','App\Http\Controllers\Travelport\TravelPortApisController@multi_trip_search');
Route::get('/get-cities','App\Http\Controllers\City\CityController@index');
Route::post('/get-farerules','App\Http\Controllers\Travelport\TravelPortApisController@getFareRules');
Route::post('/book-flight','App\Http\Controllers\Booking\BookingController@reserveFlight');
Route::post('/issue-ticket','App\Http\Controllers\Booking\BookingController@issueTicket');
Route::get('/issue-ticket-hitit','App\Http\Controllers\Booking\BookingController@issueTicketHitit');
Route::get('/cancel-booking','App\Http\Controllers\Booking\BookingController@cancelBooking');

Route::get('/get-booking-by-pnr','App\Http\Controllers\Booking\BookingController@getBookingByPnr');
Route::get('/get-booking-by-pnr-db','App\Http\Controllers\Booking\BookingController@getBookingByPnrUsingDB');
});
//Route::get('/profile','App\Http\Controllers\Admin\LoginController@me')->middleware('api_data_logger','auth:api');

Route::post('/admin-login','App\Http\Controllers\Admin\LoginAdminController@login');
Route::get('/profile','App\Http\Controllers\Admin\LoginAdminController@userProfile')->middleware('auth:api');


//Air Blue Routes

Route::get('/one-way-search-airblue','App\Http\Controllers\TravelAirBlue\TravelAirBlueController@one_way_trip_filter');
Route::get('/round-trip-search-airblue','App\Http\Controllers\TravelAirBlue\TravelAirBlueController@round_trip_filter');
Route::get('/issue-ticket-airblue','App\Http\Controllers\Booking\BookingController@issueTicketAirblue');


//Air Sial Routes

Route::get('/one-way-search-airsial','App\Http\Controllers\TravelAirSial\TravelAirSialController@one_way_trip_filter');
Route::get('/round-trip-search-airsial','App\Http\Controllers\TravelAirSial\TravelAirSialController@round_trip_filter');
Route::get('/issue-ticket-airsial','App\Http\Controllers\Booking\BookingController@issueTicketAirSial');

//User Subscriptions using email

Route::post('/subscribe','App\Http\Controllers\subscriptions\SubscriptionsController@create');
Route::get('/sendemail','App\Http\Controllers\subscriptions\SubscriptionsController@sendemail');


Route::get('/cancelrequest','App\Http\Controllers\Booking\BookingController@cancelrequest');
Route::get('/paynow','App\Http\Controllers\Payment\PaymentController@index');

//Hotel Api Routes
Route::get('/gethotels','App\Http\Controllers\Hotels\HotelController@index');

