<?php

namespace App\Http\Controllers\Hotels;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\Vendor_image;
use App\Models\Vendor_amenity;
use App\Models\Vendor_complimentary;

class HotelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        $vendor = new Vendor();
        $where['vendors.status'] = 'Active';
        $where['sector_id'] = $request->sector;
        $vendors = $vendor->getVendorsUsingCond($where);
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
