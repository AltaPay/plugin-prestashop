#!/bin/bash

docker build ../ --file Dockerfile-build-image -t plugin-prestashop-package-build
docker run --rm --mount type=bind,source="$(pwd)/..",target=/app plugin-prestashop-package-build ../bin/bash -c 'cd /app && bash build.sh'
