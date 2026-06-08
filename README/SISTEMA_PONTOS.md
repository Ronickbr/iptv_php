# Sistema de Pontos - KMKZ IPTV

## 📋 Visão Geral

O Sistema de Pontos foi implementado para gamificar a experiência do usuário e incentivar ações específicas na plataforma KMKZ IPTV. Os usuários ganham pontos por diversas atividades e podem trocar por recompensas.

## 🎯 Funcionalidades Implementadas

### 1. Gerenciamento de Regras de Pontos (Admin)
- **Localização**: `admin.php` - Seção "Gerenciar Pontos"
- **Funcionalidades**:
  - Criar novas regras de pontos
  - Editar regras existentes
  - Ativar/desativar regras
  - Excluir regras
  - Conceder pontos manualmente
  - Visualizar histórico de pontos dos usuários
  - Acompanhar ranking VIP global e por período (Mês/Semana)

### 2. Tipos de Ações que Concedem Pontos

#### Login Diário
- **Pontos**: Configurável via admin
- **Limite**: 1 vez por dia
- **Integração**: `dashboard.php` (automático no login)

#### Assinatura de Plano
- **Pontos**: Configurável via admin
- **Integração**: `includes/functions.php` - função `createSubscription()`
- **Trigger**: Criação de nova assinatura

#### Indicação de Amigos
- **Pontos**: Configurável via admin
- **Integração**: `includes/functions.php` - função `createReferral()`
- **Trigger**: Indicação bem-sucedida

#### Pagamento Realizado
- **Pontos**: Configurável via admin
- **Integração**: `payment.php` e `includes/functions.php` - função `processPayment()`
- **Trigger**: Pagamento confirmado

#### Compartilhamento Social
- **Pontos**: Configurável via admin
- **Integração**: `includes/functions.php` - função `awardSocialSharePoints()`
- **Plataformas**: Facebook, Twitter, Instagram, WhatsApp

#### Avaliação/Review
- **Pontos**: Configurável via admin
- **Integração**: `includes/functions.php` - função `awardReviewPoints()`
- **Baseado**: Nota da avaliação (1-5 estrelas)

#### Cadastro Completo
- **Pontos**: Configurável via admin
- **Integração**: `includes/functions.php` - função `createUser()`
- **Trigger**: Primeiro cadastro do usuário

### 3. Ranking VIP de Membros
- **Localização**: `dashboard.php` - Aba "Ranking VIP"
- **Interface**: Design premium com badges (🥇, 🥈, 🥉), filtros de período e animações de entrada.
- **Filtros**: Sempre (total), Mês, Semana.
- **Integração**: Consome dinamicamente a API `api/points.php?action=leaderboard`.
- **Privacidade**: Mascaramento automático de emails de outros usuários.

## 🗄️ Estrutura do Banco de Dados

### Tabela: `points_rules`
```sql
CREATE TABLE points_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    points INT NOT NULL,
    daily_limit INT DEFAULT NULL,
    monthly_limit INT DEFAULT NULL,
    total_limit INT DEFAULT NULL,
    description TEXT,
    conditions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabela: `user_points_history`
```sql
CREATE TABLE user_points_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rule_id INT,
    action_type VARCHAR(100) NOT NULL,
    points INT NOT NULL,
    description TEXT,
    reference_id INT,
    reference_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (rule_id) REFERENCES points_rules(id)
);
```

## 🔧 Funções Principais

### Gerenciamento de Regras
- `getPointsRules($db, $activeOnly = false)` - Buscar regras
- `createPointsRule($db, $data)` - Criar nova regra
- `updatePointsRule($db, $id, $data)` - Atualizar regra
- `deletePointsRule($db, $id)` - Excluir regra
- `togglePointsRuleStatus($db, $id)` - Ativar/desativar regra

### Concessão de Pontos
- `awardPointsByRule($db, $userId, $actionType, $referenceId, $details)` - Conceder pontos por regra
- `awardDailyLoginPoints($db, $userId)` - Pontos de login diário
- `awardSocialSharePoints($db, $userId, $platform)` - Pontos de compartilhamento
- `awardReviewPoints($db, $userId, $rating)` - Pontos de avaliação
- `processPayment($db, $subscriptionId, $amount, $paymentMethod)` - Pontos de pagamento

### Verificações e Limites
- `checkPointsLimits($db, $userId, $ruleId, $actionType)` - Verificar limites
- `checkRuleConditions($db, $rule, $details)` - Verificar condições
- `getUserPointsHistory($db, $userId, $limit)` - Histórico do usuário

## 📁 Arquivos Modificados/Criados

### Arquivos Principais
1. **`admin.php`** - Interface de gerenciamento de pontos
2. **`admin_points_handler.php`** - Handler AJAX para operações de pontos
3. **`includes/functions.php`** - Funções do sistema de pontos
4. **`dashboard.php`** - Integração de login diário
5. **`payment.php`** - Integração de pontos por pagamento
6. **`test_points_system.php`** - Página de teste do sistema

### Integrações
- **Login Diário**: Automático no `dashboard.php`
- **Assinaturas**: Integrado na função `createSubscription()`
- **Indicações**: Integrado na função `createReferral()`
- **Pagamentos**: Integrado no `payment.php`
- **Cadastros**: Integrado na função `createUser()`

## 🧪 Como Testar

1. **Acesse a página de teste**: `test_points_system.php`
2. **Teste as ações**:
   - Login Diário
   - Compartilhamento Social
   - Fazer Avaliação
   - Criar Assinatura
   - Fazer Indicação

3. **Verifique no admin**: `admin.php` > Seção "Gerenciar Pontos"
   - Criar/editar regras
   - Ver histórico de pontos
   - Conceder pontos manualmente

## 🎮 Regras de Negócio

### Limites
- **Diário**: Máximo de pontos por dia para uma ação
- **Mensal**: Máximo de pontos por mês para uma ação
- **Total**: Máximo de pontos total para uma ação

### Condições
- Suporte a condições JSON personalizadas
- Verificação automática antes da concessão
- Flexibilidade para regras complexas

### Histórico
- Registro completo de todas as transações
- Rastreabilidade por usuário
- Referências a objetos relacionados

## 🔄 Fluxo de Concessão de Pontos

1. **Ação do Usuário** (login, pagamento, etc.)
2. **Verificação de Regra Ativa** para o tipo de ação
3. **Verificação de Limites** (diário, mensal, total)
4. **Verificação de Condições** específicas
5. **Concessão de Pontos** e atualização do saldo
6. **Registro no Histórico** para auditoria

## 🎁 Integração com Recompensas

O sistema de pontos está integrado com o sistema de recompensas existente em `rewards.php`, permitindo que os usuários troquem seus pontos por prêmios e benefícios.

## 🚀 Próximos Passos

1. **Notificações**: Sistema de notificações quando pontos são ganhos
2. **Badges**: Sistema de conquistas e badges
3. **API**: Endpoints para aplicativos móveis
5. **Analytics**: Dashboard de analytics de pontos

---

**Desenvolvido para KMKZ IPTV** 🎬✨