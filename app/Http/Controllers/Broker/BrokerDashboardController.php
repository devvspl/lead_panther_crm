<?php

namespace App\Http\Controllers\Broker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BrokerDashboardController extends Controller
{
    public function index()
    {
        return view('pages.dashboard', ['role' => 'broker']);
    }
}
