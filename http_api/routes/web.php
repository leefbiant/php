<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/bar', function () {
  return "bar";  
});

Route::get('/api/index', 'HttpApiSvr@index');
Route::get('/api/getuserinfo/{id}', 'HttpApiSvr@getuserinfo');

Route::post('/api/setuserinfo', 'HttpApiSvr@setuserinfo');
Route::delete('/api/deluser/{id}', 'HttpApiSvr@deluser');
Route::put('/api/updateuserinfo/{id}', 'HttpApiSvr@updateuserinfo');


Route::get('/api/live', 'HttpApiSvr@live');
