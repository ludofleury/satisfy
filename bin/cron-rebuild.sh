#!/bin/bash

/usr/local/bin/php /var/www/bin/console satisfy:rebuild --skip-errors /var/www/satis.json /var/www/html/public > /tmp/satis.log
