<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Transaction;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $data['sumdaysales'] = Transaction::whereDate('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
        $data['summonthsales'] = Transaction::whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
        return view('dashboard.dashboard',compact('data'));
    }
    public function more()
    {
        return view('dashboard.more');
    }
}
