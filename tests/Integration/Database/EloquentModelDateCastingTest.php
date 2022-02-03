<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use YlsIdeas\CockroachDb\Tests\Integration\Database\DatabaseTestCase;

uses(DatabaseTestCase::class);

test('dates are custom castable', function () {
    $user = TestModel1::create([
        'date_field' => '2019-10-01',
        'datetime_field' => '2019-10-01 10:15:20',
    ]);

    $this->assertSame('2019-10', $user->toArray()['date_field']);
    $this->assertSame('2019-10 10:15', $user->toArray()['datetime_field']);
    $this->assertInstanceOf(Carbon::class, $user->date_field);
    $this->assertInstanceOf(Carbon::class, $user->datetime_field);
});

test('dates formatted attribute bindings', function () {
    $bindings = [];

    app()->make('db')->listen(static function ($query) use (&$bindings) {
        $bindings = $query->bindings;
    });

    TestModel1::create([
        'date_field' => '2019-10-01',
        'datetime_field' => '2019-10-01 10:15:20',
        'immutable_date_field' => '2019-10-01',
        'immutable_datetime_field' => '2019-10-01 10:15',
    ]);

    $this->assertSame(['2019-10-01', '2019-10-01 10:15:20', '2019-10-01', '2019-10-01 10:15'], $bindings);
});

test('dates formatted array and json', function () {
    $user = TestModel1::create([
        'id' => 1,
        'date_field' => '2019-10-01',
        'datetime_field' => '2019-10-01 10:15:20',
        'immutable_date_field' => '2019-10-01',
        'immutable_datetime_field' => '2019-10-01 10:15',
    ]);

    $expected = [
        'id' => 1,
        'date_field' => '2019-10',
        'datetime_field' => '2019-10 10:15',
        'immutable_date_field' => '2019-10',
        'immutable_datetime_field' => '2019-10 10:15',
    ];

    $this->assertSame($expected, $user->toArray());
    $this->assertSame(json_encode($expected), $user->toJson());
});

test('custom date casts are compared as dates for carbon instances', function () {
    $user = TestModel1::create([
        'date_field' => '2019-10-01',
        'datetime_field' => '2019-10-01 10:15:20',
        'immutable_date_field' => '2019-10-01',
        'immutable_datetime_field' => '2019-10-01 10:15:20',
    ]);

    $user->date_field = new Carbon('2019-10-01');
    $user->datetime_field = new Carbon('2019-10-01 10:15:20');
    $user->immutable_date_field = new CarbonImmutable('2019-10-01');
    $user->immutable_datetime_field = new CarbonImmutable('2019-10-01 10:15:20');

    $this->assertArrayNotHasKey('date_field', $user->getDirty());
    $this->assertArrayNotHasKey('datetime_field', $user->getDirty());
    $this->assertArrayNotHasKey('immutable_date_field', $user->getDirty());
    $this->assertArrayNotHasKey('immutable_datetime_field', $user->getDirty());
});

test('custom date casts are compared as dates for string values', function () {
    $user = TestModel1::create([
        'date_field' => '2019-10-01',
        'datetime_field' => '2019-10-01 10:15:20',
        'immutable_date_field' => '2019-10-01',
        'immutable_datetime_field' => '2019-10-01 10:15:20',
    ]);

    $user->date_field = '2019-10-01';
    $user->datetime_field = '2019-10-01 10:15:20';
    $user->immutable_date_field = '2019-10-01';
    $user->immutable_datetime_field = '2019-10-01 10:15:20';

    $this->assertArrayNotHasKey('date_field', $user->getDirty());
    $this->assertArrayNotHasKey('datetime_field', $user->getDirty());
    $this->assertArrayNotHasKey('immutable_date_field', $user->getDirty());
    $this->assertArrayNotHasKey('immutable_datetime_field', $user->getDirty());
});

// Helpers
function defineDatabaseMigrationsAfterDatabaseRefreshed()
{
    Schema::create('test_model1', function (Blueprint $table) {
        $table->increments('id');
        $table->date('date_field')->nullable();
        $table->datetime('datetime_field')->nullable();
        $table->date('immutable_date_field')->nullable();
        $table->datetime('immutable_datetime_field')->nullable();
    });
}
