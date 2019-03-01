# LinkScanner plugin

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://api.travis-ci.org/mirko-pagliai/cakephp-link-scanner.svg?branch=master)](https://travis-ci.org/mirko-pagliai/cakephp-link-scanner)
[![Build status](https://ci.appveyor.com/api/projects/status/hqk7fxtad6r75wk3?svg=true)](https://ci.appveyor.com/project/mirko-pagliai/cakephp-link-scanner)
[![codecov](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner/branch/master/graph/badge.svg)](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner)

*LinkScanner* is a CakePHP plugin to scan links.

Did you like this plugin? Its development requires a lot of time for me.
Please consider the possibility of making [a donation](//paypal.me/mirkopagliai):  
even a coffee is enough! Thank you.

[![Make a donation](https://www.paypalobjects.com/webstatic/mktg/logo-center/logo_paypal_carte.jpg)](//paypal.me/mirkopagliai)

## Installation
You can install the plugin via composer:
```bash
$ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner
```
    
Then you have to edit `APP/config/bootstrap.php` to load the plugin:
```php
Plugin::load('LinkScanner', ['bootstrap' => true]);
```

For more information on how to load the plugin, please refer to the 
[Cookbook](http://book.cakephp.org/3.0/en/plugins.html#loading-a-plugin).

## How to use
Please, refer to the wiki:
- **[How to use the LinkScanner utility](https://github.com/mirko-pagliai/cakephp-link-scanner/wiki/How-to-use-the-LinkScanner-utility)**
- **[Examples for ResultScan](https://github.com/mirko-pagliai/cakephp-link-scanner/wiki/Examples-for-ResultScan)**

In addition, you can refer to our [API](//mirko-pagliai.github.io/cakephp-link-scanner).

## To do list
* allow the use of a configuration file for the shell;

## Versioning
For transparency and insight into our release cycle and to maintain backward 
compatibility, *Assets* will be maintained under the 
[Semantic Versioning guidelines](http://semver.org).
