<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordProperty;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use League\Csv\Reader;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Prefixes used to encode extension-data mappings in the column-mapping payload
const CSV_PERSON_CUSTOM_PREFIX = 'pcustom_';
const CSV_FAMILY_CUSTOM_PREFIX = 'fcustom_';
const CSV_PERSON_PROP_PREFIX   = 'pprop_';
const CSV_FAMILY_PROP_PREFIX   = 'fprop_';

// Stable English category tags appended to CSV column headers for extension
// fields, e.g. "Highest Degree Received (Person Custom)". Intentionally NOT
// translated — the suffix has to parse consistently across locales so a
// template downloaded in one language still auto-maps in another.
const CSV_GROUP_TAGS = [
    'Person Custom'   => 'Person Custom',
    'Family Custom'   => 'Family Custom',
    'Person Property' => 'Person Property',
    'Family Property' => 'Family Property',
];

// Human-readable labels for the built-in Person/Family columns. Keys must match CSV_FIELD_ALIASES.
const CSV_CORE_FIELD_LABELS = [
    'FamilyID'       => 'Family ID',
    'Title'          => 'Title',
    'FirstName'      => 'First Name',
    'MiddleName'     => 'Middle Name',
    'LastName'       => 'Last Name',
    'Suffix'         => 'Suffix',
    'Gender'         => 'Gender',
    'Envelope'       => 'Envelope',
    'Address1'       => 'Address 1',
    'Address2'       => 'Address 2',
    'City'           => 'City',
    'State'          => 'State',
    'Zip'            => 'Zip / Postal Code',
    'Country'        => 'Country',
    'HomePhone'      => 'Home Phone',
    'WorkPhone'      => 'Work Phone',
    'MobilePhone'    => 'Mobile Phone',
    'Email'          => 'Email',
    'WorkEmail'      => 'Work Email',
    'BirthDate'      => 'Birth Date',
    'MembershipDate' => 'Membership Date',
    'WeddingDate'    => 'Wedding Date',
    'Classification' => 'Classification',
    'FamilyRole'     => 'Family Role',
];

// Maps ChurchCRM field names to known CSV header aliases (all lowercase)
const CSV_FIELD_ALIASES = [
    'FamilyID'       => ['familyid', 'family_id', 'family id', 'fid'],
    'Title'          => ['title', 'prefix', 'salutation'],
    'FirstName'      => ['firstname', 'first_name', 'first name', 'fname', 'first', 'given_name', 'given name'],
    'MiddleName'     => ['middlename', 'middle_name', 'middle name', 'mname', 'middle'],
    'LastName'       => ['lastname', 'last_name', 'last name', 'lname', 'last', 'surname', 'family_name', 'family name'],
    'Suffix'         => ['suffix'],
    'Gender'         => ['gender', 'sex'],
    'Envelope'       => ['envelope', 'envelope_number', 'envelope number'],
    'Address1'       => ['address1', 'address_1', 'address', 'street', 'street_address'],
    'Address2'       => ['address2', 'address_2'],
    'City'           => ['city', 'town'],
    'State'          => ['state', 'province', 'region'],
    'Zip'            => ['zip', 'zipcode', 'zip_code', 'postal', 'postal_code', 'postcode'],
    'Country'        => ['country'],
    'HomePhone'      => ['homephone', 'home_phone', 'home phone', 'phone', 'telephone'],
    'WorkPhone'      => ['workphone', 'work_phone', 'work phone', 'office_phone', 'office phone'],
    'MobilePhone'    => ['mobilephone', 'mobile_phone', 'mobile phone', 'cellphone', 'cell_phone', 'cell phone', 'mobile', 'cell'],
    'Email'          => ['email', 'e-mail', 'email_address', 'email address'],
    'WorkEmail'      => ['workemail', 'work_email', 'work email', 'business_email', 'business email'],
    'BirthDate'      => ['birthdate', 'birth_date', 'birth date', 'birthday', 'dob', 'date_of_birth', 'date of birth'],
    'MembershipDate' => ['membershipdate', 'membership_date', 'membership date', 'joined', 'join_date', 'join date'],
    'WeddingDate'    => ['weddingdate', 'wedding_date', 'wedding date', 'anniversary'],
    'Classification' => ['classification', 'class', 'member_type', 'member type', 'membertype', 'membership_type', 'membership type'],
    'FamilyRole'     => ['familyrole', 'family_role', 'family role', 'role', 'household_role', 'household role'],
];

