#!/bin/bash

/**
 * Automation script to generate gRPC classes for PHP.
 * Run this from the project root.
 */

/* Create destination directory if not exists */
mkdir -p php-app/proto

/* Generate PHP code using the php-app container */
docker compose exec php-app protoc --proto_path=/app/proto \
  --php_out=/var/www/html/proto \
  --grpc_out=/var/www/html/proto \
  --plugin=protoc-gen-grpc=$(which grpc_php_plugin || echo "/usr/local/bin/grpc_php_plugin") \
  /app/proto/analysis.proto

echo "PHP gRPC classes generated in php-app/proto/"