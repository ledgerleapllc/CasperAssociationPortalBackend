#!/usr/bin/env bash

docker build -t cspr-mem-port-backend --progress=plain . 2>&1 | tee build.log
