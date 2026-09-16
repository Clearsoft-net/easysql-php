# [2.0.0](https://github.com/Clearsoft-net/easysql-php/compare/v1.7.0...v2.0.0) (2026-09-16)


* feat!: restructure SDK into a multipackage Composer repository ([3c36c45](https://github.com/Clearsoft-net/easysql-php/commit/3c36c45d97435f8dc2f3b3cca23d2f5a06ceee7c))


### BREAKING CHANGES

* the single clearsoft/easysql-sdk package was split and
namespaces changed:
  Clearsoft\EasySQL\SDK\Client            -> Clearsoft\EasySQL\Client\Client
  Clearsoft\EasySQL\SDK\Models\*          -> Clearsoft\EasySQL\Client\Models\*
  Clearsoft\EasySQL\SDK\Exceptions\*      -> Clearsoft\EasySQL\Client\Exceptions\*
  Clearsoft\EasySQL\SDK\EasySQLClient     -> Clearsoft\EasySQL\Client\Http\EasySQLClient
  Clearsoft\EasySQL\SDK\TokenStoreInterface -> Clearsoft\EasySQL\Client\Http\TokenStoreInterface
Connector methods now take their path parameters explicitly
(getConnector($id), updateConnector($body, $id), syncConnector($body, $id)).
A migration table is documented in the README.

# [1.7.0](https://github.com/Clearsoft-net/easysql-php/compare/v1.6.0...v1.7.0) (2026-09-11)


### Bug Fixes

* **tests:** update tests for OIDC auth ([53056a5](https://github.com/Clearsoft-net/easysql-php/commit/53056a5d70ff65ccdfff9870591b35f6f2ed44c6))


### Features

* **sdk:** regenerate SDK from latest spec ([#8](https://github.com/Clearsoft-net/easysql-php/issues/8)) ([347c966](https://github.com/Clearsoft-net/easysql-php/commit/347c9667f995e3bd292f20835d63d49a52692bdc))
* **sdk:** regenerate SDK from latest spec ([#9](https://github.com/Clearsoft-net/easysql-php/issues/9)) ([ef4161f](https://github.com/Clearsoft-net/easysql-php/commit/ef4161f08494478defe3e8faa723617a6882b7f7))

# [1.6.0](https://github.com/Clearsoft-net/easysql-php/compare/v1.5.0...v1.6.0) (2026-09-07)


### Features

* **sdk:** regenerate SDK from latest spec ([#7](https://github.com/Clearsoft-net/easysql-php/issues/7)) ([cbfaa71](https://github.com/Clearsoft-net/easysql-php/commit/cbfaa71457eda537de64ce4bea1d4b4d7e0cf47c))

# [1.5.0](https://github.com/Clearsoft-net/easysql-php/compare/v1.4.0...v1.5.0) (2026-09-04)


### Bug Fixes

* **deps:** lock composer platform to php 8.2 for CI compatibility ([eb39018](https://github.com/Clearsoft-net/easysql-php/commit/eb390189e53222c59f0396a342906b6bd4a2dcf9))


### Features

* **laravel:** integrate Laravel service provider, facade and package auto-discovery ([0f4fc20](https://github.com/Clearsoft-net/easysql-php/commit/0f4fc20abcea745cbb5cf690498a94150a951205))

# [1.4.0](https://github.com/Clearsoft-net/easysql-sdk-php/compare/v1.3.0...v1.4.0) (2026-06-07)


### Features

* auto-approve and auto-merge SDK regeneration PRs ([bc6c09e](https://github.com/Clearsoft-net/easysql-sdk-php/commit/bc6c09efa198134060b8a7f7d95b9efbb3c12f02))

# [1.3.0](https://github.com/Clearsoft-net/easysql-sdk-php/compare/v1.2.0...v1.3.0) (2026-06-07)


### Features

* **sdk:** regenerate SDK from latest spec ([955c2f7](https://github.com/Clearsoft-net/easysql-sdk-php/commit/955c2f78efc78de660d05a88f9b1cd589c3e43f8))

# [1.2.0](https://github.com/Clearsoft-net/easysql-sdk-php/compare/v1.1.0...v1.2.0) (2026-06-07)


### Features

* **sdk:** regenerate SDK from latest spec ([19531f2](https://github.com/Clearsoft-net/easysql-sdk-php/commit/19531f2f8074ffb66cc8752b55f18beaa79cf6a7))

# [1.1.0](https://github.com/Clearsoft-net/easysql-sdk-php/compare/v1.0.0...v1.1.0) (2026-06-07)


### Features

* **sdk:** support custom HTTP client injection and throw ApiException on HTTP errors ([24c4547](https://github.com/Clearsoft-net/easysql-sdk-php/commit/24c45476296f4aadba86ddd537d5b901689ac5bd))

# 1.0.0 (2026-06-07)


### Bug Fixes

* fallback to PAT token for PR creation ([f6c094f](https://github.com/Clearsoft-net/easysql-sdk-php/commit/f6c094fbd6c885a02e2b11b5f3bddc7fa818344c))
* use explicit PAT token since GITHUB_TOKEN is org-restricted ([3d9bf13](https://github.com/Clearsoft-net/easysql-sdk-php/commit/3d9bf131912d74f296ea55d5272fbcaad96372d1))
* use GITHUB_TOKEN now that org permits write permissions ([64ea92e](https://github.com/Clearsoft-net/easysql-sdk-php/commit/64ea92e145e9a09f1ab4fda3ec989abcc5347a1c))


### Features

* auto-generated Client and 25 model classes ([597d721](https://github.com/Clearsoft-net/easysql-sdk-php/commit/597d7217f23faa8050ab20a55d70edfd289d331e))
* code generation pipeline from OpenAPI spec ([15f37fe](https://github.com/Clearsoft-net/easysql-sdk-php/commit/15f37fe35138104e580418625d5726e89e5d817b))
* PHPUnit tests with Guzzle MockHandler ([8fede65](https://github.com/Clearsoft-net/easysql-sdk-php/commit/8fede654406c183de9fa16df088364ed6796f4bf))
* **release:** configure semantic-release for automated releases ([99744f9](https://github.com/Clearsoft-net/easysql-sdk-php/commit/99744f90c25bfd7dffaaafcafbf47f2602f87849))
