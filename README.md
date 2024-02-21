# Tripal Cultivate: Base Module

**Developed by the University of Saskatchewan, Pulse Crop Bioinformatics team.**

<!-- Summarize the main features of this package in point form below. -->

- Provides the Tripal Cultivate ontology to Tripal. This provides well described terms for germplasm types, breeding methods, metadata and relationships.
- Provides a package dashboard on the Tripal Extensions listing for a unified entry point into administration of Tripal Cultivate. We plan for it to include:
   - Charts summarizing content across data types
   - Quick links to data-specific configuration
   - Status views (e.g. most recent data upload, if there is unpublished data)
- Any additional functionality which is shared among the data type specific modules.

## Citation

If you use this module in your Tripal site, please use this citation to reference our work any place where you described your resulting Tripal site. For example, if you publish your site in a journal then this citation should be in the reference section and anywhere functionality provided by this module is discussed in the above text should reference it.

> Lacey-Anne Sanderson, Carolyn Caron, Reynold Tan, Ruobin Liu, Kirstin Bett (2024). Tripal Cultivate -Sharing data for smarter agriculture!. Development Version. University of Saskatchewan, Pulse Crop Research Group, Saskatoon, SK, Canada.

## Technology Stack

*See specific version compatibility in the automated testing section below.*

- Drupal
- Tripal 4.x
- PostgreSQL
- PHP
- Apache2

### Automated Testing

This package is dedicated to a high standard of automated testing. We use
PHPUnit for testing and CodeClimate to ensure good test coverage and maintainability.
There are more details on [our CodeClimate project page] describing our specific
maintainability issues and test coverage.

![MaintainabilityBadge]
![TestCoverageBadge]

The following compatibility is proven via automated testing workflows.

|  Drupal     |  10.0.x         |  10.1.x         |  10.2.x         |
|-------------|-----------------|-----------------|-----------------|
| **PHP 8.1** | ![Grid1A-Badge] | ![Grid1B-Badge] | ![Grid1C-Badge] |
| **PHP 8.2** | ![Grid2A-Badge] | ![Grid2B-Badge] | ![Grid2C-Badge] |
| **PHP 8.3** |                 |                 | ![Grid3C-Badge] |

[our CodeClimate project page]: https://codeclimate.com/github/TripalCultivate/TripalCultivate
[MaintainabilityBadge]: https://api.codeclimate.com/v1/badges/730d572b51ad41cbbd69/maintainability
[TestCoverageBadge]: https://api.codeclimate.com/v1/badges/730d572b51ad41cbbd69/test_coverage

[Grid1A-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid1A.yml/badge.svg
[Grid1B-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid1B.yml/badge.svg
[Grid1C-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid1C.yml/badge.svg

[Grid2A-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid2A.yml/badge.svg
[Grid2B-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid2B.yml/badge.svg
[Grid2C-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid2C.yml/badge.svg

[Grid3C-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-Grid3C.yml/badge.svg
