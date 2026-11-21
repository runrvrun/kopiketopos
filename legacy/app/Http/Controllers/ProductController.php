<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Product;
use Image;
use Session;

class ProductController extends Controller
{
    
    public function index()
    {
        $products = Product::all();
        return view('product.index',compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('product.createupdate');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
        ]);

        $requestData = $request->all();
        // upload images
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $name = time().rand(1000,9999).'.'.$image->getClientOriginalExtension();
            $destinationPath = 'uploads/product';
            $image = Image::make($image->getRealPath())->resize(null,300, function ($constraint) {
                $constraint->aspectRatio();
            })->crop(300,300)->save(base_path('public/'.$destinationPath).'/'.$name);
            $requestData['image']=$destinationPath.'/'.$name;    
        }        
        Product::create($requestData);
        Session::flash('message', 'Product created'); 
        Session::flash('alert-class', 'alert-success'); 
        return redirect('product');
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
        $item = Product::find($id);
        return view('product.createupdate', compact('item'));        
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
        
        $request->validate([
            'name' => 'required',
            'price' => 'required',
        ]);

        $requestData = $request->all();
        // upload images
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $name = time().rand(1000,9999).'.'.$image->getClientOriginalExtension();
            $destinationPath = 'uploads/product';
            $image = Image::make($image->getRealPath())->resize(null,300, function ($constraint) {
                $constraint->aspectRatio();
            })->crop(300,300)->save(base_path('public/'.$destinationPath).'/'.$name);
            $requestData['image']=$destinationPath.'/'.$name;    
        }        
        Product::find($id)->update($requestData);
        Session::flash('message', 'Product updated'); 
        Session::flash('alert-class', 'alert-success'); 
        return redirect('product');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Product::destroy($id);
        Session::flash('message', 'Product deleted'); 
        Session::flash('alert-class', 'alert-success'); 
        return redirect('product');
    }

    public function list(Request $request)
    {
        $products = Product::all();
        return response()->json($products);
    }
    public function add(Request $request){
        $requestData = $request->all();
        $product = Product::create($requestData);
        return response()->json($product);
    }
}