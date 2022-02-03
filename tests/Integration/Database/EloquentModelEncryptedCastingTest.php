<?php

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTestCase::class);

beforeEach(function () {
    $this->encrypter = $this->mock(Encrypter::class);
    Crypt::swap($this->encrypter);

    Model::$encrypter = null;
});

test('strings are castable', function () {
    $this->encrypter->expects('encrypt')
        ->with('this is a secret string', false)
        ->andReturn('encrypted-secret-string');
    $this->encrypter->expects('decrypt')
        ->with('encrypted-secret-string', false)
        ->andReturn('this is a secret string');

    /** @var \YlsIdeas\CockroachDb\Tests\Integration\Database\EncryptedCast $subject */
    $subject = EncryptedCast::create([
        'secret' => 'this is a secret string',
    ]);

    $this->assertSame('this is a secret string', $subject->secret);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret' => 'encrypted-secret-string',
    ]);
});

test('arrays are castable', function () {
    $this->encrypter->expects('encrypt')
        ->with('{"key1":"value1"}', false)
        ->andReturn('encrypted-secret-array-string');
    $this->encrypter->expects('decrypt')
        ->with('encrypted-secret-array-string', false)
        ->andReturn('{"key1":"value1"}');

    /** @var \YlsIdeas\CockroachDb\Tests\Integration\Database\EncryptedCast $subject */
    $subject = EncryptedCast::create([
        'secret_array' => ['key1' => 'value1'],
    ]);

    $this->assertSame(['key1' => 'value1'], $subject->secret_array);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_array' => 'encrypted-secret-array-string',
    ]);
});

test('json is castable', function () {
    $this->encrypter->expects('encrypt')
        ->with('{"key1":"value1"}', false)
        ->andReturn('encrypted-secret-json-string');
    $this->encrypter->expects('decrypt')
        ->with('encrypted-secret-json-string', false)
        ->andReturn('{"key1":"value1"}');

    /** @var \YlsIdeas\CockroachDb\Tests\Integration\Database\EncryptedCast $subject */
    $subject = EncryptedCast::create([
        'secret_json' => ['key1' => 'value1'],
    ]);

    $this->assertSame(['key1' => 'value1'], $subject->secret_json);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_json' => 'encrypted-secret-json-string',
    ]);
});

test('json attribute is castable', function () {
    $this->encrypter->expects('encrypt')
        ->with('{"key1":"value1"}', false)
        ->andReturn('encrypted-secret-json-string');
    $this->encrypter->expects('decrypt')
        ->with('encrypted-secret-json-string', false)
        ->andReturn('{"key1":"value1"}');
    $this->encrypter->expects('encrypt')
        ->with('{"key1":"value1","key2":"value2"}', false)
        ->andReturn('encrypted-secret-json-string2');
    $this->encrypter->expects('decrypt')
        ->with('encrypted-secret-json-string2', false)
        ->andReturn('{"key1":"value1","key2":"value2"}');

    $subject = new EncryptedCast([
        'secret_json' => ['key1' => 'value1'],
    ]);
    $subject->fill([
        'secret_json->key2' => 'value2',
    ]);
    $subject->save();

    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $subject->secret_json);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_json' => 'encrypted-secret-json-string2',
    ]);
});

test('object is castable', function () {
    $object = new stdClass();
    $object->key1 = 'value1';

    $this->encrypter->expects('encrypt')
        ->with('{"key1":"value1"}', false)
        ->andReturn('encrypted-secret-object-string');
    $this->encrypter->expects('decrypt')
        ->twice()
        ->with('encrypted-secret-object-string', false)
        ->andReturn('{"key1":"value1"}');

    /** @var \YlsIdeas\CockroachDb\Tests\Integration\Database\EncryptedCast $object */
    $object = EncryptedCast::create([
        'secret_object' => $object,
    ]);

    $this->assertInstanceOf(stdClass::class, $object->secret_object);
    $this->assertSame('value1', $object->secret_object->key1);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $object->id,
        'secret_object' => 'encrypted-secret-object-string',
    ]);
});

test('collection is castable', function () {
    $this->encrypter->expects('encrypt')
        ->with('{"key1":"value1"}', false)
        ->andReturn('encrypted-secret-collection-string');
    $this->encrypter->expects('decrypt')
        ->twice()
        ->with('encrypted-secret-collection-string', false)
        ->andReturn('{"key1":"value1"}');

    /** @var \YlsIdeas\CockroachDb\Tests\Integration\Database\EncryptedCast $subject */
    $subject = EncryptedCast::create([
        'secret_collection' => new Collection(['key1' => 'value1']),
    ]);

    $this->assertInstanceOf(Collection::class, $subject->secret_collection);
    $this->assertSame('value1', $subject->secret_collection->get('key1'));
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_collection' => 'encrypted-secret-collection-string',
    ]);
});

