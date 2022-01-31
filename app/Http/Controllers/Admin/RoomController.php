<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Room_image;
use App\Models\Room_amenity;



class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $room = new Room();
        $rooms = $room->getRooms(array());
        if ($rooms) {
            $roomsArray = array();
            if (!empty($rooms)) {
                foreach ($rooms as $key => $room) {
                    $roomsArray[] = $room;
                    $rImage = new Room_image();
                    $roomImages = $rImage->where('room_id', $room['id'])->get();
                    $rAmenity = new Room_amenity();
                    $RoomAmenities = $rAmenity->where('room_id', $room['id'])->get();
                    $roomImgArr = array();
                    if (!empty($roomImages)) {
                        foreach($roomImages as $imgKey => $img) {
                            $rmImgArr[$imgKey] = $img;
                             $rmImgArr[$imgKey]['image_path'] = URL('/').'/public/room_images/'.$img['image_name'];
                            $roomImgArr = $rmImgArr;
                        }
                    }
                    $roomArray[$key]['room_images'] = $roomImgArr;
                    $roomArray[$key]['room_amenities'] = $RoomAmenities;
                }
            }
            $return = array(
                'status' => '200',
                'message' => 'Room Listing',
                'data' => $roomsArray
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Rooms Not Found',
                'data' => new \stdClass()
            );
        }

        return response()->json($return);
    }

    public function addroom(Request $request) {
        $rules = [
            'vendor_id' => 'required',
            'title' => 'required',
            'no_of_beds' => 'required',
            'no_of_adults' => 'required',
            'no_of_childs' => 'required',
            'one_night_price' => 'required',
            'refundable' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }

        $room = new Room();
        $addRoom = $room->addRoom($request);
        if ($addRoom['status'] === TRUE) {
            $return = array(
                'status' => '200',
                'message' => $addRoom['message'],
                'data' => new \stdClass()
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Operation Unsuccessful!',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

    public function update(Request $request, $id) {
        $rules = [
            'status' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }
        $room = new Room();
        $update = array(
            'status' => $request->status
        );
        $updated = $room->where('id', $id)->update($update);
        if ($updated) {
            $return = array(
                'status' => '200',
                'message' => 'Room ' . ucfirst($request->status) . ' Successfully',
                'data' => new \stdClass()
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Room Not ' . ucfirst($request->status) . ' Successfully',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
    
    public function deleteimage($id){
        $roomImg = new Room_image();
        if($id && $id !== ""){
            $roomImgData = $roomImg->where('id',$id)->first();
            $path = 'public/room_images/'.$roomImgData->image_name;
            if(file_exists($path)){
                File::delete($path);
            }
            $deleted = $roomImg->where('id',$id)->delete();
            if ($deleted) {
                $remaining_images = $roomImg->where('vendor_id',$roomImgData->room_id)->get();
                $roomImgArr = array();
                    if (!empty($remaining_images)) {
                        foreach($remaining_images as $imgKey => $img) {
                            $rmImgArr[$imgKey] = $img;
                             $rmImgArr[$imgKey]['image_path'] = URL('/').'/public/room_images/'.$img['image_name'];
                            $roomImgArr = $rmImgArr;
                        }
                    }
            $return = array(
                'status' => '200',
                'message' => 'Room Image Deleted Successfully',
                'data' => $roomImgArr
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Room Image Not Deleted Successfully',
                'data' => new \stdClass()
            );
        }
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Please Provide Valid Id',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
}
