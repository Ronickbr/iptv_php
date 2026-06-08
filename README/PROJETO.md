# 🚀 KMKZ IPTV - Sistema Completo de Gerenciamento

## 📋 Visão Geral

O KMKZ IPTV é um sistema completo de gerenciamento de serviços IPTV com API REST robusta, sistema de pontos gamificado, gerenciamento de usuários, planos, assinaturas, pagamentos e recompensas.

## 🏗️ Arquitetura do Sistema

### Backend (API REST)
- **Linguagem**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Arquitetura**: RESTful API com padrão MVC
- **Autenticação**: Sessões PHP com middleware de segurança
- **Segurança**: Rate limiting, proteção contra força bruta, validação de entrada

### Estrutura de Arquivos

```
KMKZIPTV/
├── public/                    # Única pasta exposta pelo servidor web (DocumentRoot)
│   ├── .htaccess
│   ├── assets/                # CSS/JS/imagens do frontend
│   ├── api/                   # API REST (endpoints e roteamento via .htaccess)
│   │   ├── .htaccess
│   │   ├── index.php
│   │   ├── config.php
│   │   ├── middleware.php
│   │   ├── auth.php
│   │   ├── users.php
│   │   ├── plans.php
│   │   ├── subscriptions.php
│   │   ├── payments.php
│   │   ├── points.php
│   │   ├── settings.php
│   │   └── dashboard.php
│   ├── index.php              # Landing page
│   ├── login.php              # Autenticação do frontend
│   ├── dashboard.php          # Painel do usuário
│   └── admin.php              # Painel administrativo
├── config/                    # Configurações do app (fora da pasta pública)
├── includes/                  # Funções/utilitários PHP compartilhados (fora da pasta pública)
├── database/                  # Scripts SQL
│   ├── init.sql
│   ├── api_database.sql
│   ├── points_rules.sql
│   └── referrals_update.sql
├── scripts/                   # Scripts auxiliares (fora da pasta pública)
│   └── api_test.php
├── README/                    # Documentação
├── Dockerfile
├── docker-compose.yml
├── apache-config.conf
└── PROJETO.md
```

**Motivação da organização**:
- Evita expor arquivos internos (config/includes/sql/docs) ao público.
- Simplifica deploy: basta apontar o servidor web para `public/`.
- Reduz risco de vazamento acidental e facilita manutenção/escala do projeto.

## 🔧 Instalação e Configuração

### 1. Requisitos do Sistema
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache/Nginx com mod_rewrite
- Extensões PHP: PDO, JSON, mbstring

### 2. Instalação Rápida

1. **Clone/Baixe o projeto**:
   ```bash
   git clone [repositorio] KMKZIPTV
   cd KMKZIPTV
   ```

2. **Configure o servidor web** para apontar para a pasta do projeto

3. **Execute o instalador**:
   - Acesse: `http://seudominio.com/install.php`
   - Preencha as configurações do banco de dados
   - Configure a conta do administrador
   - Clique em "Instalar Sistema"

4. **Teste a instalação**:
   - API: `http://seudominio.com/api/`
   - Teste rápido: `scripts/api_test.php` (executar via CLI/ambiente interno)

### 3. Configuração Manual

Se preferir configurar manualmente:

