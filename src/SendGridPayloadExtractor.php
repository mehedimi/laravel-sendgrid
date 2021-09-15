<?php

namespace Mehedi\Sendgrid;

use Illuminate\Contracts\Support\Arrayable;
use Swift_Attachment;
use Swift_Mime_Headers_ParameterizedHeader;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;

class SendGridPayloadExtractor implements Arrayable
{
    /**
     * Swift message object
     *
     * @var Swift_Mime_SimpleMessage $message
     */
    protected $message;

    /**
     * Payload data
     *
     * @var array $payload
     */
    protected $payload = [];

    /**
     * SendGrid reserved headers
     *
     * @var string[] $reservedHeaders
     */
    protected $reservedHeaders = [
        'x-sg-id', 'x-sg-eid', 'received', 'dkim-signature', 'content-type', 'content-transfer-encoding', 'to', 'from',
        'subject', 'reply-to', 'cc', 'bcc'
    ];

    public function __construct(Swift_Mime_SimpleMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get array version of payload from message
     *
     * @return array
     */
    public function get()
    {
        return $this->payload;
    }

    /**
     * Get payload extractor instance
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return SendGridPayloadExtractor
     */
    public static function extract(Swift_Mime_SimpleMessage $message)
    {
        return (new self($message))
            ->to()
            ->cc()
            ->bcc()
            ->from()
            ->body()
            ->replyTo()
            ->subject()
            ->headers()
            ->attachments();
    }

    /**
     * Get mail from address
     *
     * @return $this
     */
    protected function from()
    {
        $this->payload['from'] = $this->mapSingleAddress($this->message->getFrom());

        return $this;
    }

    /**
     * Get recipients email address
     *
     * @return $this
     */
    protected function to()
    {
        $this->payload['personalizations']['to'] = $this->mapMultipleAddress($this->message->getTo());

        return $this;
    }

    /**
     * Extract cc's name and address
     *
     * @return $this
     */
    protected function cc()
    {
        if (is_array($this->message->getCc())) {
            $this->payload['personalizations']['cc'] = $this->mapMultipleAddress($this->message->getCc());
        }

        return $this;
    }

    /**
     * Extract bcc's name and address
     *
     * @return $this
     */
    protected function bcc()
    {
        if (! is_null($this->message->getBcc())) {
            $this->payload['personalizations']['bcc'] = $this->mapMultipleAddress($this->message->getBcc());
        }

        return $this;
    }

    /**
     * Extract reply to address and name
     *
     * @return $this
     */
    protected function replyTo()
    {
        if (is_array($this->message->getReplyTo())) {
            $this->payload['reply_to'] = $this->mapSingleAddress($this->message->getReplyTo());
        }

        return $this;
    }

    /**
     * Extract subject line
     *
     * @return $this
     */
    protected function subject()
    {
        $this->payload['subject'] = $this->message->getSubject();

        return $this;
    }

    /**
     * Extract email body
     *
     * @return $this
     */
    protected function body()
    {
        $this->payload['content'] = $this->getMessageParts();

        return $this;
    }

    /**
     * Extract attachment files
     *
     * @return $this
     */
    protected function attachments()
    {
        $attachments = [];

        foreach ($this->message->getChildren() as $child) {
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
     * Extract valid headers
     *
     * @return $this
     */
    protected function headers()
    {
        $this->payload['headers'] = [
            'X-Message-ID' => $this->message->getId()
        ];

        $this->message->getHeaders()->addTextHeader('X-Message-ID', $this->message->getId());

        foreach ($this->message->getHeaders()->getAll() as $header) {
            /** @var Swift_Mime_Headers_ParameterizedHeader $header */
            if (is_string($header->getFieldBodyModel()) && ! in_array(strtolower($header->getFieldName()), $this->reservedHeaders)) {
                $this->payload['headers'][$header->getFieldName()] = $header->getFieldBodyModel();
            }
        }

        return $this;
    }

    /**
     * Get message parts
     *
     * @return array|null
     */
    protected function getMessageParts()
    {
        /**
         * Valid mime types
         */
        $validMimes = ['text/plain', 'text/html'];

        $contents = [];

        foreach ($this->message->getChildren() as $part) {
            if ($part instanceof Swift_MimePart && in_array($part->getContentType(), $validMimes)) {
                $contents[] = [
                    'type' => $part->getContentType(),
                    'value' => $part->getBody()
                ];
            }
        }

        if (is_null($this->message->getBody()) && empty($contents)) {
            return null;
        }

        if ($this->hasNotHtmlPart($contents)) {
            $contents[] = [
                'type' => 'text/html',
                'value' => $this->message->getBody()
            ];
        }

        usort($contents, function ($first, $second) {
            if ($first['type'] === 'text/plain') {
                return -1;
            }

            if ($first['type'] === 'text/html' && $second['type'] === 'text/x-amp-html') {
                return -1;
            }

            return 1;
        });

        return $contents;
    }

    /**
     * Find if html part is available
     *
     * @param array $parts
     * @return bool
     */
    protected function hasNotHtmlPart(array $parts)
    {
        $html = array_filter($parts, function ($part) {
            return $part['type'] === 'text/html';
        });

        return empty($html);
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

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return $this->get();
    }
}
