<?php

test('Resources can be retrieved', function () {
    $res = \Frc\Payrix\Payrix::get('txns');

    expect($res)->toBeInstanceOf(\Frc\Payrix\Http\Response::class);
});

test('Resources can be retrieved using the resource class', function () {
    $res = \Frc\Payrix\Models\Transaction::get();

    expect($res)->toBeInstanceOf(\Frc\Payrix\Http\Response::class);
});


it('can retrieve a single instance', function () {
    $given = \Frc\Payrix\Models\Apikey::get()->first();

    $retrieved = \Frc\Payrix\Models\Apikey::get($given->id);

    expect($retrieved)->toBeInstanceOf(\Frc\Payrix\Models\Apikey::class);
    expect($retrieved->id)->toBe($given->id);

});