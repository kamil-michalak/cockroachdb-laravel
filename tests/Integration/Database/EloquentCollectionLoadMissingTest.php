<?php

namespace YlsIdeas\CockroachDb\Tests\Integration\Database\EloquentCollectionLoadMissingTest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use YlsIdeas\CockroachDb\Tests\Integration\Database\DatabaseTestCase;

class EloquentCollectionLoadMissingTest extends DatabaseTestCase
{
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed()
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

    public function test_load_missing()
    {
        $posts = Post::with('comments', 'user')->get();

        DB::enableQueryLog();

        $posts->loadMissing('comments.parent.revisions:revisions.comment_id', 'user:id');

        $this->assertCount(2, DB::getQueryLog());
        $this->assertTrue($posts[0]->comments[0]->relationLoaded('parent'));
        $this->assertTrue($posts[0]->comments[1]->parent->relationLoaded('revisions'));
        $this->assertArrayNotHasKey('id', $posts[0]->comments[1]->parent->revisions[0]->getAttributes());
    }

    public function test_load_missing_with_closure()
    {
        $posts = Post::with('comments')->get();

        DB::enableQueryLog();

        $posts->loadMissing(['comments.parent' => function ($query) {
            $query->select('id');
        }]);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertTrue($posts[0]->comments[0]->relationLoaded('parent'));
        $this->assertArrayNotHasKey('post_id', $posts[0]->comments[1]->parent->getAttributes());
    }

    public function test_load_missing_with_duplicate_relation_name()
    {
        $posts = Post::with('comments')->get();

        DB::enableQueryLog();

        $posts->loadMissing('comments.parent.parent');

        $this->assertCount(2, DB::getQueryLog());
        $this->assertTrue($posts[0]->comments[0]->relationLoaded('parent'));
        $this->assertTrue($posts[0]->comments[1]->parent->relationLoaded('parent'));
    }

    public function test_load_missing_without_initial_load()
    {
        $user = User::first();
        $user->loadMissing('posts.postRelation.postSubRelations.postSubSubRelations');

        $this->assertEquals(2, $user->posts->count());
        $this->assertNull($user->posts[0]->postRelation);
        $this->assertInstanceOf(PostRelation::class, $user->posts[1]->postRelation);
        $this->assertEquals(1, $user->posts[1]->postRelation->postSubRelations->count());
        $this->assertInstanceOf(PostSubRelation::class, $user->posts[1]->postRelation->postSubRelations[0]);
        $this->assertEquals(1, $user->posts[1]->postRelation->postSubRelations[0]->postSubSubRelations->count());
        $this->assertInstanceOf(PostSubSubRelation::class, $user->posts[1]->postRelation->postSubRelations[0]->postSubSubRelations[0]);
    }
}

class Comment extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(self::class);
    }

    public function revisions()
    {
        return $this->hasMany(Revision::class);
    }
}

class Post extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function postRelation()
    {
        return $this->hasOne(PostRelation::class);
    }
}

class PostRelation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function postSubRelations()
    {
        return $this->hasMany(PostSubRelation::class);
    }
}

class PostSubRelation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function postSubSubRelations()
    {
        return $this->hasMany(PostSubSubRelation::class);
    }
}

class PostSubSubRelation extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

class Revision extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

class User extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
