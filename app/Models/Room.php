<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Room_amenity;
use App\Models\Room_image;
use Auth;

class Room extends Model {

    use HasFactory;

    public $timestamps = false;

    public function getRooms($where) {
        if (!empty($where)) {
            $roomsData = $this
                    ->select('rooms.*', 'vendors.property_full_name as vendor_property_name', 'vendors.vendor_contact_no as vendor_contact', 'vendors.email as vendor_email')
                    ->where($where)
                    ->where('rooms.status', '!=', getConstant('STATUS_DELETED'))
                    ->rightJoin('vendors', 'rooms.vendor_id', '=', 'vendors.id')
                    ->first();
        } else {
            $roomsData = $this
                    ->select('rooms.*', 'vendors.property_full_name as vendor_property_name', 'vendors.vendor_contact_no as vendor_contact', 'vendors.email as vendor_email')
                    ->where('rooms.status', '!=', getConstant('STATUS_DELETED'))
                    ->rightJoin('vendors', 'rooms.vendor_id', '=', 'vendors.id')
                    ->first();
        }
        return $roomsData;
    }

    public function  addRoom($input) {
        $where1['rooms.id'] = $input->vendor_id;
        $isExist = $this->getRooms($where1);
        $amenitiesArr = json_decode($input->amenities);
        if ($input->room_id === "0" && $isExist === null) {
            $this->vendor_id = $input->vendor_id;
            $this->title = $input->title;
            $this->description = $input->room_description;
            $this->no_of_beds = $input->no_of_beds;
            $this->no_of_adults = $input->no_of_adults;
            $this->no_of_childs = $input->no_of_childs;
            $this->one_night_price = $input->one_night_price;
            $this->refundable = $input->refundable;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $this->date_updated = date(getConstant('DATETIME_DB_FORMAT'));
            $this->save();
            $currentRoomId = $this->id;
            if ($input->hasfile('room_images')) {
                foreach ($input->file('room_images') as $file) {
                    $name = time() . rand(1, 100) . '.' . $file->extension();
                    $file->move(public_path('room_images'), $name);

                    $roomImage = new Room_image();

                    $roomImage->room_id = $currentRoomId;
                    $roomImage->image_name = $name;
                    $roomImage->save();
                }
            }
            $this->addAmenities($amenitiesArr, $currentRoomId);
            $message = "Room Added Successfully!";
        } else {
            $update['title'] = $input->title;
            $update['description'] = $input->room_description;
            $update['no_of_beds'] = $input->no_of_beds;
            $update['no_of_adults'] = $input->no_of_adults;
            $update['no_of_childs'] = $input->no_of_childs;
            $update['one_night_price'] = $input->one_night_price;
            $update['refundable'] = $input->refundable;
            $update['date_updated'] = date(getConstant('DATETIME_DB_FORMAT'));
            $this->where('id', $isExist->id)->update($update);
            $message = "Room Updated Successfully!";
            if ($input->hasfile('room_images')) {
                foreach ($input->file('room_images') as $file) {
                    $name = time() . rand(1, 100) . '.' . $file->extension();
                    $file->move(public_path('room_images'), $name);

                    $roomImage = new Room_image();

                    $roomImage->room_id = $isExist->id;
                    $roomImage->image_name = $name;
                    $roomImage->save();
                }
            }
            $RoomAm = new Room_amenity();
            $RoomAm->where('room_id', $isExist->id)->delete();
            $this->addAmenities($amenitiesArr, $isExist->id);
        }

        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

    public function addAmenities($amenitiesArr, $roomId) {
        if (!empty($amenitiesArr)) {
            foreach ($amenitiesArr as $am) {
                $roomAm = new Room_amenity();
                $roomAm->room_id = $roomId;
                $roomAm->amenity = $am;
                $roomAm->save();
            }
        }
        return True;
    }

}
