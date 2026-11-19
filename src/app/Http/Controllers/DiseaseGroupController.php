<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DiseaseGroupController extends Controller
{
    public function index(): View
    {
        $diseases = DB::table('diseases_group')
            ->select('cd', 'name_th', 'name_en', 'score')
            ->orderBy('cd')
            ->paginate(10);

        return view('diseases-group.index', compact('diseases'));
    }
}