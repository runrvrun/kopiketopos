<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('login', 'AuthController@login');
    Route::post('signup', 'AuthController@signup');
  
    Route::group([
      'middleware' => 'auth:api'
    ], function() {
        Route::get('logout', 'AuthController@logout');
        Route::get('user/list', 'UserController@list');
        Route::get('user', 'AuthController@user');
    });
});

Route::group([
    'prefix' => 'product'
], function () {
    Route::group([
      'middleware' => 'auth:api'
    ], function() {
        Route::get('list', 'ProductController@list');
        Route::post('add', 'ProductController@add');
    });
});
