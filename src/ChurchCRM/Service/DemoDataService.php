<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Service\GroupService;
use ChurchCRM\dto\SystemConfig;
use DateTime;
use Exception;
use JsonException;
use Propel\Runtime\Propel;

class DemoDataService
{
    private const DATA_PATH = __DIR__ . '/../../admin/demo';

    private array $importResult = [
        'success' => false,
        'imported' => [
            'groups' => 0,
            'families' => 0,
            'people' => 0,
            'notes' => 0,
        ],
        'warnings' => [],
        'errors' => [],
        'startTime' => null,
        'endTime' => null
    ];

    private array $familyMap = [];
    private array $personMap = [];
    private array $groupMap = [];
    private array $groupNameToId = [];
    private $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    public function importDemoData(bool $includeFinancial = false, bool $includeEvents = false, bool $includeSundaySchool = false): array
    {
        $this->importResult['startTime'] = microtime(true);

        try {
            $this->logger->info('Demo data import started', [
                'includeFinancial' => $includeFinancial,
                'includeEvents' => $includeEvents,
                'includeSundaySchool' => $includeSundaySchool
            ]);

            $emailMap = $this->importCongregation();

            $this->importGroups($includeSundaySchool, $emailMap);

            $this->importResult['success'] = true;
            $this->importResult['endTime'] = microtime(true);
            $duration = $this->importResult['endTime'] - $this->importResult['startTime'];

            $this->logger->info('Demo data import completed successfully', [
                'duration' => $duration,
                'imported' => $this->importResult['imported'],
                'warnings' => count($this->importResult['warnings']),
                'errors' => count($this->importResult['errors'])
            ]);

            // Log detailed warnings and errors for debugging
            if (!empty($this->importResult['warnings'])) {
                foreach ($this->importResult['warnings'] as $warning) {
                    $this->logger->warning('Demo import warning', ['message' => $warning]);
                }
            }
            if (!empty($this->importResult['errors'])) {
                foreach ($this->importResult['errors'] as $error) {
                    $this->logger->error('Demo import error', ['message' => $error]);
                }
            }

            return $this->importResult;

        } catch (Exception $e) {
            $this->importResult['success'] = false;
            $this->importResult['errors'][] = $e->getMessage();
            $this->importResult['endTime'] = microtime(true);

            $this->logger->error('Demo data import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->importResult;
        }
    }

    /**
     * Import congregation data (families, people, notes) from `people.json` created under src/admin/demo
     * Returns email -> personId map for use in group membership linking
     */
    private function importCongregation(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $emailMap = [];

        $filePath = self::DATA_PATH . '/people.json';
        try {
            $json = json_decode(file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = 'Invalid demo JSON: ' . $e->getMessage();
            $this->addWarning($msg, ['exception' => $e->getMessage()]);
            $logger->error('Demo import JSON parse failed', ['error' => $msg]);
            return $emailMap;
        }

        if (!$json) {
            $msg = 'Invalid demo JSON';
            $this->addWarning($msg);
            $logger->error('Demo import JSON empty', ['error' => $msg]);
            return $emailMap;
        }

        $families = $json['families'] ?? $json['data'] ?? [];
        $logger->info('Starting families import', ['total_families' => count($families)]);

        // Build classification name to ID map
        $classificationMap = [];
        $classifications = ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find();
        foreach ($classifications as $cls) {
            $classificationMap[$cls->getOptionName()] = $cls->getOptionId();
        }

        $familyIndex = 0;
        $personIndex = 0;
        $today = new DateTime();
        
        foreach ($families as $famData) {
            try {
                $family = new Family();
                if (!empty($famData['name'])) {
                    $family->setName($famData['name']);
                }
                $addr = $famData['address'] ?? [];
                $family->setAddress1($addr['line1'] ?? null);
                $family->setAddress2($addr['line2'] ?? null);
                $family->setCity($addr['city'] ?? null);
                $family->setState($addr['state'] ?? null);
                $family->setZip($addr['zip'] ?? null);
                $family->setCountry($addr['country'] ?? null);

                $contact = $famData['contact'] ?? [];
                $phone = $contact['phone'] ?? [];
                $family->setHomePhone($phone['home'] ?? null);
                $family->setWorkPhone($phone['work'] ?? null);
                $family->setCellPhone($phone['cell'] ?? null);
                $family->setEmail($contact['email'] ?? null);

                // Override first family's wedding date to today, otherwise use JSON data
                if ($familyIndex === 0) {
                    $family->setWeddingDate($today);
                } elseif (!empty($famData['weddingDate'])) {
                    try {
                        $family->setWeddingDate(new DateTime($famData['weddingDate']));
                    } catch (Exception $e) {
                        $familyName = $famData['name'] ?? 'unknown';
                        $msg = "Invalid wedding date for family '{$familyName}': {$e->getMessage()}";
                        $this->addWarning($msg);
                        $logger->warning('Family wedding date parse failed', [
                            'family_name' => $familyName,
                            'weddingDate' => $famData['weddingDate'] ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                if (!empty($famData['createdAt'])) {
                    try {
                        $family->setDateEntered(new DateTime($famData['createdAt']));
                    } catch (Exception $e) {
                        $familyName = $famData['name'] ?? 'unknown';
                        $msg = "Invalid createdAt for family '{$familyName}': {$e->getMessage()}";
                        $this->importResult['warnings'][] = $msg;
                        $logger->warning('Family createdAt parse failed', [
                            'family_name' => $familyName,
                            'createdAt' => $famData['createdAt'] ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $family->setSendNewsletter((isset($famData['sendNewsletter']) && $famData['sendNewsletter']) ? 'TRUE' : 'FALSE');
                $family->save();

                $this->familyMap[$family->getId()] = $family;
                $this->importResult['imported']['families']++;

                // members
                $members = $famData['members'] ?? [];
                foreach ($members as $m) {
                    try {
                        $person = new Person();
                        $person->setFamId($family->getId());
                        $person->setFirstName($m['firstName'] ?? null);
                        $person->setLastName($m['lastName'] ?? null);
                        $person->setMiddleName($m['middleName'] ?? null);
                        
                        // Override first person's birthday to today, otherwise use JSON data
                        if ($personIndex === 0) {
                            $person->setBirthYear((int)$today->format('Y'));
                            $person->setBirthMonth((int)$today->format('m'));
                            $person->setBirthDay((int)$today->format('d'));
                        } elseif (!empty($m['birthYear']) && !empty($m['birthMonth']) && !empty($m['birthDay'])) {
                            $person->setBirthYear((int)$m['birthYear']);
                            $person->setBirthMonth((int)$m['birthMonth']);
                            $person->setBirthDay((int)$m['birthDay']);
                        }
                        
                        if (!empty($m['gender'])) {
                            $person->setGender(strtolower($m['gender']) === 'male' ? 1 : (strtolower($m['gender']) === 'female' ? 2 : 0));
                        }
                        if (!empty($m['classification']) && isset($classificationMap[$m['classification']])) {
                            $person->setClsId($classificationMap[$m['classification']]);
                        }
                        if (!empty($m['familyRole'])) {
                            $person->setFmrId((int)$m['familyRole']);
                        }
                        $person->setEmail($m['email'] ?? null);
                        $person->setHomePhone($m['phone'] ?? null);
                        if (!empty($m['createdAt'])) {
                            try { $person->setDateEntered(new DateTime($m['createdAt'])); } catch (Exception $e) {}
                        }
                        $person->save();
                        $this->personMap[$person->getId()] = $person;
                        $this->importResult['imported']['people']++;

                        // person notes
                        $pnotes = $m['notes'] ?? [];
                        foreach ($pnotes as $pn) {
                            try {
                                $note = new Note();
                                $note->setPerId($person->getId());
                                $note->setType($pn['type'] ?? null);
                                $note->setText($pn['text'] ?? null);
                                if (!empty($pn['date'])) {
                                    try { $note->setDateEntered(new DateTime($pn['date'])); } catch (Exception $e) {}
                                }
                                $note->setPrivate(!empty($pn['private']) ? 1 : 0);
                                $note->save();
                                $this->importResult['imported']['notes']++;
                            } catch (Exception $e) {
                                $msg = "Person note import failed: {$e->getMessage()}";
                                $this->addWarning($msg, ['exception' => $e->getMessage()]);
                            }
                        }
                    } catch (Exception $e) {
                        $msg = "Member import failed: {$e->getMessage()}";
                        $this->addWarning($msg, ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    }
                    $personIndex++;
                }

                // family notes
                $fnotes = $famData['notes'] ?? [];
                foreach ($fnotes as $fn) {
                    try {
                        $note = new Note();
                        $note->setFamId($family->getId());
                        $note->setType($fn['type'] ?? null);
                        $note->setText($fn['text'] ?? null);
                        if (!empty($fn['date'])) {
                            try { $note->setDateEntered(new DateTime($fn['date'])); } catch (Exception $e) {}
                        }
                        $note->setPrivate(!empty($fn['private']) ? 1 : 0);
                        $note->save();
                        $this->importResult['imported']['notes']++;
                    } catch (Exception $e) {
                        $msg = "Family note import failed: {$e->getMessage()}";
                        $this->addWarning($msg, ['exception' => $e->getMessage()]);
                    }
                }

                $logger->info('Family imported', [
                    'family_name' => $family->getName(),
                    'family_id' => $family->getId()
                ]);
                
                $familyIndex++;

            } catch (Exception $e) {
                $familyName = $famData['name'] ?? 'unknown';
                $errorMsg = $e->getMessage();
                // Capture underlying database error if available
                if ($e->getPrevious() !== null) {
                    $errorMsg .= " | DB Error: {$e->getPrevious()->getMessage()}";
                }
                $this->addWarning("Family '{$familyName}' import failed: {$errorMsg}", [
                    'family_name' => $familyName,
                    'error' => $errorMsg,
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Build email -> personId map from imported people
        foreach ($this->personMap as $pid => $personObj) {
            try {
                $email = strtolower(trim($personObj->getEmail() ?? ''));
                if ($email !== '') {
                    $emailMap[$email] = (int)$pid;
                }
            } catch (Exception $e) {
                // ignore
            }
        }
        
        return $emailMap;
    }

    /**
     * Import groups from `groups.json` placed under admin/demo and create memberships.
     */
    private function importGroups(bool $includeSundaySchool, array $emailMap): void
    {
        $logger = LoggerUtils::getAppLogger();

        $data = $this->loadJsonFile('groups.json');
        if (!$data) {
            $this->logger->warning('Groups import skipped: groups.json missing or invalid', ['file' => 'groups.json']);
            return;
        }

        $groupService = new GroupService();

        // First pass: create groups
        foreach ($data as $groupData) {
            try {
                $isSS = !empty($groupData['isSundaySchool']);
                // If includeSundaySchool flag is false, skip Sunday School groups; otherwise import all groups.
                if (!$includeSundaySchool && $isSS) {
                    continue;
                }

                $group = new Group();
                // Use Group convenience helpers so the model creates role lists/options for Sunday School
                if ($isSS) {
                    $group->makeSundaySchool();
                } else {
                    // leave type as default (0) for non-sunday groups
                    $group->setType(0);
                }
                $group->setName($groupData['name'] ?? '');
                $group->setDescription($groupData['description'] ?? '');
                $group->setHasSpecialProps(false);
                $groupActive = isset($groupData['active']) ? (bool)$groupData['active'] : true;
                $group->setActive($groupActive);
                $group->save();

                $this->groupMap[(int)$group->getId()] = $group;
                $this->groupNameToId[trim((string)$group->getName())] = (int)$group->getId();
                $this->importResult['imported']['groups']++;
                if ($isSS && isset($this->importResult['imported']['sunday_schools'])) {
                    $this->importResult['imported']['sunday_schools']++;
                }
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Group import failed for '{$groupData['name']}' : {$e->getMessage()}";
            }
        }

        // Second pass: create memberships for groups
        foreach ($data as $groupData) {
            try {
                $isSS = !empty($groupData['isSundaySchool']);
                if (!$includeSundaySchool && $isSS) {
                    continue;
                }

                $groupName = trim((string)($groupData['name'] ?? ''));
                if ($groupName === '' || !isset($this->groupNameToId[$groupName])) {
                    $this->addWarning("Group '{$groupName}' not found in created groups, skipping memberships", ['group_name' => $groupName]);
                    continue;
                }

                $groupId = (int)$this->groupNameToId[$groupName];

                $roleNameToId = [];
                try {
                    $roles = $groupService->getGroupRoles((string)$groupId);
                    foreach ($roles as $r) {
                        if (isset($r['lst_OptionName']) && isset($r['lst_OptionID'])) {
                            $roleNameToId[$r['lst_OptionName']] = (int)$r['lst_OptionID'];
                        }
                    }
                } catch (Exception $e) {
                    $msg = "Failed to load roles for group '{$groupName}' (id: {$groupId}): {$e->getMessage()}";
                    $this->importResult['warnings'][] = $msg;
                    $logger->warning('Group roles fetch failed', [
                        'group_name' => $groupName,
                        'group_id' => $groupId,
                        'error' => $e->getMessage()
                    ]);
                }

                $members = $groupData['members'] ?? [];
                foreach ($members as $m) {
                    $email = strtolower(trim($m['email'] ?? ''));
                    if ($email === '') {
                        continue;
                    }
                    if (!isset($emailMap[$email])) {
                        $this->addWarning("Group '{$groupName}': member with email '{$email}' not found", ['group_name' => $groupName, 'email' => $email]);
                        continue;
                    }

                    $personId = (int)$emailMap[$email];
                    $roleId = 1;
                    $roleName = $m['role'] ?? '';
                    if (!empty($roleName)) {
                        $rn = ucfirst(strtolower(trim($roleName)));
                        if (isset($roleNameToId[$rn])) {
                            $roleId = $roleNameToId[$rn];
                        } else {
                            try {
                                $newRole = $groupService->addGroupRole((string)$groupId, $rn);
                                if (!empty($newRole['newRole']['roleID'])) {
                                    $roleId = (int)$newRole['newRole']['roleID'];
                                    $roleNameToId[$rn] = $roleId;
                                }
                            } catch (Exception $e) {
                                // fallback to default
                            }
                        }
                    }

                    // Use internal add so imports don't require an interactive auth context
                    $groupService->addUserToGroupInternal($groupId, $personId, $roleId);
                }
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Membership import failed for group '{$groupData['name']}' : {$e->getMessage()}";
            }
        }
    }

    private function importNotes(): void
    {
        $data = $this->loadJsonFile('note_nte.json');
        if (!$data) return;

        foreach ($data as $noteData) {
            try {
                $jsonPersonId = isset($noteData['nte_per_ID']) ? (int) $noteData['nte_per_ID'] : 0;
                $jsonFamilyId = isset($noteData['nte_fam_ID']) ? (int) $noteData['nte_fam_ID'] : 0;

                // Determine target (person or family)
                $targetIsPerson = $jsonPersonId > 0;
                $targetIsFamily = !$targetIsPerson && $jsonFamilyId > 0;

                if (!$targetIsPerson && !$targetIsFamily) {
                    $this->importResult['warnings'][] = "Note: missing person or family reference, skipping";
                    continue;
                }

                // Validate existence in maps
                if ($targetIsPerson && !isset($this->personMap[$jsonPersonId])) {
                    $this->importResult['warnings'][] = "Note: Person {$jsonPersonId} not found, skipping";
                    continue;
                }

                if ($targetIsFamily && !isset($this->familyMap[$jsonFamilyId])) {
                    $this->importResult['warnings'][] = "Note: Family {$jsonFamilyId} not found, skipping";
                    continue;
                }

                // Validate note type (include common types used in demo data)
                $validTypes = ['prayer', 'service', 'counsel', 'contact', 'create', 'visit', 'counseling', 'member', 'edit', 'verify'];
                if (!empty($noteData['nte_Type']) && !in_array($noteData['nte_Type'], $validTypes)) {
                    $this->importResult['warnings'][] = "Note: Invalid type '{$noteData['nte_Type']}', skipping";
                    continue;
                }

                $note = new Note();

                // Do NOT set the primary key (nte_id) from the JSON to avoid PK collisions on non-empty note tables.

                // Attach to person or family using the DB id from the objects we created/located earlier
                if ($targetIsPerson) {
                    $personObj = $this->personMap[$jsonPersonId];
                    $note->setPerId((int) $personObj->getId());
                } else {
                    $familyObj = $this->familyMap[$jsonFamilyId];
                    $note->setFamId((int) $familyObj->getId());
                }

                // Text and type
                $note->setText($noteData['nte_Text'] ?? '');
                if (!empty($noteData['nte_Type'])) {
                    $note->setType($noteData['nte_Type']);
                }

                // Date entered (optional)
                if (!empty($noteData['nte_DateEntered'])) {
                    $note->setDateEntered(new DateTime($noteData['nte_DateEntered']));
                }

                // EnteredBy / EditedBy (optional)
                if (!empty($noteData['nte_EnteredBy'])) {
                    $note->setEnteredBy((int) $noteData['nte_EnteredBy']);
                }
                if (!empty($noteData['nte_EditedBy'])) {
                    $note->setEditedBy((int) $noteData['nte_EditedBy']);
                }

                $note->save();

                $this->importResult['imported']['notes']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Note import error: {$e->getMessage()}";
            }
        }
    }

    /**
     * Record and log a warning for the importer
     */
    private function addWarning(string $message, array $context = []): void
    {
        $this->importResult['warnings'][] = $message;
        $this->logger->warning($message, $context);
    }

    // Legacy membership import removed - memberships are created from `groups.json` during import.

    // Event, attendance, pledge and calendar-event imports removed.

    private function loadJsonFile(string $filename): ?array
    {
        $filepath = self::DATA_PATH . '/' . $filename;

        if (!file_exists($filepath)) {
            $this->importResult['errors'][] = "Data file not found: {$filename}";
            return null;
        }

        try {
            $json = file_get_contents($filepath);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                $this->importResult['errors'][] = "Invalid JSON in file: {$filename}";
                return null;
            }

            return $data;
        } catch (JsonException $e) {
            $this->importResult['errors'][] = "JSON parse error in file {$filename}: {$e->getMessage()}";
            return null;
        }
    }
}
