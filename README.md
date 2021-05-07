# LinkScanner plugin

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://api.travis-ci.org/mirko-pagliai/cakephp-link-scanner.svg?branch=master)](https://travis-ci.org/mirko-pagliai/cakephp-link-scanner)
[![Build status](https://ci.appveyor.com/api/projects/status/hqk7fxtad6r75wk3?svg=true)](https://ci.appveyor.com/project/mirko-pagliai/cakephp-link-scanner)
[![codecov](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner/branch/master/graph/badge.svg)](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner)
[![CodeFactor](https://www.codefactor.io/repository/github/mirko-pagliai/cakephp-link-scanner/badge)](https://www.codefactor.io/repository/github/mirko-pagliai/cakephp-link-scanner)

*LinkScanner* is a CakePHP plugin for recursively scanning links: starting from
a full base url, it performs GET requests, checks the status codes, inspects the
response bodies and, if it finds other links, it continues recursively scanning.

![gif of terminal](https://github.com/mirko-pagliai/cakephp-link-scanner/raw/master/docs/tty.gif)

Did you like this plugin? Its development requires a lot of time for me.
Please consider the possibility of making [a donation](//paypal.me/mirkopagliai):
even a coffee is enough! Thank you.

[![Make a donation](https://www.paypalobjects.com/webstatic/mktg/logo-center/logo_paypal_carte.jpg)](//paypal.me/mirkopagliai)

## Installation
You can install the plugin via composer:
```bash
$ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner
```

Then you have to load the plugin. For more information on how to load the plugin,
please refer to the [Cookbook](//book.cakephp.org/4.0/en/plugins.html#loading-a-plugin).

Simply, you can execute the shell command to enable the plugin:
```bash
bin/cake plugin load LinkScanner
```
This would update your application's bootstrap method.

### Installation on older CakePHP and PHP versions
Recent packages and the master branch require at least CakePHP 4.0 and PHP 7.2.
Instead, the [cakephp3](//github.com/mirko-pagliai/cakephp-link-scanner/tree/cakephp3) branch
requires at least PHP 5.6.

In this case, you can install the package as well:
```bash
$ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner:dev-cakephp3
```

Note that the `cakephp3` branch will no longer be updated as of May 7, 2021,
except for security patches, and it matches the
[1.1.6](//github.com/mirko-pagliai/cakephp-link-scanner/releases/tag/1.1.6) version.

## Configuration
It's not essential, but it may be useful to set the `App.fullBaseUrl` value
correctly [refer to the Cookbook](//book.cakephp.org/4.0/en/development/configuration.html#general-configuration),
especially if you plan to use the plugin mainly on your app, so as not to have
to indicate the full base url which to start the scan every time.

## How to use
Please, refer to the wiki:
*   [How to use the LinkScanner utility](//github.com/mirko-pagliai/cakephp-link-scanner/wiki/How-to-use-the-LinkScanner-utility)
*   [How to use the LinkScannerCommand](//github.com/mirko-pagliai/cakephp-link-scanner/wiki/How-to-use-the-LinkScannerCommand)
*   [Examples for ResultScan](//github.com/mirko-pagliai/cakephp-link-scanner/wiki/Examples-for-ResultScan)

In addition, you can refer to our [API](//mirko-pagliai.github.io/cakephp-link-scanner).

## To do list
*   allow the use of a configuration file for the shell;
*   allow to export results as html and/or xml.

## Versioning
For transparency and insight into our release cycle and to maintain backward
compatibility, *Assets* will be maintained under the
[Semantic Versioning guidelines](//semver.org).
