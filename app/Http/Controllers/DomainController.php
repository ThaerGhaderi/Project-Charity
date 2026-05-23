<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
      public function index()
    {
        $domains = Domain::select('id','name')->get();
        return response()->json([
            'status_code' => 200,
            'message'=>'retrive data successfully',
            'data'    => $domains
        ]);
    }
}
