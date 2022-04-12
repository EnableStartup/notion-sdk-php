<?php

declare(strict_types=1);

namespace Brd6\Test\NotionSdkPhp\Endpoint;

use Brd6\NotionSdkPhp\Client;
use Brd6\NotionSdkPhp\ClientOptions;
use Brd6\NotionSdkPhp\Endpoint\DatabasesEndpoint;
use Brd6\NotionSdkPhp\Resource\Database;
use Brd6\NotionSdkPhp\Resource\Database\DatabaseRequest;
use Brd6\NotionSdkPhp\Resource\Database\PropertyConfiguration\NumberPropertyConfiguration;
use Brd6\NotionSdkPhp\Resource\Database\PropertyConfiguration\SelectPropertyConfiguration;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\CheckboxPropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\DatePropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\FilesPropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\MultiSelectPropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\NumberPropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\PeoplePropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\RichTextPropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\SelectPropertyObject;
use Brd6\NotionSdkPhp\Resource\Database\PropertyObject\TitlePropertyObject;
use Brd6\NotionSdkPhp\Resource\Page\Parent\PageIdParent;
use Brd6\NotionSdkPhp\Resource\Pagination\PageResults;
use Brd6\NotionSdkPhp\Resource\Pagination\PaginationRequest;
use Brd6\NotionSdkPhp\Resource\Property\SelectProperty;
use Brd6\NotionSdkPhp\Resource\RichText\Text;
use Brd6\Test\NotionSdkPhp\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function count;
use function file_get_contents;
use function json_decode;

class DatabasesEndpointTest extends TestCase
{
    public function testInstance(): void
    {
        $client = new Client();
        $databases = new DatabasesEndpoint($client);

        $this->assertInstanceOf(DatabasesEndpoint::class, $client->databases());
        $this->assertInstanceOf(DatabasesEndpoint::class, $databases);
    }

