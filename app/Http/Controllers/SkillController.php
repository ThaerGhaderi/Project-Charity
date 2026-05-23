<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index()
    {
        $skills = Skill::select('id','name')->get();
        return response()->json([
            'status_code' => 200,
            'message'=>'retrive data successfully',
            'data'    => $skills
        ]);
    }
}
