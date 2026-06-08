# KMKZ IPTV

Sistema em PHP para site de IPTV com landing page, autenticacao, dashboard, painel administrativo, API REST, sistema de planos, pagamentos e pontos.

## Visao Geral

O projeto foi organizado para funcionar tanto em ambiente local com Docker quanto em hospedagem tradicional com Apache e MySQL.

Principais recursos:

- Landing page em PHP com planos e fluxo de assinatura
- Login, logout e dashboard de usuarios
- Painel administrativo
- API REST em `public/api/`
- Banco MySQL com estrutura pronta em `database/init.sql`
- Instalador web para gerar `.env` e configurar o sistema
- Integracao basica com GA4 e Meta Pixel por variaveis de ambiente

## Estrutura do Projeto

```text
KMKZIPTV/
|-- config/                      Configuracao do banco e carregamento do .env
|-- database/                    Scripts SQL
|-- includes/                    Funcoes e integracoes compartilhadas
|-- public/                      Pasta publica do site
|   |-- api/                     Endpoints da API
|   |-- assets/                  CSS, JS e imagens
|   |-- install.php              Instalador web
|   |-- index.php                Home
|   |-- login.php                Login
|   |-- dashboard.php            Painel do usuario
|   |-- admin.php                Painel administrativo
|-- README/                      Documentacao adicional
|-- .env.example                 Modelo de configuracao
|-- ARQUIVOS_PARA_HOSPEDAGEM.md  Guia de upload
|-- docker-compose.yml           Ambiente local com Docker
```

## Requisitos

Para hospedagem tradicional:

- PHP 8.0 ou superior
- MySQL 5.7+ ou MySQL 8+
- Extensao `pdo_mysql`
- Apache com `mod_rewrite`
- Permissao para gravar o arquivo `.env`

Para ambiente local com Docker:

- Docker Desktop
- Docker Compose

## Instalacao em Hospedagem

### 1. Envie os arquivos

