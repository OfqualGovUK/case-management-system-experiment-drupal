# Cases Module

## Introduction

The **Cases** module provides a custom controller and a block that render
a Carbon Design System data table using inline templates. This is useful
for displaying structured case data in a consistent, styled format.

## Features

- **Custom Route**: Displays a table of cases using a controller.
- **Custom Block**: Embeds the same table in a block that can be placed in any region.
- **Carbon Design System Integration**: Uses an inline Twig template to render
  the table with Carbon V1 styles.

## Requirements

* Drupal 11 core
* PHP 8.2 or higher

No additional modules or libraries are required.

## Installation

Install the Cases module using a standard Drupal installation method.

1. Place the  module in your Drupal installation under '/modules/custom'.
2. Enable the module via the UI or Drush:
```
drush en cases -y
drush cr
```

## Usage

- Visit the '/cases' route to view a Carbon-styled data table of
  predefined case entries rendered by the module’s controller.
- Add the **Cases Table Block** in the Block Layout UI to embed
  the same table in any region.
- The table is rendered using the Carbon Design System’s
  `cds-data-table` Twig component for styling consistency.

## Maintainers

* Module: Cases ('cases')
* Maintainer: Ofqual Drupal Team
* License: GNU General Public License v2 or later
