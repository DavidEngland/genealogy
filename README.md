# Genealogy Research Repository

Repository: https://github.com/DavidEngland/genealogy

A collection of genealogy processing scripts, workflow tools, and data extraction utilities. This repository contains working implementations for WikiTree profile management, GEDCOM parsing, FamilySearch integration, and automated biographical record generation.

**Focus**: Workflow automation and data processing tools for genealogical research. Eventually intended to develop reusable tools to aid other genealogists.

## Repository Structure

### 📁 Key Directories

| Folder | Purpose |
|--------|---------|
| **albums/** | Family heritage albums with photos and narratives |
| **scripts/** | PHP and Python utilities for data processing |
| **schema/** | GEDCOM-to-JSON conversion infrastructure |
| **unknowns/** | Generated search links for missing WikiTree IDs |
| **GEDs/** | GEDCOM source files |
| **search-results/** | FamilySearch query results (CSV) |
| **histories/** | Regional historical documentation (AL, TN, KY, MS) |
| **data/** | Processed genealogical exports (CSV, JSON) |

### 📄 Profile Files

Individual biographical profiles stored as `Surname-WikiTreeID.md` at repository root:
- Example: `England-1357.md`, `Hargrove-286.md`, `Duncan-3524.md`
- Format: WikiTree-compatible markdown with sources and citations
- Cross-referenced using WikiTree ID format

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

## 🛠️ Scripts & Workflow Tools

The repository contains practical genealogy automation scripts for data processing and workflow management. PHP implementations have proven more reliable than Python for most tasks.

### Working PHP Scripts

| Script | Purpose | Status |
|--------|---------|--------|
| **generate_album.php** | Generate family heritage album markdown from WikiTree data | ✓ New |
| **gedcom_parser.php** | Parse GEDCOM files to JSON format | ✓ Working |
| **csv_exporter.php** | Export JSON to CSV (lookup/full modes) | ✓ Working |
| **wikitree_profile_parser.php** | Extract WikiTree profile data | ✓ Working |
| **wikitree_convert.php** | Convert GEDCOM to WikiTree biography format | ✓ Working |
| **csv_to_wikitree_sources.php** | Generate WikiTree source citations from CSV | ✓ Working |
| **gedcom_to_biography.php** | Create biographical profiles from GEDCOM | ✓ Working |
| **gedcom_extract_sources.php** | Extract source citations | ✓ Working |
| **batch-sources.php** | Batch process source citations | ✓ Working |
| **wikitree_heritage_client.php** | WikiTree API client for heritage albums | ✓ New |

### Python Utility Scripts

| Script | Purpose | Notes |
|--------|---------|-------|
| **extract_unknown_wikitree_ids.py** | Generate search links for unknown WikiTree IDs | Latest working script |
| **search_16th_regiment.py** | WikiTree API search for military records | Functional but rate-limited |
| **csv_to_markdown_16th_regiment.py** | Convert CSV search results to markdown | Working |
| **update_shoals.py** | Regional history updates | Experimental |
| **complete_updates.py** | Batch biographical updates | Legacy/experimental |
| **final_updates.py** | Final data processing | Legacy/experimental |

**Note**: Recent Python projects for complex parsing have been less reliable. PHP implementations handle GEDCOM and WikiTree data more consistently.

---

## � Workflow Documentation

Documented workflows for genealogical research automation:

- **[FAMILYSEARCH-WORKFLOW.md](FAMILYSEARCH-WORKFLOW.md)** - FamilySearch to WikiTree integration process
- **[KNOWN_MATCHES_GUIDE.md](KNOWN_MATCHES_GUIDE.md)** - Matching and verification procedures
- **[IMPLEMENTATION_16TH_REGIMENT_COMPLETE.md](IMPLEMENTATION_16TH_REGIMENT_COMPLETE.md)** - Military record processing
- **[CHECKLIST_16TH_REGIMENT.md](CHECKLIST_16TH_REGIMENT.md)** - Regiment research checklist

### Key Workflows

1. **Family Heritage Albums** (Latest)
   - Generate markdown from WikiTree profiles
   - Add curated photos and family stories
   - Output ready for PDF conversion
   - Documentation: [albums/README.md](albums/README.md)
   - Setup: [albums/ALBUM_SETUP.md](albums/ALBUM_SETUP.md)

2. **Unknown WikiTree ID Search**
   - Extract persons with placeholder IDs (`-##`, `-?`, etc.)
   - Generate clickable WikiTree search links
   - Output: `unknowns/*.md` files for manual verification
   - Tool: `extract_unknown_wikitree_ids.py`

3. **GEDCOM Processing Pipeline**
   - Parse GEDCOM → JSON: `gedcom_parser.php`
   - Export JSON → CSV: `csv_exporter.php`
   - Convert to WikiTree format: `gedcom_to_biography.php`

4. **WikiTree API Integration**
   - Search WikiTree by name/dates: `search_16th_regiment.py`
   - Convert results to markdown: `csv_to_markdown_16th_regiment.py`
   - Batch source generation: `csv_to_wikitree_sources.php`

5. **FamilySearch Integration**
   - Manual search and CSV export via FamilySearch
   - Process CSV to WikiTree sources: `csv_to_wikitree_sources.php`
   - Update profiles with citations

---

## 🔍 WikiTree Profile Management

The repository manages biographical profiles primarily as WikiTree-formatted markdown files:

- **170+ individual profile files** (e.g., `England-1357.md`, `Hargrove-286.md`)
- **WikiTree ID format**: `Surname-Number`
- **Standardized structure**: Biography, Sources, References, Categories
- **Cross-referenced families**: England, Gresham, Hargrove, Duncan, Lewis, Ball, Brewer, White/Whitten, Lawson, Pigg families

### Data Sources
- GEDCOM files (multiple family lines)
- FamilySearch search results (CSV exports)
- WikiTree API queries
- Census records, military records, land records

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

### Quick Start: Generate Family Heritage Album
```bash
# Single person
php scripts/generate_album.php --id England-1357 --bio --parents --photos

# Batch mode (from CSV)
php scripts/generate_album.php --input albums/england-pioneers.csv --album england-pioneers

# Output: albums/builds/album-england-pioneers.md
```

Then manually edit the markdown and convert to PDF using Pandoc or VS Code preview.

### Quick Start: Extract Unknown WikiTree IDs
```bash
python3 extract_unknown_wikitree_ids.py
# Output: unknowns/*.md files with clickable search links
```

### GEDCOM Processing
1. Place GEDCOM files (.ged) in the [GEDs/](GEDs/) folder
2. Run GEDCOM parser:
   ```bash
   php schema/gedcom_parser.php input.ged > output.json
   ```
3. Export to CSV:
   ```bash
   php schema/csv_exporter.php output.json [lookup|full]
   ```

### WikiTree API Search
```bash
python3 scripts/search_16th_regiment.py  # Example: military record search
python3 scripts/csv_to_markdown_16th_regiment.py results.csv
```

---

## 🎯 Development Goals & Next Steps

- **Reusable genealogy tools** for broader community adoption
- **PHP-based parsing libraries** (more reliable than Python for GEDCOM/WikiTree data)
- **Automated WikiTree profile updates** with source citations
- **FamilySearch-to-WikiTree integration** workflows
- **Heritage album PDF generation** pipeline
- **Schema standardization** for genealogical JSON/XML formats

---

**Repository Owner**: David Edward England, PhD  
**ORCID**: https://orcid.org/0009-0001-2095-6646  
**Email**: DavidEngland@Hotmail.Com  
**Last Updated**: February 6, 2026  
**Primary Tools**: PHP (data processing), Python (search/extraction), Markdown (profiles)
