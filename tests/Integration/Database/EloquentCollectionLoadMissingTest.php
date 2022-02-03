<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use YlsIdeas\CockroachDb\Tests\Integration\Database\DatabaseTestCase;

uses(DatabaseTestCase::class);

test('load missing', function () {
    $posts = Post::with('comments', 'user')->get();

    DB::enableQueryLog();

    $posts->loadMissing('comments.parent.revisions:revisions.comment_id', 'user:id');

    $this->assertCount(2, DB::getQueryLog());
    $this->assertTrue($posts[0]->comments[0]->relationLoaded('parent'));
    $this->assertTrue($posts[0]->comments[1]->parent->relationLoaded('revisions'));
    $this->assertArrayNotHasKey('id', $posts[0]->comments[1]->parent->revisions[0]->getAttributes());
});

test('load missing with closure', function () {
    $posts = Post::with('comments')->get();

    DB::enableQueryLog();

    $posts->loadMissing(['comments.parent' => function ($query) {
        $query->select('id');
    }]);

    $this->assertCount(1, DB::getQueryLog());
    $this->assertTrue($posts[0]->comments[0]->relationLoaded('parent'));
    $this->assertArrayNotHasKey('post_id', $posts[0]->comments[1]->parent->getAttributes());
});

test('load missing with duplicate relation name', function () {
    $posts = Post::with('comments')->get();

    DB::enableQueryLog();

    $posts->loadMissing('comments.parent.parent');

    $this->assertCount(2, DB::getQueryLog());
    $this->assertTrue($posts[0]->comments[0]->relationLoaded('parent'));
    $this->assertTrue($posts[0]->comments[1]->parent->relationLoaded('parent'));
});

test('load missing without initial load', function () {
    $user = User::first();
    $user->loadMissing('posts.postRelation.postSubRelations.postSubSubRelations');

    $this->assertEquals(2, $user->posts->count());
    $this->assertNull($user->posts[0]->postRelation);
    $this->assertInstanceOf(PostRelation::class, $user->posts[1]->postRelation);
    $this->assertEquals(1, $user->posts[1]->postRelation->postSubRelations->count());
    $this->assertInstanceOf(PostSubRelation::class, $user->posts[1]->postRelation->postSubRelations[0]);
    $this->assertEquals(1, $user->posts[1]->postRelation->postSubRelations[0]->postSubSubRelations->count());
    $this->assertInstanceOf(PostSubSubRelation::class, $user->posts[1]->postRelation->postSubRelations[0]->postSubSubRelations[0]);
});

// Helpers
function defineDatabaseMigrationsAfterDatabaseRefreshed()
{
    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('user_id');
    });

    Schema::create('comments', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('parent_id')->nullable();
        $table->unsignedInteger('post_id');
    });

    Schema::create('revisions', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('comment_id');
    });

    Schema::create('post_relations', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('post_id');
    });

    Schema::create('post_sub_relations', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('post_relation_id');
    });

    Schema::create('post_sub_sub_relations', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('post_sub_relation_id');
    });

    User::create(['id' => 1]);

    Post::create(['id' => 1, 'user_id' => 1]);

    Comment::create(['id' => 1, 'parent_id' => null, 'post_id' => 1]);
    Comment::create(['id' => 2, 'parent_id' => 1, 'post_id' => 1]);
    Comment::create(['id' => 3, 'parent_id' => 2, 'post_id' => 1]);

    Revision::create(['id' => 1, 'comment_id' => 1]);

    Post::create(['id' => 2, 'user_id' => 1]);
    PostRelation::create(['id' => 1, 'post_id' => 2]);
    PostSubRelation::create(['id' => 1, 'post_relation_id' => 1]);
    PostSubSubRelation::create(['id' => 1, 'post_sub_relation_id' => 1]);
}

function parent()
{
    return test()->belongsTo(self::class);
}

function revisions()
{
    return test()->hasMany(Revision::class);
}

function comments()
{
    return test()->hasMany(Comment::class);
}

function user()
{
    return test()->belongsTo(User::class);
}

function postRelation()
{
    return test()->hasOne(PostRelation::class);
}

function postSubRelations()
{
    return test()->hasMany(PostSubRelation::class);
}

function postSubSubRelations()
{
    return test()->hasMany(PostSubSubRelation::class);
}

function posts()
{
    return test()->hasMany(Post::class);
}
