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
        $transactions = Transaction::orderBy('created_at','desc')->get();
        return view('transaction.index',compact('transactions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $products = Product::all();
        return view('transaction.create', compact('products'));        
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
        $requestData['total_price'] = $request->subtotal;
        $trx = Transaction::create($requestData);
        $selecteditems = explode(',',$request->selecteditems);
        $selit = array();
        $prev_value = array('value' => null, 'amount' => null);
        foreach ($selecteditems as $val) {
            if ($prev_value['value'] != $val) {
                unset($prev_value);
                $prev_value = array('value' => $val, 'amount' => 0);
                $selit[] =& $prev_value;
            }
            $prev_value['amount']++;
        }        
        foreach($selit as $item){
            $req['transaction_id'] = $trx->id;
            $req['product_id'] = $item['value'];
            $req['amount'] = $item['amount'];
            Product_transaction::create($req);
        }
        Session::flash('message', 'Transaction created'); 
        Session::flash('alert-class', 'alert-success'); 
        return redirect(route('transaction.edit',$trx));
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
        $item = Transaction::find($id);
        $item2 = Product_transaction::select('products.id','name','price','image','amount')->join('products','product_id','products.id')->where('transaction_id',$item->id)->get();
        return view('transaction.update', compact('item','item2'));        
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
        $requestData['total_price'] = $request->subtotal;
        $requestData['customer'] = $request->customer;
        Transaction::find($id)->update($requestData);
        $requestData2 = $request->quant;
        $trx = Transaction::find($id);
        Product_transaction::where('transaction_id',$trx->id)->delete();
        foreach($requestData2 as $key=>$value){
            $req['transaction_id'] = $trx->id;
            $req['product_id'] = $key;
            $req['amount'] = $value;
            Product_transaction::create($req);
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
        $items = Transaction::whereNull('status')->orderBy('created_at','desc')->get();
        return view('transaction.ongoing',compact('items'));
    }

    public function payment($id)
    {
        return view('transaction.complete');
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
