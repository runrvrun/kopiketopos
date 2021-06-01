<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;
use App\Transaction;
use App\Product_transaction;
use App\Product;
use Session;
use App\Exports\TransactionExport;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(Auth::user()->role == 'admin'){
            $transactions = Transaction::with('store')->orderBy('created_at','desc')->get();
        }else{
            $transactions = Transaction::where('store_id',Auth::user()->store_id)->orderBy('created_at','desc')->get();
        }
        return view('transaction.index',compact('transactions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $items = Product::selectRaw('id, name, price, image, 0 as amount')->orderBy('name','asc')->get();
        return view('transaction.transaction', compact('items'));        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $requestData['user_id'] = Auth::user()->id;
        $requestData['total_item'] = $request->count;
        $requestData['total_price'] = $request->totalprice;
        $requestData['customer'] = $request->customer;
        $requestData['discount'] = $request->discount;
        $requestData['downpayment'] = $request->downpayment;
        $requestData['store_id'] = (Auth::user()->store_id > 0)? Auth::user()->store_id : 1;
        $trxtoday = Transaction::whereDate('created_at',Carbon::today())->count();
        $datetoday = Carbon::now();
        $requestData['key'] = $datetoday->format('Ymd').str_pad($trxtoday+1, 5, '0', STR_PAD_LEFT);
        $trx = Transaction::create($requestData);
        $requestData2 = $request->quant;
        foreach($requestData2 as $key=>$value){
            // insert only if amount > 0
            if($value > 0){
                $req['transaction_id'] = $trx->id;
                $req['product_id'] = $key;
                $req['amount'] = $value;
                Product_transaction::create($req);
            }
        }
        Session::flash('message', 'Transaction created'); 
        Session::flash('alert-class', 'alert-success'); 
        if(isset($request->payment)){
            return redirect('transaction/payment/'.$trx->id);
        }else{
            return redirect('transaction/ongoing');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $trx = Transaction::find($id);
        $items = Product_transaction::select('products.id','name','price','image','amount')->join('products','product_id','products.id')->where('transaction_id',$trx->id)->orderBy('name','asc')->get();
        $itemids = Product_transaction::where('transaction_id',$trx->id)->pluck('product_id');
        $allitems = Product::selectRaw('id, name, price, image, 0 as amount')->whereNotIn('id',$itemids)->orderBy('name','asc')->get();
        $items = $items->merge($allitems);
        // return view('transaction.transaction', compact('item','item2'));        
        return view('transaction.transaction', compact('trx','items'));        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if(isset($request->payment)){
            $requestData['status'] = 'Paid';
        }
        $requestData['total_item'] = $request->count;
        $requestData['total_price'] = $request->totalprice;
        $requestData['customer'] = $request->customer;
        $requestData['discount'] = $request->discount;
        $requestData['downpayment'] = $request->downpayment;
        Transaction::find($id)->update($requestData);
        $requestData2 = $request->quant;
        $trx = Transaction::find($id);
        Product_transaction::where('transaction_id',$trx->id)->delete();
        foreach($requestData2 as $key=>$value){
            // insert only if amount > 0
            if($value > 0){
                $req['transaction_id'] = $trx->id;
                $req['product_id'] = $key;
                $req['amount'] = $value;
                Product_transaction::create($req);
            }
        }
        Session::flash('message', 'Transaction updated'); 
        Session::flash('alert-class', 'alert-success'); 
        if(isset($request->payment)){
            return redirect('transaction/payment/'.$id);
        }else{
            return redirect('transaction/ongoing');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Transaction::destroy($id);
        Session::flash('message', 'Transaction deleted'); 
        Session::flash('alert-class', 'alert-success'); 
        return redirect('transaction');
    }

    public function ongoing()
    {
        if(Auth::user()->role == 'admin'){
            $items = Transaction::with('store')->whereNull('status')->orderBy('created_at','desc')->get();
        }else{
            $items = Transaction::where('store_id',Auth::user()->store_id)->whereNull('status')->orderBy('created_at','desc')->get();
        }
        return view('transaction.ongoing',compact('items'));
    }
    
    public function payment($id)
    {
        return view('transaction.complete', compact('id'));
    }

    public function receipt($id)
    {
        $transaction = Transaction::find($id);
        $products = Product_transaction::with('product')->where('transaction_id',$id)->get();
        return view('transaction.receipt', compact('transaction','products'));
    }

    public function downloadhistory()
    {
        return view('transaction.downloadhistory');
    }
    public function generatedownloadhistory(Request $request)
    {
        $monthyear = $request->month;
        $year = substr($monthyear,0,4);
        $month = substr($monthyear,4,2);
        return (new TransactionExport($monthyear))->download('kopiketo-transaction-'.$year.'-'.$month.'.xlsx');
    }
}
