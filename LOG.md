# Changelog — DSK Tool PHP

## [1.0.0] — 2026-04-11

### Added
- Full multilingual support: FR, EN, DE, ES — session-persisted via `?lang=xx`
- Language switcher with inline SVG flags in the header
- Disk Visual Map (SVG, radial view) in the Disk tab
- Tracks tab — per-track summary (SPT, sector size, GAP, filler, Sum DATA)
- Hexagon Disk Protection detection — Type 1, 2 and 3
- Repack button — rebuilds the DSK with a new creator and CP/M FAT signature
- Version number and release date in the footer (`APP_VERSION`, `APP_DATE`)
- `.gitignore` — excludes uploaded `.dsk` files, `.claude/`, IDE folders
- Full English PHPDoc on all classes (`DskParser`, `DiskStats`, `DskWriter`, `DskRepackager`, `ProtectionDetector`, `CpmDirectoryParser`, `FormatHelper`, `CsrfService`, `FileCleanupService`)

### Changed
- Application renamed from *DSK Sector Viewer* to **DSK Tool PHP**
- `FormatHelper::bytes()` and `sectorTooltip()` now accept `$t` for localised units and flags
- All templates fully wired to `$t[key]` — no hardcoded strings remain
- Docker container renamed to `dsk-tool-php`

---

## [0.4.0] — 2026-04-10

### Added
- 4 language flags (FR / EN / DE / ES) in the header
- Repack download button in the disk banner

---

## [0.3.0] — 2026-04-08

### Added
- Hexagon Disk Protection detection — Type 2 and Type 3
- Disk Visual Map (radial SVG) in the Disk tab
- Tracks & Sectors tab

---

## [0.2.0] — 2026-03-31

### Added
- CI/CD pipeline — Docker Hub push and VPS deploy script

---

## [0.1.0] — 2026-03-25

### Added
- Initial project deposit — Extended DSK parser, sector map, CP/M directory reader
