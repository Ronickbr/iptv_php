# KMKZ IPTV

<p align="center">
  <img src="public/assets/images/Logo.png" alt="KMKZ IPTV" width="260">
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white">
  <img alt="Docker" src="https://img.shields.io/badge/Docker-supported-2496ED?logo=docker&logoColor=white">
  <img alt="Status" src="https://img.shields.io/badge/status-active-16a34a">
  <img alt="Install" src="https://img.shields.io/badge/installer-web-blue">
</p>

Sistema em PHP para operacao de IPTV com landing page, autenticacao, dashboard, painel administrativo, API REST, planos, pagamentos e sistema de pontos.

## Sumario

- [Visao Geral](#visao-geral)
- [Recursos](#recursos)
- [Capturas E Fluxo](#capturas-e-fluxo)
- [Estrutura Do Projeto](#estrutura-do-projeto)
- [Requisitos](#requisitos)
- [Instalacao Em Hospedagem](#instalacao-em-hospedagem)
- [Instalacao Em public_html](#instalacao-em-public_html)
- [Instalacao Local Com Docker](#instalacao-local-com-docker)
- [Credenciais E Ambientes](#credenciais-e-ambientes)
- [Configuracao](#configuracao)
- [Banco De Dados](#banco-de-dados)
- [API](#api)
- [Paginas Principais](#paginas-principais)
- [Seguranca E Operacao](#seguranca-e-operacao)
- [Roadmap](#roadmap)
- [Documentacao Adicional](#documentacao-adicional)
- [Solucao De Problemas](#solucao-de-problemas)
- [Publicacao No Git](#publicacao-no-git)

## Visao Geral

O KMKZ IPTV foi estruturado para funcionar em dois cenarios:

- hospedagem tradicional com Apache e MySQL
- ambiente local com Docker

O projeto utiliza uma pasta publica separada, `public/`, enquanto configuracoes e arquivos internos ficam fora da area exposta.

## Recursos

- landing page com foco em conversao
- login, logout e dashboard de usuarios
- painel administrativo
- endpoints em `public/api/`
- instalador web em `public/install.php`
- leitura automatica do arquivo `.env`
- importacao do banco a partir de `database/init.sql`
- sistema de pontos, recompensas e planos
- integracao opcional com GA4 e Meta Pixel

## Capturas E Fluxo

### Identidade visual

O repositorio ja inclui a identidade visual principal em [Logo.png](file:///d:/Sites/KMKZIPTV/public/assets/images/Logo.png).

### Fluxo principal do sistema

| Tela | Arquivo | Objetivo |
|---|---|---|
| Home | [index.php](file:///d:/Sites/KMKZIPTV/public/index.php) | Apresentar planos, proposta comercial e CTA |
| Login | [login.php](file:///d:/Sites/KMKZIPTV/public/login.php) | Autenticacao dos usuarios |
| Assinatura | [subscribe.php](file:///d:/Sites/KMKZIPTV/public/subscribe.php) | Captura de dados do assinante |
| Pagamento | [payment.php](file:///d:/Sites/KMKZIPTV/public/payment.php) | Fluxo de pagamento |
| Dashboard | [dashboard.php](file:///d:/Sites/KMKZIPTV/public/dashboard.php) | Painel do usuario |
| Admin | [admin.php](file:///d:/Sites/KMKZIPTV/public/admin.php) | Painel administrativo |
| Instalador | [install.php](file:///d:/Sites/KMKZIPTV/public/install.php) | Configuracao inicial do projeto |

### Observacao sobre capturas reais

Se voce quiser enriquecer ainda mais o repositorio, o proximo passo ideal e adicionar screenshots reais dessas telas no GitHub e referenciar essas imagens aqui no README.

## Estrutura Do Projeto

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

### Hospedagem tradicional

- PHP 8.0 ou superior
- MySQL 5.7+ ou MySQL 8+
- extensao `pdo_mysql`
- Apache com `mod_rewrite`
- permissao para gravar o arquivo `.env`

### Ambiente local

- Docker Desktop
- Docker Compose

## Instalacao Em Hospedagem

### 1. Envie os arquivos

Consulte [ARQUIVOS_PARA_HOSPEDAGEM.md](file:///d:/Sites/KMKZIPTV/ARQUIVOS_PARA_HOSPEDAGEM.md) para saber exatamente o que subir para o servidor.

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

Se a hospedagem nao permitir que o PHP crie o banco automaticamente, crie o banco antes no painel:

- nome do banco
- usuario do banco
- senha do banco
- host do banco
- porta do banco

### 4. Execute o instalador

Acesse:

```text
https://seu-dominio.com/install.php
```

O instalador em [install.php](file:///d:/Sites/KMKZIPTV/public/install.php) faz o seguinte:

- valida pre-requisitos
- conecta ao MySQL
- importa `database/init.sql`
- remove usuarios de demonstracao inseguros
- cria o administrador informado
- gera o arquivo `.env`
- cria a pasta `storage/`

### 5. Acesse o sistema

- site: `https://seu-dominio.com/`
- login: `https://seu-dominio.com/login.php`
- api: `https://seu-dominio.com/api/`

## Instalacao Em public_html

Se a sua hospedagem nao permite apontar o dominio diretamente para a pasta `public/`, voce pode manter os caminhos atuais usando a seguinte estrutura:

```text
/home/SEU_USUARIO/
|-- config/
|-- database/
|-- includes/
|-- storage/
|-- .env
|-- public_html/
|   |-- index.php
|   |-- install.php
|   |-- login.php
|   |-- dashboard.php
|   |-- admin.php
|   |-- api/
|   |-- assets/
```

### Como montar essa estrutura

1. Copie o conteudo interno de `public/` para `public_html/`
2. Deixe `config/`, `database/`, `includes/` e `.env` um nivel acima de `public_html/`
3. Acesse `https://seu-dominio.com/install.php`
4. Finalize a instalacao normalmente

### Por que isso funciona

Os arquivos publicos usam `dirname(__DIR__)` para localizar a raiz do projeto. Quando `public_html/` fica um nivel abaixo de `config/`, `database/` e `includes/`, a resolucao continua funcionando sem alterar o codigo.

## Instalacao Local Com Docker

### Subir o ambiente

```bash
docker compose up -d
```

### URLs locais

- site: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

### Parar o ambiente

```bash
docker compose down
```

## Credenciais E Ambientes

### Credenciais do banco no Docker

- host: `db`
- banco: `kmkz_iptv`
- usuario: `root`
- senha: `rootpassword`

### Usuarios de demonstracao do SQL inicial

Quando o banco e carregado diretamente pelo `database/init.sql`, os seguintes usuarios podem existir em ambiente de desenvolvimento:

- admin: `admin@kmkz.com` / `admin123`
- usuario de teste: `user@test.com` / `admin123`

Em producao:

- use o instalador web para criar seu proprio administrador
- altere ou remova qualquer conta padrao
- nunca mantenha essas credenciais em ambiente publico

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
ALLOW_INSTALL=0
ALLOW_MIGRATIONS=0
```

Use [`.env.example`](file:///d:/Sites/KMKZIPTV/.env.example) como referencia.

## Banco De Dados

O arquivo principal de estrutura e carga inicial e:

- [init.sql](file:///d:/Sites/KMKZIPTV/database/init.sql)

Ele inclui:

- tabelas de usuarios, planos, assinaturas e pagamentos
- sistema de pontos e recompensas
- configuracoes basicas do sistema
- triggers e views auxiliares

## API

A API fica em `public/api/`.

### Endpoints de exemplo

- `POST /api/auth.php?action=login`
- `POST /api/auth.php?action=logout`
- `GET /api/plans.php?action=list`
- `GET /api/dashboard.php?action=admin`
- `GET /api/users.php?action=list`

### Arquivos principais da API

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

## Seguranca E Operacao

- o arquivo `.env` fica fora do versionamento por causa do `.gitignore`
- o instalador bloqueia nova instalacao quando o `.env` ja existe
- em producao, mantenha `APP_DEBUG=false`
- apos instalar, remova ou restrinja `public/install.php`
- revise contas padrao se voce importar o SQL manualmente

## Roadmap

- adicionar screenshots reais das telas no README
- integrar gateway de pagamento real
- melhorar o fluxo de renovacao de assinatura
- adicionar logs e observabilidade mais completos
- expandir a documentacao da API
- adaptar opcionalmente o projeto para cenarios sem `public/`

## Documentacao Adicional

- [ARQUIVOS_PARA_HOSPEDAGEM.md](file:///d:/Sites/KMKZIPTV/ARQUIVOS_PARA_HOSPEDAGEM.md)
- [README/README.md](file:///d:/Sites/KMKZIPTV/README/README.md)
- [README/PROJETO.md](file:///d:/Sites/KMKZIPTV/README/PROJETO.md)
- [README/QUICK_START.md](file:///d:/Sites/KMKZIPTV/README/QUICK_START.md)
- [README/API.md](file:///d:/Sites/KMKZIPTV/README/API.md)

## Solucao De Problemas

### O instalador nao abre

- confirme se o dominio aponta para `public/` ou se a estrutura `public_html` foi montada corretamente
- confirme se `public/.htaccess` ou a regra equivalente esta sendo respeitada
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

## Publicacao No Git

Caso esteja iniciando o versionamento agora:

```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/Ronickbr/iptv_php.git
git branch -M main
git push -u origin main
```
