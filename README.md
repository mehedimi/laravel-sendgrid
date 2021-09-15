![Preview](https://banners.beyondco.de/SendGrid%20Mailer.png?theme=light&packageManager=composer+require&packageName=mehedimi%2Flaravel-sendgrid&pattern=architect&style=style_2&description=A+mailer+that+can+send+email+using+sendgrid++gateway&md=1&showWatermark=0&fontSize=100px&images=mail&widths=300&heights=300)
# SendGrid mailer for Laravel Framework

## Installation
```shell
composer require mehedimi/laravel-sendgrid
```

## Configure

First you need to add the sendgrid api config `services.php` file
```
'sendgrid' => [
  'api_key' => env('SENDGRID_API_KEY')
]
```
Next you need to add the sendgrid mailable config into `mail.php` file on `mailers` array
```
'sendgrid' => [
    'transport' => 'sendgrid',
    'options' => [
      // optional sendgrid `/mail/send` endpoints value (if you need)
    ]
],
```
Third step you need to add `SENDGRID_API_KEY` environment variable on the `.env` file and set value of `MAIL_MAILER` to `sendgrid`
