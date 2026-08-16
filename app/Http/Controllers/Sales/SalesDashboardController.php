<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function index()
    {
        return view('pages.dashboard');
    }
}