    public function testQueryDatabase(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertEquals('POST', $method);
            $this->assertStringContainsString('databases/a5926cb0-9070-4fea-94f7-494e59a0e75c/query', $url);

            return new MockResponse(
                (string) file_get_contents('tests/fixtures/client_databases_query_200.json'),
                [
                    'http_code' => 200,
                ],
            );
        });

        $options = (new ClientOptions())
            ->setAuth('secret_valid-auth')
            ->setHttpClient($httpClient);

        $client = new Client($options);

        /** @var PageResults $paginationResponse */
        $paginationResponse = $client->databases()->query('a5926cb0-9070-4fea-94f7-494e59a0e75c');

        $this->assertNotNull($paginationResponse);
        $this->assertInstanceOf(PageResults::class, $paginationResponse);

        $this->assertEquals('page', $paginationResponse->getType());
        $this->assertEquals('list', $paginationResponse->getObject());
        $this->assertGreaterThan(0, count($paginationResponse->getResults()));

        $resultPage = $paginationResponse->getResults()[0];

        $this->assertEquals('page', $resultPage->getObject());
        $this->assertNotEmpty($resultPage->getId());
    }

    public function testQueryDatabaseWithPagination(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertEquals('POST', $method);
            $this->assertEquals(2, $options['query']['page_size']);

            return new MockResponse(
                (string) file_get_contents('tests/fixtures/client_databases_query_200.json'),
                [
                    'http_code' => 200,
                ],
            );
        });

        $options = (new ClientOptions())
            ->setAuth('secret_valid-auth')
            ->setHttpClient($httpClient);

        $client = new Client($options);

        $paginationRequest = new PaginationRequest();
        $paginationRequest->setPageSize(2);

        /** @var PageResults $paginationResponse */
        $paginationResponse = $client->databases()
            ->query('a5926cb0-9070-4fea-94f7-494e59a0e75c', null, $paginationRequest);

        $this->assertNotNull($paginationResponse);
    }

    public function testQueryDatabaseWithFilter(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertEquals('POST', $method);

            /** @var array $body */
            $body = json_decode($options['body'], true);

            $this->assertArrayHasKey('filter', $body);
            $this->assertArrayHasKey('property', $body['filter']);
            $this->assertArrayHasKey('select', $body['filter']);
            $this->assertEquals('Reading', $body['filter']['select']['equals']);

            return new MockResponse(
                (string) file_get_contents('tests/fixtures/client_databases_query_200.json'),
                [
                    'http_code' => 200,
                ],
            );
        });

        $options = (new ClientOptions())
            ->setAuth('secret_valid-auth')
            ->setHttpClient($httpClient);

        $client = new Client($options);

        $databaseRequest = new DatabaseRequest();
        $databaseRequest->setFilter([
            'property' => 'Status',
            'select' => [
                'equals' => 'Reading',
            ],
        ]);

        /** @var PageResults $paginationResponse */
        $paginationResponse = $client->databases()
            ->query('a5926cb0-9070-4fea-94f7-494e59a0e75c', $databaseRequest);

        $this->assertNotNull($paginationResponse);
    }

    public function testCreateDatabase(): void
    {
        $httpClient = new MockHttpClient(function ($method, $url, $options) {
            $this->assertStringContainsString('POST', $method);
            $this->assertStringContainsString('databases', $url);

            /** @var array $body */
            $body = json_decode($options['body'], true);

            $this->assertArrayHasKey('parent', $body);
            $this->assertArrayHasKey('title', $body);
            $this->assertArrayHasKey('properties', $body);
            $this->assertArrayHasKey('title', $body['properties']['name']);

            return new MockResponse(
                (string) file_get_contents('tests/fixtures/client_databases_create_database_200.json'),
                [
                    'http_code' => 200,
                ],
            );
        });

        $options = (new ClientOptions())
            ->setHttpClient($httpClient);

        $client = new Client($options);

        $database = new Database();
        $database->setParent((new PageIdParent())->setPageId('4a808e6e88454d49a447fb2a4c460f6f'));
        $database->setTitle([
            Text::fromContent('Grocery List'),
        ]);

        $foodGroupSelectProperties = [
            (new SelectProperty())
                ->setName('🥦Vegetable')
                ->setColor('green'),
            (new SelectProperty())
                ->setName('🍎Fruit')
                ->setColor('red'),
            (new SelectProperty())
                ->setName('💪Protein')
                ->setColor('yellow'),
        ];

        $storeAvailabilitySelectProperties = [
            (new SelectProperty())
                ->setName('Duc Loi Market')
                ->setColor('blue'),
            (new SelectProperty())
                ->setName('Rainbow Grocery')
                ->setColor('gray'),
            (new SelectProperty())
                ->setName('Nijiya Market')
                ->setColor('purple'),
            (new SelectProperty())
                ->setName("Gus's Community Market")
                ->setColor('yellow'),
        ];

        $multiSelectPropertyObject = new MultiSelectPropertyObject();
        $multiSelectPropertyObject->setType('multi_select');
        $multiSelectPropertyObject->setMultiSelect(
            (new SelectPropertyConfiguration())->setOptions($storeAvailabilitySelectProperties),
        );

        $foodGroupSelectObject = new SelectPropertyObject();
        $foodGroupSelectObject->setSelect(
            (new SelectPropertyConfiguration())->setOptions($foodGroupSelectProperties),
        );
        $priceObject = (new NumberPropertyObject())->setNumber(
            (new NumberPropertyConfiguration())->setFormat('dollar'),
        );

        $properties = [
            'name' => new TitlePropertyObject(),
            'description' => new RichTextPropertyObject(),
            'in stock' => new CheckboxPropertyObject(),
            'food group' => $foodGroupSelectObject,
            'price' => $priceObject,
            'last ordered' => new DatePropertyObject(),
            'store availability' => $multiSelectPropertyObject,
            '+1' => new PeoplePropertyObject(),
            'photo' => new FilesPropertyObject(),
        ];
        $database->setProperties($properties);

        $databaseCreated = $client->databases()->create($database);

        $this->assertNotEmpty($databaseCreated->getProperties());
        $this->assertNotEmpty($databaseCreated->getId());
        $this->assertNotEmpty($databaseCreated->getTitle());
    }
}