function autoMapHeader(string $header, array $extensionFields = []): ?string
{
    $normalized = strtolower(trim($header));
    foreach (CSV_FIELD_ALIASES as $field => $aliases) {
        if (in_array($normalized, $aliases, true)) {
            return $field;
        }
    }
    // Extension fields: match on (a) the bare display name ("Notes") and
    // (b) the category-suffixed form emitted by the downloaded template
    // ("Notes (Family Property)"). The suffixed form disambiguates when a
    // name collides across categories — the bare-name branch below picks
    // the first match, which is acceptable as long as the template suffix
    // is preserved on the way back in.
    foreach ($extensionFields as $field) {
        $label = strtolower(trim($field['label']));
        if ($label === $normalized) {
            return $field['key'];
        }
        if (!empty($field['groupTag'])) {
            $suffixed = strtolower($field['label'] . ' (' . $field['groupTag'] . ')');
            if ($suffixed === $normalized) {
                return $field['key'];
            }
        }
    }
    return null;
}

/**
 * Build the full list of mappable target fields: core Person/Family columns,
 * custom person/family fields, and person/family properties — each tagged
 * with a human-readable label and a group for <optgroup> rendering.
 *
 * @return array<int, array{key: string, label: string, group: string}>
 */
function buildCsvImportFieldCatalog(): array
{
    $fields = [];

    foreach (CSV_CORE_FIELD_LABELS as $key => $label) {
        $fields[] = ['key' => $key, 'label' => gettext($label), 'group' => gettext('Person / Family'), 'groupTag' => ''];
    }

    foreach (PersonCustomMasterQuery::create()->orderByOrder()->find() as $cf) {
        // PersonCustomMaster::getId() already returns the raw column name
        // ("c3") — do NOT prepend another 'c'. The writer validates /^c\d+$/
        // on the suffix and looks up the type map by the same string.
        $fields[] = [
            'key'      => CSV_PERSON_CUSTOM_PREFIX . $cf->getId(),
            'label'    => $cf->getName(),
            'group'    => gettext('Person Custom'),
            'groupTag' => CSV_GROUP_TAGS['Person Custom'],
        ];
    }

    foreach (FamilyCustomMasterQuery::create()->orderByOrder()->find() as $cf) {
        $fields[] = [
            'key'      => CSV_FAMILY_CUSTOM_PREFIX . $cf->getField(),
            'label'    => $cf->getName(),
            'group'    => gettext('Family Custom'),
            'groupTag' => CSV_GROUP_TAGS['Family Custom'],
        ];
    }

    foreach (PropertyQuery::create()->filterByProClass('p')->orderByProName()->find() as $prop) {
        $fields[] = [
            'key'      => CSV_PERSON_PROP_PREFIX . $prop->getProId(),
            'label'    => $prop->getProName(),
            'group'    => gettext('Person Property'),
            'groupTag' => CSV_GROUP_TAGS['Person Property'],
        ];
    }

    foreach (PropertyQuery::create()->filterByProClass('f')->orderByProName()->find() as $prop) {
        $fields[] = [
            'key'      => CSV_FAMILY_PROP_PREFIX . $prop->getProId(),
            'label'    => $prop->getProName(),
            'group'    => gettext('Family Property'),
            'groupTag' => CSV_GROUP_TAGS['Family Property'],
        ];
    }

    return $fields;
}

/**
 * Build the CSV column header for a catalog entry. Core columns emit the
 * stable machine key ("FamilyID", "FirstName") so a template downloaded in
 * one locale still auto-maps cleanly in another — localizing the header
 * would break round-tripping across locales. Extension columns use the
 * user's own label plus a category suffix so the source is visible in
 * Excel and headers stay unique across the four extension buckets (a
 * person property and a family property that happen to share a name won't
 * collide).
 */
function csvColumnHeaderFor(array $field): string
{
    if (empty($field['groupTag'])) {
        return $field['key'];
    }
    return $field['label'] . ' (' . $field['groupTag'] . ')';
}

/**
 * Upsert into person_custom/family_custom using parameterized SQL. Column names
 * are data-driven (c1, c2, ...) so we can't use Propel setters; we validate each
 * column matches /^c\d+$/ and only accept columns present in $typeMap before
 * including them in the SQL.
 *
 * Uses `INSERT ... ON DUPLICATE KEY UPDATE` (not REPLACE INTO) so that unmapped
 * custom columns are preserved on re-import — REPLACE would delete+reinsert the
 * row and reset every other c<N> column to its default.
 *
 * @param array<string, string> $values  column name (e.g. "c1") => raw CSV value
 * @param array<string, int>    $typeMap column name => custom field type_ID
 */
