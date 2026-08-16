<?php

namespace App\Http\Controllers\ChannelPartner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PartnerDashboardController extends Controller
{
    public function index()
    {
        return view('pages.dashboard');
    }
}
