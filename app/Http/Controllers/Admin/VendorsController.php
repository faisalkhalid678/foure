<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\Vendor_image;
use App\Models\Vendor_amenity;
use App\Models\Vendor_complimentary;
use File;

class VendorsController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $vendor = new Vendor();
        $vendors = $vendor->getVendors(array());
        if ($vendors) {
            $vendorArray = array();
            if (!empty($vendors)) {
                foreach ($vendors as $key => $vendor) {
                    $vendorArray[] = $vendor;
                    $vImage = new Vendor_image();
                    $vendorImages = $vImage->where('vendor_id', $vendor['id'])->get();
                    $vAmenity = new Vendor_amenity();
                    $vendorAmenities = $vAmenity->where('vendor_id', $vendor['id'])->get();
                    $vComplimentary = new Vendor_complimentary();
                    $vendorComplimentaries = $vComplimentary->where('vendor_id', $vendor['id'])->get();
                    $vendorImgArr = array();
                    if (!empty($vendorImages)) {
                        foreach($vendorImages as $imgKey => $img) {
                            $venImgArr[$imgKey] = $img;
                             $venImgArr[$imgKey]['image_path'] = URL('/').'/public/vendor_images/'.$img['image_name'];
                            $vendorImgArr = $venImgArr;
                        }
                    }
                    $vendorArray[$key]['vendor_images'] = $vendorImgArr;
                    $vendorArray[$key]['vendor_amenities'] = $vendorAmenities;
                    $vendorArray[$key]['vendor_complimentaries'] = $vendorComplimentaries;
                }
            }
            $return = array(
                'status' => '200',
                'message' => 'Vendors Listing',
                'data' => $vendorArray
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Vendors Not Found',
                'data' => new \stdClass()
            );
        }

        return response()->json($return);
    }

    public function addVendor(Request $request) {

        $rules = [
            'user_id' => 'required',
            'property_full_name' => 'required',
            'no_of_rooms' => 'required',
            'email' => 'required',
            'vendor_contact_no' => 'required',
            'sector_id' => 'required',
            'rating' => 'required',
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

        $vendor = new Vendor();
        $addVendor = $vendor->addVendor($request);
        if ($addVendor['status'] === TRUE) {
            $return = array(
                'status' => '200',
                'message' => $addVendor['message'],
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
        $vendor = new Vendor();
        $update = array(
            'status' => $request->status
        );
        $updated = $vendor->where('id', $id)->update($update);
        if ($updated) {
            $return = array(
                'status' => '200',
                'message' => 'Vendor ' . ucfirst($request->status) . ' Successfully',
                'data' => new \stdClass()
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Vendor Not ' . ucfirst($request->status) . ' Successfully',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
    
    public function deleteimage($id){
        $vendorImg = new Vendor_image();
        if($id && $id !== ""){
            $vendorImgData = $vendorImg->where('id',$id)->first();
            $path = 'public/vendor_images/'.$vendorImgData->image_name;
            if(file_exists($path)){
                File::delete($path);
            }
            $deleted = $vendorImg->where('id',$id)->delete();
            if ($deleted) {
                $remaining_images = $vendorImg->where('vendor_id',$vendorImgData->vendor_id)->get();
                $vendorImgArr = array();
                    if (!empty($remaining_images)) {
                        foreach($remaining_images as $imgKey => $img) {
                            $venImgArr[$imgKey] = $img;
                             $venImgArr[$imgKey]['image_path'] = URL('/').'/public/vendor_images/'.$img['image_name'];
                            $vendorImgArr = $venImgArr;
                        }
                    }
            $return = array(
                'status' => '200',
                'message' => 'Vendor Image Deleted Successfully',
                'data' => $vendorImgArr
            );
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Vendor Image Not Deleted Successfully',
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
