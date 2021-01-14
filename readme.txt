===  Altapay for PrestaShop ===


== Code Analysis==

1. PHPStan is being used for running static code analysis. It's configuration file 'phpstan.neon' is available is this repository. The directories mentioned under scnDirectories option in phpstan.neon, are required for running the analysis. These directories belong to prestashop. If you don't have prestashop downloaded, you'll need to download it first and then make sure the paths are correctly reflected in phpstan.neon file. Once done, we can run the analysis: 
  i.  First install composer packages using 'composer install'
  ii. Then run '_PS_ROOT_DIR_=./ php vendor/phpstan/phpstan/phpstan.phar --configuration=phpstan.neon analyse ./' to run the analysis. It'll print out any errors detected by PHPStan.

2. Php-CS-Fixer is being used for fixing php coding standard relared issues. After installing composer packge, simply run 'php vendor/bin/php-cs-fixer fix --dry-run --diff' and it'll print out any issues detected which you can automatically fix by running command 'php vendor/bin/php-cs-fixer fix' 

