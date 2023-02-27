#!/usr/bin/env bash

docker run -v $(pwd)/config:/etc/nginx/conf.d/ -t -a stdout trafex/php-nginx:3.0.0 nginx -t -c /etc/nginx/nginx.conf