function writeCustomFields(\Propel\Runtime\Connection\ConnectionInterface $con, string $table, string $pkColumn, int $recordId, array $values, array $typeMap): void
{
    if (empty($values)) {
        return;
    }
    $safeColumns = [];
    $params      = [];
    foreach ($values as $col => $raw) {
        if (!preg_match('/^c\d+$/', $col) || !isset($typeMap[$col])) {
            continue;
        }
        $safeColumns[] = $col;
        $params[]      = formatCustomFieldValue($typeMap[$col], (string) $raw);
    }
    if (empty($safeColumns)) {
        return;
    }
    $columnList   = '`' . $pkColumn . '`, ' . implode(', ', array_map(fn ($c) => '`' . $c . '`', $safeColumns));
    $placeholders = '?' . str_repeat(', ?', count($safeColumns));
    $updateClause = implode(', ', array_map(fn ($c) => '`' . $c . '` = VALUES(`' . $c . '`)', $safeColumns));
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        $table,
        $columnList,
        $placeholders,
        $updateClause,
    );
    $stmt = $con->prepare($sql);
    $stmt->execute(array_merge([$recordId], $params));
}

/**
 * Assign properties to a person or family via the RecordProperty Propel model.
 * Uses the caller's transaction connection so property rows participate in the
 * same transaction as the person/family writes in /csv/execute.
 *
 * Semantics match the interactive property-assignment routes:
 *
 * - Prompt-less properties (pro_Prompt empty): presence-by-boolean. A value
 *   of yes/true/1/y/t assigns the property with an empty value; no/false/0/
 *   blank/anything else is treated as "do not assign" (and is a no-op even
 *   if an existing assignment is present — the CSV importer never unassigns).
 * - Prompted properties (pro_Prompt set): the CSV value is sanitized with
 *   InputUtils::sanitizeText() and only assigned when non-empty.
 *
 * @param array<int, string>       $assignments property_pro.pro_ID => raw CSV value
 * @param array<int, string|null>  $promptMap   property_pro.pro_ID => pro_Prompt text (null/empty means promptless)
 */
function writeProperties(\Propel\Runtime\Connection\ConnectionInterface $con, int $recordId, array $assignments, array $promptMap): void
{
    foreach ($assignments as $proId => $raw) {
        $prompt = $promptMap[$proId] ?? '';
        $raw    = trim((string) $raw);

        if ($prompt === '' || $prompt === null) {
            // Promptless: boolean presence
            $lower = strtolower($raw);
            $truthy = in_array($lower, ['yes', 'y', 'true', 't', '1'], true);
            if (!$truthy) {
                continue;
            }
            $valueToStore = '';
        } else {
            // Prompted: sanitized text, skip blanks
            if ($raw === '') {
                continue;
            }
            $valueToStore = InputUtils::sanitizeText($raw);
        }

        $existing = RecordPropertyQuery::create()
            ->filterByPropertyId($proId)
            ->filterByRecordId($recordId)
            ->findOne($con);
        if ($existing !== null) {
            $existing->setPropertyValue($valueToStore);
            $existing->save($con);
            continue;
        }
        $rp = new RecordProperty();
        $rp->setPropertyId($proId);
        $rp->setRecordId($recordId);
        $rp->setPropertyValue($valueToStore);
        $rp->save($con);
    }
}

/**
 * Person::postInsert and Family::postInsert automatically write a timeline note
 * of type 'create' with text "Created" whenever a new record is saved. Rewrite
 * that note's text to "Imported from CSV" so the timeline makes the provenance
 * of imported records explicit, without duplicating the note.
 */
function markCreateNoteAsImported(\Propel\Runtime\Connection\ConnectionInterface $con, ?int $personId = null, ?int $familyId = null): void
{
    $query = NoteQuery::create()->filterByType('create');
    if ($personId !== null) {
        $query->filterByPerId($personId);
    }
    if ($familyId !== null) {
        $query->filterByFamId($familyId);
    }
    $note = $query->orderByDateEntered('DESC')->findOne($con);
    if ($note === null) {
        return;
    }
    $note->setText(gettext('Imported from CSV'));
    $note->save($con);
}

/**
 * Format a raw CSV value for writing into a `person_custom` / `family_custom`
 * column based on the custom field's type_ID. Returns `null` to clear the value.
 */
