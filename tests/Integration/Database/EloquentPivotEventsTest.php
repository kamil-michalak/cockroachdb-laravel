<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTestCase::class);

beforeEach(function () {
    // clear event log between requests
    PivotEventsTestCollaborator::$eventsCalled = [];
});

test('pivot will trigger events to be fired', function () {
    $user = PivotEventsTestUser::forceCreate(['email' => 'taylor@laravel.com']);
    $user2 = PivotEventsTestUser::forceCreate(['email' => 'ralph@ralphschindler.com']);
    $project = PivotEventsTestProject::forceCreate(['name' => 'Test Project']);

    $project->collaborators()->attach($user);
    $this->assertEquals(['saving', 'creating', 'created', 'saved'], PivotEventsTestCollaborator::$eventsCalled);

    PivotEventsTestCollaborator::$eventsCalled = [];
    $project->collaborators()->sync([$user2->id]);
    $this->assertEquals(['deleting', 'deleted', 'saving', 'creating', 'created', 'saved'], PivotEventsTestCollaborator::$eventsCalled);

    PivotEventsTestCollaborator::$eventsCalled = [];
    $project->collaborators()->sync([$user->id => ['role' => 'owner'], $user2->id => ['role' => 'contributor']]);
    $this->assertEquals(['saving', 'creating', 'created', 'saved', 'saving', 'updating', 'updated', 'saved'], PivotEventsTestCollaborator::$eventsCalled);

    PivotEventsTestCollaborator::$eventsCalled = [];
    $project->collaborators()->detach($user);
    $this->assertEquals(['deleting', 'deleted'], PivotEventsTestCollaborator::$eventsCalled);
});

test('pivot with pivot criteria trigger events to be fired on create update none on detach', function () {
    $user = PivotEventsTestUser::forceCreate(['email' => 'taylor@laravel.com']);
    $user2 = PivotEventsTestUser::forceCreate(['email' => 'ralph@ralphschindler.com']);
    $project = PivotEventsTestProject::forceCreate(['name' => 'Test Project']);

    $project->contributors()->sync([$user->id, $user2->id]);
    $this->assertEquals(['saving', 'creating', 'created', 'saved', 'saving', 'creating', 'created', 'saved'], PivotEventsTestCollaborator::$eventsCalled);

    PivotEventsTestCollaborator::$eventsCalled = [];
    $project->contributors()->detach($user->id);
    $this->assertEquals([], PivotEventsTestCollaborator::$eventsCalled);
});

test('custom pivot update event has existing attributes', function () {
    $_SERVER['pivot_attributes'] = false;

    $user = PivotEventsTestUser::forceCreate([
        'id' => 1,
        'email' => 'taylor@laravel.com',
    ]);

    $project = PivotEventsTestProject::forceCreate([
        'id' => 2,
        'name' => 'Test Project',
    ]);

    $project->collaborators()->attach($user, ['permissions' => ['foo', 'bar']]);

    $project->collaborators()->updateExistingPivot($user->id, ['role' => 'Lead Developer']);

    $this->assertEquals(
        [
            'user_id' => '1',
            'project_id' => '2',
            'permissions' => '["foo","bar"]',
            'role' => 'Lead Developer',
        ],
        $_SERVER['pivot_attributes']
    );
});

test('custom pivot update event has dirty correct', function () {
    $_SERVER['pivot_dirty_attributes'] = false;

    $user = PivotEventsTestUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $project = PivotEventsTestProject::forceCreate([
        'name' => 'Test Project',
    ]);

    $project->collaborators()->attach($user, ['permissions' => ['foo', 'bar'], 'role' => 'Developer']);

    $project->collaborators()->updateExistingPivot($user->id, ['role' => 'Lead Developer']);

    $this->assertSame(['role' => 'Lead Developer'], $_SERVER['pivot_dirty_attributes']);
});

// Helpers
function defineDatabaseMigrationsAfterDatabaseRefreshed()
{
    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('email');
        $table->timestamps();
    });

    Schema::create('projects', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('project_users', function (Blueprint $table) {
        $table->integer('user_id');
        $table->integer('project_id');
        $table->text('permissions')->nullable();
        $table->string('role')->nullable();
    });
}

function collaborators()
{
    return test()->belongsToMany(
        PivotEventsTestUser::class,
        'project_users',
        'project_id',
        'user_id'
    )->using(PivotEventsTestCollaborator::class);
}

function contributors()
{
    return test()->belongsToMany(PivotEventsTestUser::class, 'project_users', 'project_id', 'user_id')
        ->using(PivotEventsTestCollaborator::class)
        ->wherePivot('role', 'contributor');
}

function boot()
{
    parent::boot();

    static::creating(function ($model) {
        static::$eventsCalled[] = 'creating';
    });

    static::created(function ($model) {
        static::$eventsCalled[] = 'created';
    });

    static::updating(function ($model) {
        static::$eventsCalled[] = 'updating';
    });

    static::updated(function ($model) {
        $_SERVER['pivot_attributes'] = $model->getAttributes();
        $_SERVER['pivot_dirty_attributes'] = $model->getDirty();
        static::$eventsCalled[] = 'updated';
    });

    static::saving(function ($model) {
        static::$eventsCalled[] = 'saving';
    });

    static::saved(function ($model) {
        static::$eventsCalled[] = 'saved';
    });

    static::deleting(function ($model) {
        static::$eventsCalled[] = 'deleting';
    });

    static::deleted(function ($model) {
        static::$eventsCalled[] = 'deleted';
    });
}
