<?php

use GuzzleHttp\Psr7\Response as ApiResponse;
use Mockery as m;
use Symfony\Component\HttpFoundation\Response;
use TookanTech\Chapaar\Drivers\Kavenegar\KavenegarConnector;
use TookanTech\Chapaar\Drivers\Kavenegar\KavenegarMessage;
use TookanTech\Chapaar\Exceptions\ApiException;

beforeEach(fn () => config()->set('chapaar.default', 'kavenegar'));
afterEach(fn () => m::close());
it('can generate endpoint', function () {
    $endpoint = (new KavenegarConnector())::endpoint('sms', 'send.json');
    expect($endpoint)->toBe('https://api.kavenegar.com/v1/sms/send.json');
});
it('should select kavenegar based on config', function () {
    config()->set('chapaar.default', 'kavenegar');
    $driver = (new \TookanTech\Chapaar\SmsMessage())->driver();
    expect(get_class($driver))->toBe(KavenegarMessage::class);

});
it('can send plain message', function () {

    $expected_response = $this->generateResponse(Response::HTTP_OK, 'success');
    // Arrange
    $mockedMessage = m::mock(KavenegarMessage::class);
    $connector = m::mock(KavenegarConnector::class);

    $connector->shouldReceive('send')->once()->with($mockedMessage)->andReturn($expected_response);
    $connector->shouldReceive('endpoint')->with('path')->andReturn('path_response');
    $connector->shouldReceive('performApi')->with('path_response', m::type('array'))->andReturn($expected_response);
    // Act
    $result = $connector->send($mockedMessage);
    // Assert
    expect($result)->toEqual($expected_response);

});

it('can send with template', function () {

    $expected_data = [
        'return' => [
            'status' => 200,
            'message' => 'Success',
        ],
        'entries' => [
            [
                'messageId' => 123,
            ],
        ],
    ];
    $expected_response = $this->generateResponse(Response::HTTP_OK, 'success', $expected_data);
    $mockedMessage = m::mock(KavenegarMessage::class);

    $connector = m::mock(KavenegarConnector::class);
    $connector->shouldReceive('verify')->with($mockedMessage)->once()->andReturn($expected_response);
    $result = $connector->verify($mockedMessage);
    expect($result)->toEqual($expected_response);
});

it('can return exception on empty body', function () {

    $mockedApiResponse = new ApiResponse(404, [], json_encode([]));

    $connector = m::mock(KavenegarConnector::class);
    $connector->shouldAllowMockingProtectedMethods();
    $connector->shouldReceive('processApiResponse')->once()->andThrow(
        new \TookanTech\Chapaar\Exceptions\HttpException('Response is not valid JSON', 404)
    );

    $connector->processApiResponse($mockedApiResponse);
})->throws(\TookanTech\Chapaar\Exceptions\HttpException::class, 'Response is not valid JSON', 404);

it('can return exception on not success status', function () {

    $mockedApiResponse = new ApiResponse(418, [], json_encode(['return' => [
        'status' => 418,
        'message' => 'Insufficient credit',
    ]]));

    $connector = m::mock(KavenegarConnector::class)->makePartial();
    $connector->shouldAllowMockingProtectedMethods();
    $connector->shouldReceive('processApiResponse')->once()->andThrow(
        new ApiException('Insufficient credit', 418)
    );

    $connector->processApiResponse($mockedApiResponse);
})->throws(ApiException::class, 'Insufficient credit', 418);
