<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', 'HomeController@index');
Route::get('/home', 'HomeController@index')->name('home');
Auth::routes();

Route::group(['middleware'=>['auth']], function (){
    Route::get('create-subscription','SubscriptionController@index')->name('create-subscription');
    Route::post('create-subscription','SubscriptionController@create')->name('create-subscription');
    Route::get('cancel-subscription','SubscriptionController@cancel')->name('cancel-subscription');
    Route::post('unsubscribe','SubscriptionController@unsubscribe')->name('unsubscribe');
    Route::get('transactions','SubscriptionController@transactions')->name('transactions');


    Route::get('payment-methods','PaymentMethodController@index')->name('payment-methods');
    Route::get('update-payment-method','PaymentMethodController@edit')->name('update-payment-method');
    Route::get('add-payment-method','PaymentMethodController@create')->name('add-payment-method');
    Route::post('payment-method-store','PaymentMethodController@store')->name('payment_method.store');

    Route::post('default-payment-method/{card_id}','PaymentMethodController@makeDefault')->name('payment_method.default');
    Route::post('delete-payment-method/{card_id}','PaymentMethodController@deleteIt')->name('payment_method.delete');
});

Route::get('auto-renewal','SubscriptionController@autoRenewal');

Route::get('test-sub','SubscriptionController@createDummySubscription');
Route::get('dum-refund','SubscriptionController@dummyRefund');
Route::get('test-mail','SubscriptionController@testMail');
