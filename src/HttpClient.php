<?php

namespace MechtaMarket\HttpClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\TransferStats;
use GuzzleHttp\UriTemplate\UriTemplate;
use MechtaMarket\HttpClient\Exceptions\ConnectionException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

class HttpClient
{
    protected Client $client;

    protected string $baseUrl = '';

    protected $handler;

    protected array $urlParameters = [];

    protected string $bodyFormat = '';

    protected string|StreamInterface|null $pendingBody = null;

    protected array $pendingFiles = [];

    protected CookieJar $cookies;

    protected TransferStats $transferStats;

    protected array $options = [];

    protected int $tries = 1;

    protected bool $async = false;

    protected PromiseInterface $promise;

    protected \MechtaMarket\HttpClient\Request $request;

    protected array $beforeSendingCallbacks = [];

    protected array $mergeableOptions = [
        'cookies',
        'form_params',
        'headers',
        'json',
        'multipart',
        'query',
    ];

    private array $middlewares = [];

    public function __construct()
    {
        $this->asJson();

        $this->cookies = new CookieJar();

        $this->options = [
            'connect_timeout' => 5,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            'http_errors' => false,
            'timeout' => 5,
            'cookies' => $this->cookies,
        ];
    }

    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function appendGuzzleMiddleware(Middleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Прикрепить тело запроса
     */
    public function withBody(string $content, string $contentType = 'application/json'): static
    {
        $this->bodyFormat('body');

        $this->pendingBody = $content;

        $this->contentType($contentType);

        return $this;
    }

    /**
     * Отметить что запрос включает json.
     */
    public function asJson(): static
    {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    /**
     * Отметить что запрос включает форму.
     */
    public function asForm(): static
    {
        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    /**
     * Прикрепить к запросу файл.
     */
    public function attach(string|array $name, string $contents = '', string $filename = null, array $headers = []):
    static
    {
        if (is_array($name)) {
            foreach ($name as $file) {
                $this->attach(...$file);
            }

            return $this;
        }

        $this->asMultipart();

        $this->pendingFiles[] = array_filter([
            'name' => $name,
            'contents' => $contents,
            'headers' => $headers,
            'filename' => $filename,
        ]);

        return $this;
    }

    public function asMultipart(): static
    {
        return $this->bodyFormat('multipart');
    }

    public function bodyFormat(string $format): static
    {
        $this->bodyFormat = $format;
        return $this;
    }

    public function withQueryParameters(array $parameters): static
    {
        $this->options = array_merge_recursive($this->options, [
            'query' => $parameters,
        ]);
        return $this;
    }

    public function contentType(string $contentType): static
    {
        $this->options['headers']['Content-Type'] = $contentType;

        return $this;
    }

    public function acceptJson(): static
    {
        return $this->accept('application/json');
    }

    public function accept(string $contentType): static
    {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    public function withHeaders(array $headers): static
    {
        $this->options = array_merge_recursive($this->options, [
            'headers' => $headers,
        ]);
        return $this;
    }

    /**
     * Заменить заголовки, другие заголовки останутся.
     */
    public function replaceHeaders(array $headers): static
    {
        $this->options['headers'] = array_merge($this->options['headers'] ?? [], $headers);

        return $this;
    }

    public function withBasicAuth(string $username, string $password): static
    {
        $this->options['auth'] = [$username, $password];
        return $this;
    }

    public function withDigestAuth(string $username, string $password): static
    {
        $this->options['auth'] = [$username, $password, 'digest'];
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->options['headers']['Authorization'] = trim($type.' '.$token);
        return $this;
    }

    public function withUserAgent(string $userAgent): static
    {
        $this->options['headers']['User-Agent'] = trim($userAgent);
        return $this;
    }

    public function withUrlParameters(array $parameters = []): static
    {
        $this->urlParameters = $parameters;
        return $this;
    }

    public function withCookies(array $cookies, string $domain): static
    {
        foreach($cookies as $name => $value) {
            $this->cookies->setCookie(new SetCookie([
                'Domain' => $domain,
                'Name' => $name,
                'Value' => $value,
                'Discard' => true,
            ]));
        }
        return $this;
    }

    public function maxRedirects(int $max): static
    {
        $this->options['allow_redirects']['max'] = $max;

        return $this;
    }

    public function withoutRedirecting(): static
    {
        $this->options['allow_redirects'] = false;
        return $this;
    }

    public function withoutVerifying(): static
    {
        $this->options['verify'] = false;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->options['timeout'] = $seconds;

        return $this;
    }

    public function connectTimeout(int $seconds): static
    {
        $this->options['connect_timeout'] = $seconds;
        return $this;
    }

    public function withOptions(array $options): static
    {
        $this->options = array_replace_recursive(
            array_merge_recursive($this->options, array_intersect_key($options, array_flip((array)
            $this->mergeableOptions))),
            $options
        );

        return $this;
    }

    public function beforeSending(callable $callback): static
    {
        $this->beforeSendingCallbacks[] = $callback;
        return $this;
    }

    public function dump(): static
    {
        $values = func_get_args();

        return $this->beforeSending(function (Request $request, array $options) use ($values) {
            foreach (array_merge($values, [$request, $options]) as $value) {
                var_dump($value);
            }
        });
    }

    public function dd(): static
    {
        $values = func_get_args();

        return $this->beforeSending(function (Request $request, array $options) use ($values) {
            foreach (array_merge($values, [$request, $options]) as $value) {
                var_dump($value);
            }

            exit(1);
        });
    }

    public function get(string $url, array|string|null $query = null): Response
    {
        return $this->send('GET', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    public function head(string $url, array|string $query = null): Response
    {
        return $this->send('HEAD', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    public function post(string $url, array $data = []): Response
    {
        return $this->send('POST', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    public function patch(string $url, array $data = []): Response
    {
        return $this->send('PATCH', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    public function put(string $url, $data = []): Response
    {
        return $this->send('PUT', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    public function delete(string $url, $data = []): Response
    {
        return $this->send('DELETE', $url, empty($data) ? [] : [
            $this->bodyFormat => $data,
        ]);
    }

    final function send(string $method, string $url, array $options = []): Response
    {
        if (! $this->hasSchemeInUrl($url)) {
            $url = ltrim(rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/'), '/');
        }

        $url = $this->expandUrlParameters($url);

        $options = $this->parseHttpOptions($options);

        [$this->pendingBody, $this->pendingFiles] = [null, []];

        return $this->sendWithRetries($method, $url, $options);
    }

    public function withRetries(int $int): static
    {
        $this->tries = $int;

        return $this;
    }

    protected function expandUrlParameters(string $url): string
    {
        return UriTemplate::expand($url, $this->urlParameters);
    }

    protected function parseHttpOptions(array $options): array
    {
        if (isset($options[$this->bodyFormat])) {
            if ($this->bodyFormat === 'multipart') {
                $options[$this->bodyFormat] = $this->parseMultipartBodyFormat($options[$this->bodyFormat]);
            } elseif ($this->bodyFormat === 'body') {
                $options[$this->bodyFormat] = $this->pendingBody;
            }

            if (is_array($options[$this->bodyFormat])) {
                $options[$this->bodyFormat] = array_merge(
                    $options[$this->bodyFormat],
                    $this->pendingFiles
                );
            }
        } else {
            $options[$this->bodyFormat] = $this->pendingBody;
        }

        foreach($options as $key => $value) {
            $options[$key] = $value;
        }

        return $options;
    }

    protected function parseMultipartBodyFormat(array $data): array
    {
        $result = [];
        foreach($data as $key => $value) {
            $result[] = is_array($value) ? $value : ['name' => $key, 'contents' => $value];
        }
        return array_values($result);
    }

    protected function sendRequest(string $method, string $url, array $options = []): MessageInterface|PromiseInterface
    {
        $clientMethod = $this->async ? 'requestAsync' : 'request';

        $onStats = function ($transferStats) {
            if (($callback = ($this->options['on_stats'] ?? false)) instanceof \Closure) {
                $transferStats = $callback($transferStats) ?: $transferStats;
            }

            $this->transferStats = $transferStats;
        };

        $mergedOptions = $this->normalizeRequestOptions($this->mergeOptions([
            'on_stats' => $onStats,
        ], $options));

        return $this->buildClient()->$clientMethod($method, $url, $mergedOptions);
    }

    protected function normalizeRequestOptions(array $options): array
    {
        foreach ($options as $key => $value) {
            $options[$key] = match (true) {
                is_array($value) => $this->normalizeRequestOptions($value),
                default => $value,
            };
        }

        return $options;
    }

    protected function populateResponse(Response $response): Response
    {
        $response->cookies = $this->cookies;

        $response->transferStats = $this->transferStats;

        return $response;
    }

    public function buildClient(): Client
    {
        return $this->client ?? $this->createClient($this->buildHandlerStack());
    }

    public function createClient(HandlerStack $handlerStack): Client
    {
        return new Client([
            'handler' => $handlerStack,
            'cookies' => true,
        ]);
    }

    public function buildHandlerStack(): HandlerStack
    {
        $handler = HandlerStack::create($this->handler);
        foreach($this->middlewares as $middleware){
            $handler->push($middleware);
        }

        return $handler;
    }

    public function mergeOptions(...$options): array
    {
        return array_replace_recursive(
            array_merge_recursive($this->options, array_intersect_key($options, array_flip($this->mergeableOptions))),
            ...$options
        );
    }

    protected function newResponse(MessageInterface $response): Response
    {
        return new Response($response);
    }

    public function async(bool $async = true): static
    {
        $this->async = $async;

        return $this;
    }

    public function getPromise(): ?PromiseInterface
    {
        return $this->promise;
    }

    public function setClient(Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    private function hasSchemeInUrl(string $url): bool
    {
        $needles = ['http://', 'https://'];
        foreach ($needles as $needle) {
            if ($needle !== '' && str_starts_with($url, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function getStringAfterCharacter(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    private function sendWithRetries($method, $url, $options): Response
    {
        $times = $this->tries;

        beginning:
        $times--;

        try {
            $response = $this->newResponse($this->sendRequest($method, $url, $options));

            if (! $response->successful()) {
                throw new ConnectionException();
            }

            $this->populateResponse($response);

            return $response;

        } catch (Throwable $e) {
            if ($times < 1) {
                return $response ?? throw $e;
            }

            goto beginning;
        }
    }

    public function setHandler($handler): static
    {
        $this->handler = $handler;

        return $this;
    }
}