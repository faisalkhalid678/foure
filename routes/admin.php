<?php


/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::post('/admin-login','App\Http\Controllers\Admin\LoginAdminController@login');
Route::get('/profile','App\Http\Controllers\Admin\LoginAdminController@userProfile')->middleware('auth:api');
Route::get('/getbookings','App\Http\Controllers\Admin\BookingController@getBookings')->middleware('auth:api');
Route::get('/getcancelrequests','App\Http\Controllers\Admin\BookingController@getCancelRequests');
Route::get('/getnotifications','App\Http\Controllers\Admin\BookingController@getNotifications');
Route::get('/marknotificationsasread','App\Http\Controllers\Admin\BookingController@markNotificationsRead');
Route::get('/getsettings','App\Http\Controllers\Admin\SettingsController@index');
Route::get('/updatesettings','App\Http\Controllers\Admin\SettingsController@update');

//Routes for User Rights and roles
Route::get('/getrights','App\Http\Controllers\Admin\RightsController@index');
Route::get('/addrights','App\Http\Controllers\Admin\RightsController@addrights');
Route::get('/deleteright/{id}','App\Http\Controllers\Admin\RightsController@destroy');

Route::get('/getroles','App\Http\Controllers\Admin\RolesController@index');
Route::post('/addroles','App\Http\Controllers\Admin\RolesController@addroles');
Route::get('/updaterolestatus/{id}','App\Http\Controllers\Admin\RolesController@update');

Route::get('/getregions','App\Http\Controllers\Admin\RegionController@index');
Route::get('/addregion','App\Http\Controllers\Admin\RegionController@addregion');
Route::get('/deleteregion/{id}','App\Http\Controllers\Admin\RegionController@destroy');

Route::get('/getsectors','App\Http\Controllers\Admin\SectorController@index');
Route::get('/addsector','App\Http\Controllers\Admin\SectorController@addsector');
Route::get('/updatesectorstatus/{id}','App\Http\Controllers\Admin\SectorController@update');

Route::get('/getareas','App\Http\Controllers\Admin\AreaController@index');
Route::get('/addarea','App\Http\Controllers\Admin\AreaController@addarea');
Route::get('/updateareastatus/{id}','App\Http\Controllers\Admin\AreaController@update');

Route::get('/getusers','App\Http\Controllers\Admin\UsersController@index');
Route::get('/adduser','App\Http\Controllers\Admin\UsersController@adduser')->middleware('auth:api');
Route::get('/updateuserstatus/{id}','App\Http\Controllers\Admin\UsersController@update');

Route::get('/getvendors','App\Http\Controllers\Admin\VendorsController@index');
Route::post('/addvendor','App\Http\Controllers\Admin\VendorsController@addvendor')->middleware('auth:api');
Route::get('/updatevendorstatus/{id}','App\Http\Controllers\Admin\VendorsController@update');
Route::get('/deletevendorimage/{id}','App\Http\Controllers\Admin\VendorsController@deleteimage');

Route::get('/getroom','App\Http\Controllers\Admin\RoomController@index');
Route::post('/addroom','App\Http\Controllers\Admin\RoomController@addroom');
Route::get('/updateroomstatus/{id}','App\Http\Controllers\Admin\RoomController@update');
Route::get('/deleteroomimage/{id}','App\Http\Controllers\Admin\RoomController@deleteimage');



