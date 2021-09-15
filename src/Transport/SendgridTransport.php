<?php

namespace Mehedi\Sendgrid\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Mail\Transport\Transport;
use Mehedi\Sendgrid\SendGridPayloadExtractor;
use Swift_Mime_SimpleMessage;

class SendgridTransport extends Transport
{
    /**
     * Base uri of sendgrid API
     */
    const BASE_URI = 'https://api.sendgrid.com/v3/';

    /**
     * Request timeout in seconds
     */
    const TIMEOUT = 60;

    /**
     * Http client
     *
     * @var Client $client
     */
    protected $client;

    /**
     * SendGrid service configuration
     *
     * @var array $config
     */
    protected $config;

    /**
     * SendGrid mailer options
     *
     * @var array $mailerOptions
     */
    protected $mailerOptions;


    public function __construct(Client $client, array $config, array $mailerOptions = [])
    {
        $this->config = $config;
        $this->client = $client;
        $this->mailerOptions = $mailerOptions;
    }

    /**
     * @inheritdoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $payload = SendGridPayloadExtractor::extract($message)->toArray();

        $this->client->post('mail/send', [
            RequestOptions::JSON => array_merge($payload, $this->mailerOptions['options'] ?? []),
            RequestOptions::HEADERS => [
                'Authorization' => sprintf('Bearer %s', $this->config['api_key'])
            ]
        ]);

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}
