#  Altapay for PrestaShop #


## Code Analysis ##

Prerequisites:
These are the steps that must be followed before running any of the codeanalysis tools.
1. Download PrestaShop source code from https://download.prestashop.com/download/releases/prestashop_1.7.7.0.zip and extract it.
2. Then, clone this repository into PrestaShop's 'modules' folder.
3. Afterwards, clone https://github.com/AltaPay/sdk-php.git anywhere and only copy the contents of it's lib directory into lib/altapay/altapay-php-sdk/lib/ directory of this repository.
4. Finally, run composer install in this repository as it will install the required packages.

Now, we can use our code analysis tools.

PhpStan is being used for running static code analysis. It's configuration file 'phpstan.neon' is available is this repository. To run it follow the instructions given below. 
- Run command '_PS_ROOT_DIR_=../../ php vendor/phpstan/phpstan/phpstan.phar --configuration=phpstan.neon analyse ./' to run the analysis. It'll print out any errors detected by PHPStan.
Note: _PS_ROOT_DIR_ should point to prestashop root directory. And, path at the end of command denotes the path of directory, in this case our repository/plugin, where we need to run phpstan. 

Next, Php-CS-Fixer is being used for fixing php coding standard relared issues. It's config file .php_cs.dist is also available in this repository. 
1. To visualize the issues reported by Php-CS-Fixer, before actually fixing them, use command 'php vendor/bin/php-cs-fixer fix --dry-run --diff' and it'll print out any issues detected
2. Then, you can fix these which issues by running command 'php vendor/bin/php-cs-fixer fix' 


# How to run cypress test successfully in your environment 

## Prerequisites: 

1) Magento2 should be installed on publically accessible URL
2) Cypress should be installed

## Steps 

1) Install dependencies `npm i`

2) Update "cypress/fixtures/config.json" 

3) Execute `./node_modules/.bin/cypress run` in the terminal to run all the tests
