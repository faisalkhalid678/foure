<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Auth;
use App\Models\Vendor_image;
use App\Models\Vendor_amenity;

class Vendor extends Model {

    use HasFactory;

    public $timestamps = false;

    public function getVendorsUsingCond($where) {

        $vendorsData = $this
                        ->select('vendors.*', 'users.first_name as user_first_name', 'users.last_name as user_last_name', 'users.email as user_email', 'users.mobile as user_mobile')
                        ->where($where)
                        ->rightJoin('users', 'vendors.user_id', '=', 'users.id')
                        ->get()->toArray();

        return $vendorsData;
    }

    public function getVendors($where) {
        if (!empty($where)) {
            $vendorsData = $this
                    ->select('vendors.*', 'users.first_name as user_first_name', 'users.last_name as user_last_name', 'users.email as user_email', 'users.mobile as user_mobile')
                    ->where($where)
                    ->where('vendors.status', '!=', getConstant('STATUS_DELETED'))
                    ->rightJoin('users', 'vendors.user_id', '=', 'users.id')
                    ->first();
        } else {
            $vendorsData = $this
                            ->select('vendors.*', 'users.first_name as user_first_name', 'users.last_name as user_last_name', 'users.email as user_email', 'users.mobile as user_mobile')
                            ->where('vendors.status', '!=', getConstant('STATUS_DELETED'))
                            ->rightJoin('users', 'vendors.user_id', '=', 'users.id')
                            ->get()->toArray();
        }
        return $vendorsData;
    }

    public function addVendor($input) {
        $where1['vendors.id'] = $input->vendor_id;
        $isExist = $this->getVendors($where1);
        $amenitiesArr = json_decode($input->amenities);
        $complimentariesArr = json_decode($input->complimentaries);
        if ($input->vendor_id === "0" && $isExist === null) {
            $this->user_id = $input->user_id;
            $this->main_vendor = Auth::user()->id;
            $this->email = $input->email;
            $this->vendor_contact_no = $input->vendor_contact_no;
            $this->description = $input->vendor_description;
            $this->property_full_name = $input->property_full_name;
            $this->no_of_rooms = $input->no_of_rooms;
            $this->sector_id = $input->sector_id;
            $this->rating = $input->rating;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $this->date_updated = date(getConstant('DATETIME_DB_FORMAT'));
            $this->save();
            $currentVendorId = $this->id;
            if ($input->hasfile('vendor_images')) {
                foreach ($input->file('vendor_images') as $file) {
                    $name = time() . rand(1, 100) . '.' . $file->extension();
                    $file->move(public_path('vendor_images'), $name);

                    $vendorImage = new Vendor_image();

                    $vendorImage->vendor_id = $currentVendorId;
                    $vendorImage->image_name = $name;
                    $vendorImage->save();
                }
            }
            $this->addAmenities($amenitiesArr, $currentVendorId);
            $this->addComplimentaries($complimentariesArr, $currentVendorId);
            $message = "Vendor Added Successfully!";
        } else {
            $update['user_id'] = $input->user_id;
            $update['email'] = $input->email;
            $update['vendor_contact_no'] = $input->vendor_contact_no;
            $update['property_full_name'] = $input->property_full_name;
            $update['description'] = $input->vendor_description;
            $update['no_of_rooms'] = $input->no_of_rooms;
            $update['sector_id'] = $input->sector_id;
            $update['rating'] = $input->rating;
            $update['date_updated'] = date(getConstant('DATETIME_DB_FORMAT'));
            $this->where('id', $isExist->id)->update($update);
            $message = "Vendor Updated Successfully!";
            if ($input->hasfile('vendor_images')) {
                foreach ($input->file('vendor_images') as $file) {
                    $name = time() . rand(1, 100) . '.' . $file->extension();
                    $file->move(public_path('vendor_images'), $name);

                    $vendorImage = new Vendor_image();

                    $vendorImage->vendor_id = $isExist->id;
                    $vendorImage->image_name = $name;
                    $vendorImage->save();
                }
            }
            $vendorAm = new Vendor_amenity();
            $vendorAm->where('vendor_id', $isExist->id)->delete();
            $vendorComp = new Vendor_complimentary();
            $vendorComp->where('vendor_id', $isExist->id)->delete();
            $this->addAmenities($amenitiesArr, $isExist->id);
            $this->addComplimentaries($complimentariesArr, $isExist->id);
        }

        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

    public function addAmenities($amenitiesArr, $vendorId) {
        if (!empty($amenitiesArr)) {
            foreach ($amenitiesArr as $am) {
                $vendorAm = new Vendor_amenity();
                $vendorAm->vendor_id = $vendorId;
                $vendorAm->amenity = $am;
                $vendorAm->save();
            }
        }
        return True;
    }

    public function addComplimentaries($complimentariesArr, $vendorId) {
        if (!empty($complimentariesArr)) {
            foreach ($complimentariesArr as $comp) {
                $vendorComp = new Vendor_complimentary();
                $vendorComp->vendor_id = $vendorId;
                $vendorComp->complimentary = $comp;
                $vendorComp->save();
            }
        }
        return True;
    }

}
