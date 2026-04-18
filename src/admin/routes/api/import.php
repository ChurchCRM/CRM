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
    // Fallback: case-insensitive exact match of the CSV header against the
    // display name of a custom field or property.
    foreach ($extensionFields as $field) {
        if (strtolower(trim($field['label'])) === $normalized) {
            return $field['key'];
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
        $fields[] = ['key' => $key, 'label' => gettext($label), 'group' => gettext('Person / Family')];
    }

    foreach (PersonCustomMasterQuery::create()->orderByOrder()->find() as $cf) {
        $fields[] = [
            'key'   => CSV_PERSON_CUSTOM_PREFIX . $cf->getId(),
            'label' => $cf->getName(),
            'group' => gettext('Person Custom'),
        ];
    }

    foreach (FamilyCustomMasterQuery::create()->orderByOrder()->find() as $cf) {
        $fields[] = [
            'key'   => CSV_FAMILY_CUSTOM_PREFIX . $cf->getField(),
            'label' => $cf->getName(),
            'group' => gettext('Family Custom'),
        ];
    }

    foreach (PropertyQuery::create()->filterByProClass('p')->orderByProName()->find() as $prop) {
        $fields[] = [
            'key'   => CSV_PERSON_PROP_PREFIX . $prop->getProId(),
            'label' => $prop->getProName(),
            'group' => gettext('Person Property'),
        ];
    }

    foreach (PropertyQuery::create()->filterByProClass('f')->orderByProName()->find() as $prop) {
        $fields[] = [
            'key'   => CSV_FAMILY_PROP_PREFIX . $prop->getProId(),
            'label' => $prop->getProName(),
            'group' => gettext('Family Property'),
        ];
    }

    return $fields;
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
 * @param array<int, string> $assignments property_pro.pro_ID => value string
 */
function writeProperties(\Propel\Runtime\Connection\ConnectionInterface $con, int $recordId, array $assignments): void
{
    foreach ($assignments as $proId => $value) {
        $existing = RecordPropertyQuery::create()
            ->filterByPropertyId($proId)
            ->filterByRecordId($recordId)
            ->findOne($con);
        if ($existing !== null) {
            $existing->setPropertyValue((string) $value);
            $existing->save($con);
            continue;
        }
        $rp = new RecordProperty();
        $rp->setPropertyId($proId);
        $rp->setRecordId($recordId);
        $rp->setPropertyValue((string) $value);
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
        case 2: // DATE
            $ts = strtotime($raw);
            return $ts === false ? null : date('Y-m-d', $ts);
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
        $file = __DIR__ . '/../../data/csv-families-template.csv';
        if (!file_exists($file)) {
            return SlimUtils::renderErrorJSON($response, gettext('CSV template not found'), [], 404, null, $request);
        }

        $contents = file_get_contents($file);

        $response = $response->withHeader('Content-Type', 'text/csv');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="csv-families-template.csv"');
        $response->getBody()->write($contents);

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

        // Parse headers and grab first data row as sample
        $csv = Reader::createFromPath($tmpPath, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $sample = null;
        foreach ($csv->getRecords() as $record) {
            $sample = $record;
            break;
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
            $personCustomTypes[$cf->getId()] = (int) $cf->getTypeId();
        }
        $familyCustomTypes = [];
        foreach (FamilyCustomMasterQuery::create()->find() as $cf) {
            $familyCustomTypes[$cf->getField()] = (int) $cf->getTypeId();
        }

        // Build allow-lists of property IDs per class so a spoofed mapping payload can't
        // assign a family property to a person or vice versa.
        $allowedPersonPropIds = [];
        foreach (PropertyQuery::create()->filterByProClass('p')->find() as $prop) {
            $allowedPersonPropIds[(int) $prop->getProId()] = true;
        }
        $allowedFamilyPropIds = [];
        foreach (PropertyQuery::create()->filterByProClass('f')->find() as $prop) {
            $allowedFamilyPropIds[(int) $prop->getProId()] = true;
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
                        if ($proId > 0 && isset($allowedPersonPropIds[$proId])) {
                            $personProps[$proId] = $value;
                        }
                    } elseif (str_starts_with($crmField, CSV_FAMILY_PROP_PREFIX)) {
                        $proId = (int) substr($crmField, strlen(CSV_FAMILY_PROP_PREFIX));
                        if ($proId > 0 && isset($allowedFamilyPropIds[$proId])) {
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

                        writeCustomFields($con, 'family_custom', 'fam_ID', $family->getId(), $familyCustoms, $familyCustomTypes);
                        writeProperties($con, $family->getId(), $familyProps);
                        markCreateNoteAsImported($con, familyId: $family->getId());
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

                // Birth date
                if (!empty($data['BirthDate'])) {
                    $raw   = trim($data['BirthDate']);
                    $month = 0;
                    $day   = 0;
                    $year  = null; // null = no year provided

                    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
                        // YYYY-MM-DD or YYYY-M-D (e.g. 2001-07-04, 0000-07-04)
                        $month = (int) $m[2];
                        $day   = (int) $m[3];
                        $y     = (int) $m[1];
                        $year  = $y > 0 ? $y : null;
                    } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $raw, $m)) {
                        // M/D/YYYY, M-D-YYYY, M/D/YY (e.g. 1/1/2025, 7-4-2001, 7/4/25)
                        $month   = (int) $m[1];
                        $day     = (int) $m[2];
                        $yearRaw = $m[3];
                        $y       = (int) $yearRaw;
                        if (strlen($yearRaw) === 2 && $y > 0) {
                            // Match PHP's date parsing rules: 00-69 => 2000-2069, 70-99 => 1970-1999
                            $y += $y >= 70 ? 1900 : 2000;
                        }
                        $year = $y > 0 ? $y : null;
                    } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $raw, $m)) {
                        // M/D or M-D (e.g. 1/1, 7/4, 7-4) — no year
                        $month = (int) $m[1];
                        $day   = (int) $m[2];
                        $year  = null;
                    } else {
                        // Fallback: try strtotime for unrecognised formats (year assumed present)
                        $ts = strtotime($raw);
                        if ($ts !== false) {
                            $month = (int) date('n', $ts);
                            $day   = (int) date('j', $ts);
                            $year  = (int) date('Y', $ts);
                        }
                    }

                    if ($month > 0 && $day > 0) {
                        $person->setBirthMonth($month);
                        $person->setBirthDay($day);
                        if ($year !== null) {
                            $person->setBirthYear($year);
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
                writeProperties($con, $person->getId(), $personProps);
                markCreateNoteAsImported($con, personId: $person->getId());

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
