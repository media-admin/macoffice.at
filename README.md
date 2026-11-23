# macoffice.at

Neuaufbau der Website **macoffice.at** auf Basis des Media Lab Starter Kits (Media Lab Tritremmel GmbH).

## Struktur

| Pfad | Inhalt |
|---|---|
| `cms/wp-content/themes/macoffice/` | Projekt-Theme (Vite, SCSS 7-1, ACF Pro), Ausgangsbasis: `custom-theme` des Starter Kits |
| `cms/wp-content/plugins/macoffice-project/` | Projekt-Plugin (CPTs, Taxonomien, ACF-JSON, Shortcodes), Ausgangsbasis: `media-lab-project-starter` |
| `cms/wp-content/plugins/media-lab-agency-core/` | Media Lab Agency Core (gemeinsam gepflegt) |
| `cms/wp-content/plugins/media-lab-woocommerce/` | Shop-Funktionen inkl. Katalogmodus (gemeinsam gepflegt) |
| `cms/wp-content/plugins/media-lab-backup/` | Media Lab Backup (gemeinsam gepflegt) |
| `cms/wp-content/mu-plugins/` | Must-Use-Plugins |
| `docs/` | Kit-Dokumentation (`docs/01–15`) sowie `docs/starter-kit/` (ehemalige Root-Dokumente des Kits) |

Nicht im Repo (laut `.gitignore`): WordPress-Core, `wp-config*.php`, Uploads, Drittanbieter-Plugins (Allowlist: nur `media-lab-*` und `macoffice-project`), `node_modules`, Build-Artefakte des Themes.

## Umgebungen

- **LocalDev:** `https://at.macoffice-2026.localdev` (Laravel Valet)
- **Staging:** `https://macoffice.media-lab.dev` (Hetzner)
- **Live:** `https://macoffice.at` (bleibt unverändert, bis die Abnahme erfolgt ist)

## LocalDev einrichten (Laravel Valet)

```bash
cd ~/Valet-Umgebung
git clone https://github.com/media-admin/macoffice.at.git at.macoffice-2026
cd at.macoffice-2026
bash scripts/local-setup.sh
```

Das Script legt DB `at_macoffice_2026` (Präfix `mo_`) an, installiert WordPress in `cms/` (siteurl mit `/cms`, home ohne), aktiviert Plugins und Theme, setzt den `wp-content`-Symlink, verlinkt und sichert die Domain in Valet und baut die Assets. Optionen und Variablen stehen im Kopf des Scripts. ACF Pro (Lizenz) wird aus einem bestehenden Projektordner kopiert.

## Entwicklung

```bash
composer install      # nur falls vendor/ fehlt
npm ci
npm run dev           # Vite-Dev-Server
npm run build         # Theme + Blöcke bauen
```

Zugangsdaten (DB, SFTP, Salts) gehören **nicht** ins Repo. Vorlagen: `.env.staging` / `.env.production` enthalten nur Platzhalter.

## Projektplanung

Phasen, Aufgaben und Entscheidungen werden im Notion-Projekt „Website macoffice.at“ geführt.
