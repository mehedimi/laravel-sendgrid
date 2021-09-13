<?php

namespace Mehedi\Tests;

use GuzzleHttp\Client;
use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Mail\MailManager;
use Mehedi\Sendgrid\Transport\SendgridTransport;
use PHPUnit\Framework\TestCase;

class SendgridTransportTest extends TestCase
{

    public function testGetTransport()
    {
        $transport = $this->mailTransport();

        $this->assertInstanceOf(SendgridTransport::class, $transport);
    }

    public function testPayload()
    {
        /** @var SendgridTransport $transport */
        $transport = $this->mailTransport();

        $message = new \Swift_Message('Subject', 'body', 'text/html');

        $message->setFrom('from@example.com', 'From Name')
            ->setTo('to@example.com', 'To name')
            ->setBcc('bcc1@example.com', 'BCC Name')
            ->setCc('cc@example.com', 'CC name')
            ->setReplyTo('reply@example.com')
            ->addPart('html', 'text/html')
            ->addPart('text', 'text/plan');

        $payload = $transport->getPayload($message);

        $this->assertEquals(['email' => 'from@example.com', 'name' => 'From Name'], $payload['from']);
        $this->assertEquals([['email' => 'to@example.com', 'name' => 'To name']], $payload['personalizations']['to']);
        $this->assertEquals([['email' => 'cc@example.com', 'name' => 'CC name']], $payload['personalizations']['cc']);
        $this->assertEquals([['email' => 'bcc1@example.com', 'name' => 'BCC Name']], $payload['personalizations']['bcc']);
        $this->assertEquals(['email' => 'reply@example.com', 'name' => ''], $payload['reply_to']);
    }

    protected function mailTransport()
    {
        $container = new Container;

        $container->singleton('config', function () {
            return new Repository([
                'services.sendgrid' => [
                    'api_key' => 'foo'
                ],
            ]);
        });

        $manager = new MailManager($container);

        $manager->extend('sendgrid', function () use ($container) {
            return new SendgridTransport(new Client(), $container['config']['services.sendgrid']);
        });

        return $manager->createTransport(['transport' => 'sendgrid']);
    }

}
