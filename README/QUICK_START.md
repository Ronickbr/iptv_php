# 🚀 Início Rápido - KMKZ IPTV

## ⚡ Comandos Essenciais

### 1. Iniciar o projeto
```bash
docker compose up -d
```

### 2. Parar o projeto
```bash
docker compose down
```

### 3. Ver logs
```bash
docker compose logs -f
```

## 🌐 URLs de Acesso

| Serviço | URL | Credenciais |
|---------|-----|-------------|
| **Site Principal** | http://localhost:8080 | - |
| **phpMyAdmin** | http://localhost:8081 | root / rootpassword |

## 📱 Páginas Disponíveis

- **Home**: http://localhost:8080
- **Assinatura**: http://localhost:8080/subscribe.php
- **Pagamento**: http://localhost:8080/payment.php
- **Dashboard**: http://localhost:8080/dashboard.php
- **Prêmios**: http://localhost:8080/rewards.php

## 🎯 Teste Rápido

1. Acesse http://localhost:8080
2. Clique em "Assinar Agora" em qualquer plano
3. Preencha o formulário de cadastro
4. Simule o pagamento
5. Acesse o dashboard do usuário

## 🔧 Solução de Problemas

### Erro "Port already in use"
```bash
# Verificar o que está usando a porta
netstat -ano | findstr :8080

# Parar o processo ou mudar a porta no docker-compose.yml
```

### Banco não conecta
```bash
# Verificar status dos containers
docker compose ps

# Reiniciar apenas o MySQL
docker compose restart mysql
```

### Limpar tudo e recomeçar
```bash
docker compose down -v
docker compose up -d
```

## 📊 Dados de Teste

### Usuário Demo
- **Nome**: João Silva
- **Email**: joao@email.com
- **Pontos**: 250

### Planos Disponíveis
- **Mensal**: R$ 29,90
- **Trimestral**: R$ 79,90
- **Semestral**: R$ 149,90
- **Anual**: R$ 279,90

## 🎁 Sistema de Pontos

- **Indicação**: 100 pontos
- **Renovação**: 50 pontos
- **Avaliação**: 25 pontos
- **1 Mês Grátis**: 500 pontos

---

**💡 Dica**: Mantenha o Docker Desktop rodando antes de executar os comandos!