Consulte [ARQUIVOS_PARA_HOSPEDAGEM.md](file:///d:/Sites/KMKZIPTV/ARQUIVOS_PARA_HOSPEDAGEM.md) para saber exatamente quais pastas devem ser enviadas ao servidor.

Resumo do upload:

- `config/`
- `database/`
- `includes/`
- `public/`
- `.env.example`

### 2. Configure o dominio

O ideal e apontar o dominio ou subdominio para a pasta `public/`.

Exemplo:

```text
/home/usuario/kmkziptv/public
```

### 3. Crie o banco de dados

Se a sua hospedagem nao permitir que o PHP crie o banco automaticamente, crie antes no painel da hospedagem:

- nome do banco
- usuario do banco
- senha do banco
- host do banco
- porta do banco

### 4. Execute o instalador

Acesse no navegador:

```text
https://seu-dominio.com/install.php
```

O instalador em [install.php](file:///d:/Sites/KMKZIPTV/public/install.php) faz o seguinte:

- valida os pre-requisitos
- conecta ao MySQL
- importa `database/init.sql`
- remove usuarios de demonstracao
- cria o administrador informado
- gera o arquivo `.env`
- cria a pasta `storage/`

### 5. Acesse o sistema

Depois da instalacao:

- site: `https://seu-dominio.com/`
- login: `https://seu-dominio.com/login.php`
- api: `https://seu-dominio.com/api/`

## Instalacao Local com Docker

### Subir o ambiente

```bash
docker compose up -d
```

### URLs locais

- site: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

Credenciais do banco no Docker:

- host: `db`
- banco: `kmkz_iptv`
- usuario: `root`
- senha: `rootpassword`

### Parar o ambiente

```bash
docker compose down
```

## Configuracao

O projeto le configuracoes a partir do arquivo `.env` na raiz do projeto.

Exemplo:

```env
DB_HOST=localhost
DB_NAME=kmkz_iptv
DB_USER=seu_usuario
DB_PASS=sua_senha
DB_PORT=3306

APP_NAME="KMKZ IPTV"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com
TIMEZONE=America/Sao_Paulo

API_BASE_URL=https://seu-dominio.com
API_VERIFY_SSL=1
API_DEBUG=0
CORS_ALLOWED_ORIGINS=https://seu-dominio.com

GA4_MEASUREMENT_ID=
META_PIXEL_ID=
```

Use [`.env.example`](file:///d:/Sites/KMKZIPTV/.env.example) como referencia.

## Banco de Dados

O arquivo principal de estrutura e carga inicial e:

- [init.sql](file:///d:/Sites/KMKZIPTV/database/init.sql)

Ele inclui:

- tabelas de usuarios, planos, assinaturas e pagamentos
- sistema de pontos e recompensas
- configuracoes basicas do sistema
- triggers e views auxiliares

## API

A API fica em `public/api/`.

Exemplos de endpoints:

- `POST /api/auth.php?action=login`
- `POST /api/auth.php?action=logout`
- `GET /api/plans.php?action=list`
- `GET /api/dashboard.php?action=admin`
- `GET /api/users.php?action=list`

Arquivos principais da API:

- [config.php](file:///d:/Sites/KMKZIPTV/public/api/config.php)
- [auth.php](file:///d:/Sites/KMKZIPTV/public/api/auth.php)
- [plans.php](file:///d:/Sites/KMKZIPTV/public/api/plans.php)
- [dashboard.php](file:///d:/Sites/KMKZIPTV/public/api/dashboard.php)

## Paginas Principais

- [index.php](file:///d:/Sites/KMKZIPTV/public/index.php)
- [plans.php](file:///d:/Sites/KMKZIPTV/public/plans.php)
- [subscribe.php](file:///d:/Sites/KMKZIPTV/public/subscribe.php)
- [payment.php](file:///d:/Sites/KMKZIPTV/public/payment.php)
- [dashboard.php](file:///d:/Sites/KMKZIPTV/public/dashboard.php)
- [admin.php](file:///d:/Sites/KMKZIPTV/public/admin.php)
- [rewards.php](file:///d:/Sites/KMKZIPTV/public/rewards.php)

## Seguranca e Operacao

- O arquivo `.env` fica fora do versionamento por causa do `.gitignore`
- O instalador bloqueia nova instalacao quando o `.env` ja existe
- Em producao, mantenha `APP_DEBUG=false`
- Apos instalar, recomenda-se remover ou restringir o acesso a `public/install.php`

## Documentacao Adicional

- [ARQUIVOS_PARA_HOSPEDAGEM.md](file:///d:/Sites/KMKZIPTV/ARQUIVOS_PARA_HOSPEDAGEM.md)
- [README/README.md](file:///d:/Sites/KMKZIPTV/README/README.md)
- [README/PROJETO.md](file:///d:/Sites/KMKZIPTV/README/PROJETO.md)
- [README/QUICK_START.md](file:///d:/Sites/KMKZIPTV/README/QUICK_START.md)
- [README/API.md](file:///d:/Sites/KMKZIPTV/README/API.md)

## Solucao de Problemas

### O instalador nao abre

- confirme se o dominio aponta para `public/`
- confirme se `public/.htaccess` esta sendo respeitado
- confirme se o PHP esta habilitado na hospedagem

### Erro ao gravar o `.env`

- conceda permissao de escrita na pasta raiz do projeto
- verifique se ja existe um `.env` antigo

### Erro ao conectar no banco

- revise host, porta, usuario e senha
- se necessario, crie o banco manualmente no painel da hospedagem

### API nao responde corretamente

- confirme `APP_URL` e `API_BASE_URL`
- revise `CORS_ALLOWED_ORIGINS`
- teste o endpoint `/api/`

## Publicacao no Git

Caso esteja iniciando o versionamento agora:

```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/Ronickbr/iptv_php.git
git branch -M main
git push -u origin main
```
