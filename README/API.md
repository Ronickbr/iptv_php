# API KMKZ IPTV

API REST para gerenciamento do sistema KMKZ IPTV - Sistema completo de gerenciamento de assinaturas IPTV com sistema de pontos gamificado.

## 📋 Índice

- [Visão Geral](#visão-geral)
- [Estrutura da API](#estrutura-da-api)
- [Autenticação](#autenticação)
- [Endpoints](#endpoints)
- [Códigos de Resposta](#códigos-de-resposta)
- [Exemplos de Uso](#exemplos-de-uso)
- [Segurança](#segurança)
- [Instalação](#instalação)

## 🎯 Visão Geral

A API KMKZ IPTV fornece endpoints RESTful para:

- **Autenticação**: Login, registro, recuperação de senha
- **Usuários**: Gerenciamento completo de usuários
- **Planos**: Criação e gerenciamento de planos de assinatura
- **Assinaturas**: Controle de assinaturas e renovações
- **Pagamentos**: Processamento de pagamentos (PIX, Cartão, etc.)
- **Pontos**: Sistema gamificado de pontos e recompensas
- **Dashboard**: Estatísticas e métricas do sistema

## 🏗️ Estrutura da API

```
api/
├── index.php          # Roteador principal
├── config.php         # Configurações e utilitários
├── middleware.php     # Middleware de segurança
├── auth.php          # Endpoints de autenticação
├── users.php         # Gerenciamento de usuários
├── plans.php         # Gerenciamento de planos
├── subscriptions.php # Gerenciamento de assinaturas
├── payments.php      # Gerenciamento de pagamentos
├── points.php        # Sistema de pontos e recompensas
├── dashboard.php     # Dashboards e estatísticas
├── .htaccess         # Configurações do Apache
└── README.md         # Esta documentação
```

## 🔐 Autenticação

A API utiliza autenticação baseada em sessões PHP. Para acessar endpoints protegidos:

1. Faça login através do endpoint `/api/auth.php?action=login`
2. A sessão será mantida automaticamente
3. Alguns endpoints requerem privilégios de administrador

### Login
```http
POST /api/auth.php?action=login
Content-Type: application/json

{
    "email": "usuario@exemplo.com",
    "password": "senha123"
}
```

## 📡 Endpoints

### Autenticação (`/api/auth.php`)

| Ação | Método | Descrição |
|------|--------|----------|
| `login` | POST | Fazer login |
| `logout` | POST | Fazer logout |
| `register` | POST | Registrar novo usuário |
| `check` | GET | Verificar sessão |
| `forgot_password` | POST | Recuperar senha |

### Usuários (`/api/users.php`)

| Ação | Método | Admin | Descrição |
|------|--------|-------|----------|
| `list` | GET | ✅ | Listar usuários |
| `get` | GET | ✅ | Obter usuário específico |
| `create` | POST | ✅ | Criar usuário |
| `update` | PUT | ✅ | Atualizar usuário |
| `delete` | DELETE | ✅ | Deletar usuário |
| `profile` | GET | ❌ | Ver próprio perfil |
| `stats` | GET | ✅ | Estatísticas de usuários |

### Planos (`/api/plans.php`)

| Ação | Método | Admin | Descrição |
|------|--------|-------|----------|
| `list` | GET | ❌ | Listar planos |
| `get` | GET | ❌ | Obter plano específico |
| `create` | POST | ✅ | Criar plano |
| `update` | PUT | ✅ | Atualizar plano |
| `delete` | DELETE | ✅ | Deletar plano |
| `duplicate` | POST | ✅ | Duplicar plano |
| `stats` | GET | ✅ | Estatísticas de planos |

### Assinaturas (`/api/subscriptions.php`)

| Ação | Método | Admin | Descrição |
|------|--------|-------|----------|
| `list` | GET | ✅ | Listar assinaturas |
| `get` | GET | ✅ | Obter assinatura específica |
| `create` | POST | ❌ | Criar assinatura |
| `update` | PUT | ✅ | Atualizar assinatura |
| `cancel` | POST | ❌ | Cancelar assinatura |
| `renew` | POST | ❌ | Renovar assinatura |
| `user_subscriptions` | GET | ❌ | Assinaturas do usuário |
| `stats` | GET | ✅ | Estatísticas de assinaturas |

### Pagamentos (`/api/payments.php`)

| Ação | Método | Admin | Descrição |
|------|--------|-------|----------|
| `list` | GET | ✅ | Listar pagamentos |
| `get` | GET | ✅ | Obter pagamento específico |
| `create` | POST | ❌ | Criar pagamento |
| `update_status` | PUT | ✅ | Atualizar status |
| `process_webhook` | POST | ❌ | Processar webhook |
| `user_payments` | GET | ❌ | Pagamentos do usuário |
| `generate_pix` | POST | ❌ | Gerar código PIX |
| `stats` | GET | ✅ | Estatísticas de pagamentos |

### Pontos (`/api/points.php`)

| Ação | Método | Admin | Descrição |
|------|--------|-------|----------|
| `balance` | GET | ❌ | Saldo de pontos |
| `history` | GET | ❌ | Histórico de pontos |
| `rules` | GET | ❌ | Regras de pontuação |
| `award` | POST | ✅ | Conceder pontos |
| `deduct` | POST | ✅ | Deduzir pontos |
| `leaderboard` | GET | ❌ | Ranking de usuários |
| `rewards` | GET | ❌ | Listar recompensas |
| `redeem` | POST | ❌ | Resgatar recompensa |
| `user_rewards` | GET | ❌ | Recompensas do usuário |
| `stats` | GET | ✅ | Estatísticas de pontos |

### Dashboard (`/api/dashboard.php`)

| Ação | Método | Admin | Descrição |
|------|--------|-------|----------|
| `admin` | GET | ✅ | Dashboard administrativo |
| `user` | GET | ❌ | Dashboard do usuário |
| `stats` | GET | ✅ | Estatísticas gerais |
| `recent_activity` | GET | ✅ | Atividade recente |
| `notifications` | GET | ❌ | Obter notificações |
| `mark_notification_read` | POST | ❌ | Marcar notificação como lida |

## 📊 Códigos de Resposta

| Código | Descrição |
|--------|----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida |
| 401 | Não autenticado |
| 403 | Acesso negado |
| 404 | Não encontrado |
| 405 | Método não permitido |
| 429 | Muitas requisições |
| 500 | Erro interno do servidor |

## 📝 Exemplos de Uso

### Criar um novo usuário
```http
POST /api/users.php?action=create
Content-Type: application/json

{
    "name": "João Silva",
    "email": "joao@exemplo.com",
    "password": "senha123",
    "phone": "11999999999"
}
```

### Listar planos disponíveis
```http
GET /api/plans.php?action=list&page=1&limit=10
```

### Criar uma assinatura
```http
POST /api/subscriptions.php?action=create
Content-Type: application/json

{
    "plan_id": 1,
    "payment_method": "pix"
}
```

### Verificar saldo de pontos
```http
GET /api/points.php?action=balance
```

## 🔒 Segurança

A API implementa várias camadas de segurança:

- **Rate Limiting**: Limite de requisições por IP
- **Validação de Entrada**: Sanitização contra XSS e SQL Injection
- **CSRF Protection**: Proteção contra ataques CSRF
- **Autenticação**: Sistema de sessões seguro
- **Headers de Segurança**: X-Frame-Options, X-XSS-Protection, etc.
- **Logs de Auditoria**: Registro de atividades importantes

## 🚀 Instalação

1. **Configurar Banco de Dados**:
   ```sql
   -- Execute o script database/init.sql
   ```

2. **Configurar Variáveis de Ambiente**:
   ```env
   DB_HOST=localhost
   DB_NAME=kmkz_iptv
   DB_USER=seu_usuario
   DB_PASS=sua_senha
   ```

3. **Configurar Apache**:
   - Certifique-se de que mod_rewrite está habilitado
   - O arquivo .htaccess já está configurado

4. **Testar a API**:
   ```http
   GET /api/
   ```

## 📞 Suporte

Para suporte técnico ou dúvidas sobre a API, consulte:

- Documentação completa do projeto: `PROJETO.md`
- Estrutura do banco: `database/init.sql`
- Comandos Docker: `docker-commands.md`

---

**KMKZ IPTV API v1.0** - Sistema completo de gerenciamento de assinaturas IPTV