1. **Crie o banco de dados**:
   ```sql
   CREATE DATABASE kmkz_iptv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importe a estrutura**:
   ```bash
   mysql -u usuario -p kmkz_iptv < database/api_database.sql
   ```

3. **Configure o arquivo .env** na pasta `api/`:
   ```env
   DB_HOST=localhost
   DB_NAME=kmkz_iptv
   DB_USER=seu_usuario
   DB_PASS=sua_senha
   API_VERSION=1.0
   API_DEBUG=false
   SESSION_TIMEOUT=3600
   ```

## 📊 Funcionalidades Principais

### 🔐 Sistema de Autenticação
- Login/Logout seguro
- Registro de usuários
- Verificação de sessão
- Proteção contra força bruta
- Rate limiting

### 👥 Gerenciamento de Usuários
- CRUD completo de usuários
- Níveis de acesso (admin/user)
- Estatísticas de usuários
- Histórico de atividades
- Sistema de pontos integrado

### 📋 Gerenciamento de Planos
- Criação e edição de planos
- Planos populares
- Configuração de recursos
- Estatísticas de assinaturas
- Duplicação de planos

### 📅 Sistema de Assinaturas
- Criação automática de assinaturas
- Renovação automática
- Controle de dispositivos
- Histórico completo
- Notificações de expiração

### 💳 Processamento de Pagamentos
- Múltiplos métodos de pagamento
- Histórico de transações
- Relatórios financeiros
- Integração com gateways
- Controle de inadimplência

### ⭐ Sistema de Pontos Gamificado
- Regras customizáveis de pontos
- Ações automáticas (login diário, compartilhamento)
- Histórico detalhado
- Estatísticas por usuário
- Limites diários/totais

### 🎁 Sistema de Recompensas
- Catálogo de recompensas
- Resgate com pontos
- Diferentes tipos (desconto, mês grátis, upgrade)
- Controle de estoque
- Histórico de resgates

### 📊 Dashboards e Relatórios
- Dashboard administrativo
- Dashboard do usuário
- Estatísticas em tempo real
- Relatórios financeiros
- Métricas de crescimento
- Saúde do sistema

## 🔌 API REST

### Endpoints Principais

#### Autenticação
- `POST /api/auth.php?action=login` - Login
- `POST /api/auth.php?action=logout` - Logout
- `POST /api/auth.php?action=register` - Registro
- `GET /api/auth.php?action=check` - Verificar sessão

#### Usuários
- `GET /api/users.php?action=list` - Listar usuários
- `GET /api/users.php?action=profile&id={id}` - Perfil do usuário
- `POST /api/users.php?action=create` - Criar usuário
- `PUT /api/users.php?action=update` - Atualizar usuário

#### Planos
- `GET /api/plans.php?action=list` - Listar planos
- `GET /api/plans.php?action=stats` - Estatísticas de planos
- `POST /api/plans.php?action=create` - Criar plano
- `PUT /api/plans.php?action=update` - Atualizar plano

#### Dashboard
- `GET /api/dashboard.php?action=admin_overview` - Visão geral admin
- `GET /api/dashboard.php?action=user_dashboard` - Dashboard usuário
- `GET /api/dashboard.php?action=system_health` - Saúde do sistema

### Formato de Resposta

```json
{
  "success": true,
  "data": {
    "message": "Operação realizada com sucesso",
    "result": {}
  },
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 100,
    "total_pages": 5
  }
}
```

## 🛡️ Segurança

### Medidas Implementadas
- **Rate Limiting**: Limite de requisições por IP
- **Proteção contra Força Bruta**: Bloqueio temporário após tentativas falhadas
- **Validação de Entrada**: Sanitização de todos os dados
- **Prepared Statements**: Proteção contra SQL Injection
- **CSRF Protection**: Validação de origem das requisições
- **Headers de Segurança**: X-Frame-Options, X-XSS-Protection, etc.
- **Auditoria**: Log de todas as ações importantes

### Configurações de Segurança
```php
// Rate limiting
ApiMiddleware::rateLimit(60, 3600); // 60 req/hora

// Proteção força bruta
ApiMiddleware::bruteForceProtection($email, $ip);

// Validação de entrada
ApiMiddleware::validateInput($data, $rules);
```

## 📱 Frontend (Exemplo)

O arquivo `example.html` demonstra como integrar com a API:

```javascript
// Fazer login
async function login() {
    const result = await apiRequest('auth.php?action=login', {
        method: 'POST',
        body: JSON.stringify({ email, password })
    });
}

