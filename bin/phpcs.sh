#!/usr/bin/env bash
echo "PHP Code Sniffer with Magento Extension Quality Program"
docker-compose exec magento \
	phpcs --config-set show_warnings 0
docker-compose exec magento \
	phpcs --standard=Magento2 --exclude=PHPCompatibility --extensions=php --ignore=*/vendor/,*/Tests/ --colors -s -p -v /var/www/html/app/code/Latitude/Payment
	$*