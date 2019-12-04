<?php

use Illuminate\Http\Request;
// use Symfony\Component\Routing\Annotation\Route;
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
// Route::post('login', 'ApiController@login');
// Route::post('register', 'ApiController@register');
Route::get('/', function(){
    return 'Welcome to Museum';
});
Route::post('social_login', 'ApiController@socialLogin');
Route::post('register', 'ApiController@register');
Route::post('login', 'ApiController@login');
Route::post('forgot-password', 'ApiController@forgotPassword');
Route::post('resend-otp', 'ApiController@resendOtp');
Route::post('verify-otp', 'ApiController@verificationOtp');


Route::group(['middleware' => 'jwt.verify'], function () {
    Route::get('logout', 'ApiController@logout');
    Route::patch('update-password/{id}', 'ApiController@update_Password');
    Route::get('user_profile', 'ApiController@getUserProfile');
    Route::get('search_people', 'ApiController@searchPeople');
    Route::post('follow_user', 'ApiController@followUser');
    Route::post('change_profile_pic', 'ApiController@changeProfilePic');

    Route::post('upload_media', 'AlbumController@uploadMedia');
    Route::post('add_album', 'AlbumController@saveAlbum');
    Route::get('user_albums', 'AlbumController@userAlbumList');
    Route::patch('edit_album/{id}', 'AlbumController@updateAlbum');
    Route::get('get_user_details', 'ApiController@getUserDetails'); // User viewing Another User Profile
    Route::delete('delete_album/{id}', 'AlbumController@deleteAlbum'); // User viewing Another User Profile

    //Stories Create 
    Route::post('stories-create', 'AlbumController@storiesCreate');
    Route::get('stories', 'AlbumController@getStories');
    Route::get('stories-detail', 'AlbumController@userStoriesList');
    
});