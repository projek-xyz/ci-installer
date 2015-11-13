# Composer Installer for Creasi CI-Starter

[![License](https://img.shields.io/packagist/l/projek-xyz/ci-installer.svg?style=flat-square)](https://packagist.org/packages/projek-xyz/ci-installer)
[![Build Status](https://img.shields.io/travis/projek-xyz/ci-installer.svg?style=flat-square)](http://travis-ci.org/projek-xyz/ci-installer)
[![Gittip Donate](http://img.shields.io/gratipay/feryardiant.svg?style=flat-square)](https://www.gratipay.com/feryardiant/ "Donate using Gittip")
[![PayPal Donate](https://img.shields.io/badge/paypal-donate-orange.svg?style=flat-square)](http://j.mp/1Qp9MUT "Donate using Paypal")

**CAUTION: THIS FORK IS CONTAINS MODIFICATIONS FROM [compwright/codeigniter-installers](https://github.com/compwright/codeigniter-installers) TO MEET MY PERSONAL NEED, YOU SHOULD GO WITH THE ORIGINAL ONE INSTEAD.**

## Install

Via [Composer](https://getcomposer.org/)

```bash
$ composer require projek-xyz/ci-installer --prefer-dist
```

## Usage

To use, simply specify the desired `type` from the list below and `require` the
`projek-xyz/ci-installer` package in your `composer.json` file, like so:

```json
{
	"name": "vendor/package",
	"type": "projek-ci-module",
	"require": {
		"projek-xyz/ci-installer": "*"
	}
}
```

It's also support package types that [compwright/codeigniter-installers](https://github.com/compwright/codeigniter-installers#supported-package-types) had except for `spark` (I've remove it, sorry)

## Testing

```bash
phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Credits

1. [Jonathon Hill](https://github.com/compwright) as author of awesome [compwright/codeigniter-installers](https://github.com/compwright/codeigniter-installers).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
