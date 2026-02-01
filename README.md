# Genealogy Research Repository

A comprehensive genealogical research database with 2,100+ biographical records, GEDCOM processing infrastructure, and regional history documentation organized by state and county.

## Repository Structure

### 📁 Core Folders

| Folder | Purpose |
|--------|---------|
| **ancestors/** | 2,000+ WikiTree biographical profiles (Family-WikiTreeID.md format) |
| **ancestors/family/** | Per-person research notes (Surname/WikiTreeID-notes.md format) |
| **data/** | Processed genealogical exports (CSV lookup tables, JSON datasets) |
| **GEDs/** | GEDCOM source files and related documentation |
| **histories/** | Regional historical narratives organized by state/county/locality |
| **scripts/** | Utility scripts for data processing (PHP, Python, Shell) |
| **schema/** | GEDCOM-to-JSON conversion infrastructure and JSON schemas |
| **search-results/** | FamilySearch query result files (CSV format) |
| **research-notes/** | Active research documentation and source citations |
| **misc/** | Miscellaneous references (profiles/, references/ subfolders) |
| **natives/** | Native American lineage research |
| **Books/** | Reference materials and historical texts |
| **sources/** | Primary source documentation |
| **bak/** | Archive/backup files |

### 🗂️ Histories Structure

Regional histories organized by state and county:

```
histories/
├── AL/
│   ├── Lauderdale/
│   ├── Colbert/
│   └── Madison/
├── TN/
│   ├── Hardin/        ← Hardin County history (1815-1840)
│   ├── Lawrence/
│   └── Wayne/
└── KY/
```

Each county folder contains regional narratives, locality histories, and supporting documentation.

---

## 📊 Data Infrastructure

### Genealogical Records

- **2,100+ biographical files** - Individual and family profiles
- **19,000+ indexed records** - Genealogical data from GEDCOM processing
- **100+ CSV datasets** - Processed exports for analysis

### Schema & Conversion

The repository includes a **GEDCOM-to-JSON conversion pipeline** for standardized data processing:

- **schema.json** - JSON Schema defining genealogical record structure
- **gedcom_parser.php** - Converts GEDCOM files to JSON format
- **csv_exporter.php** - Exports JSON data to CSV (lookup or full format)
- **schema.v1.json, schema.v3.json** - Versioned schemas

See [schema/README.md](schema/README.md) for detailed documentation.

### Quick Start: GEDCOM Processing

1. Place GEDCOM files (.ged) in the [GEDs/](GEDs/) folder
2. Run GEDCOM parser via PHP:
   ```bash
   php schema/gedcom_parser.php input.ged > output.json
   ```
3. Export to CSV:
   ```bash
   php schema/csv_exporter.php output.json [lookup|full]
   ```

---

## 🛠️ Scripts

Utility scripts for genealogical data processing:

| Script | Purpose |
|--------|---------|
| **complete_updates.py** | Full genealogical record updates |
| **final_updates.py** | Final data processing and verification |
| **make_final_edits.py** | Batch editing for genealogical records |
| **update_shoals.py** | Update regional history for The Shoals area |
| **gedcom_parser.php** | Parse GEDCOM files to JSON |
| **csv_exporter.php** | Export JSON datasets to CSV |
| **wikitree_parser.php** | Extract WikiTree profile data |

Located in [scripts/](scripts/) folder. See [scripts/README.md](scripts/README.md) for documentation.

---

## 📝 Key Resources

- **[From Revolution to the Frontier.md](From%20Revolution%20to%20the%20Frontier.md)** - Historical overview
- **[Land grab at the Shoals.md](Land%20grab%20at%20the%20Shoals.md)** - The Shoals region history
- **[The Shoals.md](The%20Shoals.md)** - Geographic and demographic context
- **[16th Mississippi Militia.md](16th%20Mississippi%20Militia.md)** - Military records

---

## 🔍 Main WikiTree Families

The repository contains extensive biographical research for:

- **England** family
- **Gresham** family
- **Hargrove** family
- **Duncan** family
- **Lewis** family
- **Pigg** family
- **White/Whitten** families
- **Ball** family
- **Brewer** family
- **Lawson** family

See [ancestors/](ancestors/) for individual profiles.

---

## 📋 File Naming Conventions

- **Biographical files**: `FamilyName-WikiTreeID.md`
  - Example: `England-1357.md`, `Gresham-182.md`

- **GEDCOM files**: `FamilyName.ged` or `LocationName.ged`
  - Example: `Duncans.ged`, `JabezPerkins.ged`

- **Research notes**: `Topic-WikiTreeID-Research.md` or `Topic-sources.md`
  - Example: `Welch-1883-Research.md`, `England-1055-sources.md`

- **Per-person notes (active)**: `ancestors/family/Surname/Surname-WikiTreeID-notes.md`
  - Example: `ancestors/family/White/White-16150-notes.md`

- **Search results**: `FamilyName-search-results.csv` (FamilySearch exports)

---

## 🔐 Privacy & Git Configuration

- See [PRIVACY_QUICKSTART.md](PRIVACY_QUICKSTART.md) for privacy settings
- See [GIT_PRIVACY_SETUP.md](GIT_PRIVACY_SETUP.md) for git configuration
- Sensitive biographical data is managed per repository policies

---

## 🚀 Getting Started

1. **Browse ancestors**: Start in [ancestors/](ancestors/) for biographical profiles
2. **Explore histories**: Check [histories/](histories/) for regional narratives
3. **Process GEDCOM files**: Use schema infrastructure to convert and export genealogical data
4. **Research documentation**: See [research-notes/](research-notes/) for methodology and source citations

---

**Repository Owner**: David England  
**Last Updated**: January 31, 2026  
**Records**: 19,000+ indexed genealogical entries
