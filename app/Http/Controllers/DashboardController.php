<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Transaction;
use App\Store;
use Auth;

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
        $data['stores'] = Store::get();
        if(Auth::user()->role == 'admin'){
            $data['sumdaysales'] = Transaction::whereDate('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
            $data['sumdaydiscount'] = Transaction::whereDate('created_at', Carbon::today())->where('status','Paid')->sum('discount');
            $data['summonthsales'] = Transaction::whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
            $data['summonthdiscount'] = Transaction::whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('discount');
            $data['sum3monthsales'] = Transaction::whereDate('created_at','>', Carbon::today()->submonth(3)->startOfMonth())->where('status','Paid')->sum('total_price');
            $data['sum3monthdiscount'] = Transaction::whereMonth('created_at','>', Carbon::today()->submonth(3))->where('status','Paid')->sum('discount');
            foreach($data['stores'] as $store){
                $data[$store->id]['sumdaysales'] = Transaction::where('store_id',$store->id)->whereDate('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
                $data[$store->id]['sumdaydiscount'] = Transaction::where('store_id',$store->id)->whereDate('created_at', Carbon::today())->where('status','Paid')->sum('discount');
                $data[$store->id]['summonthsales'] = Transaction::where('store_id',$store->id)->whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
                $data[$store->id]['summonthdiscount'] = Transaction::where('store_id',$store->id)->whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('discount');
                $data[$store->id]['sum3monthsales'] = Transaction::where('store_id',$store->id)->whereDate('created_at','>', Carbon::today()->submonth(3)->startOfMonth())->where('status','Paid')->sum('total_price');
                $data[$store->id]['sum3monthdiscount'] = Transaction::where('store_id',$store->id)->whereMonth('created_at','>', Carbon::today()->submonth(3))->where('status','Paid')->sum('discount');    
            }
        }else{
            $data[Auth::user()->store_id]['sumdaysales'] = Transaction::where('store_id',Auth::user()->store_id)->whereDate('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
            $data[Auth::user()->store_id]['sumdaydiscount'] = Transaction::where('store_id',Auth::user()->store_id)->whereDate('created_at', Carbon::today())->where('status','Paid')->sum('discount');
            $data[Auth::user()->store_id]['summonthsales'] = Transaction::where('store_id',Auth::user()->store_id)->whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('total_price');
            $data[Auth::user()->store_id]['summonthdiscount'] = Transaction::where('store_id',Auth::user()->store_id)->whereMonth('created_at', Carbon::today())->where('status','Paid')->sum('discount');
            $data[Auth::user()->store_id]['sum3monthsales'] = Transaction::where('store_id',Auth::user()->store_id)->whereDate('created_at','>', Carbon::today()->submonth(3)->startOfMonth())->where('status','Paid')->sum('total_price');
            $data[Auth::user()->store_id]['sum3monthdiscount'] = Transaction::where('store_id',Auth::user()->store_id)->whereMonth('created_at','>', Carbon::today()->submonth(3))->where('status','Paid')->sum('discount');
        }
        return view('dashboard.dashboard',compact('data'));
    }
    public function more()
    {
        return view('dashboard.more');
    }
}
