<?php

Route::get('/404', ['uses' => 'IndexController@show404', 'as' => '404']);
Route::get('user/login', ['uses' => 'UserController@login', 'as' => 'user.login']);
Route::post('user/login', ['uses' => 'UserController@loginPost', 'as' => 'user.login.post']);
Route::get('user', ['uses' => 'UserController@index', 'as' => 'user.index']);
Route::get('download/{lnk?}', ['uses' => 'DownloadController@download', 'as' => 'download.link']);
Route::get('order/bills/{id?}/{lnk?}', ['uses' => 'OrderController@bills', 'as' => 'order.bills']);
Route::post('api/lists/{model?}', ['uses' => 'ApiController@lists', 'as' => 'api.lists']);
Route::get('admin/worker/{commands?}', ['uses' => 'AdminController@worker', 'as' => 'admin.worker']);
Route::resource('admin', 'AdminController', ['only' => ['index', 'show', 'update']]);

// @todo rewrite:
// check: AuthenticatesAndRegistersUsers, PasswordBroker, ResetsPasswords
// RemindersController
