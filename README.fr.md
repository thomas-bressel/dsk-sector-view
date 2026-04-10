# DSK Tool PHP

> Analyseur de disquettes Amstrad CPC au format Extended DSK — visualisation des pistes, secteurs, catalogue CP/M, protections et repackaging.

**Version 1.0.0 — 2026-04-10**

---

### Présentation

**DSK Tool PHP** est une application web en PHP pur permettant d'analyser des fichiers `.dsk` au format **Extended CPC DSK** (Amstrad CPC). Elle offre une interface moderne à onglets pour explorer l'ensemble des informations contenues dans une image disquette : structure physique, catalogue de fichiers CP/M, carte visuelle des secteurs, détection des protections et repackaging.

L'interface est disponible en **français**, **anglais**, **allemand** et **espagnol**.

---

### Fonctionnalités

- **Upload sécurisé** : formulaire avec vérification CSRF, validation de l'extension, de la taille (max 5 Mo) et de la signature binaire du fichier
- **Repackaging automatique** : génération d'un fichier `.dsk` repacké téléchargeable après chaque analyse
- **Onglet DISK** : carte visuelle circulaire des secteurs + spécifications générales (format, créateur, nombre de pistes/faces, tailles déclarées/réelles, Sum DATA) et répartition des secteurs par taille
- **Onglet FILES** : catalogue CP/M — liste des fichiers avec nom, extension, numéro d'utilisateur, attributs (lecture seule, caché) et taille
- **Onglet MAP** : carte visuelle des secteurs par piste avec code couleur (normal, effacé, weak, incomplet) et barre de statistiques
- **Onglet SECTORS** : tableau exhaustif de chaque secteur (ID, taille déclarée/réelle, Sum DATA, flags FDC SR1/SR2, statuts Erased/Weak/Used)
- **Onglet TRACKS** : récapitulatif par piste (nombre de secteurs, taille totale, octets GAP et FILLER, Sum DATA)
- **Onglet INFOS** : documentation technique complète sur les protections (Weak Sectors, Erased Sectors, Size 6, GAPS), le format DSK, les flags FDC et la FAT CP/M
- **Onglet DATA** : dump hexadécimal secteur par secteur
- **Interface multilingue** : FR / EN / DE / ES, choix persisté en session
- **Nettoyage automatique** des fichiers uploadés après 1 heure

---

### Code couleur des secteurs (onglets MAP et DISK)

| Couleur | Signification |
|---|---|
| Blanc `#FFFFFF` | Secteur normal — utilisé |
| Gris `#A0A0A0` | Secteur normal — vide |
| Bleu clair `#84CFEF` | Secteur effacé (Erased) — utilisé |
| Bleu `#0073DF` | Secteur effacé (Erased) — vide |
| Rouge `#FF0000` | Secteur faible (Weak) — utilisé |
| Rouge foncé `#A00000` | Secteur faible (Weak) — vide |
| Magenta `#FF00FF` | Weak + Erased — utilisé |
| Magenta foncé `#BA00BA` | Weak + Erased — vide |
| Orange `#FFB300` | Protection taille 6 (N=6) — utilisé |
| Blanc + bordure verte pointillée | Secteur incomplet (realSize ≠ declSize) |

---

### Structure du projet

