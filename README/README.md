# KMKZ IPTV - Sistema Completo

Um sistema completo de IPTV desenvolvido em PHP, MySQL e Bootstrap com ambiente Docker.

## 🚀 Características

- **Frontend Responsivo**: Interface moderna com Bootstrap 5
- **Sistema de Usuários**: Registro, login e dashboard
- **Planos de Assinatura**: Múltiplos planos com diferentes durações
- **Sistema de Fidelidade**: Pontos por indicações e resgates
- **Programa de Indicações**: Ganhe pontos indicando amigos
- **Loja de Prêmios**: Troque pontos por benefícios
- **Ambiente Docker**: Configuração completa com Docker Compose
- **Banco de Dados**: MySQL com estrutura completa

## 📋 Pré-requisitos

- Docker Desktop
- Docker Compose
- Git (opcional)

## 🛠️ Instalação

### 1. Clone ou baixe o projeto

```bash
git clone <url-do-repositorio>
cd KMKZIPTV
```

### 2. Inicie os containers Docker

```bash
docker-compose up -d
```

Este comando irá:
- Criar e iniciar o container do MySQL
- Criar e iniciar o container do PHP/Apache
- Criar e iniciar o container do phpMyAdmin
- Executar automaticamente o script de inicialização do banco

### 3. Aguarde a inicialização

Aguarde alguns minutos para que todos os serviços sejam iniciados e o banco de dados seja configurado.

### 4. Acesse a aplicação

- **Site Principal**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
  - Usuário: `root`
  - Senha: `rootpassword`

## 🌐 URLs Disponíveis

| Página | URL | Descrição |
|--------|-----|-----------|
| Home | http://localhost:8080 | Página inicial com planos |
| Assinatura | http://localhost:8080/subscribe.php | Formulário de assinatura |
| Pagamento | http://localhost:8080/payment.php | Simulação de pagamento |
| Dashboard | http://localhost:8080/dashboard.php | Painel do usuário |
| Prêmios | http://localhost:8080/rewards.php | Loja de prêmios |
| phpMyAdmin | http://localhost:8081 | Administração do banco |

## 🗄️ Estrutura do Banco de Dados

O sistema possui as seguintes tabelas:

- **users**: Usuários do sistema
- **plans**: Planos de assinatura disponíveis
- **subscriptions**: Assinaturas dos usuários
- **referrals**: Sistema de indicações
- **rewards**: Prêmios disponíveis
- **reward_redemptions**: Histórico de resgates
- **testimonials**: Depoimentos dos clientes

## 🎯 Funcionalidades Principais

### Sistema de Assinatura
- Múltiplos planos (Mensal, Trimestral, Semestral, Anual)
- Processo de pagamento simulado
- Diferentes métodos de pagamento (PIX, Cartão, Boleto)

### Sistema de Fidelidade
- Ganhe 100 pontos por indicação
- Ganhe 50 pontos por renovação
- Ganhe 25 pontos por avaliação
- Troque pontos por meses grátis, descontos e brindes

### Dashboard do Usuário
- Visualização da assinatura ativa
- Estatísticas de indicações
- Saldo de pontos
- Link de indicação personalizado
- Compartilhamento em redes sociais

### Loja de Prêmios
- Resgate de prêmios com pontos
- Histórico de resgates
- Diferentes tipos de prêmios

## 🔧 Configuração

### Variáveis de Ambiente

As configurações do banco estão no `docker-compose.yml`:

```yaml
MYSQL_ROOT_PASSWORD: rootpassword
MYSQL_DATABASE: kmkz_iptv
MYSQL_USER: kmkz_user
MYSQL_PASSWORD: kmkz_password
```

### Configuração do PHP

As configurações de conexão estão em `config/database.php` e usam as variáveis de ambiente automaticamente.

## 📱 Design Responsivo

O sistema é totalmente responsivo e funciona em:
- Desktop
- Tablets
- Smartphones

## 🎨 Personalização

### Cores e Estilos

As cores principais estão definidas em `assets/css/style.css`:

```css
:root {
    --primary-color: #FFD700;
    --secondary-color: #FFA500;
    --background-dark: #0a0a0a;
    --background-card: #1a1a1a;
    --text-light: #ffffff;
    --text-muted: #cccccc;
}
```

### Logo e Imagens

Substitua as imagens em `assets/images/` pelas suas próprias:
- `logo.png`: Logo da empresa
- Outras imagens conforme necessário

## 🚀 Deploy em Produção

### 1. Configurações de Segurança

- Altere as senhas do banco de dados
- Configure HTTPS
- Ajuste as configurações do PHP para produção

### 2. Variáveis de Ambiente

Crie um arquivo `.env` com suas configurações:

```env
DB_HOST=localhost
DB_NAME=kmkz_iptv
DB_USER=seu_usuario
DB_PASS=sua_senha
```

### 3. Backup do Banco

```bash
docker exec kmkziptv_mysql mysqldump -u root -p kmkz_iptv > backup.sql
```

## 🛠️ Comandos Úteis

### Parar os containers
```bash
docker-compose down
```

### Ver logs
```bash
docker-compose logs -f
```

### Reiniciar um serviço
```bash
docker-compose restart web
```

### Acessar o container do PHP
```bash
docker exec -it kmkziptv_web bash
```

### Acessar o MySQL
```bash
docker exec -it kmkziptv_mysql mysql -u root -p
```

## 📁 Estrutura de Arquivos

```
KMKZIPTV/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── script.js
│   └── images/
├── config/
│   └── database.php
├── database/
│   └── init.sql
├── includes/
│   └── functions.php
├── apache-config.conf
├── docker-compose.yml
├── Dockerfile
├── index.php
├── subscribe.php
├── payment.php
├── dashboard.php
├── rewards.php
└── README.md
```

## 🐛 Solução de Problemas

### Erro de Conexão com Banco
- Verifique se o container MySQL está rodando
- Confirme as credenciais no `docker-compose.yml`
- Aguarde a inicialização completa do MySQL

### Página não carrega
- Verifique se o container web está rodando
- Confirme se a porta 8080 está disponível
- Verifique os logs: `docker-compose logs web`

### phpMyAdmin não acessa
- Verifique se a porta 8081 está disponível
- Confirme as credenciais (root/rootpassword)
- Aguarde a inicialização do MySQL

## 📞 Suporte

Para suporte técnico:
- Verifique os logs dos containers
- Consulte a documentação do Docker
- Verifique as configurações do PHP e MySQL

## 📄 Licença

Este projeto é fornecido como está, para fins educacionais e de demonstração.

## 🔄 Atualizações

Para atualizar o projeto:
1. Faça backup do banco de dados
2. Baixe a nova versão
3. Execute `docker-compose down && docker-compose up -d`
4. Restaure os dados se necessário

---

**Desenvolvido com ❤️ usando PHP, MySQL, Bootstrap e Docker**