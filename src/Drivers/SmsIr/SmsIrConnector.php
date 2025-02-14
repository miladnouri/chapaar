<?php

namespace TookanTech\Chapaar\Drivers\SmsIr;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TookanTech\Chapaar\Contracts\DriverConnector;
use TookanTech\Chapaar\Exceptions\ApiException;
use TookanTech\Chapaar\Exceptions\HttpException;
use TookanTech\Chapaar\Traits\HasResponse;

class SmsIrConnector implements DriverConnector
{
    use HasResponse;

    protected Client $client;

    public function __construct()
    {
        self::$setting = (object) config('chapaar.drivers.smsir');
        $this->client = new Client([
            'headers' => [
                'x-api-key' => self::$setting->api_key,
                'Accept' => 'text/plain',
                'Content-Type' => 'application/json',
            ],
        ]);

    }

    /**
     * @param  SmsIrMessage  $message
     *
     * @throws GuzzleException|HttpException|ApiException
     */
    public function send($message): object
    {
        $url = self::endpoint('send', 'bulk');
        $params = [
            'lineNumber' => $message->getFrom() ?: self::$setting->line_number,
            'MessageText' => $message->getContent(),
            'Mobiles' => (array) $message->getTo(),
            'SendDateTime' => $message->getDate() ?? null,
        ];

        $response = $this->performApi($url, $params);

        return $this->generateResponse($response->status, $response->message, (array) $response->data);
    }

    /**
     * @param  SmsIrMessage  $message
     *
     * @throws GuzzleException
     */
    public function verify($message): object
    {
        $receiver = $message->getTo();
        $url = self::endpoint('send', 'verify');
        $params = [
            'mobile' => $receiver,
            'templateId' => $message->getTemplate(),
            'parameters' => $message->getTokens(),
        ];

        $response = $this->performApi($url, $params);

        return $this->generateResponse($response->status, $response->message, (array) $response->data);

    }

    /**
     * @throws GuzzleException
     */
    public function account(): object
    {
        $url = self::endpoint('credit');

        //todo:: use performApi method by passing request type to the method
        $response = $this->client->get($url);
        $response = $this->processApiResponse($response);

        return $this->generateAccountResponse($response->data, 0);
    }

    /**
     * @throws GuzzleException
     */
    public function outbox($page_size = 100, $page_number = 1): object
    {
        $url = self::endpoint('send', 'live')."?PageSize=$page_size&PageNumber=$page_number";

        $response = $this->client->get($url);
        $response = $this->processApiResponse($response);

        return collect($response->data)->map(function ($item) {
            return $this->generateReportResponse($item->messageId, $item->mobile, $item->messageText, $item->sendDateTime, $item->lineNumber, $item->cost);
        });
    }

    /**
     * @throws GuzzleException
     */
    public function performApi(string $url, array $params = []): object
    {
        $response = $this->client->post($url, [
            'json' => $params,
        ]);

        return $this->processApiResponse($response);
    }

    /**
     * @throws HttpException | ApiException
     */
    protected function validateResponseStatus($status_code, $json_response): void
    {
        if ($json_response === null) {
            throw new HttpException('Response is not valid JSON', $status_code);
        }

        if ($json_response->status !== 1) {
            throw new ApiException($json_response->message, $json_response->status);
        }
    }
}
