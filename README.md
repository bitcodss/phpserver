# PHP Server Stack 🔧

Docker-based PHP 7.4 hosting stack with Admin Dashboard.

## Architecture

- **PHP 7.4-FPM** — Docker container
- **Nginx** — Reverse proxy for PHP (port 9080)
- **MariaDB 10.11** — Database server
- **phpMyAdmin** — DB management UI (port 9081)
- **Redis 7** — Session/cache store
- **SFTP** — File access (port 2222)
- **Caddy** (host-level) — Outer reverse proxy with auto SSL

## Quick Start

```bash
# 1. Clone
git clone https://github.com/bitcodss/phpserver.git
cd phpserver

# 2. Configure environment
cp docker/.env.example docker/.env
# Edit docker/.env with your passwords

# 3. Start the stack
cd docker
docker compose up -d --build

# 4. Import database (optional)
docker exec -i cid-mariadb mysql -u root -p'YOUR_ROOT_PASSWORD' < docker/database/all_databases.sql

# 5. Import user grants (optional)
docker exec -i cid-mariadb mysql -u root -p'YOUR_ROOT_PASSWORD' < docker/database/users_grants.sql
```

## Directory Structure

```
├── docker/
│   ├── docker-compose.yml    # Main compose file
│   ├── .env.example          # Environment template
│   ├── php/                  # PHP-FPM Dockerfile & config
│   ├── nginx/                # Nginx configs
│   └── database/             # DB dumps & user grants
├── sites/
│   ├── opc.bitco.link/       # Admin dashboard site
│   │   └── public/admin/     # Dashboard UI & API
│   └── opc2.bitco.link/      # Application site
└── scripts/                  # Management scripts
```

## Admin Dashboard

Access at `http://your-domain/admin/` — provides:
- Server info & metrics
- Site management (add/remove sites)
- PHP settings editor
- FPM pool configuration  
- Database management (create/drop)
- User management
- Log viewer
- Security overview

## Ports

| Service | Port | Binding |
|---------|------|---------|
| Nginx | 9080 | localhost only |
| phpMyAdmin | 9081 | localhost only |
| SFTP | 2222 | public |

## Notes

- Caddy config is managed separately on the host
- Database dumps in `docker/database/` include all databases and user grants
- Default passwords in `.env.example` — **change them before deploying**
