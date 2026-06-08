# Refatoração KMKZ IPTV — PHP → Node.js + Vite + React

> **Para Claude:** Use superpowers:executing-plans para implementar tarefa a tarefa.

**Goal:** Reescrever o projeto KMKZ IPTV de PHP puro para uma stack moderna com Node.js (API REST com Express), React (frontend com Vite) e MySQL (mesmas tabelas) em uma nova pasta `d:\Sites\KMKZIPTV-v2`.

**Arquitetura:**
- `backend/` — Express.js + mysql2 (REST API, JWT auth, mesma estrutura de rotas do PHP)
- `frontend/` — Vite + React + CSS puro (com Tailwind), replicando todo o design glassmorphism existente

**Tech Stack:**
- Node.js 20+, Express 4, mysql2, bcryptjs, jsonwebtoken, cors, dotenv, nodemon
- Vite 5, React 19, React Router 6, Axios, Chart.js (react-chartjs-2)

---

## Estrutura Final do Projeto

```
d:/Sites/KMKZIPTV-v2/
├── backend/
│   ├── src/
│   │   ├── config/        db.js, env.js
│   │   ├── middleware/    auth.js, rateLimit.js, errorHandler.js
│   │   ├── routes/        auth.js, users.js, plans.js, subscriptions.js,
│   │   │                  payments.js, points.js, rewards.js, admin.js, settings.js
│   │   ├── controllers/   (um por rota)
│   │   └── app.js
│   ├── database.sql       (mesmo schema PHP)
│   ├── .env.example
│   └── package.json
└── frontend/
    ├── src/
    │   ├── assets/css/    style.css, ui-enhancements.css, dashboard-fix.css
    │   ├── components/    Navbar, Sidebar, BentoGrid, StatCard, Modal…
    │   ├── pages/         Home, Login, Register, Dashboard, Admin, Plans, Rewards…
    │   ├── hooks/         useAuth.js, useApi.js
    │   ├── context/       AuthContext.jsx
    │   ├── services/      api.js (axios instance)
    │   └── main.jsx
    ├── index.html
    ├── vite.config.js
    └── package.json
```

---

## Tarefa 1 — Scaffolding do Monorepo

**Arquivos:** Criar `d:\Sites\KMKZIPTV-v2\` com ambas as pastas

**Passo 1:** Criar estrutura de pastas raiz
**Passo 2:** Inicializar backend com npm
**Passo 3:** Inicializar frontend com Vite + React

---

## Tarefa 2 — Backend: Config, DB e Middleware Auth

**Arquivos:**
- Criar: `backend/src/config/db.js`
- Criar: `backend/src/middleware/auth.js`
- Criar: `backend/src/middleware/errorHandler.js`
- Criar: `backend/src/app.js`
- Criar: `backend/.env.example`
- Criar: `backend/package.json`

---

## Tarefa 3 — Backend: Rotas de Auth (login/register/logout)

**Arquivos:**
- Criar: `backend/src/routes/auth.js`
- Criar: `backend/src/controllers/authController.js`

Replicar: `api/auth.php` — login, register, check-auth, logout

---

## Tarefa 4 — Backend: Rotas de Usuário e Dashboard

**Arquivos:**
- Criar: `backend/src/routes/users.js`
- Criar: `backend/src/controllers/usersController.js`

Replicar: `api/users.php`, `api/dashboard.php`

---

## Tarefa 5 — Backend: Planos, Assinaturas, Pagamentos

**Arquivos:**
- Criar: `backend/src/routes/plans.js`
- Criar: `backend/src/routes/subscriptions.js`
- Criar: `backend/src/routes/payments.js`
- Criar: `backend/src/controllers/` (3 controllers)

---

## Tarefa 6 — Backend: Pontos, Recompensas, Admin, Settings

**Arquivos:**
- Criar: `backend/src/routes/points.js`
- Criar: `backend/src/routes/rewards.js`
- Criar: `backend/src/routes/admin.js`
- Criar: `backend/src/controllers/` (4 controllers)

---

## Tarefa 7 — Frontend: Setup Vite + React + CSS

**Arquivos:**
- Criar: `frontend/vite.config.js`
- Criar: `frontend/src/main.jsx`
- Criar: `frontend/src/App.jsx` (rotas)
- Copiar: os 3 arquivos CSS do projeto PHP para `frontend/src/assets/css/`
- Criar: `frontend/src/services/api.js` (axios instance)
- Criar: `frontend/src/context/AuthContext.jsx`

---

## Tarefa 8 — Frontend: Componentes Compartilhados

**Arquivos:**
- Criar: `frontend/src/components/Sidebar.jsx`
- Criar: `frontend/src/components/TopNavbar.jsx`
- Criar: `frontend/src/components/BentoGrid.jsx`
- Criar: `frontend/src/components/StatCard.jsx`
- Criar: `frontend/src/components/Modal.jsx`
- Criar: `frontend/src/components/PointsBadge.jsx`

---

## Tarefa 9 — Frontend: Páginas Públicas (Home, Login, Planos)

**Arquivos:**
- Criar: `frontend/src/pages/Home.jsx` (index.php)
- Criar: `frontend/src/pages/Login.jsx` (login.php)
- Criar: `frontend/src/pages/Register.jsx`
- Criar: `frontend/src/pages/Plans.jsx` (plans.php)

---

## Tarefa 10 — Frontend: Dashboard do Usuário

**Arquivo:** `frontend/src/pages/Dashboard.jsx`

Replicar: `dashboard.php` completo com todas as seções (Bento Grid, Assinatura, Indicações, Ranking, Prêmios, Perfil, Suporte) e mais outras que poderiam estar a mostra.

---

## Tarefa 11 — Frontend: Painel Admin

**Arquivo:** `frontend/src/pages/Admin.jsx`

Replicar: `admin.php` (gestão de usuários, planos, pagamentos, pontos)

---

## Tarefa 12 — SQL e README Final

**Arquivos:**
- Criar: `backend/database.sql` (schema idêntico ao PHP, ou melhorado)
- Criar: `README.md` raiz com instruções de setup

