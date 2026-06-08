# Comandos Docker para Teste da API KMKZ IPTV

## Comandos Básicos

### 1. Iniciar os containers
```bash
docker-compose up -d
```

### 2. Verificar status dos containers
```bash
docker-compose ps
```

### 3. Acessar o container web (PHP/Apache)
```bash
docker-compose exec web bash
```

### 4. Testar arquivos PHP dentro do container
```bash
# Dentro do container web:
php -l /var/www/html/api/status.php
php -l /var/www/html/api/test_basic.php
php -l /var/www/html/api/minimal.php
```

### 5. Executar arquivos PHP diretamente
```bash
# Dentro do container web:
php /var/www/html/api/status.php
php /var/www/html/api/test_basic.php
```

### 6. Verificar logs de erro do Apache
```bash
# Dentro do container web:
tail -f /var/log/apache2/error.log
```

### 7. Verificar logs do container
```bash
# Do host:
docker-compose logs web
docker-compose logs web --tail=50
```

## URLs para Teste no Navegador

- **Arquivo mínimo**: http://localhost:8080/api/minimal.php
- **Teste básico**: http://localhost:8080/api/test_basic.php
- **Status da API**: http://localhost:8080/api/status.php
- **Health Check**: http://localhost:8080/api/health_check.php
- **Teste isolado**: http://localhost:8080/api/isolated_test.php

## Comandos de Diagnóstico

### Verificar configuração PHP
```bash
docker-compose exec web php -i | grep -E "(error_log|display_errors|log_errors)"
```

### Verificar extensões PHP
```bash
docker-compose exec web php -m
```

### Verificar conectividade com banco
```bash
docker-compose exec web php -r "try { \$pdo = new PDO('mysql:host=db;dbname=kmkz_iptv', 'root', 'rootpassword'); echo 'Conexão OK'; } catch(Exception \$e) { echo 'Erro: ' . \$e->getMessage(); }"
```

### Reiniciar apenas o container web
```bash
docker-compose restart web
```

### Reconstruir o container web
```bash
docker-compose build web
docker-compose up -d web
```

## Resolução de Problemas

### Se ainda houver erro 500:

1. **Verificar logs em tempo real**:
   ```bash
   docker-compose logs -f web
   ```

2. **Acessar container e verificar arquivos**:
   ```bash
   docker-compose exec web bash
   ls -la /var/www/html/api/
   cat /var/log/apache2/error.log
   ```

3. **Testar sintaxe de todos os arquivos PHP**:
   ```bash
   docker-compose exec web find /var/www/html/api -name "*.php" -exec php -l {} \;
   ```

4. **Verificar permissões**:
   ```bash
   docker-compose exec web ls -la /var/www/html/api/
   ```

5. **Recriar containers do zero**:
   ```bash
   docker-compose down
   docker-compose build --no-cache
   docker-compose up -d
   ```