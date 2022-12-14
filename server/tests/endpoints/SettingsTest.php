<?php
namespace Robert2\Tests;

use Fig\Http\Message\StatusCodeInterface as StatusCode;

final class SettingsTest extends ApiTestCase
{
    public function testGetAll()
    {
        $this->client->get('/api/settings');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseData([
            'eventSummary' => [
                'customText' => [
                    'title' => "Contrat",
                    'content' => "Un petit contrat de test.",
                ],
                'materialDisplayMode' => 'categories',
                'showLegalNumbers' => true,
            ],
            'calendar' => [
                'event' => [
                    'showBorrower' => false,
                    'showLocation' => true,
                ],
                'public' => [
                    'enabled' => true,
                    'url' => '/calendar/public/dfe7cd82-52b9-4c9b-aaed-033df210f23b.ics',
                ],
            ],
        ]);
    }

    public function testUpdateBadKey(): void
    {
        $this->client->put('/api/settings', [
            'inexistant_settings' => 'some-value',
            'eventSummary.customText.title' => null,
        ]);
        $this->assertValidationError([
            'inexistant_settings' => ["This setting does not exists."],
        ]);
    }

    public function testUpdateBadValue(): void
    {
        $this->client->put('/api/settings', [
            'calendar.event.showBorrower' => 'foo',
            'eventSummary.materialDisplayMode' => 'not-valid',
            'eventSummary.customText.title' => str_repeat('A', 192),
            'calendar.public.uuid' => 'not-valid',
        ]);
        $this->assertValidationError([
            'calendar.event.showBorrower' => [
                'Must be a boolean value',
            ],
            'eventSummary.materialDisplayMode' => [
                'One of the following rules must be verified',
                'Must be equal to "categories"',
                'Must be equal to "sub-categories"',
                'Must be equal to "parks"',
                'Must be equal to "flat"',
            ],
            'eventSummary.customText.title' => [
                '191 max. characters',
            ],
            'calendar.public.uuid' => [
                'The unique identifier (UUID) is not valid.',
            ],
        ]);
    }

    public function testUpdate(): void
    {
        $this->client->put('/api/settings', [
            'eventSummary' => [
                'customText' => [
                    'title' => 'foo',
                    'content' => 'bar',
                ],
                'materialDisplayMode' => 'categories',
                'showLegalNumbers' => true,
            ],
            'calendar' => [
                'event' => [
                    'showBorrower' => false,
                    'showLocation' => false,
                ],
                'public' => [
                    'enabled' => false,
                ],
            ],
        ]);
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseData([
            'eventSummary' => [
                'customText' => [
                    'title' => 'foo',
                    'content' => 'bar',
                ],
                'materialDisplayMode' => 'categories',
                'showLegalNumbers' => true,
            ],
            'calendar' => [
                'event' => [
                    'showBorrower' => false,
                    'showLocation' => false,
                ],
                'public' => [
                    'enabled' => false,
                ],
            ],
        ]);

        // - Syntaxe alternative
        $this->client->put('/api/settings', [
            'calendar.event.showBorrower' => true,
            'eventSummary.materialDisplayMode' => 'flat',
            'eventSummary.customText.title' => null,
            'eventSummary.customText.content' => null,
            'eventSummary.showLegalNumbers' => false,
        ]);
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseData([
            'eventSummary' => [
                'customText' => [
                    'title' => null,
                    'content' => null,
                ],
                'materialDisplayMode' => 'flat',
                'showLegalNumbers' => false,
            ],
            'calendar' => [
                'event' => [
                    'showBorrower' => true,
                    'showLocation' => false,
                ],
                'public' => [
                    'enabled' => false,
                ],
            ],
        ]);
    }

    public function testReset(): void
    {
        // - Par d??faut, le mode d'affichage du mat??riel est `sub-categories`.
        $this->client->delete('/api/settings/eventSummary.materialDisplayMode');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseHasKeyEquals('eventSummary.materialDisplayMode', 'sub-categories');

        // - Par d??faut, l'UUID de calendrier est un UUID al??atoire.
        $this->client->delete('/api/settings/calendar.public.url');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseHasKeyNotEquals(
            'calendar.public.url',
            '/calendar/public/dfe7cd82-52b9-4c9b-aaed-033df210f23b.ics'
        );

        // - Par d??faut, le calendrier public est d??sactiv??.
        $this->client->delete('/api/settings/calendar.public.enabled');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseHasKeyEquals('calendar.public.enabled', false);
    }
}
