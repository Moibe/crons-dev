#!/bin/bash

cd /home/moibe/apps/c_geosignal/app/config && /usr/bin/php address.cmd -k payment_domain -v https://www.moibe.me/ && cd /home/moibe/apps/c_geosignal && php app/console cache:clear --env=prod
