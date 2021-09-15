<?php

namespace Mehedi\Sendgrid;

use GuzzleHttp\Client;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Mehedi\Sendgrid\Transport\SendgridTransport;

class LaravelSendgridServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->afterResolving(MailManager::class, function (MailManager $mailManager) {
            $mailManager->extend('sendgrid', function () {
                $client = new Client([
                    'base_uri' => SendgridTransport::BASE_URI,
                    'timeout'  => SendgridTransport::TIMEOUT,
                ]);

                return new SendgridTransport(
                    $client,
                    $this->app['config']['services.sendgrid'],
                    $this->app['config']['mail.mailers.sendgrid']
                );
            });
        });
    }
}
