<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BuilderDashboardController extends Controller
{
    public function index()
    {
        return view('pages.dashboard');
    }
}
