# Link scanner plugin

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://api.travis-ci.org/mirko-pagliai/cakephp-link-scanner.svg?branch=master)](https://travis-ci.org/mirko-pagliai/cakephp-link-scanner)
[![Build status](https://ci.appveyor.com/api/projects/status/hqk7fxtad6r75wk3?svg=true)](https://ci.appveyor.com/project/mirko-pagliai/cakephp-link-scanner)
[![codecov](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner/branch/master/graph/badge.svg)](https://codecov.io/gh/mirko-pagliai/cakephp-link-scanner)

*Link scanner* is a CakePHP plugin to scan links.

## Installation
You can install the plugin via composer:

    $ composer require --prefer-dist mirko-pagliai/cakephp-link-scanner
    
Then you have to edit `APP/config/bootstrap.php` to load the plugin:

    Plugin::load('LinkScanner', ['bootstrap' => true]);

For more information on how to load the plugin, please refer to the 
[Cookbook](http://book.cakephp.org/3.0/en/plugins.html#loading-a-plugin).

## To do list
* allow optional scanning of external links, without recursion;
* allow the use of a configuration file for the shell;
* allow to use a regex to exclude links.

## Versioning
For transparency and insight into our release cycle and to maintain backward 
compatibility, *Assets* will be maintained under the 
[Semantic Versioning guidelines](http://semver.org).
