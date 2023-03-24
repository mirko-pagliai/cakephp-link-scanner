# LinkScanner plugin

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![CI](https://github.com/mirko-pagliai/cakephp-link-scanner/actions/workflows/ci.yml/badge.svg)](https://github.com/mirko-pagliai/cakephp-link-scanner/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner/branch/master/graph/badge.svg)](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/519cd9567f2848b68ed3df0f58f6cfc5)](https://www.codacy.com/gh/mirko-pagliai/cakephp-link-scanner/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=mirko-pagliai/cakephp-link-scanner&amp;utm_campaign=Badge_Grade)
[![CodeFactor](https://www.codefactor.io/repository/github/mirko-pagliai/cakephp-link-scanner/badge)](https://www.codefactor.io/repository/github/mirko-pagliai/cakephp-link-scanner)

*LinkScanner* is a CakePHP plugin for recursively scanning links: starting from
a full base url, it performs GET requests, checks the status codes, inspects the
response bodies and, if it finds other links, it continues recursively scanning.

![gif of terminal](https://github.com/mirko-pagliai/cakephp-link-scanner/raw/master/docs/tty.gif)

Did you like this plugin? Its development requires a lot of time for me.
Please consider the possibility of making [a donation](//paypal.me/mirkopagliai):
even a coffee is enough! Thank you.

[![Make a donation](https://www.paypalobjects.com/webstatic/mktg/logo-center/logo_paypal_carte.jpg)](https://paypal.me/mirkopagliai)

***

  * [Installation](#installation)
    + [Installation on older CakePHP and PHP versions](#installation-on-older-cakephp-and-php-versions)
      - [For PHP 7.2 or later](#for-php-72-or-later)
      - [For PHP 5.6 and CakePHP 3 or later](#for-php-56-and-cakephp-3-or-later)
  * [Configuration](#configuration)
  * [How to use](#how-to-use)
  * [To do list](#to-do-list)
  * [Versioning](#versioning)

## Installation
You can install the plugin via composer:
```bash
$ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner
```

Then you have to load the plugin. For more information on how to load the plugin,
please refer to the [Cookbook](https://book.cakephp.org/4.0/en/plugins.html#loading-a-plugin).

Simply, you can execute the shell command to enable the plugin:
```bash
bin/cake plugin load LinkScanner
```
This would update your application's bootstrap method.

### Installation on older CakePHP and PHP versions
Recent packages and the master branch require at least CakePHP 4.3 and PHP 7.4
and the current development of the code is based on these and later versions of
CakePHP and PHP.
However, there are still some branches compatible with previous versions of
CakePHP and PHP.

#### For PHP 7.2 or later
The [php7.2](https://github.com/mirko-pagliai/cakephp-link-scanner/tree/php7.2) branch
requires at least PHP 7.2.

In this case, you can install the package as well:
```bash
$ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner:dev-php7.2
```

Note that the `php7.2` branch will no longer be updated as of May 3, 2022,
except for security patches, and it matches the
[1.1.11](https://github.com/mirko-pagliai/cakephp-link-scanner/releases/tag/1.1.11) version.

#### For PHP 5.6 and CakePHP 3 or later
The [cakephp3](//github.com/mirko-pagliai/cakephp-link-scanner/tree/cakephp3) branch
requires at least PHP 5.6 and CakePHP 3.

In this case, you can install the package as well:
```bash
$ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner:dev-cakephp3
```

Note that the `cakephp3` branch will no longer be updated as of May 7, 2021,
except for security patches, and it matches the
[1.1.6](//github.com/mirko-pagliai/cakephp-link-scanner/releases/tag/1.1.6) version.

## Configuration
It's not essential, but it may be useful to set the `App.fullBaseUrl` value
correctly [refer to the Cookbook](https://book.cakephp.org/4.0/en/development/configuration.html#general-configuration),
especially if you plan to use the plugin mainly on your app, so as not to have
to indicate the full base url which to start the scan every time.

## How to use
Please, refer to the wiki:
*   [How to use the LinkScanner utility](https://github.com/mirko-pagliai/cakephp-link-scanner/wiki/How-to-use-the-LinkScanner-utility)
*   [How to use the LinkScannerCommand](https://github.com/mirko-pagliai/cakephp-link-scanner/wiki/How-to-use-the-LinkScannerCommand)
*   [Examples for ResultScan](https://github.com/mirko-pagliai/cakephp-link-scanner/wiki/Examples-for-ResultScan)

In addition, you can refer to our [API](https://mirko-pagliai.github.io/cakephp-link-scanner).

## To do list
*   allow the use of a configuration file for the shell;
*   allow to export results as html and/or xml.

## Versioning
For transparency and insight into our release cycle and to maintain backward
compatibility, *Assets* will be maintained under the
[Semantic Versioning guidelines](https://semver.org).
