# DSK Tool PHP

> Amstrad CPC disk image analyser for the Extended DSK format — track, sector, CP/M catalogue, copy-protection visualisation and repacking.

**Version 1.0.0 — 2026-04-10**

---

### Overview

**DSK Tool PHP** is a pure PHP web application for analysing Amstrad CPC disk image files in **Extended CPC DSK** format. It provides a modern tabbed interface to explore all information contained in a disk image: physical structure, CP/M file catalogue, visual sector map, copy protection detection, and repacking.

The interface is available in **French**, **English**, **German** and **Spanish**.

---

### Features

- **Secure upload**: CSRF-protected form with extension validation, size limit (max 5 MB), and binary signature check
- **Automatic repacking**: a repacked `.dsk` file is generated and made available for download after each analysis
- **DISK tab**: circular visual sector map + general disk specifications (format, creator, track/side count, declared/real sizes, Sum DATA) and sector size breakdown
- **FILES tab**: CP/M catalogue — file list with name, extension, user number, attributes (read-only, hidden) and size
- **MAP tab**: visual sector map per track with colour coding (normal, erased, weak, incomplete) and statistics bar
- **SECTORS tab**: exhaustive table of every sector (ID, declared/real size, Sum DATA, FDC SR1/SR2 flags, Erased/Weak/Used status)
- **TRACKS tab**: per-track summary (sector count, total size, GAP and FILLER bytes, Sum DATA)
- **INFOS tab**: full technical documentation on protections (Weak Sectors, Erased Sectors, Size 6, GAPS), the DSK format, FDC flags, and the CP/M FAT
- **DATA tab**: sector-by-sector hexadecimal dump
- **Multilingual interface**: FR / EN / DE / ES, language choice persisted in session
- **Automatic cleanup** of uploaded files after 1 hour

---

### Sector colour coding (MAP and DISK tabs)

| Colour | Meaning |
|---|---|
| White `#FFFFFF` | Normal sector — used |
| Grey `#A0A0A0` | Normal sector — empty |
| Light blue `#84CFEF` | Erased sector — used |
| Blue `#0073DF` | Erased sector — empty |
| Red `#FF0000` | Weak sector — used |
| Dark red `#A00000` | Weak sector — empty |
| Magenta `#FF00FF` | Weak + Erased — used |
| Dark magenta `#BA00BA` | Weak + Erased — empty |
| Orange `#FFB300` | Size-6 protection (N=6) — used |
| White + dashed green border | Incomplete sector (realSize ≠ declSize) |

---

### Project structure

```
dsk-tool-php/
├── index.php                        Single entry point (bootstrap + dispatch)
├── config/
│   └── app.php                      Configuration constants (version, paths)
├── lang/
│   ├── fr.php                       French translations
│   ├── en.php                       English translations
│   ├── de.php                       German translations
│   └── es.php                       Spanish translations
├── src/
│   ├── Domain/
│   │   ├── DskParser.php            Binary parsing of the .dsk file
│   │   ├── DskWriter.php            Writing / repacking of the .dsk file
│   │   ├── CpmDirectoryParser.php   CP/M directory parsing
│   │   └── DiskStats.php            Aggregated metrics computation
│   ├── Service/
│   │   ├── CsrfService.php          CSRF token management
│   │   ├── FileCleanupService.php   Expired upload cleanup
│   │   ├── UploadService.php        File validation and storage
│   │   ├── DskRepackager.php        Repacking orchestration
│   │   └── ProtectionDetector.php   Copy-protection detection
│   └── Helper/
│       └── FormatHelper.php         Display utility functions
├── templates/
│   ├── layout.php                   Global HTML skeleton (header, flags, footer)
│   ├── upload.php                   Upload form view
│   ├── disk_banner.php              Disk info banner
│   ├── partials/
│   │   └── error_msg.php            Reusable error message component
│   └── tabs/
│       ├── tab_disk.php             DISK tab (visual map + specifications)
│       ├── tab_files.php            FILES tab
│       ├── tab_map.php              MAP tab
│       ├── tab_sectors.php          SECTORS tab
│       ├── tab_tracks.php           TRACKS tab
│       ├── tab_infos.php            INFOS tab
│       └── tab_data.php             DATA tab (hex dump)
├── public/
│   └── assets/
│       ├── style.css                CSS styles
│       ├── app.js                   JavaScript (tabs, drag-and-drop)
│       └── img/
│           ├── logo-dsk-tool-php.webp       Main logo
│           └── logo-dsk-tool-php-mini.webp  Miniature logo (favicon, banner)
└── files/                           Temporary upload storage
```

---

### Architecture

The application follows a **strict separation of concerns** without any framework:

- **Domain**: pure business logic (binary parsing, writing, statistics). No dependency on upper layers.
- **Service**: orchestration of cross-cutting operations (upload, security, cleanup, repacking, protection detection).
- **Helper**: pure display functions reusable across templates.
- **Templates**: presentation only. No business logic — only conditional rendering and helper calls.
- **`lang/`**: translation arrays returned by `require`, loaded according to the active language stored in session.
- **`index.php`**: minimal entry point that orchestrates layers without mixing them.

---

### Requirements

- PHP 8.0 or higher
- `fileinfo` extension enabled
- Write permission on the `files/` directory
- Web server (Apache, Nginx, or PHP built-in server)

---

### Installation

```bash
# Copy the dsk-tool-php/ folder to your web root
# Ensure the upload directory is writable
chmod 755 files/

# Run with PHP built-in server (development)
php -S localhost:8080 -t .
```

Then open `http://localhost:8080` in your browser.

---

### Supported DSK formats

- ✅ **Extended CPC DSK** (signature: `EXTENDED CPC DSK File`)
- ✅ **Standard MV-CPCEMU DSK** (signature: `MV - CPCEMU`)
- ❌ Other DSK variants not supported

---

### Docker deployment

The project ships with a ready-to-use `Dockerfile` and `docker-compose.yml`.

**Local development:**
```bash
docker compose up --build
# Open http://localhost:8080
```

**Production on a VPS:**
```bash
# Remove the source volume from docker-compose.yml (the "- .:/var/www/html" line)
# then build and start in the background:
docker compose up --build -d
```

Uploaded files are persisted in a named Docker volume (`dsk_uploads`) — they survive container restarts and rebuilds.

---

### Changelog

| Version | Date | Notes |
|---|---|---|
| 1.0.0 | 2026-04-10 | First public release — FR/EN/DE/ES multilingual interface, visual disk map, repacking, copy-protection detection |