// Listar planos
async function getPlans() {
    const result = await apiRequest('plans.php?action=list');
}
```

## 🗄️ Banco de Dados

### Tabelas Principais
- **users**: Usuários do sistema
- **plans**: Planos de assinatura
- **subscriptions**: Assinaturas ativas
- **payments**: Histórico de pagamentos
- **points_rules**: Regras do sistema de pontos
- **points_history**: Histórico de pontos
- **rewards**: Catálogo de recompensas
- **user_rewards**: Recompensas resgatadas
- **audit_log**: Log de auditoria
- **system_settings**: Configurações do sistema

### Relacionamentos
```
users (1) -----> (N) subscriptions
plans (1) -----> (N) subscriptions
subscriptions (1) -----> (N) payments
users (1) -----> (N) points_history
points_rules (1) -----> (N) points_history
rewards (1) -----> (N) user_rewards
users (1) -----> (N) user_rewards
```

## 🔄 Fluxo de Trabalho

### 1. Cadastro de Usuário
1. Usuário se registra via API
2. Sistema valida dados
3. Cria conta com pontos de boas-vindas
4. Envia email de confirmação (opcional)

### 2. Assinatura de Plano
1. Usuário escolhe plano
2. Sistema cria assinatura pendente
3. Processa pagamento
4. Ativa assinatura se pagamento aprovado
5. Concede pontos de assinatura

### 3. Sistema de Pontos
1. Usuário realiza ações (login, compartilhamento)
2. Sistema verifica regras aplicáveis
3. Concede pontos automaticamente
4. Atualiza saldo do usuário
5. Registra no histórico

### 4. Resgate de Recompensas
1. Usuário visualiza catálogo
2. Escolhe recompensa
3. Sistema verifica saldo de pontos
4. Processa resgate
5. Aplica benefício
6. Deduz pontos

## 📈 Monitoramento e Manutenção

### Logs do Sistema
- **Audit Log**: Todas as ações importantes
- **Error Log**: Erros e exceções
- **Access Log**: Acessos à API
- **Security Log**: Tentativas de acesso suspeitas

### Métricas Importantes
- Taxa de conversão de usuários
- Receita mensal recorrente (MRR)
- Churn rate (taxa de cancelamento)
- Engagement do sistema de pontos
- Performance da API

### Manutenção Regular
```sql
-- Otimizar tabelas
OPTIMIZE TABLE users, subscriptions, payments;

-- Limpar logs antigos
DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Verificar integridade
CHECK TABLE users, subscriptions, payments;
```

## 🚀 Próximos Passos

### Melhorias Sugeridas
1. **Frontend Completo**: Desenvolver interface web completa
2. **App Mobile**: Aplicativo para iOS/Android
3. **Integração de Pagamentos**: PIX, cartões, PayPal
4. **Notificações**: Email, SMS, push notifications
5. **Relatórios Avançados**: Business Intelligence
6. **API v2**: GraphQL ou melhorias REST
7. **Microserviços**: Separar em serviços menores
8. **Cache**: Redis/Memcached para performance
9. **CDN**: Distribuição de conteúdo
10. **Backup Automático**: Rotinas de backup

### Integrações Possíveis
- **Gateways de Pagamento**: Stripe, PagSeguro, Mercado Pago
- **Provedores de Email**: SendGrid, Mailgun
- **Analytics**: Google Analytics, Mixpanel
- **Suporte**: Zendesk, Intercom
- **Streaming**: Wowza, Nginx RTMP

## 📞 Suporte

### Documentação
- **API**: `/api/README.md`
- **Testes**: `/api/test.php`
- **Exemplo**: `/api/example.html`

### Troubleshooting

**Erro de Conexão com Banco**:
```bash
# Verificar configurações
cat api/.env

# Testar conexão
mysql -h localhost -u usuario -p banco
```

**Erro 500 na API**:
```bash
# Verificar logs
tail -f /var/log/apache2/error.log

# Verificar permissões
chmod 755 api/
chown www-data:www-data api/
```

**Rate Limit Atingido**:
```php
// Ajustar limites em config.php
define('API_RATE_LIMIT_REQUESTS', 100);
define('API_RATE_LIMIT_WINDOW', 3600);
```

## 📄 Licença

Este projeto é proprietário e confidencial. Todos os direitos reservados.

---

**Desenvolvido com ❤️ para KMKZ IPTV**

*Versão: 1.0*  
*Data: 2024*  
*Autor: Assistente AI*
