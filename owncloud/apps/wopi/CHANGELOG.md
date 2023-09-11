# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.7.0] - 2023-08-11

### Changed

- [#243](https://github.com/owncloud/wopi/pull/243) - Always return an int from Symfony Command execute method 
- Minimum core version 10.11, mimimum php version 7.4
- Dependencies updated

### Fixed

- [#254](https://github.com/owncloud/wopi/pull/254) - fix: use firebase/php-jwt from core (#254)


## [1.6.1] - 2023-02-02

### Fixed

- [#237](https://github.com/owncloud/wopi/pull/237) - fix permission problem for file actions
- [#236](https://github.com/owncloud/wopi/pull/236) - validate public link permissions in CheckFileInfo
- [#233](https://github.com/owncloud/wopi/pull/233) - Allow editing of public link file if share permission allows
- [#230](https://github.com/owncloud/wopi/pull/230) - Explicitly cast fileId to string
- [#208](https://github.com/owncloud/wopi/pull/208) - Fix: respect themed browser title
- [#206](https://github.com/owncloud/wopi/pull/206) - Fix: csp policy o356
- [#203](https://github.com/owncloud/wopi/pull/203) - Handle open file stream for PutFile 
- [#202](https://github.com/owncloud/wopi/pull/202) - For legacy hooks do not hint type
- [#200](https://github.com/owncloud/wopi/pull/200) - Editing through a public link folder

## [1.6.0] - 2022-03-31

### Added

- Feat: enable business user flow [#171](https://github.com/owncloud/wopi/pull/171)
- Support master key encryption [#184](https://github.com/owncloud/wopi/pull/184])
- Add view and edit to default file click actions [#185](https://github.com/owncloud/wopi/pull/185)


## [1.5.1] - 2021-10-05

### Fixed

- Fix wopi ignores group restriction - [#161](https://github.com/owncloud/wopi/pull/161)


## [1.5.0] - 2021-01-20

### Added

- Add handling for public links -[#108](https://github.com/owncloud/wopi/issues/108)

## [1.4.0] - 2020-06-22

### Added

- Move to the new licensing management - [#94](https://github.com/owncloud/wopi/issues/94)
- Added requirements - [#80](https://github.com/owncloud/wopi/issues/80)
- Support PHP 7.4 - [#91](https://github.com/owncloud/wopi/issues/91)

### Changed

- Set owncloud min-version to 10.5
- Bump libraries

## [1.3.0] - 2020-01-08

### Added

- Add support for Put Relative - [#68](https://github.com/owncloud/wopi/issues/68)
- Enable PHP 7.3 - [#73](https://github.com/owncloud/wopi/issues/73)

### Fixed

- Fix bug when server default is different than English - [#67](https://github.com/owncloud/wopi/issues/67)
- Don't register scripts, if session user is not member of allowed group - [#64](https://github.com/owncloud/wopi/issues/64)

## [1.2.0] - 2019-06-19

### Fixed

- Proper handling of unsupported `Save As` error cases [#51](https://github.com/owncloud/wopi/pull/51)
- Creation of new Excel Files with Safari [#53](https://github.com/owncloud/wopi/pull/53)
- Correctly detect uppercase file extension [#46](https://github.com/owncloud/wopi/issues/46)[#52](https://github.com/owncloud/wopi/pull/52)
- Translation issues with Excel [#51](https://github.com/owncloud/wopi/pull/51)
- Extraction of Content Security Policies [#50](https://github.com/owncloud/wopi/pull/50) [#47](https://github.com/owncloud/wopi/issues/47)
- Properly set application ID in app template [#50](https://github.com/owncloud/wopi/pull/50)

## [1.1.0] - 2019-03-14

### Added

- Add support for different languages - [#42](https://github.com/owncloud/wopi/issues/42)

## 1.0.0 - 2019-02-08

- Initial release

[Unreleased]: https://github.com/owncloud/wopi/compare/v1.7.0..master
[1.7.0]: https://github.com/owncloud/wopi/compare/v1.6.0..v1.7.0
[1.6.0]: https://github.com/owncloud/wopi/compare/v1.5.1..v1.6.0
[1.5.1]: https://github.com/owncloud/wopi/compare/v1.5.0..v1.5.1
[1.5.0]: https://github.com/owncloud/wopi/compare/v1.4.0..v1.5.0
[1.4.0]: https://github.com/owncloud/wopi/compare/v1.3.0..v1.4.0
[1.3.0]: https://github.com/owncloud/wopi/compare/v1.2.0..v1.3.0
[1.2.0]: https://github.com/owncloud/wopi/compare/v1.1.0..v1.2.0
[1.1.0]: https://github.com/owncloud/wopi/compare/v1.0.0..v1.1.0
