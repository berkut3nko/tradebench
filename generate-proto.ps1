# generate-proto.ps1
if (-not (Test-Path "php-app/proto")) { 
    New-Item -ItemType Directory -Path "php-app/proto" -Force 
}

# Виконуємо генерацію через контейнер
docker compose exec php-app protoc --proto_path=/app/proto `
  --php_out=/var/www/html/proto `
  --grpc_out=/var/www/html/proto `
  --plugin=protoc-gen-grpc=/usr/bin/grpc_php_plugin `
  /app/proto/analysis.proto

Write-Host "PHP gRPC classes generated successfully." -ForegroundColor Green