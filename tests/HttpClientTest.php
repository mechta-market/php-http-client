<?php declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MechtaMarket\HttpClient\HttpClient;

class HttpClientTest extends PHPUnit\Framework\TestCase
{
    private MockHandler $mockHandler;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
    }

    public function getSuccessResponse(): Response
    {
        return new Response(200, [], 'Hello World');
    }

    public function testClientGetRequest(): void
    {
        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);

        $response = $httpClient->get("www.mechta.kz");

        $this->assertTrue($response->successful());
        $this->assertStringContainsString('Hello World', $response->body());
    }

    public function testClientClientError(): void
    {
        $this->mockHandler->append(new Response(400, [], 'Something wrong'));
        $handlerStack = HandlerStack::create($this->mockHandler);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);

        $response = $httpClient->get("www.mechta.kz");

        $this->assertTrue($response->clientError());
    }

    public function testClientServerError(): void
    {
        $this->mockHandler->append(new Response(500, [], 'Houston, we have a problem'));
        $handlerStack = HandlerStack::create($this->mockHandler);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);

        $response = $httpClient->get("www.mechta.kz");

        $this->assertTrue($response->serverError());
    }

    public function testClientWithCookies(): void
    {
        $httpClient = new HttpClient();
        $httpClient->withCookies(["TestCookie" => "testing"], "httpbin.org");

        $response = $httpClient->get("https://httpbin.org/cookies");

        $this->assertArrayHasKey("cookies", $response->json());
        $this->assertEquals(["TestCookie" => "testing"], $response->json()["cookies"]);
    }

    public function testClientWithResponseCookies(): void
    {
        $httpClient = new HttpClient();
        $httpClient->withCookies(["TestCookie" => "testing"], "httpbin.org");

        $response = $httpClient->get("https://httpbin.org/cookies/set/foo/bar");

        $this->assertArrayHasKey("cookies", $response->json());
        $cookie = $response->cookies()->getCookieByName("foo");

        $this->assertEquals("bar", $cookie->getValue());
    }

    public function testClientWithToken(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);
        $httpClient->withToken("random_string_token_value");

        $httpClient->get("www.mechta.kz");

        $headers = $container[0]['request']->getHeaders();
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals("Bearer random_string_token_value", $headers['Authorization'][0]);
    }

    public function testClientWithBasicAuth(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);
        $httpClient->withBasicAuth("user", "random_string_token_value");

        $httpClient->get("www.mechta.kz");

        $headers = $container[0]['request']->getHeaders();
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith("Basic", $headers['Authorization'][0]);
    }

    public function testClientWithBody(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);
        $httpClient->withBody("Test body");

        $httpClient->post("www.mechta.kz");

        $body = $container[0]['request']->getBody();

        $this->assertEquals("Test body", $body);
    }

    public function testClientSendObjectAsJson(): void
    {
        $requestSubObject = new stdClass();
        $requestSubObject->subAttrString = 'substr';

        $requestObject = new stdClass();
        $requestObject->attrString = 'str';
        $requestObject->attrInt = 123;
        $requestObject->attrArray = [1,2,3];
        $requestObject->attrObject = $requestSubObject;


        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);

        $httpClient->post("www.mechta.kz", $requestObject);

        $body = $container[0]['request']->getBody();

        $this->assertEquals(json_encode($requestObject), $body->getContents());
    }

    public function testClientWithJson(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);

        $httpClient->post("www.mechta.kz", ['test' => 'body']);
        $httpClient->asJson();

        $body = $container[0]['request']->getBody();

        $this->assertEquals('{"test":"body"}', $body);
    }

//    public function testClientWithFile(): void
//    {
//
//    }

    public function testClientWithQueryParameters(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);
        $httpClient->withQueryParameters(["foo" => "bar", "test" => "value"]);

        $httpClient->get("www.mechta.kz");

        $query = $container[0]['request']->getUri()->getQuery();

        $this->assertEquals('foo=bar&test=value', $query);
    }

    public function testClientWithRequestHeaders(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse());
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);
        $httpClient->withHeaders(["Foo" => "Bar"]);

        $httpClient->get("www.mechta.kz");

        $headers = $container[0]['request']->getHeaders();
        $this->assertArrayHasKey('Foo', $headers);
        $this->assertEquals("Bar", $headers['Foo'][0]);
    }

    public function testClientWithResponseHeaders(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $this->mockHandler->append($this->getSuccessResponse()->withHeader("Foo", "Bar"));
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $httpClient = new HttpClient();
        $httpClient->setHandler($handlerStack);

        $httpClient->get("www.mechta.kz");

        $headers = $container[0]["response"]->getHeaders();
        $this->assertArrayHasKey('Foo', $headers);
        $this->assertEquals("Bar", $headers['Foo'][0]);
    }
}