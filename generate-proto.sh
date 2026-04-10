#!/bin/bash

mkdir -p php-app/proto

# Генеруємо лише Data-класи (без плагіна)
docker compose exec php-app protoc --proto_path=/app/proto \
  --php_out=/var/www/html/proto \
  /app/proto/analysis.proto

echo "Protobuf data classes generated successfully."