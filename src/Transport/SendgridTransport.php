<?php

namespace Mehedi\Sendgrid\Transport;

use GuzzleHttp\Client;
use Illuminate\Mail\Transport\Transport;
use Mehedi\Sendgrid\PayloadExtractor;
use Swift_Attachment;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;

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
        $payload = new PayloadExtractor($message);
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
            ->extractBody($message)
            ->extractAttachments($message);

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
        $this->payload['from'] = $this->mapSingleAddress($message->getFrom());

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
        $this->payload['personalizations']['to'] = $this->mapMultipleAddress($message->getTo());

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
        $this->payload['personalizations']['cc'] = $this->mapMultipleAddress($message->getCc());

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
        $this->payload['personalizations']['bcc'] = $this->mapMultipleAddress($message->getBcc());

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
        $this->payload['reply_to'] = $this->mapSingleAddress($message->getReplyTo());

        return $this;
    }

    /**
     * Extract email body
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendgridTransport
     */
    protected function extractBody(Swift_Mime_SimpleMessage &$message)
    {
        $this->payload['content'] = $this->getMessagePart($message);

        return $this;
    }

    /**
     * Extract attachment files
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return $this
     */
    protected function extractAttachments(Swift_Mime_SimpleMessage &$message)
    {
        $attachments = [];

        foreach ($message->getChildren() as $child) {
            if (!$child instanceof Swift_Attachment) {
                continue;
            }

            $attachments[] = [
                'content'     => base64_encode($child->getBody()),
                'filename'    => $child->getFilename(),
                'type'        => $child->getContentType(),
                'disposition' => $child->getDisposition(),
                'content_id'  => $child->getId(),
            ];
        }

        if (! empty($attachments)) {
            $this->payload['attachments'] = $attachments;
        }

        return $this;
    }

    /**
     * Get message parts
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array|null
     */
    protected function getMessagePart(Swift_Mime_SimpleMessage &$message)
    {
        /**
         * Valid mime types
         */
        $validMimes = ['text/plan', 'text/html'];

        $contents = [];

        foreach ($message->getChildren() as $part) {
            if ($part instanceof Swift_MimePart && in_array($part->getContentType(), $validMimes)) {
                $contents[] = [
                    'type' => $part->getContentType(),
                    'value' => $part->getBody()
                ];
            }
        }


        if (is_null($message->getBody()) && empty($contents)) {
            return null;
        }

        $contentType = $message->getContentType();

        $hasExists = array_filter($contents, function ($part) use ($contentType) {
            return $contentType === $part['type'];
        });

        if (! $hasExists && in_array($contentType, $validMimes)) {
            $contents[] = [
                'type' => $contentType,
                'value' => $message->getBody()
            ];
        }

        usort($contents, function ($first, $second) {
            if ($first['type'] === 'text/plan') {
                return -1;
            }

            return 1;
        });

        return $contents;
    }

    /**
     * Map single address
     *
     * @param array $address
     * @return array
     */
    protected function mapSingleAddress(array $address)
    {
        return [
            'email' => key($address),
            'name' => $address[key($address)]
        ];
    }

    /**
     * Map multiple addresses
     *
     * @param array $addresses
     * @return array|array[]
     */
    protected function mapMultipleAddress(array $addresses)
    {
        return array_map(function ($email) use ($addresses) {
            return [
                'email' => $email,
                'name' => $addresses[$email]
            ];
        }, array_keys($addresses));
    }
}
