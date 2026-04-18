<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use League\Csv\Reader;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

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

function autoMapHeader(string $header): ?string
{
    $normalized = strtolower(trim($header));
    foreach (CSV_FIELD_ALIASES as $field => $aliases) {
        if (in_array($normalized, $aliases, true)) {
            return $field;
        }
    }
    return null;
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

        // Build auto-mapping suggestions
        $mappings = [];
        foreach ($headers as $header) {
            $mappings[$header] = autoMapHeader($header);
        }

        return SlimUtils::renderJSON($response, [
            'url'      => '',
            'token'    => $token,
            'headers'  => $headers,
            'mappings' => $mappings,
            'fields'   => array_keys(CSV_FIELD_ALIASES),
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

        try {
            foreach ($csv->getRecords() as $row) {
                // Map CSV row to a flat key=>value array using the column mapping
                $data = [];
                foreach ($mapping as $csvHeader => $crmField) {
                    if (!empty($crmField) && isset($row[$csvHeader])) {
                        $data[$crmField] = trim($row[$csvHeader]);
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
                        // M/D/YYYY, M-D-YYYY, M/D/YY (e.g. 1/1/2025, 7-4-2001)
                        $month = (int) $m[1];
                        $day   = (int) $m[2];
                        $y     = (int) $m[3];
                        $year  = $y > 0 ? $y : null;
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
