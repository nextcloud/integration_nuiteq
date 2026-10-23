<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 1.3.0 - 2026-09-29

### Added

- Added support for Nextcloud 36

### Changed

- Update Psalm, nextcloud/coding-standard and @nextcloud/eslint-config, and sync the lint workflows
- Update dependencies & translations

### Fixed

- Show the board page of an account whose NUITEQ Stage key is no longer accepted, instead of an empty page
- Show the text of the empty states on the board page again
- Show the buttons that create a board and that send a board link to Talk in the primary style again

## 1.2.0 - 2026-07-27

### Added

- Added support for Nextcloud 35

### Changed

- Migrate to Vue 3 and @nextcloud/vue 9
- Sync the npm build, Psalm and php-cs workflows with the current organization templates
- Update dependencies & translations

### Removed

- Nextcloud 32 and PHP 8.1 support

## 1.1.2 - 2026-04-20

### Added

- Added support for Nextcloud 34

### Changed

- Update dependencies & translations

### Removed

- Nextcloud 30 and 31 support

## 1.1.1 - 2025-11-10

### Added

- Added support for Nextcloud 33

### Changed

- Update dependencies & translations

## 1.1.0 - 2025-07-10

### Added

- Added support for Nextcloud 32

### Removed

- Nextcloud 28 and 29 support

## 1.0.8 - 2024-11-18

### Added

- Added support for Nextcloud 31

### Changed

- Update dependencies

### Fixed

- Encrypt secrets in the database and do not expose them to the UI

## 1.0.7 - 2024-07-24

### Added

- Added support for Nextcloud 30

### Changed

- Update dependencies

### Removed

- Nextcloud 27 support

### Fixed

- Exception when clicking on the integration icon in the top menu

## 1.0.6 - 2024-03-07

### Added

- Added support for Nextcloud 29

### Changed

- Update dependencies

### Removed

- Nextcloud 25 and 26 support

## 1.0.5 - 2023-10-24

### Added

- Added support for Nextcloud 28

### Changed

- Update dependencies

### Removed

- Nextcloud 22 to 24 support

## 1.0.4 - 2023-03-24

### Added

- Added support for Nextcloud 26 and 27

### Changed

- Use the latest @nextcloud/vue and make adjustments

## 1.0.3 - 2022-09-07

### Fixed

- Style of the Talk modal: paddings/margins, always show the header/footer

## 1.0.1 - 2022-09-06

### Changed

- App description

## 1.0.0 - 2022-09-05

### Added

- Initial release for Nextcloud 22 to 25
