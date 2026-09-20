# Backlog — juno

App-specifiek. Server-werk staat in `/home/claude/BACKLOG.md`.

## Open

### Fase 2 — echte aanroep
1. ai-studio: client `juno` aanmaken, prompt `juno-tick` met model Haiku 4.5.
   **Geblokkeerd:** ai-studio geeft `provider_quota`, het Anthropic-krediet is op.
2. `kernel/AiStudioAgent.php` — HTTP-client, `conversation_id: null`, timeout, nette
   foutafhandeling bij quota/5xx.
3. systemd service + timer (geen cron: geeft sandboxing en journalctl).

### Fase 3 — gates en metrieken
4. Zachte gate: tweede ai-studio-prompt als judge, drempel in `config.php`.
5. Voortgangs- en kwaliteitsmetriek samen in `metrics.csv` (de tang).

### Fase 4 — reflectie en critic
6. Reflectie-tick elke 10 ticks die `PLAYBOOK.md` bijwerkt, met cap van 20 regels.
7. Critic elke 20 ticks: leest het hele artifact tegen de bevroren missie.
8. Inbox-verwerking in de tick (sorteren naar backlog, playbook of journal).

### Fase 5 — sandbox
9. systemd-hardening: `ProtectSystem=strict`, `ProtectHome=tmpfs`, `BindPaths=/home/juno`,
   `PrivateTmp=yes`, `NoNewPrivileges=yes`.

### Overig
10. GitHub-repo `lrjohnst/juno` aanmaken en koppelen.
11. Klein leesdashboard (Laravel) dat `metrics.csv` en `JOURNAL.md` toont — alleen lezen.

## Afgerond

- Fase 1: skelet, bevroren missie, kernel, testsuite (2026-09-20).
