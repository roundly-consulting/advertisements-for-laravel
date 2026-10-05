<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/advertisements-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/advertisements-for-laravel/main/art/hero.png" alt="Advertisements for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/advertisements-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/advertisements-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/advertisements-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/advertisements-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/advertisements-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/advertisements-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Advertisements for Laravel

Run ads in your Laravel app: a draft → schedule → publish → expire → archive lifecycle,
placements (zones) that serve the live ads, impression and click tracking with click-through
rates, per-locale names and slugs, exact `Money` prices, geo-targeting and a responsive creative
per placement with a text-ad fallback.

## Installation

Requires PHP 8.4 with `ext-bcmath`, and Laravel 12 or 13.

```bash
composer require roundly-consulting/advertisements-for-laravel
php artisan vendor:publish --tag="advertisements-migrations"
php artisan vendor:publish --tag="media-migrations"   # creatives live in media-library-for-laravel
php artisan migrate
```

If the models that author ads use UUID or ULID keys, set `ADVERTISEMENTS_KEY_TYPE=uuid` / `ulid`
**before** migrating.

## Usage

Create an ad, place it in a zone and publish it:

```php
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Money\Money;

Placement::create(['slug' => 'sidebar', 'name' => ['en' => 'Sidebar']]);

$ad = Advertisements::create(new AdvertisementData(
    name: 'Vintage road bike',
    price: Money::ofMajor('250.00', 'EUR'),
    author: $user,                                  // any Eloquent model
));

Advertisements::for($ad)->placements()->attach(['sidebar']);
Advertisements::publish($ad);                       // live now; pass a date to schedule it
```

Serve the zone and count what happens:

```php
$ad = Advertisements::random('sidebar');            // one live ad for the zone

echo Advertisements::render($ad, 'sidebar');        // its creative, or the text-ad fallback

Advertisements::for($ad)->track('sidebar')->impression();
Advertisements::for($ad)->track('sidebar')->click();

$ad->impressions();                                 // 1
$ad->ctr();                                         // 1.0
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/advertisements-for-laravel](https://roundly-consulting.com/open-source/docs/advertisements-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
