<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::select('id','name')->get();
        return response()->json([
            'status_code' => 200,
            'message'=>'retrive data successfully',
            'data'    => $cities
        ]);
    }
}
