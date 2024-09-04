# Обертка над GuzzleHTTP
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/mechta-market/php-http-client/test.yml)
![Packagist Downloads](https://img.shields.io/packagist/dt/mechta-market/php-http-client)

## Установка
```shell
composer require mechta-market/php-http-client
```

## Примеры:

### GET запрос
```php
$httpClient = new HttpClient();
$response = $httpClient->get("example.org");

var_dump($response->successful());
```

### GET запрос с параметрами
```php
$httpClient = new HttpClient();
$httpClient->withQueryParameters(["foo" => "bar", "test" => "value"]);
$response = $httpClient->get("example.org");

var_dump($response->successful());
```

### POST запрос
```php
$httpClient = new HttpClient();
$data = ["foo" => "bar"];
$response = $httpClient->post("example.org", $data);

var_dump($response->successful());
```

### POST запрос с body
```php
$httpClient = new HttpClient();
$data = ["foo" => "bar"];
$httpClient->withBody($data);

$response = $httpClient->post("example.org");
```

### Bearer token
```php
$httpClient = new HttpClient();
$httpClient->withToken("random_string_token_value");

$response = $httpClient->get("example.org");
```

### Basic auth
```php
$httpClient = new HttpClient();
$httpClient->withBasicAuth("user", "random_string_token_value");

$response = $httpClient->get("example.org");
```
