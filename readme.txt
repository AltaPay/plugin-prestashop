===  Altapay for PrestaShop ===


== Code Analysis==
PhpStan and Php-CS-Fixer tools are being used for static code analysyis and verifying php coding standards. Follow the below mentioned steps to run these tools
1. Download PrestaShop source code from https://download.prestashop.com/download/releases/prestashop_1.7.7.0.zip and extract it.
2. Then, clone this repository into PrestaShop's modules folder.
3. Afterwards, clone https://github.com/AltaPay/sdk-php.git anywhere and only copy the contents of it's lib folder into the lib folder of this repository.
4. Now we can run composer install in our plugin as it will install the required packages.
5. For running PhpStan run command '_PS_ROOT_DIR_=./ php vendor/phpstan/phpstan/phpstan.phar --configuration=phpstan.neon analyse ./'. It'll print out any errors detected by PHPStan.
6. And for running Php-CS-Fixer use command 'php vendor/bin/php-cs-fixer fix --dry-run --diff' and it'll print out any issues detected which you can automatically fix by running command 'php vendor/bin/php-cs-fixer fix' 

