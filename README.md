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

### Docker

We automatically build images for this module using Github Workflows. Specifically, [knowpulse/tripalcultivate-base](https://hub.docker.com/repository/docker/knowpulse/tripalcultivate-base/general) contains a full Tripal site with this module installed. Note this image builds off the [knowpulse/tripalcultivate-tripal](https://hub.docker.com/repository/docker/knowpulse/tripalcultivate-tripal/general) image which extends [tripalproject/tripaldocker](https://hub.docker.com/r/tripalproject/tripaldocker) with our theme and other requirements.

```
docker pull knowpulse/tripalcultivate-base:latest
docker run --publish=80:80 -tid --name=trpcultivate-basee knowpulse/tripalcultivate-base:latest
```

### Automated Testing

This package is dedicated to a high standard of automated testing. We use
PHPUnit for testing and QLTY Cloud to ensure good test coverage and maintainability.
There are more details on [our QLTY Cloud project page] describing our specific
maintainability issues and test coverage.

[![Maintainability](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate/maintainability.svg)](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate)
[![Code Coverage](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate/coverage.svg)](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate)

The following compatibility is proven via automated testing workflows.

| PHP\Drupal | 10.6.x              | 11.3.x              | 11.4.x              |
|------------|---------------------|---------------------|---------------------|
| **PHP8.2** | ![Grid82-106-Badge] |                     |                     |
| **PHP8.3** | ![Grid83-106-Badge] | ![Grid83-113-Badge] | ![Grid83-114-Badge] |
| **PHP8.4** | ![Grid84-106-Badge] | ![Grid84-113-Badge] | ![Grid84-114-Badge] |
| **PHP8.5** |                     | ![Grid85-113-Badge] | ![Grid85-114-Badge] |


[our QLTY Cloud project page]: https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate

[Grid82-106-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.2_D10_6x.yml/badge.svg
[Grid83-106-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.3_D10_6x.yml/badge.svg
[Grid83-113-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.3_D11_3x.yml/badge.svg
[Grid83-114-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.3_D11_4x.yml/badge.svg
[Grid84-106-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.4_D10_6x.yml/badge.svg
[Grid84-113-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.4_D11_3x.yml/badge.svg
[Grid84-114-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.4_D11_4x.yml/badge.svg
[Grid85-113-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.5_D11_3x.yml/badge.svg
[Grid85-114-Badge]: https://github.com/TripalCultivate/TripalCultivate/actions/workflows/MAIN-phpunit-php8.5_D11_4x.yml/badge.svg
