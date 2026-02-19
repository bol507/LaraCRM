<?php

use App\Http\Controllers\Auth\LoginController;
use App\Models\VtigerUser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
/*
Route::get('/test-vtiger', function () {
    try {
        $users = VtigerUser::select('id', 'user_name', 'first_name', 'last_name', 'email1')
                          ->limit(5)
                          ->get();
        return response()->json($users);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
*/


