#!/usr/bin/env bash
echo "PHP Code Sniffer with Magento Extension Quality Program"
docker-compose exec magento \
	phpcs --config-set show_warnings 0
docker-compose exec magento \
	phpcs --config-set installed_paths /root/.composer/vendor/magento/magento-coding-standard,/root/.composer/vendor/phpcompatibility/php-compatibility
docker-compose exec magento \
	phpcs --standard=/var/www/html/app/code/Latitude/Payment/ruleset.xml --extensions=php --ignore=*/vendor/,*/Tests/ --colors -s -p -v /var/www/html/app/code/Latitude/Payment
	$*