function formatCustomFieldValue(int $typeId, string $raw)
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    switch ($typeId) {
        case 1: // True / False ENUM('false','true')
            $lower = strtolower($raw);
            return match ($lower) {
                'true', 'yes', 'y', '1', 't' => 'true',
                'false', 'no', 'n', '0', 'f' => 'false',
                default                      => null,
            };
        case 2: // DATE — SQL DATE column requires a full year. Partial dates
            // (bare "7/4") are dropped rather than silently assigned the
            // current year, which is what strtotime() used to do.
            $parsed = DateTimeUtils::parsePartialDate($raw);
            if ($parsed === null || $parsed['month'] === 0 || $parsed['day'] === 0 || $parsed['year'] === null) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $parsed['year'], $parsed['month'], $parsed['day']);
        case 6: // YEAR
            return ctype_digit($raw) && strlen($raw) === 4 ? $raw : null;
        case 7: // ENUM('winter','spring','summer','fall')
            $lower = strtolower($raw);
            return in_array($lower, ['winter', 'spring', 'summer', 'fall'], true) ? $lower : null;
        case 8: // INT
        case 9: // MEDIUMINT (person-from-group FK)
        case 12: // TINYINT (custom list option FK)
            $int = filter_var($raw, FILTER_VALIDATE_INT);
            return $int === false ? null : (string) $int;
        case 10: // DECIMAL money
            $clean = str_replace([',', '$'], '', $raw);
            return is_numeric($clean) ? $clean : null;
        case 3: // VARCHAR(50)
        case 4: // VARCHAR(100)
        case 5: // TEXT
        case 11: // Phone VARCHAR(30)
        default:
            return $raw;
    }
}

