<?php

namespace MechtaMarket\HttpClient\Exceptions;

use GuzzleHttp\Psr7\Message;
use \MechtaMarket\HttpClient\Response;

class RequestException extends \Exception
{
    public $response;

    /**
     * Create a new exception instance.
     *
     * @param  \MechtaMarket\HttpClient\Response  $response
     * @return void
     */
    public function __construct(Response $response)
    {
        parent::__construct($this->prepareMessage($response), $response->status());

        $this->response = $response;
    }

    /**
     * Prepare the exception message.
     *
     * @param  \MechtaMarket\HttpClient\Response  $response
     * @return string
     */
    protected function prepareMessage(Response $response): string
    {
        $message = "HTTP request returned status code {$response->status()}";

        $summary = Message::bodySummary($response->toPsrResponse());

        return is_null($summary) ? $message : $message .= ":\n{$summary}\n";
    }
}