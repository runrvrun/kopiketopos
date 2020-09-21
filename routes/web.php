<?php

use Illuminate\Support\Facades\Route;

// Auth::routes();
Auth::routes(['register' => false]);

Route::group([
    'middleware' => 'auth'
  ], function() {
      Route::get('/', 'DashboardController@index')->name('home');
      Route::get('/home', 'DashboardController@index')->name('home');
      Route::get('/more', 'DashboardController@more');
      Route::resource('/product', 'ProductController');
      Route::resource('/employee', 'EmployeeController');
      Route::get('/transaction/ongoing', 'TransactionController@ongoing');
      Route::get('/transaction/history', 'TransactionController@index');
      Route::get('/transaction/downloadhistory', 'TransactionController@downloadhistory');
      Route::get('/transaction/generatedownloadhistory', 'TransactionController@generatedownloadhistory');
      Route::get('/transaction/payment/{a}', 'TransactionController@payment');
      Route::resource('/transaction', 'TransactionController');
    });
