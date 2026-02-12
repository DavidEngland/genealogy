To take your genealogical tools to the next level, you can use these specific prompts in VS Code (Copilot, Cursor, or ChatGPT). I’ve organized them by the specific goal they achieve.
1. Enhancing the PHP Parsing Logic
The current script is great at "People," but "Sources" and "Places" are still a bit thin. Use these prompts to build out those modules:
 * For Source Mapping:
   > "Modify the parseGedcom function to identify level 0 @S@ (Source) and @R@ (Repository) records. Create a global $sourceLibrary array to store them. Then, update the INDI parsing logic so that when it hits a SOUR tag, it looks up the title from $sourceLibrary and adds a structured citation to the sources array in the JSON output."
   >
 * For Geo-Standardization:
   > "Refactor the extractLocations function. Instead of just returning a list of strings, have it build a unique places collection for the root of the JSON file. Assign each unique location a placeId (e.g., P1, P2) and replace the raw strings in the birth and death objects with these IDs to follow the schema's relational structure."
   >
 * For Date Normalization:
   > "Create a helper function normalizeGedcomDate that takes GEDCOM strings like 'ABT 1850' or 'BET 1861 AND 1865' and returns a structured object with a year (integer), isRange (boolean), and formalDate (ISO format where possible). Integrate this into the event parsing logic."
   >
2. Strengthening the JSON Schema
To make the schema a "source of truth" that prevents bad data from entering your system:
 * For Reusability ($defs):
   > "Refactor this JSON schema to use a $defs section. Define a reusable Event object and a SourceCitation object to reduce redundancy in the birth, death, marriage, and burial properties."
   >
 * For Strict Validation:
   > "Add regex pattern validation to all ID fields in the schema. For example, gedcomId should strictly follow ^@I\d+@$, and wikitreeId should require the Surname-Number format. Also, ensure that sex only accepts 'M', 'F', 'U', or 'X'."
   >
 * For Cross-Field Dependencies:
   > "Update the schema using if-then requirements. For example, if isPOW is true in the militaryService object, then prisonCamp should be a required field."
   >
3. Testing and Reliability
Genealogy data is famously messy. These prompts help you ensure your script doesn't crash on "edge case" GEDCOMs:
 * For Unit Testing:
   > "Write a PHPUnit test suite for this script. Focus on testing the extractDatabaseIds function with a variety of malformed URLs (e.g., Ancestry links with extra tracking parameters, FamilySearch ARKs, and plain text notes) to ensure the regex patterns are resilient."
   >
 * For Performance (Large GEDCOMs):
   > "The current script loads the entire GEDCOM into memory using file(). Refactor the loading logic to use a Generator or fgets() to stream the file line-by-line. This will allow the script to process files larger than 100MB without hitting PHP memory limits."
   >
4. Advanced WikiTree Features
If you want to automate the "social" side of WikiTree:
 * For Relationship Linking:
   > "Update the Markdown generator to automatically create 'See Also' sections. If a person has siblings or multiple spouses identified in the families array, generate a list of WikiTree-style links for those relatives at the bottom of the Biography section."
   >
A Logical Next Step
Would you like me to generate a "Category Rules" JSON file that you can feed into your script to automatically tag Civil War soldiers, Appalachian pioneers, or specific DNA-tested lines?
