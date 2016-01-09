<?php

get('/404', array('uses' => 'IndexController@show404', 'as' => '404'));
get('user/login', array('uses' => 'UserController@login', 'as' => 'user.login'));
post('user/login', array('uses' => 'UserController@loginPost', 'as' => 'user.login.post'));
get('user', array('uses' => 'UserController@index', 'as' => 'user.index'));
get('download/{lnk?}', array('uses' => 'DownloadController@download', 'as' => 'download.link'));
get('order/bills/{id?}/{lnk?}', array('uses' => 'OrderController@bills', 'as' => 'order.bills'));
post('api/lists/{model?}', array('uses' => 'ApiController@lists', 'as' => 'api.lists'));
get('admin/worker/{commands?}', array('uses' => 'AdminController@worker', 'as' => 'admin.worker'));
Route::resource('admin', 'AdminController', array('only' => array('index', 'show', 'update')));

// @todo rewrite:
// check: AuthenticatesAndRegistersUsers, PasswordBroker, ResetsPasswords
// RemindersController
