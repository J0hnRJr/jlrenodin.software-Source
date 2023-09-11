# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.4.0] - 2023-08-11

### Changed

- [#178](https://github.com/owncloud/metrics/pull/178) - Symfony 5

## [1.3.0] - 2023-05-11

### Fixed

- [#162](https://github.com/owncloud/metrics/pull/162) - [QA] external storage usage counts against primary storage size
- [#163](https://github.com/owncloud/metrics/pull/163) - [QA] overwriting a file in external storage counts against quota, even if no new version is generated 
- [#5700](https://github.com/owncloud/enterprise/issues/5700) - Metrics - default and unlimited quota is shown as 0 Bytes 


## [1.2.0] - 2023-03-21

- [#159](https://github.com/owncloud/metrics/pull/159) [#5006](https://github.com/owncloud/enterprise/issues/5006) - Support available storage returned by objectstorages
- [#157](https://github.com/owncloud/metrics/pull/157) -  fix: avatar colors are now identical with avatar colors as used anywhere else in ownCloud
- [#155](https://github.com/owncloud/metrics/pull/155) - add support for objectstore in file count
- [#152](https://github.com/owncloud/metrics/pull/152) - Use directory references and add some changes pending of activity app
- [#143](https://github.com/owncloud/metrics/pull/144) - fix: use one sql to rule all metrics

## [1.1.0] - 2021-11-21

### Fixed

- Fixed share link count in dashboard [#127](https://github.com/owncloud/metrics/pull/127)
- Fixed missing namespace [#130] (https://github.com/owncloud/metrics/pull/127)

### Added

- Dashboard visual enhancements [#104](https://github.com/owncloud/metrics/pull/104)
- Configuration to disable the dashboard [#125](https://github.com/owncloud/metrics/pull/125)
- CSV download for system metrics  [#126](https://github.com/owncloud/metrics/pull/126)

## [1.0.1] - 2021-03-16

### Fixed

- Fix min-version - [#106](https://github.com/owncloud/metrics/issues/106)

## [1.0.0] - 2021-03-01

### Fixed

- Fixed link to online admin manual
- Fix the baseurl of the metrics api endpoint - [#91](https://github.com/owncloud/metrics/issues/91)

### Changed

- Make metrics an enterprise app - [#97](https://github.com/owncloud/metrics/pull/97)
- Translations updated
- Api and dashboard enhancements - [#54](https://github.com/owncloud/metrics/issues/54)
- Bump libraries

### Removed

- Get rid of PUG in templates - [#60](https://github.com/owncloud/metrics/issues/60)

## [0.6.1] - 2020-04-09

### Fixed

- Fix pug conversion - [#34](https://github.com/owncloud/metrics/issues/34)

### Added

- Add translation support - [#33](https://github.com/owncloud/metrics/issues/33)

### Changed

- Update symfony (v4.4.4 => v4.4.5) - [#32](https://github.com/owncloud/metrics/issues/32)

## [0.6.0] - 2020-02-20

### Changed

- Bumped Symfony to version 4.4 [#29](https://github.com/owncloud/metrics/pull/29)

## [0.5.0] - 2019-12-16

### Added

- Metrics UI [#19](https://github.com/owncloud/metrics/pull/19)

## [0.0.3] - 2019-12-11

### Removed

- Drop Support for php 7.0

## [0.0.2] - 2019-11-25

### Added

-  Provision to download metrics in csv file format [#7](https://github.com/owncloud/metrics/pull/7)

## 0.0.1 - Initial Release

[Unreleased]: https://github.com/owncloud/metrics/compare/v1.4.0...master
[1.4.0]: https://github.com/owncloud/metrics/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/owncloud/metrics/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/owncloud/metrics/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/owncloud/metrics/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/owncloud/metrics/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/owncloud/metrics/compare/v0.6.1...v1.0.0
[0.6.1]: https://github.com/owncloud/metrics/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/owncloud/metrics/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/owncloud/metrics/compare/v0.0.3...v0.5.0
[0.0.3]: https://github.com/owncloud/metrics/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/owncloud/metrics/compare/v0.0.1...v0.0.2
