<?php
namespace Robert2\Tests;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Robert2\API\Models\Tag;

final class TagsTest extends ApiTestCase
{
    public static function data(int $id)
    {
        return static::_dataFactory($id, [
            [
                'id' => 1,
                'name' => 'pro',
            ],
        ]);
    }

    public function testGetTags()
    {
        $this->client->get('/api/tags');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseData([
            self::data(1),
        ]);

        $this->client->get('/api/tags?deleted=1');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseData([]);
    }

    public function testCreateTagWithoutData()
    {
        $this->client->post('/api/tags');
        $this->assertStatusCode(StatusCode::STATUS_BAD_REQUEST);
        $this->assertErrorMessage("No data was provided.");
    }

    public function testCreateTagBadData()
    {
        $this->client->post('/api/tags', ['foo' => 'bar']);
        $this->assertValidationError([
            'name' => ["This field is mandatory"],
        ]);
    }

    public function testCreateTagDuplicate()
    {
        $this->client->post('/api/tags', ['name' => 'pro']);
        $this->assertValidationError();
    }

    public function testCreateTag()
    {
        $this->client->post('/api/tags', ['name' => 'New tag']);
        $this->assertStatusCode(StatusCode::STATUS_CREATED);
        $this->assertResponseData([
            'id' => 2,
            'name' => 'New tag',
        ]);
    }

    public function testDeleteAndDestroyTag()
    {
        // - First call: soft delete.
        $this->client->delete('/api/tags/1');
        $this->assertStatusCode(StatusCode::STATUS_NO_CONTENT);
        $softDeleted = Tag::withTrashed()->find(1);
        $this->assertNotNull($softDeleted);
        $this->assertNotEmpty($softDeleted->deleted_at);

        // - Second call: actually DESTROY record from DB
        $this->client->delete('/api/tags/1');
        $this->assertStatusCode(StatusCode::STATUS_NO_CONTENT);
        $this->assertNull(Tag::withTrashed()->find(1));
    }

    public function testRestoreTagNotFound()
    {
        $this->client->put('/api/tags/restore/999');
        $this->assertNotFound();
    }

    public function testRestoreTag()
    {
        // - First, delete tag #1
        $this->client->delete('/api/tags/1');
        $this->assertStatusCode(StatusCode::STATUS_NO_CONTENT);

        // - Then, restore tag #1
        $this->client->put('/api/tags/restore/1');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertNotNull(Tag::find(1));
    }
}
