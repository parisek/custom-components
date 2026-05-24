# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] — 2026-05-24

Initial standalone release. Distributed via GitHub
(`parisek/custom-components`); installable as a Composer package.

Includes GitHub Actions CI that validates `composer.json` and verifies
the package + its `require-dev` set resolves and installs cleanly
against `packages.drupal.org/8`. PHPUnit and PHPStan are configured
locally (`phpunit.xml.dist`, `phpstan.neon`) but are only exercised
by consumer projects (e.g., htdvere) — full Drupal-bootstrap test
runs in standalone CI are deferred to a later phase.

### Module behavior

Renamed from `porta/custom_components` to `parisek/custom-components`,
licensed `GPL-2.0-or-later`, with runtime `class_exists()` /
`interface_exists()` guards in `EntityHelper` for the optional
integrations `drupal/commerce`, `drupal/office_hours`, and Drupal
core's `comment` module.