test('as encrypted collection', function () {
    if (version_compare(App::version(), '8.75', '<')) {
        $this->markTestSkipped('Not included before 8.75');
    }

    $this->encrypter->expects('encryptString')
        ->twice()
        ->with('{"key1":"value1"}')
        ->andReturn('encrypted-secret-collection-string-1');
    $this->encrypter->expects('encryptString')
        ->times(12)
        ->with('{"key1":"value1","key2":"value2"}')
        ->andReturn('encrypted-secret-collection-string-2');
    $this->encrypter->expects('decryptString')
        ->once()
        ->with('encrypted-secret-collection-string-2')
        ->andReturn('{"key1":"value1","key2":"value2"}');

    $subject = new EncryptedCast();

    $subject->mergeCasts(['secret_collection' => AsEncryptedCollection::class]);

    $subject->secret_collection = new Collection(['key1' => 'value1']);
    $subject->secret_collection->put('key2', 'value2');

    $subject->save();

    $this->assertInstanceOf(Collection::class, $subject->secret_collection);
    $this->assertSame('value1', $subject->secret_collection->get('key1'));
    $this->assertSame('value2', $subject->secret_collection->get('key2'));
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_collection' => 'encrypted-secret-collection-string-2',
    ]);

    $subject = $subject->fresh();

    $this->assertInstanceOf(Collection::class, $subject->secret_collection);
    $this->assertSame('value1', $subject->secret_collection->get('key1'));
    $this->assertSame('value2', $subject->secret_collection->get('key2'));

    $subject->secret_collection = null;
    $subject->save();

    $this->assertNull($subject->secret_collection);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_collection' => null,
    ]);

    $this->assertNull($subject->fresh()->secret_collection);
});

test('as encrypted array object', function () {
    if (version_compare(App::version(), '8.75', '<')) {
        $this->markTestSkipped('Not included before 8.75');
    }

    $this->encrypter->expects('encryptString')
        ->once()
        ->with('{"key1":"value1"}')
        ->andReturn('encrypted-secret-array-string-1');
    $this->encrypter->expects('decryptString')
        ->once()
        ->with('encrypted-secret-array-string-1')
        ->andReturn('{"key1":"value1"}');
    $this->encrypter->expects('encryptString')
        ->times(12)
        ->with('{"key1":"value1","key2":"value2"}')
        ->andReturn('encrypted-secret-array-string-2');
    $this->encrypter->expects('decryptString')
        ->once()
        ->with('encrypted-secret-array-string-2')
        ->andReturn('{"key1":"value1","key2":"value2"}');

    $subject = new EncryptedCast();

    $subject->mergeCasts(['secret_array' => AsEncryptedArrayObject::class]);

    $subject->secret_array = ['key1' => 'value1'];
    $subject->secret_array['key2'] = 'value2';

    $subject->save();

    $this->assertInstanceOf(ArrayObject::class, $subject->secret_array);
    $this->assertSame('value1', $subject->secret_array['key1']);
    $this->assertSame('value2', $subject->secret_array['key2']);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_array' => 'encrypted-secret-array-string-2',
    ]);

    $subject = $subject->fresh();

    $this->assertInstanceOf(ArrayObject::class, $subject->secret_array);
    $this->assertSame('value1', $subject->secret_array['key1']);
    $this->assertSame('value2', $subject->secret_array['key2']);

    $subject->secret_array = null;
    $subject->save();

    $this->assertNull($subject->secret_array);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret_array' => null,
    ]);

    $this->assertNull($subject->fresh()->secret_array);
});

test('custom encrypter can be specified', function () {
    $customEncrypter = $this->mock(Encrypter::class);

    $this->assertNull(Model::$encrypter);

    Model::encryptUsing($customEncrypter);

    $this->assertSame($customEncrypter, Model::$encrypter);

    $this->encrypter->expects('encrypt')
        ->never();
    $this->encrypter->expects('decrypt')
        ->never();
    $customEncrypter->expects('encrypt')
        ->with('this is a secret string', false)
        ->andReturn('encrypted-secret-string');
    $customEncrypter->expects('decrypt')
        ->with('encrypted-secret-string', false)
        ->andReturn('this is a secret string');

    /** @var \YlsIdeas\CockroachDb\Tests\Integration\Database\EncryptedCast $subject */
    $subject = EncryptedCast::create([
        'secret' => 'this is a secret string',
    ]);

    $this->assertSame('this is a secret string', $subject->secret);
    $this->assertDatabaseHas('encrypted_casts', [
        'id' => $subject->id,
        'secret' => 'encrypted-secret-string',
    ]);
});

// Helpers
function defineDatabaseMigrationsAfterDatabaseRefreshed()
{
    Schema::create('encrypted_casts', function (Blueprint $table) {
        $table->increments('id');
        $table->string('secret', 1000)->nullable();
        $table->text('secret_array')->nullable();
        $table->text('secret_json')->nullable();
        $table->text('secret_object')->nullable();
        $table->text('secret_collection')->nullable();
    });
}
