# Arquivos Para Hospedagem

## O que subir

Suba estas pastas e arquivos para o servidor:

- `config/`
- `database/`
- `includes/`
- `public/`
- `.env.example`

## O que nao precisa subir

Estes arquivos e pastas sao opcionais e podem ficar fora da hospedagem:

- `README/`
- `scripts/`
- `Dockerfile`
- `docker-compose.yml`
- `apache-config.conf`
- `kmkz_refactor_plan.md`

## Estrutura recomendada

O ideal e enviar o projeto mantendo a estrutura original e configurar o dominio ou subdominio para apontar para a pasta `public/`.

Exemplo:

```text
/home/SEU_USUARIO/kmkziptv/
|-- config/
|-- database/
|-- includes/
|-- public/
|-- .env
```

Document root do dominio:

```text
/home/SEU_USUARIO/kmkziptv/public
```

## Como instalar depois do upload

1. Envie os arquivos para a hospedagem.
2. Crie o banco de dados e o usuario do banco no painel da hospedagem, se necessario.
3. Aponte o dominio/subdominio para a pasta `public/`.
4. Acesse `https://seu-dominio.com/install.php`.
5. Preencha os dados do banco, URL do site e administrador.
6. Conclua a instalacao para o sistema gerar o arquivo `.env`.

## Observacoes importantes

- Se a hospedagem nao permitir que o PHP crie o banco automaticamente, crie o banco antes no painel e informe os mesmos dados no instalador.
- Depois da instalacao, o `install.php` fica bloqueado porque o `.env` ja existe.
- Se quiser reinstalar, acesse `install.php?force=1`.
- Se sua hospedagem nao permitir apontar o dominio para a pasta `public/`, sera melhor adaptar o projeto para a estrutura `public_html`. Se quiser, eu posso fazer essa adaptacao tambem.