```
dsk-tool-php/
├── index.php                        Point d'entrée unique (bootstrap + dispatch)
├── config/
│   └── app.php                      Constantes de configuration (version, chemins)
├── lang/
│   ├── fr.php                       Traductions françaises
│   ├── en.php                       Traductions anglaises
│   ├── de.php                       Traductions allemandes
│   └── es.php                       Traductions espagnoles
├── src/
│   ├── Domain/
│   │   ├── DskParser.php            Lecture binaire du fichier .dsk
│   │   ├── DskWriter.php            Écriture / repackaging du fichier .dsk
│   │   ├── CpmDirectoryParser.php   Parsing du catalogue CP/M
│   │   └── DiskStats.php            Calcul des métriques agrégées
│   ├── Service/
│   │   ├── CsrfService.php          Gestion du token CSRF
│   │   ├── FileCleanupService.php   Nettoyage des uploads expirés
│   │   ├── UploadService.php        Validation et stockage du fichier
│   │   ├── DskRepackager.php        Orchestration du repackaging
│   │   └── ProtectionDetector.php   Détection des protections
│   └── Helper/
│       └── FormatHelper.php         Fonctions utilitaires d'affichage
├── templates/
│   ├── layout.php                   Squelette HTML global (header, drapeaux, footer)
│   ├── upload.php                   Vue formulaire d'upload
│   ├── disk_banner.php              Bannière d'information disque
│   ├── partials/
│   │   └── error_msg.php            Composant message d'erreur
│   └── tabs/
│       ├── tab_disk.php             Onglet DISK (carte visuelle + spécifications)
│       ├── tab_files.php            Onglet FILES
│       ├── tab_map.php              Onglet MAP
│       ├── tab_sectors.php          Onglet SECTORS
│       ├── tab_tracks.php           Onglet TRACKS
│       ├── tab_infos.php            Onglet INFOS
│       └── tab_data.php             Onglet DATA (dump hexadécimal)
├── public/
│   └── assets/
│       ├── style.css                Styles CSS
│       ├── app.js                   JavaScript (onglets, drag-and-drop)
│       └── img/
│           ├── logo-dsk-tool-php.webp       Logo principal
│           └── logo-dsk-tool-php-mini.webp  Logo miniature (favicon, bannière)
└── files/                           Stockage temporaire des uploads
```

---

### Architecture

L'application suit une **séparation stricte des responsabilités** sans framework :

- **Domain** : logique métier pure (parsing binaire, écriture, calcul de statistiques). Aucune dépendance vers les couches supérieures.
- **Service** : orchestration des opérations transverses (upload, sécurité, nettoyage, repackaging, détection de protections).
- **Helper** : fonctions pures d'affichage réutilisables dans les templates.
- **Templates** : présentation uniquement. Aucune logique métier, uniquement de l'affichage conditionnel et des appels aux helpers.
- **`lang/`** : tableaux de traduction retournés par `require`, chargés selon la langue active en session.
- **`index.php`** : point d'entrée minimaliste qui orchestre les couches sans les mélanger.

---

### Prérequis

- PHP 8.0 ou supérieur
- Extension `fileinfo` activée
- Droits d'écriture sur le dossier `files/`
- Serveur web (Apache, Nginx, ou serveur de développement PHP)

---

### Installation

```bash
# Copier le dossier dsk-tool-php/ dans la racine web
# Vérifier les permissions sur le dossier d'upload
chmod 755 files/

# Lancer avec le serveur intégré PHP (développement)
php -S localhost:8080 -t .
```

Ouvrir ensuite `http://localhost:8080` dans un navigateur.

---

### Formats DSK supportés

- ✅ **Extended CPC DSK** (signature : `EXTENDED CPC DSK File`)
- ✅ **DSK standard MV-CPCEMU** (signature : `MV - CPCEMU`)
- ❌ Autres variantes DSK non supportées

---

### Déploiement avec Docker

Le projet inclut un `Dockerfile` et un `docker-compose.yml` prêts à l'emploi.

**Développement local :**
```bash
docker compose up --build
# Accès sur http://localhost:8080
```

**Production sur VPS :**
```bash
# Retirer le volume de code source dans docker-compose.yml (la ligne "- .:/var/www/html")
# puis builder et lancer en arrière-plan :
docker compose up --build -d
```

Les fichiers uploadés sont persistés dans un volume Docker nommé `dsk_uploads` — ils survivent aux redémarrages et rebuilds du conteneur.

---

### Historique des versions

| Version | Date | Notes |
|---|---|---|
| 1.0.0 | 2026-04-10 | Première version publique — interface multilingue FR/EN/DE/ES, carte visuelle, repackaging, détection des protections |
