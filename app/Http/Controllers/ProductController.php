<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Product;

class ProductController extends Controller
{
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