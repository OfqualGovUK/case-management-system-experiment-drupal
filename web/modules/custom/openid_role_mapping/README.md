# OpenId Role Mapping Module

## Introduction

This module extends the functionality of the OpenID Connect module in Drupal by allowing automatic role assignment based on claims received from the identity provider.

## Features

- Maps external roles from OpenID Connect claims to Drupal roles.
- Supports nested claim structures.
- Configurable claim name and role mapping

## Requirements

* Drupal 11 core
* PHP 8.2 or higher
* https://www.drupal.org/project/openid_connect

## Installation

Install the module using a standard Drupal installation method.

1. Place the module in your Drupal installation under '/modules/custom/'.
2. Enable the module via the UI or Drush:
```
 drush en openid_role_mapping -y
 drush cr
 ```

## Maintainers

* Module: OpenId Role Mapping
* Maintainer: Ofqual Drupal Team
* License: GNU General Public License v2 or later