$app->group('/api/import', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/admin/api/import/csv/families",
     *     summary="Download a CSV import template for families (Admin role required)",
     *     tags={"Import"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="CSV file attachment"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('/csv/families', function (Request $request, Response $response, array $_args): Response {
        // Generate the template live from the current catalog. Core columns
        // keep their plain label ("First Name"); extension columns get a
        // "(Person Custom)" / "(Family Property)" suffix so the source is
        // visible in Excel and a person-custom named "Notes" doesn't clash
        // with a family-property named "Notes" in the final header row. The
        // importer strips the suffix when auto-mapping (see autoMapHeader).
        $catalog = buildCsvImportFieldCatalog();
        $headers = [];
        $seen    = []; // case-insensitive duplicate guard — worst-case safety net
        foreach ($catalog as $field) {
            $header = csvColumnHeaderFor($field);
            $key    = strtolower($header);
            $suffix = 2;
            while (isset($seen[$key])) {
                $header = csvColumnHeaderFor($field) . ' (' . $suffix . ')';
                $key    = strtolower($header);
                $suffix++;
            }
            $seen[$key] = true;
            $headers[]  = $header;
        }

        // Sample rows keyed by the core field name (FirstName, Address1, …).
        // We render the full catalog header row but only emit sample values
        // for core columns — custom-field and property values depend on each
        // church's configuration, so those cells stay blank as a prompt.
        $sampleRows = [
            ['FamilyID' => '1001', 'Title' => 'Mr',   'FirstName' => 'John',  'LastName' => 'Smith',   'Gender' => 'Male',   'Address1' => '123 Church St', 'City' => 'Springfield',  'State' => 'IL', 'Zip' => '62704', 'Country' => 'USA', 'HomePhone' => '555-1234', 'Email' => 'john.smith@example.com',  'BirthDate' => '1980-05-12', 'MembershipDate' => '2020-01-01', 'WeddingDate' => '2010-06-15', 'Classification' => 'Member', 'FamilyRole' => 'Head of Household'],
            ['FamilyID' => '1001', 'Title' => 'Mrs',  'FirstName' => 'Jane',  'LastName' => 'Smith',   'Gender' => 'Female', 'Address1' => '123 Church St', 'City' => 'Springfield',  'State' => 'IL', 'Zip' => '62704', 'Country' => 'USA', 'HomePhone' => '555-4321', 'Email' => 'jane.smith@example.com',  'BirthDate' => '1982-08-20', 'MembershipDate' => '2020-01-01', 'WeddingDate' => '2010-06-15', 'Classification' => 'Member', 'FamilyRole' => 'Spouse'],
            ['FamilyID' => '1001', 'Title' => 'Miss', 'FirstName' => 'Emily', 'LastName' => 'Smith',   'Gender' => 'Female', 'Address1' => '123 Church St', 'City' => 'Springfield',  'State' => 'IL', 'Zip' => '62704', 'Country' => 'USA', 'HomePhone' => '555-0000', 'Email' => 'emily.smith@example.com', 'BirthDate' => '2010-03-05', 'MembershipDate' => '2020-01-01',                                     'Classification' => 'Member', 'FamilyRole' => 'Child'],
            ['FamilyID' => '1002', 'Title' => 'Mr',   'FirstName' => 'Peter', 'LastName' => 'Johnson', 'Gender' => 'Male',   'Address1' => '456 Grace Ave', 'City' => 'Capital City', 'State' => 'IL', 'Zip' => '62999', 'Country' => 'USA', 'HomePhone' => '555-7777', 'Email' => 'peter.johnson@example.com', 'BirthDate' => '1975-11-03', 'MembershipDate' => '2019-03-15', 'WeddingDate' => '2005-09-20', 'Classification' => 'Member', 'FamilyRole' => 'Head of Household'],
            ['FamilyID' => '',     'Title' => 'Ms',   'FirstName' => 'Alice', 'LastName' => 'Walker',  'Gender' => 'Female', 'Address1' => '789 Solo Ave',  'City' => 'Capital City', 'State' => 'IL', 'Zip' => '62999', 'Country' => 'USA', 'HomePhone' => '555-8888', 'Email' => 'alice.walker@example.com', 'BirthDate' => '1990-02-02', 'MembershipDate' => '2021-05-01',                                     'Classification' => 'Member'],
        ];

        $exporter = new \ChurchCRM\Utils\CsvExporter();
        $exporter->insertHeaders($headers);
        foreach ($sampleRows as $sample) {
            $row = [];
            foreach ($catalog as $field) {
                // Core columns are keyed by the CRM field name; extension
                // fields have no sample value (users fill them per-site).
                $coreKey = empty($field['groupTag']) ? $field['key'] : null;
                $row[]   = ($coreKey !== null && isset($sample[$coreKey])) ? $sample[$coreKey] : '';
            }
            $exporter->insertRow($row);
        }

        $response = $response->withHeader('Content-Type', 'text/csv');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="csv-families-template.csv"');
        $response->getBody()->write($exporter->getContent());

        return $response;
    });

    /**
     * @OA\Post(
     *     path="/admin/api/import/csv/upload",
     *     summary="Upload a CSV file and return headers with auto-detected field mappings",
     *     tags={"Import"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(mediaType="multipart/form-data",
     *             @OA\Schema(@OA\Property(property="csvFile", type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Headers and auto-mapped suggestions returned"),
     *     @OA\Response(response=400, description="Invalid file"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/csv/upload', function (Request $request, Response $response, array $_args): Response {
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['csvFile'])) {
            return SlimUtils::renderErrorJSON($response, gettext('No file uploaded'), [], 400, null, $request);
        }

        $upload = $uploadedFiles['csvFile'];

        if ($upload->getError() !== UPLOAD_ERR_OK) {
            return SlimUtils::renderErrorJSON($response, gettext('File upload error'), [], 400, null, $request);
        }

        if (!str_ends_with(strtolower($upload->getClientFilename()), '.csv')) {
            return SlimUtils::renderErrorJSON($response, gettext('Only .csv files are accepted'), [], 400, null, $request);
        }

        // Save to a session-keyed temp file for the execute step
        $token = bin2hex(random_bytes(16));
        $tmpPath = sys_get_temp_dir() . '/churchcrm-csv-' . $token . '.csv';
        $upload->moveTo($tmpPath);
        $_SESSION['csv_import_tokens'][$token] = true;

        // Parse headers and grab first data row as sample. League\Csv throws
        // SyntaxError when the header row has duplicate column names (common
        // when a user manually adds columns to the template). Catch it and
        // return a clean 400 instead of a raw 500.
        try {
            $csv = Reader::createFromPath($tmpPath, 'r');
            $csv->setHeaderOffset(0);
            $headers = $csv->getHeader();

            $sample = null;
            foreach ($csv->getRecords() as $record) {
                $sample = $record;
                break;
            }
        } catch (\League\Csv\SyntaxError $e) {
            @unlink($tmpPath);
            unset($_SESSION['csv_import_tokens'][$token]);
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Your CSV has duplicate column names. Rename the duplicate columns (e.g. add "(2)" to the second one) and upload again.'),
                ['details' => $e->getMessage()],
                400,
                null,
                $request,
            );
        } catch (\League\Csv\Exception $e) {
            @unlink($tmpPath);
            unset($_SESSION['csv_import_tokens'][$token]);
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Could not parse the CSV file. Check that it is a valid comma-separated file with a header row.'),
                ['details' => $e->getMessage()],
                400,
                null,
                $request,
            );
        }

        $fieldCatalog = buildCsvImportFieldCatalog();

        // Use custom/property entries (everything past the core columns) as auto-map fallback
        $extensionFields = array_slice($fieldCatalog, count(CSV_CORE_FIELD_LABELS));

        $mappings = [];
        foreach ($headers as $header) {
            $mappings[$header] = autoMapHeader($header, $extensionFields);
        }

        return SlimUtils::renderJSON($response, [
            'url'      => '',
            'token'    => $token,
            'headers'  => $headers,
            'mappings' => $mappings,
            'fields'   => $fieldCatalog,
            'sample'   => $sample,
        ]);
    });
    /**
     * @OA\Post(
     *     path="/admin/api/import/csv/execute",
     *     summary="Execute a CSV import using a previously uploaded file and column mapping",
     *     tags={"Import"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Import completed"),
     *     @OA\Response(response=400, description="Invalid token or mapping"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/csv/execute', function (Request $request, Response $response, array $_args): Response {
        $body = (array) $request->getParsedBody();
        $token = preg_replace('/[^a-f0-9]/', '', (string) ($body['token'] ?? ''));
        $mapping = (array) ($body['mapping'] ?? []);

        if (empty($token) || empty($mapping)) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid import request'), [], 400, null, $request);
        }

        if (empty($_SESSION['csv_import_tokens'][$token])) {
            return SlimUtils::renderErrorJSON($response, gettext('Upload session expired. Please upload the file again.'), [], 400, null, $request);
        }
        unset($_SESSION['csv_import_tokens'][$token]);

        $tmpPath = sys_get_temp_dir() . '/churchcrm-csv-' . $token . '.csv';
        if (!file_exists($tmpPath)) {
            return SlimUtils::renderErrorJSON($response, gettext('Upload session expired. Please upload the file again.'), [], 400, null, $request);
        }

        $csv = Reader::createFromPath($tmpPath, 'r');
        $csv->setHeaderOffset(0);

        $con = Propel::getWriteConnection(PersonTableMap::DATABASE_NAME);
        $con->beginTransaction();

        $imported = 0;
        $skipped = 0;
        // Cache families created during this import keyed by FamilyID from CSV
        $familyCache = [];

        // List IDs are stable schema constants: 1 = Classifications, 2 = Family Roles
        $classificationListId = 1;
        $familyRoleListId     = 2;

        // Build classification name → ID lookup
        $classificationMap = [];
        foreach (ListOptionQuery::create()->filterById($classificationListId)->find() as $cls) {
            $classificationMap[strtolower($cls->getOptionName())] = $cls->getOptionId();
        }

        // Build family role name → ID lookup
        $familyRoleMap = [];
        foreach (ListOptionQuery::create()->filterById($familyRoleListId)->find() as $role) {
            $familyRoleMap[strtolower($role->getOptionName())] = $role->getOptionId();
        }

        // Load custom-field type_ID maps once so we can cast values on write.
        // Keys are the raw column names in `person_custom` / `family_custom` (e.g. "c1", "c2").
        $personCustomTypes = [];
        foreach (PersonCustomMasterQuery::create()->find() as $cf) {
            // getId() already returns the raw column name ("c3") — do NOT
            // prepend 'c'. The writer's isset($personCustomTypes[$col])
            // check must match on "c3", not "cc3".
            $personCustomTypes[$cf->getId()] = (int) $cf->getTypeId();
        }
        $familyCustomTypes = [];
        foreach (FamilyCustomMasterQuery::create()->find() as $cf) {
            $familyCustomTypes[$cf->getField()] = (int) $cf->getTypeId();
        }

        // Build allow-lists of property IDs per class (so a spoofed mapping
        // payload can't assign a family property to a person and vice versa),
        // keyed by pro_ID with pro_Prompt as the value. The prompt text drives
        // whether a property is treated as boolean (no prompt) or free-text
        // (prompted) on write. See writeProperties().
        $personPropPrompts = [];
        foreach (PropertyQuery::create()->filterByProClass('p')->find() as $prop) {
            $personPropPrompts[(int) $prop->getProId()] = (string) $prop->getProPrompt();
        }
        $familyPropPrompts = [];
        foreach (PropertyQuery::create()->filterByProClass('f')->find() as $prop) {
            $familyPropPrompts[(int) $prop->getProId()] = (string) $prop->getProPrompt();
        }

        try {
            foreach ($csv->getRecords() as $row) {
                // Partition the mapping into core / person-custom / family-custom / properties
                $data           = [];
                $personCustoms  = [];
                $familyCustoms  = [];
                $personProps    = [];
                $familyProps    = [];
                foreach ($mapping as $csvHeader => $crmField) {
                    if (empty($crmField) || !isset($row[$csvHeader])) {
                        continue;
                    }
                    $value = trim($row[$csvHeader]);
                    if (str_starts_with($crmField, CSV_PERSON_CUSTOM_PREFIX)) {
                        $col = substr($crmField, strlen(CSV_PERSON_CUSTOM_PREFIX));
                        if (preg_match('/^c\d+$/', $col) && isset($personCustomTypes[$col])) {
                            $personCustoms[$col] = $value;
                        }
                    } elseif (str_starts_with($crmField, CSV_FAMILY_CUSTOM_PREFIX)) {
                        $col = substr($crmField, strlen(CSV_FAMILY_CUSTOM_PREFIX));
                        if (preg_match('/^c\d+$/', $col) && isset($familyCustomTypes[$col])) {
                            $familyCustoms[$col] = $value;
                        }
                    } elseif (str_starts_with($crmField, CSV_PERSON_PROP_PREFIX)) {
                        $proId = (int) substr($crmField, strlen(CSV_PERSON_PROP_PREFIX));
                        if ($proId > 0 && array_key_exists($proId, $personPropPrompts)) {
                            $personProps[$proId] = $value;
                        }
                    } elseif (str_starts_with($crmField, CSV_FAMILY_PROP_PREFIX)) {
                        $proId = (int) substr($crmField, strlen(CSV_FAMILY_PROP_PREFIX));
                        if ($proId > 0 && array_key_exists($proId, $familyPropPrompts)) {
                            $familyProps[$proId] = $value;
                        }
                    } else {
                        $data[$crmField] = $value;
                    }
                }

                if (empty($data['FirstName']) || empty($data['LastName'])) {
                    $skipped++;
                    continue;
                }

                // Resolve or create Family
                $csvFamilyId = $data['FamilyID'] ?? null;
                $family = null;

                if (!empty($csvFamilyId)) {
                    if (isset($familyCache[$csvFamilyId])) {
                        $family = $familyCache[$csvFamilyId];
                    } else {
                        $family = new Family();
                        $family->setName($data['LastName'] ?? gettext('Unknown'));
                        if (!empty($data['Address1'])) $family->setAddress1($data['Address1']);
                        if (!empty($data['Address2'])) $family->setAddress2($data['Address2']);
                        if (!empty($data['City']))     $family->setCity($data['City']);
                        if (!empty($data['State']))    $family->setState($data['State']);
                        if (!empty($data['Zip']))      $family->setZip($data['Zip']);
                        if (!empty($data['Country']))  $family->setCountry($data['Country']);
                        if (!empty($data['HomePhone'])) $family->setHomePhone($data['HomePhone']);
                        if (!empty($data['Email']))    $family->setEmail($data['Email']);
                        if (!empty($data['Envelope'])) $family->setEnvelope((int) $data['Envelope']);
                        if (!empty($data['WeddingDate'])) {
                            $ts = strtotime($data['WeddingDate']);
                            if ($ts !== false) $family->setWeddingdate(date('Y-m-d', $ts));
                        }
                        $family->setDateEntered(date('YmdHis'));
                        $family->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                        $family->save($con);
                        $familyCache[$csvFamilyId] = $family;

                        // Note provenance is a best-effort side effect — don't fail the import if the rewrite hits an edge case.
                        try {
                            markCreateNoteAsImported($con, familyId: $family->getId());
                        } catch (\Throwable $e) {
                            LoggerUtils::getAppLogger()->warning('CSV import: failed to mark family create-note as imported', [
                                'familyId' => $family->getId(),
                                'error'    => $e->getMessage(),
                            ]);
                        }
                    }

                    // Apply family custom fields / properties every row (not just on
                    // family creation) so values that first appear on a later row for
                    // the same FamilyID aren't silently dropped. writeCustomFields()
                    // and writeProperties() are both no-ops when the row supplies
                    // nothing relevant for the family.
                    if (!empty($familyCustoms)) {
                        writeCustomFields($con, 'family_custom', 'fam_ID', $family->getId(), $familyCustoms, $familyCustomTypes);
                    }
                    if (!empty($familyProps)) {
                        writeProperties($con, $family->getId(), $familyProps, $familyPropPrompts);
                    }
                }

                // Create Person
                $person = new Person();
                if (!empty($data['Title']))      $person->setTitle($data['Title']);
                if (!empty($data['FirstName']))  $person->setFirstName($data['FirstName']);
                if (!empty($data['MiddleName'])) $person->setMiddleName($data['MiddleName']);
                if (!empty($data['LastName']))   $person->setLastName($data['LastName']);
                if (!empty($data['Suffix']))     $person->setSuffix($data['Suffix']);
                if (!empty($data['Address1']))   $person->setAddress1($data['Address1']);
                if (!empty($data['Address2']))   $person->setAddress2($data['Address2']);
                if (!empty($data['City']))       $person->setCity($data['City']);
                if (!empty($data['State']))      $person->setState($data['State']);
                if (!empty($data['Zip']))        $person->setZip($data['Zip']);
                if (!empty($data['Country']))    $person->setCountry($data['Country']);
                if (!empty($data['HomePhone']))  $person->setHomePhone($data['HomePhone']);
                if (!empty($data['WorkPhone']))  $person->setWorkPhone($data['WorkPhone']);
                if (!empty($data['MobilePhone'])) $person->setCellPhone($data['MobilePhone']);
                if (!empty($data['Email']))      $person->setEmail($data['Email']);
                if (!empty($data['WorkEmail']))  $person->setWorkEmail($data['WorkEmail']);
                if (!empty($data['Envelope']))   $person->setEnvelope((int) $data['Envelope']);

                // Gender: Male=1, Female=2, unknown=0
                if (!empty($data['Gender'])) {
                    $person->setGender(match (strtolower($data['Gender'])) {
                        'male', 'm', 'man', 'boy'     => 1,
                        'female', 'f', 'woman', 'girl' => 2,
                        default                        => 0,
                    });
                }

                // Birth date — see DateTimeUtils::parsePartialDate() for accepted formats.
                // Year may be null for month-day-only inputs; BirthYear is nullable on Person.
                if (!empty($data['BirthDate'])) {
                    $parsed = DateTimeUtils::parsePartialDate((string) $data['BirthDate']);
                    if ($parsed !== null && $parsed['month'] > 0 && $parsed['day'] > 0) {
                        $person->setBirthMonth($parsed['month']);
                        $person->setBirthDay($parsed['day']);
                        if ($parsed['year'] !== null) {
                            $person->setBirthYear($parsed['year']);
                        }
                    }
                }

                if (!empty($data['MembershipDate'])) {
                    $ts = strtotime($data['MembershipDate']);
                    if ($ts !== false) $person->setMembershipDate(date('Y-m-d', $ts));
                }

                // Classification: resolve name → ID (valid for any person, with or without family)
                if (!empty($data['Classification'])) {
                    $clsKey = strtolower($data['Classification']);
                    if (isset($classificationMap[$clsKey])) {
                        $person->setClsId($classificationMap[$clsKey]);
                    }
                }

                if ($family !== null) {
                    $person->setFamId($family->getId());

                    // Family role is only meaningful when the person belongs to a family
                    if (!empty($data['FamilyRole'])) {
                        $roleKey = strtolower($data['FamilyRole']);
                        if (isset($familyRoleMap[$roleKey])) {
                            $person->setFmrId($familyRoleMap[$roleKey]);
                        }
                    }
                }

                $person->setDateEntered(date('YmdHis'));
                $person->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                $person->save($con);

                writeCustomFields($con, 'person_custom', 'per_ID', $person->getId(), $personCustoms, $personCustomTypes);
                writeProperties($con, $person->getId(), $personProps, $personPropPrompts);
                try {
                    markCreateNoteAsImported($con, personId: $person->getId());
                } catch (\Throwable $e) {
                    LoggerUtils::getAppLogger()->warning('CSV import: failed to mark person create-note as imported', [
                        'personId' => $person->getId(),
                        'error'    => $e->getMessage(),
                    ]);
                }

                $imported++;
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            return SlimUtils::renderErrorJSON($response, gettext('Import failed: ') . $e->getMessage(), [], 500, $e, $request);
        } finally {
            @unlink($tmpPath);
        }

        return SlimUtils::renderJSON($response, [
            'imported' => $imported,
            'skipped'  => $skipped,
            'families' => count($familyCache),
        ]);
    });
})->add(AdminRoleAuthMiddleware::class);
