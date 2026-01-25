## Plan: GEDCOM-to-JSON Schema & CSV Export System

Create a comprehensive JSON schema aligned with schema.org microdata for genealogical records, plus a CSV export tool for wikitree IDs and names. This enables structured data storage (JSON), human-readable narrative (markdown), and index/lookup capability (CSV) across your genealogy project.

### Steps

1. **Define core JSON schema structure** mapping GEDCOM fields (INDI, FAM, BIRT, DEAT, MARR) to schema.org Person, Event, Place entities with custom genealogy extensions.

2. **Build schema.json** with top-level structure: `metadata` (project info), `people` (Person records with IDs/names/events), `families` (relationships), `events` (births/deaths/marriages), `places` (locations), and `sources`.

3. **Create GEDCOM parser script** to read `.ged` files and convert to JSON following the schema, preserving WikiTree IDs (`England-1357` format) and mapping GEDCOM `@I` numbers to structured records.

4. **Design CSV export template** with columns: `wikitree_id`, `name`, `birth_date`, `birth_place`, `death_date`, `death_place` to create a searchable index file.

5. **Determine mapping between GEDCOM IDs and WikiTree IDs** (how `@I135@` in GEDCOM relates to `England-1357` in your naming scheme).

6. **Plan integration points** for existing markdown files and scripts to reference the new JSON schema without breaking current workflows.

### Further Considerations

1. **GEDCOM import strategy** — Will you parse existing `.ged` files in bulk, or add incremental new records? Should the JSON become the source-of-truth with markdown as export, or vice versa?

2. **schema.org alignment details** — Do you want strict schema.org compliance for SEO/linked data, or loose alignment for internal use? This affects depth of modeling (e.g., detailed `schema:birthPlace` vs. simple string).

3. **Custom fields handling** — GEDCOM has custom extensions (`_FA1`, `_MREL`). Should these appear in JSON under a custom namespace, or normalize them to standard genealogy fields?