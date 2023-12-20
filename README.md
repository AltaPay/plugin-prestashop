#  AltaPay for PrestaShop #

Integrates your PrestaShop web shop to the AltaPay payments gateway.

## Supported Payment Methods & Functionalities
<table>
<tr><td>

| Functionalities	    | Support       |
| :------------------------ | :-----------: |
| Reservation               | &check;       |
| Capture                   | &check;       |
| Instant Capture           | &check;       |
| Multi Capture             | &check;       |
| Recurring / Unscheduled   | &check;       |
| Release                   | &check;       |
| Refund                    | &check;       |
| Multi Refund              | &check;       |
| 3D Secure                 | &check;       |
| Fraud prevention (other)  | &check;       |
| Reconciliation            | &check;       |
| MO/TO                     | &cross;       |

</td><td valign="top">

| Payment Method      | Support       |
| ------------------- | :-----------: |
| Card                | &check;       |
| Invoice             | &check;       |
| ePayments           | &check;       |
| Bank-to-bank        | &check;       |
| Interbank           | &check;       |
| Cash Wallet         | &check;       |
| Mobile Wallet       | &check;       |

</td></tr> </table>

## Compatibility
- PrestaShop 1.6.x (requires PHP 7.0 or later)
- Thirty Bees 1.5.x (requires PHP 7.2 or later)
- PrestaShop 1.7.x (requires PHP 7.2 or later)
- PrestaShop 8.x (requires PHP 7.2 or later)

## Code Analysis

### Prerequisites:
These are the steps that must be followed before running any of the codeanalysis tools.
1. Download PrestaShop source code from https://download.prestashop.com/download/releases/prestashop_1.7.6.4.zip and extract it.
2. Then, clone this repository into PrestaShop's 'modules' folder.
3. Afterwards, run the following commands

        composer install
        composer isolate
        composer dump -o

Now, we can use our code analysis tools.

PhpStan is being used for running static code analysis. It's configuration file 'phpstan.neon' is available in this repository. To run it follow the instructions given below. 
- Run command 
`_PS_ROOT_DIR_=../../ php vendor/phpstan/phpstan/phpstan.phar --configuration=phpstan.neon analyse ./`  
It'll print out any errors detected by PHPStan.
Note: _PS_ROOT_DIR_ should point to prestashop root directory. And, path at the end of command denotes the path of directory, in this case our repository/plugin, where we need to run phpstan. 

Next, Php-CS-Fixer is being used for fixing php coding standard relared issues. It's config file .php_cs.dist is also available in this repository. 
1. To visualize the issues reported by Php-CS-Fixer, before actually fixing them, use command
`php vendor/bin/php-cs-fixer fix --dry-run --diff` and it'll print out any issues detected.

2. Then, you can fix these which issues by running command 'php vendor/bin/php-cs-fixer fix' 


## How to run cypress tests

### Prerequisites: 

* PrestaShop should be installed with the default theme on publically accessible URL
* Cypress should be installed

### Steps 

* Install dependencies `npm i`
* Update "cypress/fixtures/config.json"
* Execute `./node_modules/.bin/cypress run` in the terminal to run all the tests

## Changelog

See [Changelog](CHANGELOG.md) for all the release notes.

## License

Distributed under the MIT License. See [LICENSE](LICENSE) for more information.

## Documentation

For more details please see [docs](https://github.com/AltaPay/plugin-prestashop/wiki)
