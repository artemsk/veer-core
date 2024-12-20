<?php

Route::get('/404', array('uses' => 'IndexController@show404', 'as' => '404'));
Route::get('user/login', array('uses' => 'UserController@login', 'as' => 'user.login'));
Route::post('user/login', array('uses' => 'UserController@loginPost', 'as' => 'user.login.post'));
Route::get('user', array('uses' => 'UserController@index', 'as' => 'user.index'));
Route::get('download/{lnk?}', array('uses' => 'DownloadController@download', 'as' => 'download.link'));
Route::get('order/bills/{id?}/{lnk?}', array('uses' => 'OrderController@bills', 'as' => 'order.bills'));
Route::post('api/lists/{model?}', array('uses' => 'ApiController@lists', 'as' => 'api.lists'));
Route::get('admin/worker/{commands?}', array('uses' => 'AdminController@worker', 'as' => 'admin.worker'));
Route::resource('admin', 'AdminController', array('only' => array('index', 'show', 'update')));

// @todo rewrite:
// check: AuthenticatesAndRegistersUsers, PasswordBroker, ResetsPasswords
// RemindersController
