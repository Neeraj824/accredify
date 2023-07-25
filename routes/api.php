<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// routes/api.php
use App\Http\Controllers\VerificationController;
// Route::middleware('auth:api')->post('/verify', [VerificationController::class, 'verify']);


// Route::group(['middleware' =>  ['auth:api'] ], function(){
//     Route::post('verify',"VerificationController@verify");
// });

Route::post('verify',"VerificationController@verify");