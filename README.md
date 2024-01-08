# [Square for WooCommerce](http://woocommerce.com/products/square/)


## Getting Started

1. Make sure you have `git`, `node`, `npm` installed. We use the node LTS version on our servers, so we recommended 
using that for development as well -- currently this is version `12.18.2`. 
1. Clone this repository locally within the plugins directory of WordPress.
1. Run `npm install` from the root directory of the repository to install dependencies.
1. Execute `npm run build:dev` from the root directory of the repository.
1. Now go to the local installation, and activate this plugin.
1. [Signup for a Square sandbox account and input your App ID and Access Token](https://docs.woocommerce.com/document/woocommerce-square/#section-6)
1. From the dropdown box for location, choose a location(The default value is Default Test Account).
1. Save.
1. The Square Merchant Account's country should match the WooCommerce store's country and currency for this to work.
1. Now, you can create items on Square and push them to WooCommerce or ViceVersa. 
1. Enable Square as a payment gateway from the Woocommerce->Settings->Payments page.
1. Now, you can use [the test cards here](https://developer.squareup.com/docs/testing/test-values) for testing Square credit card payments.

[More documentation.](https://docs.woocommerce.com/document/woocommerce-square/)

## API

The documentation for the API endpoints can be found [here](https://developer.squareup.com/reference/square/)

You can also explore the Square API from [here](https://developer.squareup.com/explorer/square/)

## Repository

* The `/woocommerce/woocommerce-square/` repository is treated as a _development_ repository: this includes development assets, like unit tests and configuration files. Commit history for this repository includes all commits for all changes to the code base, not just for new versions.

## Deployment

A "_deployment_" in our sense means:
 * validating the version in the header and `WC_Square::$version` variable match
 * generating a `.pot` file for all translatable strings in the development repository
 * building all assets running `npm run build`
 * tagging a new version
 * the changes will be pushed to a branch with the name `release/{version}` so that a PR can be issued on `/woocommerce/woocommerce-square/`
 * cloning a copy of the latest release found on the `/woocommerce/woocommerce-square/` repo into a temporary directory
 * removing all development related assets, like this file, unit tests and configuration files
 * exporting a copy of all these files into the `/trunk` and `/tags/{version}` directories on the WC Square WP.org svn repo

## Branches

* [`woocommerce/woocommerce-square/trunk`](https://github.com/woocommerce/woocommerce-square/tree/trunk) includes all code for the current version and any new pull requests merged that will be released with the next version. It can be considered stable for staging and development sites but not for production.

## Coding Standards

This project is moving towards [WooCommerce coding standards](https://href.li/?https://github.com/woocommerce/woocommerce-sniffs), though currently, it is not strictly enforced. Please respect these standards and if possible run appropriate IDE/editor plugins to help you enforce these rules.

## Testing

We are striving to subject this extension to tests at various levels. They are works in progress. The following will be updated as there is progress.
Do check with us if you want to contribute in some way towards these.
TBD - Travis Integration
TBD - Unit Testing
TBD - E2E Testing

## Contribution
Contribution can be done in many ways. We appreciate it.
* If you test this extension and find a bug/have a question, do log an issue.
* If you have fixed any of the issues, do submit a PR.
