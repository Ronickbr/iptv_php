# Instruções para Atualização do Sistema de Indicações

## ✅ Arquivos Modificados

O sistema de indicações foi atualizado com códigos únicos de 6 caracteres alfanuméricos. Os seguintes arquivos foram modificados:

### 1. **includes/functions.php**
- ✅ Adicionada função `generateReferralCode()` - gera códigos únicos de 6 caracteres
- ✅ Adicionada função `getUserByReferralCode()` - busca usuário por código
- ✅ Atualizada função `createReferral()` - gerencia indicações pendentes
- ✅ Adicionada função `processReferralSignup()` - processa cadastro de indicados
- ✅ Adicionada função `getUserReferralsCount()` - conta indicações do usuário
- ✅ Atualizada função `createUser()` - gera código automático e processa indicações

### 2. **dashboard.php**
- ✅ Link de indicação agora usa `referral_code` em vez de `id`
- ✅ Exibição do código de indicação atualizada
- ✅ Contagem de indicações usando nova função
- ✅ Mensagens de compartilhamento melhoradas

### 3. **ref.php** (NOVO)
- ✅ Processa links de referência com códigos únicos
- ✅ Armazena dados do indicador na sessão
- ✅ Redireciona para página de cadastro

### 4. **subscribe.php**
- ✅ Detecta usuários indicados
- ✅ Exibe informações do indicador no formulário
- ✅ Interface visual para indicações

## 🗄️ Atualização do Banco de Dados

### Opção 1: Executar Script PHP (Recomendado)

1. Acesse o arquivo via navegador:
   ```
   http://localhost/KMKZIPTV/update_database_manual.php
   ```

2. O script irá:
   - Adicionar campo `referral_code` na tabela `users`
   - Criar tabela `referrals` para gerenciar indicações
   - Gerar códigos únicos para usuários existentes

### Opção 2: Executar SQL Manualmente

Se preferir executar o SQL diretamente no banco:

```sql
-- 1. Adicionar campo referral_code
ALTER TABLE users ADD COLUMN referral_code VARCHAR(6) UNIQUE DEFAULT NULL AFTER points;

-- 2. Criar tabela referrals
CREATE TABLE referrals (
    id int(11) NOT NULL AUTO_INCREMENT,
    referrer_id int(11) NOT NULL,
    referred_email varchar(150) NOT NULL,
    referred_user_id int(11) DEFAULT NULL,
    status enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    points_awarded int(11) DEFAULT 0,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_referrer_id (referrer_id),
    KEY idx_referred_user_id (referred_user_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_referrals_referrer FOREIGN KEY (referrer_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_referrals_referred FOREIGN KEY (referred_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🎯 Como Funciona o Novo Sistema

### 1. **Geração de Códigos**
- Cada usuário recebe um código único de 6 caracteres (ex: `A1B2C3`)
- Códigos são alfanuméricos (letras maiúsculas + números)
- Geração automática no cadastro

### 2. **Links de Referência**
- Formato: `http://seusite.com/ref.php?code=A1B2C3`
- Ou: `http://seusite.com/subscribe.php?ref=A1B2C3&referrer=NomeUsuario`

### 3. **Processo de Indicação**
1. Usuário compartilha link com seu código
2. Novo usuário clica no link
3. Sistema detecta o indicador
4. Novo usuário se cadastra
5. Indicador recebe pontos automaticamente

### 4. **Rastreamento**
- Indicações pendentes ficam na tabela `referrals`
- Status: `pending` → `completed` quando usuário se cadastra
- Pontos concedidos automaticamente

## 🔧 Configurações

### Pontos por Indicação
Configure no admin em **Configurações de Pontos** → **Pontos por Indicação**

### Personalizar Mensagens
Edite as mensagens de compartilhamento em `dashboard.php` nas funções:
- `shareWhatsApp()`
- `shareFacebook()`

## 🚀 Testando o Sistema

1. **Acesse o dashboard** de um usuário
2. **Copie o link de indicação** (agora com código único)
3. **Abra em aba anônima** ou outro navegador
4. **Cadastre um novo usuário**
5. **Verifique se os pontos** foram concedidos ao indicador

## 📊 Monitoramento

### Verificar Códigos Gerados
```sql
SELECT name, email, referral_code FROM users WHERE referral_code IS NOT NULL;
```

### Verificar Indicações
```sql
SELECT 
    r.*,
    u1.name as referrer_name,
    u2.name as referred_name
FROM referrals r
LEFT JOIN users u1 ON r.referrer_id = u1.id
LEFT JOIN users u2 ON r.referred_user_id = u2.id
ORDER BY r.created_at DESC;
```

## ⚠️ Importante

- **Backup do banco** antes de executar as alterações
- **Teste em ambiente de desenvolvimento** primeiro
- **Códigos são únicos** - não podem ser duplicados
- **Links antigos** com ID ainda funcionam como fallback

---

**✅ Sistema implementado com sucesso!**

Agora cada usuário possui um código único de 6 caracteres para suas indicações, tornando o sistema mais profissional e fácil de usar.