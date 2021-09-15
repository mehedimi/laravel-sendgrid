<?php

namespace Mehedi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Mail\MailManager;
use Mehedi\Sendgrid\SendGridPayloadExtractor;
use Mehedi\Sendgrid\Transport\SendgridTransport;
use PHPUnit\Framework\TestCase;
use Swift_Attachment;
use Swift_Message;
use Mockery as m;

class SendgridTransportTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testGetTransport()
    {
        $transport = $this->mailTransport();

        $this->assertInstanceOf(SendgridTransport::class, $transport);
    }

    public function testPayload()
    {
        $message = new \Swift_Message('Subject', 'body', 'text/html');

        $message->setFrom('from@example.com', 'From Name')
            ->setTo('to@example.com', 'To name')
            ->setBcc('bcc1@example.com', 'BCC Name')
            ->setCc('cc@example.com', 'CC name')
            ->setReplyTo('reply@example.com')
            ->addPart('text', 'text/plain')
            ->attach(Swift_Attachment::fromPath(__DIR__.'/test.txt'))
            ->getHeaders()->addTextHeader('X-PACKAGE-NAME', 'laravel-sendgrid');

        $payload = SendGridPayloadExtractor::extract($message)->toArray();

        $this->assertEquals(['email' => 'from@example.com', 'name' => 'From Name'], $payload['from']);
        $this->assertEquals([['email' => 'to@example.com', 'name' => 'To name']], $payload['personalizations'][0]['to']);
        $this->assertEquals([['email' => 'cc@example.com', 'name' => 'CC name']], $payload['personalizations'][1]['cc']);
        $this->assertEquals([['email' => 'bcc1@example.com', 'name' => 'BCC Name']], $payload['personalizations'][2]['bcc']);
        $this->assertEquals(['email' => 'reply@example.com', 'name' => ''], $payload['reply_to']);
        $this->assertEquals('Subject', $payload['subject']);

        $this->assertCount(2, $payload['content']);
        $this->assertEquals('text/plain', $payload['content'][0]['type']);
        $this->assertEquals('text/html', $payload['content'][1]['type']);
        $this->assertEquals('body', $payload['content'][1]['value']);
        $this->assertEquals('text', $payload['content'][0]['value']);

        $this->assertCount(1, $payload['attachments']);
        $this->assertEquals(base64_encode(file_get_contents(__DIR__.'/test.txt')), $payload['attachments'][0]['content']);

        $this->assertEquals('test.txt', $payload['attachments'][0]['filename']);
        $this->assertEquals('text/plain', $payload['attachments'][0]['type']);

        $this->assertCount(3, $payload['headers']);
        $this->assertEquals('laravel-sendgrid', $payload['headers']['X-PACKAGE-NAME']);
    }

    public function testSend()
    {
        $message = new Swift_Message('Foo subject', 'Bar body');
        $message->setFrom('myself@example.com');
        $message->setTo('me@example.com');
        $message->setBcc('you@example.com');
        $message->addReplyTo('reply@example.com');

        $client = m::mock(Client::class);

        $client->shouldReceive('post')
            ->once()
            ->with('mail/send', [
                RequestOptions::JSON => [
                    'subject' => 'Foo subject',
                    'personalizations' => [
                        [
                            'to' => [
                                ['name' => null, 'email' => 'me@example.com']
                            ]
                        ],
                        [
                            'bcc' => [
                                ['name' => null, 'email' => 'you@example.com']
                            ]
                        ]
                    ],
                    'reply_to' => [
                        'email' => 'reply@example.com', 'name' => null
                    ],
                    'content' => [
                        [
                            'type' => 'text/html',
                            'value' => 'Bar body'
                        ]
                    ],
                    'from' => ['name' => null, 'email' => 'myself@example.com'],
                    'headers' => [
                        'X-Message-ID' => $message->getId(),
                        'MIME-Version' => '1.0'
                    ]
                ],
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer test'
                ]
            ]);

        $transport = new SendgridTransport($client, ['api_key' => 'test']);

        $numberOfRecipients = $transport->send($message);

        $this->assertEquals(2, $numberOfRecipients);
        $this->assertEquals($message->getId(), $message->getHeaders()->get('X-Message-ID')->getFieldBody());
    }

    public function testSendGridLocalConfiguration()
    {
        $client = m::mock(Client::class);

        $message = new Swift_Message('subject', 'body');

        $message->setFrom('from@example.com')
            ->setTo('to@example.com');

        $client->shouldReceive('post')
            ->once()
            ->with('mail/send', [
                RequestOptions::JSON => [
                    'subject' => 'subject',
                    'personalizations' => [
                        [
                            'to' => [
                                ['name' => null, 'email' => 'to@example.com']
                            ]
                        ]
                    ],
                    'from' => [
                        'name' => null,
                        'email' => 'from@example.com'
                    ],
                    'content' => [
                        [
                            'type' => 'text/html',
                            'value' => 'body'
                        ]
                    ],
                    'headers' => [
                        'X-Message-ID' => $message->getId(),
                        'MIME-Version' => '1.0'
                    ],
                    'tracking_settings' => [
                        'click_tracking' => [
                            'enable' => false
                        ]
                    ]
                ],
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer test'
                ]
            ]);

        $transport = new SendgridTransport($client, ['api_key' => 'test'], [
            'options' => [
                'tracking_settings' => [
                    'click_tracking' => [
                        'enable' => false
                    ]
                ]
            ]
        ]);

        $numberOfRecipients = $transport->send($message);

        $this->assertEquals(1, $numberOfRecipients);
    }

    protected function mailTransport()
    {
        $container = new Container;

        $container->singleton('config', function () {
            return new Repository([
                'services.sendgrid' => [
                    'api_key' => 'foo'
                ]
            ]);
        });

        $manager = new MailManager($container);

        $manager->extend('sendgrid', function () use ($container) {
            return new SendgridTransport(
                new Client(), $container['config']['services.sendgrid']
            );
        });

        return $manager->createTransport(['transport' => 'sendgrid']);
    }
}
