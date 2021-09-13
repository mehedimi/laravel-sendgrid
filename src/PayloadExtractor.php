<?php

namespace Mehedi\Sendgrid;

use Swift_Mime_SimpleMessage;

class PayloadExtractor
{
    protected $message;

    protected $payload = [];

    public function __construct(Swift_Mime_SimpleMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get array version of payload from message
     *
     * @return array
     */
    public function getPayload()
    {
        return $this->payload;
    }

    public static function extract(Swift_Mime_SimpleMessage $message)
    {
        $instance = new self($message);


    }

    protected function extractFrom()
    {
        $this->payload['from'] = $this->mapSingleAddress($this->message->getFrom());

        return $this;
    }
}
