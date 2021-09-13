<?php

namespace Mehedi\Sendgrid\Transport;

use GuzzleHttp\Client;
use Illuminate\Mail\Transport\Transport;
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
     * @var Client
     */
    protected $client;

    /**
     * @var array $config
     */
    protected $config;

    /**
     * Payload of mail
     *
     * @var array
     */
    protected $payload;

    public function __construct(Client $client, array $config)
    {
        $this->config = $config;
        $this->client = $client;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $payload = $this->getPayload($message);
    }

    /**
     * Get array data from message object
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    public function getPayload(Swift_Mime_SimpleMessage &$message)
    {
        $this->payload = [];

        $this->extractFrom($message)
            ->extractTo($message)
            ->extractCc($message)
            ->extractBcc($message)
            ->extractBcc($message)
            ->extractReplyTo($message)
            ->extractBody($message);

        return $this->payload;
    }

    /**
     * Extract from address and name
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendgridTransport
     */
    protected function extractFrom(Swift_Mime_SimpleMessage &$message)
    {
        $from = $message->getFrom();

        $this->payload['from'] = [
            'email' => key($from),
            'name' => $from[key($from)]
        ];

        return $this;
    }

    /**
     * Extract recipients name and address
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendgridTransport
     */
    protected function extractTo(Swift_Mime_SimpleMessage &$message)
    {
        $to = $message->getTo();

        $this->payload['to'] = array_map(function ($email) use ($to) {
            return [
                'email' => $email,
                'name' => $to[$email]
            ];
        }, array_keys($to));

        return $this;
    }

    /**
     * Extract cc's name and address
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendgridTransport
     */
    protected function extractCc(Swift_Mime_SimpleMessage &$message)
    {
        $cc = $message->getCc();

        $this->payload['cc'] = array_map(function ($email) use ($cc) {
            return [
                'email' => $email,
                'name' => $cc[$email]
            ];
        }, array_keys($cc));

        return $this;
    }

    /**
     * Extract bcc's name and address
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendgridTransport
     */
    protected function extractBcc(Swift_Mime_SimpleMessage &$message)
    {
        $bcc = $message->getBcc();

        $this->payload['bcc'] = array_map(function ($email) use ($bcc) {
            return [
                'email' => $email,
                'name' => $bcc[$email]
            ];
        }, array_keys($bcc));

        return $this;
    }

    /**
     * Extract reply to address and name
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendgridTransport
     */
    protected function extractReplyTo(Swift_Mime_SimpleMessage &$message)
    {
        $replyTo = $message->getReplyTo();

        $this->payload['reply_to'] = [
            'email' => key($replyTo),
            'name' => $replyTo[key($replyTo)]
        ];

        return $this;
    }

    /**
     * Extract email body
     *
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function extractBody(Swift_Mime_SimpleMessage &$message)
    {
        $body = $message->getBody();

        dd($body);
    }
}
