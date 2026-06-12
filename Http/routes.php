<?php

Route::group([
    'middleware' => 'web',
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\SendLater\Http\Controllers',
], function () {
    Route::post('/sendlater/schedule/{conversation}', 'SendLaterController@schedule')->name('sendlater.schedule');
    Route::post('/sendlater/cancel/{conversation}', 'SendLaterController@cancel')->name('sendlater.cancel');
    Route::post('/sendlater/send-now/{conversation}', 'SendLaterController@sendNow')->name('sendlater.send_now');
});
