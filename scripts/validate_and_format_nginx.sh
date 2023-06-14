#!/usr/bin/env bash

git clone https://github.com/slomkowski/nginx-config-formatter.git
nginx-config-formatter/nginxfmt.py $(pwd)/config/default.conf
docker run -v $(pwd)/config:/etc/nginx/conf.d/ -t -a stdout trafex/php-nginx:3.0.0 nginx -t -c /etc/nginx/nginx.conf
rm -rf nginx-config-formatter
