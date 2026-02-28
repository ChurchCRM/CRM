<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\FileSystemUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
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
            'funds' => 0,
            'fundraisers' => 0,
            'pledges' => 0,
            'deposits' => 0,
            'payments' => 0,
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
    /** @var array<string,int> family contact email (lowercase) → family DB id */
    private array $familyEmailToId = [];
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

            // Load demo system configuration (if present) before importing data
            $this->importSystemConfig($includeSundaySchool, $includeFinancial);

            $emailMap = $this->importCongregation();

            $this->importGroups($includeSundaySchool, $emailMap);

            if ($includeFinancial) {
                $this->importFinancial();
            }

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
     * Load `config.json` from the demo data path and write values into SystemConfig.
     * The `bEnabledSundaySchool` value will be set according to the flag passed to the API.
     */
    private function importSystemConfig(bool $includeSundaySchool, bool $includeFinancial): void
    {
        $logger = LoggerUtils::getAppLogger();
        $filePath = self::DATA_PATH . '/config.json';

        if (!file_exists($filePath)) {
            return;
        }

        try {
            $json = json_decode(file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->addWarning('Demo config.json parse failed', ['error' => $e->getMessage()]);
            $logger->error('Demo config.json parse failed', ['error' => $e->getMessage(), 'file' => $filePath]);
            return;
        }

        if (!is_array($json)) {
            $this->addWarning('Demo config.json is empty or invalid format', ['file' => $filePath]);
            $logger->warning('Demo config.json is empty or invalid format', ['file' => $filePath]);
            return;
        }

        foreach ($json as $key => $value) {
            // Skip bEnabledSundaySchool and bEnabledFinance here; we'll set them explicitly from the API flags
            if ($key === 'bEnabledSundaySchool' || $key === 'bEnabledFinance') {
                continue;
            }

            try {
                SystemConfig::setValue($key, $value);
            } catch (Exception $e) {
                $this->addWarning("Failed to set SystemConfig '{$key}' from demo config: {$e->getMessage()}", ['key' => $key, 'error' => $e->getMessage()]);
                $logger->warning('Failed to set SystemConfig from demo config', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        // Ensure the Sunday School and Finance feature toggles are set according to the API flags
        try {
            SystemConfig::setValue('bEnabledSundaySchool', $includeSundaySchool ? '1' : '0');
        } catch (Exception $e) {
            $this->addWarning('Failed to set bEnabledSundaySchool from API flag', ['error' => $e->getMessage()]);
            $logger->warning('Failed to set bEnabledSundaySchool from API flag', ['error' => $e->getMessage()]);
        }

        try {
            SystemConfig::setValue('bEnabledFinance', $includeFinancial ? '1' : '0');
        } catch (Exception $e) {
            $this->addWarning('Failed to set bEnabledFinance from API flag', ['error' => $e->getMessage()]);
            $logger->warning('Failed to set bEnabledFinance from API flag', ['error' => $e->getMessage()]);
        }

        $logger->info('Demo system config import complete', ['file' => $filePath]);
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
                        $this->addWarning($msg);
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

                // Build family-email → family-id map for financial data import
                $contactEmail = strtolower(trim($contact['email'] ?? ''));
                if ($contactEmail !== '') {
                    $this->familyEmailToId[$contactEmail] = $family->getId();
                }

                // Import demo photo for family (if provided)
                if (!empty($famData['photo'])) {
                    $basename = basename($famData['photo']);
                    $src = self::DATA_PATH . '/images/families/' . $basename;

                    if (!$this->importDemoPhotoForEntity($src, $family, 'family')) {
                        $this->addWarning("Failed to import family photo for family '{$family->getName()}'", ['src' => $src, 'family_id' => $family->getId()]);
                    }
                }

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

                        // Simplified import: demo photos are stored in DATA_PATH/images/people/<basename>
                        if (!empty($m['photo'])) {
                            $basename = basename($m['photo']);
                            $src = self::DATA_PATH . '/images/people/' . $basename;

                            if (!$this->importDemoPhotoForEntity($src, $person, 'person')) {
                                $this->addWarning("Failed to import person photo for {$person->getFirstName()} {$person->getLastName()}", ['src' => $src, 'person_id' => $person->getId()]);
                            }
                        }

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
                    'exception_class' => $e::class,
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
     * Uses Propel ORM only - no external service dependencies.
     * IMPORTANT: Only creates roles that are explicitly defined in peopleTypes. Does NOT auto-create undefined roles.
     */
    private function importGroups(bool $includeSundaySchool, array $emailMap): void
    {


        $data = $this->loadJsonFile('groups.json');
        if (!$data) {
            $this->logger->warning('Groups import skipped: groups.json missing or invalid', ['file' => 'groups.json']);
            return;
        }

        // First pass: create groups with correct types
        foreach ($data as $groupData) {
            try {
                $isSS = !empty($groupData['isSundaySchool']);
                // If includeSundaySchool flag is false, skip Sunday School groups; otherwise import all groups.
                if (!$includeSundaySchool && $isSS) {
                    continue;
                }

                $group = new Group();
                // Use Group convenience helpers for Sunday School to set type 4
                if ($isSS) {
                    $group->makeSundaySchool();
                } else {
                    // Use groupType from JSON if provided, otherwise default to 0 (Unassigned)
                    $groupType = isset($groupData['groupType']) ? (int)$groupData['groupType'] : 0;
                    $group->setType($groupType);
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
                $this->addWarning("Group import failed for '{$groupData['name']}' : {$e->getMessage()}");
            }
        }

        // Second pass: create memberships for groups
        // IMPORTANT: Only use roles defined in the group's peopleTypes array
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
                $group = $this->groupMap[$groupId] ?? null;
                if (!$group) {
                    $this->addWarning("Group '{$groupName}' (id: {$groupId}) not in map, skipping memberships", ['group_name' => $groupName]);
                    continue;
                }

                // Get the list of allowed roles for this group from peopleTypes
                $allowedRoles = $groupData['peopleTypes'] ?? [];
                if (empty($allowedRoles)) {
                    $this->addWarning("Group '{$groupName}' has no peopleTypes defined, skipping memberships", ['group_name' => $groupName]);
                    continue;
                }

                // Normalize allowed roles to match comparison (ucfirst lowercase)
                $normalizedAllowed = [];
                foreach ($allowedRoles as $role) {
                    $normalizedAllowed[ucfirst(strtolower(trim($role)))] = true;
                }

                // Load existing roles for this group using ORM (from ListOption table)
                $roleList = ListOptionQuery::create()->findById((int)$group->getRoleListId());
                $roleNameToId = [];
                if ($roleList) {
                    foreach ($roleList as $role) {
                        $roleNameToId[$role->getOptionName()] = (int)$role->getOptionId();
                    }
                }

                // Create any missing roles that are in peopleTypes
                foreach ($normalizedAllowed as $allowedRoleName => $dummy) {
                    if (!isset($roleNameToId[$allowedRoleName])) {
                        try {
                            // Create the role if it doesn't exist
                            $newRole = new ListOption();
                            $newRole->setId((int)$group->getRoleListId());
                            
                            // Get next available OptionID for this list
                            $maxRoleId = ListOptionQuery::create()
                                ->filterById((int)$group->getRoleListId())
                                ->orderByOptionId('desc')
                                ->findOne();
                            $nextRoleId = $maxRoleId ? ((int)$maxRoleId->getOptionId() + 1) : 1;
                            
                            $newRole->setOptionId($nextRoleId);
                            $newRole->setOptionName($allowedRoleName);
                            $newRole->setOptionSequence($nextRoleId);
                            $newRole->save();
                            
                            $roleNameToId[$allowedRoleName] = $nextRoleId;
                        } catch (Exception $e) {
                            $this->addWarning("Failed to create role '{$allowedRoleName}' for group '{$groupName}': {$e->getMessage()}", [
                                'group_name' => $groupName,
                                'role_name' => $allowedRoleName,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                // Now create memberships, but ONLY if the member's role is in peopleTypes
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
                    $roleName = $m['role'] ?? '';
                    
                    // Normalize the role name
                    $rn = ucfirst(strtolower(trim($roleName)));

                    // CRITICAL: Only proceed if the role is in the allowed roles for this group
                    if (!isset($normalizedAllowed[$rn])) {
                        $this->addWarning("Group '{$groupName}': member {$personId} has role '{$rn}' which is not in peopleTypes, skipping", [
                            'group_name' => $groupName,
                            'person_id' => $personId,
                            'role_name' => $rn
                        ]);
                        continue;
                    }

                    // Get the roleId from our map (should exist after the loop above)
                    $roleId = $roleNameToId[$rn] ?? 1;

                    // Create membership using ORM
                    try {
                        $membership = new Person2group2roleP2g2r();
                        $membership->setPersonId($personId);
                        $membership->setGroupId($groupId);
                        $membership->setRoleId($roleId);
                        $membership->save();
                    } catch (Exception $e) {
                        $this->addWarning("Failed to add person {$personId} to group '{$groupName}': {$e->getMessage()}", [
                            'person_id' => $personId,
                            'group_name' => $groupName,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } catch (Exception $e) {
                $this->addWarning("Membership import failed for group '{$groupData['name']}' : {$e->getMessage()}", [
                    'group_name' => $groupData['name'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Removed session flag for auth bypass (security fix)
        // If group management permissions are required, ensure import is performed by an authenticated admin.
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

    /**
     * Import financial demo data from finance.json.
     *
     * All dates are generated dynamically relative to the current date:
     *  - Previous year: all 12 months (closed deposits)
     *  - Current year:  Jan through last month (closed) + current month (OPEN)
     *
     * The open deposit contains payments dated on each giving family's
     * assigned collection Sunday, up to and including those that have
     * already passed this month (up to 3 weeks back).
     */
    private function importFinancial(): void
    {
        $data = $this->loadJsonFile('finance.json');
        if (!$data) {
            $this->addWarning('Financial data import skipped: finance.json missing or invalid');
            return;
        }

        $prevYear   = (int)date('Y') - 1;
        $currYear   = (int)date('Y');
        $currMonth  = (int)date('m');

        // 1. Create / reuse donation funds
        $fundMap = $this->importDonationFunds($data['funds'] ?? []);

        // 2. Create fundraisers
        $this->importFundRaisers($data['fundraisers'] ?? [], $prevYear, $currYear);

        // 3. Create pledges for all families
        $givingFamilies = $data['giving_families'] ?? [];
        $this->importFinancialPledges($givingFamilies, $fundMap, $prevYear, $currYear);

        // 4. Generate closed deposits for all months of prev year
        for ($m = 1; $m <= 12; $m++) {
            $this->createDepositForMonth($givingFamilies, $fundMap, $prevYear, $m, true);
        }
        // 5. Generate closed deposits for completed months of current year
        for ($m = 1; $m < $currMonth; $m++) {
            $this->createDepositForMonth($givingFamilies, $fundMap, $currYear, $m, true);
        }
        // 6. Generate OPEN deposit for current month (payments from passed Sundays only)
        $this->createDepositForMonth($givingFamilies, $fundMap, $currYear, $currMonth, false);
    }

    /** @param array<array<string,mixed>> $fundsData */
    private function importDonationFunds(array $fundsData): array
    {
        $fundMap = [];
        foreach ($fundsData as $fd) {
            $name = $fd['name'] ?? '';
            if ($name === '') {
                continue;
            }
            // Reuse existing fund with the same name (e.g. "Pledges" seeded by Install.sql)
            $existing = DonationFundQuery::create()->filterByName($name)->findOne();
            if ($existing) {
                $fundMap[$name] = $existing->getId();
                continue;
            }
            try {
                $fund = new DonationFund();
                $fund->setName($name);
                $fund->setDescription($fd['description'] ?? '');
                $fund->setActive($fd['active'] ? 'true' : 'false');
                $fund->setOrder((int)($fd['order'] ?? 1));
                $fund->save();
                $fundMap[$name] = $fund->getId();
                $this->importResult['imported']['funds']++;
            } catch (Exception $e) {
                $this->addWarning("Fund '{$name}' import failed: {$e->getMessage()}");
            }
        }
        return $fundMap;
    }

    /** @param array<array<string,mixed>> $fundraisers */
    private function importFundRaisers(array $fundraisers, int $prevYear, int $currYear): void
    {
        foreach ($fundraisers as $fr) {
            try {
                $year        = ($fr['year'] ?? 'prev') === 'prev' ? $prevYear : $currYear;
                $enteredYear = ($fr['entered_year'] ?? 'prev') === 'prev' ? $prevYear : $currYear;

                $fundraiser = new FundRaiser();
                $fundraiser->setTitle($fr['title'] ?? 'Fundraiser');
                $fundraiser->setDate(sprintf('%04d-%02d-%02d', $year, (int)$fr['month'], (int)$fr['day']));
                $fundraiser->setEnteredDate(sprintf('%04d-%02d-%02d', $enteredYear, (int)$fr['entered_month'], (int)$fr['entered_day']));
                $fundraiser->save();
                $this->importResult['imported']['fundraisers']++;
            } catch (Exception $e) {
                $this->addWarning("Fundraiser '{$fr['title']}' import failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * Create one pledge record per family per year per fund.
     *
     * @param array<array<string,mixed>> $givingFamilies
     * @param array<string,int>          $fundMap
     */
    private function importFinancialPledges(array $givingFamilies, array $fundMap, int $prevYear, int $currYear): void
    {
        $yearMap = ['prev' => $prevYear, 'curr' => $currYear];

        foreach ($givingFamilies as $family) {
            $email    = strtolower(trim($family['email'] ?? ''));
            $familyId = $this->familyEmailToId[$email] ?? null;
            if (!$familyId) {
                $this->addWarning("Financial pledge skipped: family email '{$email}' not found");
                continue;
            }

            $pledgeYears = $family['pledge_years'] ?? ['prev', 'curr'];
            $frequency   = $family['frequency'] ?? 'monthly';
            // plg_schedule ENUM: 'Weekly','Monthly','Quarterly','Once','Other'
            $schedule = match ($frequency) {
                'monthly'   => 'Monthly',
                'quarterly' => 'Quarterly',
                default     => 'Once',   // annual and any other
            };

            foreach ($pledgeYears as $yearKey) {
                $year = $yearMap[$yearKey] ?? null;
                if (!$year) {
                    continue;
                }
                $fyId = $year - 1996;

                foreach ($family['pledges'] ?? [] as $pd) {
                    $fundName = $pd['fund'] ?? '';
                    $fundId   = $fundMap[$fundName] ?? null;
                    if (!$fundId) {
                        $this->addWarning("Pledge skipped: fund '{$fundName}' not found");
                        continue;
                    }
                    try {
                        $pledge = new Pledge();
                        $pledge->setFamId($familyId);
                        $pledge->setFundId($fundId);
                        $pledge->setFyId($fyId);
                        $pledge->setDate(sprintf('%04d-01-01', $year));
                        $pledge->setAmount((float)$pd['annual']);
                        $pledge->setSchedule($schedule);
                        $pledge->setMethod('CHECK');
                        $pledge->setNondeductible(0.00);
                        $pledge->setGroupKey('');
                        $pledge->setPledgeOrPayment('Pledge');
                        $pledge->save();
                        $this->importResult['imported']['pledges']++;
                    } catch (Exception $e) {
                        $this->addWarning("Pledge import failed for '{$email}': {$e->getMessage()}");
                    }
                }
            }
        }
    }

    /**
     * Create one deposit for the given year/month and add payment entries.
     *
     * For closed months:  all giving families contribute a single payment
     *                     dated on the first Sunday of that month.
     * For the open month: only families whose collection Sunday has already
     *                     passed contribute; each payment is dated on that
     *                     family's specific collection Sunday.
     *
     * @param array<array<string,mixed>> $givingFamilies
     * @param array<string,int>          $fundMap
     */
    private function createDepositForMonth(array $givingFamilies, array $fundMap, int $year, int $month, bool $closed): void
    {
        $fyId        = $year - 1996;
        $monthName   = date('F', mktime(0, 0, 0, $month, 1, $year));
        $firstSunday = $this->firstSundayOfMonth($year, $month);
        $depositDate = sprintf('%04d-%02d-%02d', $year, $month, $firstSunday);

        // For the open deposit, compute which Sundays have already passed this month
        $passedSundays = $closed ? [] : $this->passedSundaysInMonth($year, $month);

        try {
            $deposit = new Deposit();
            $deposit->setDate($depositDate);
            $deposit->setComment("{$monthName} {$year} Offering");
            $deposit->setType('Bank');
            $deposit->setClosed($closed ? 1 : 0);
            $deposit->save();
            $this->importResult['imported']['deposits']++;
        } catch (Exception $e) {
            $this->addWarning("Deposit {$year}-{$month} creation failed: {$e->getMessage()}");
            return;
        }

        foreach ($givingFamilies as $family) {
            $email    = strtolower(trim($family['email'] ?? ''));
            $familyId = $this->familyEmailToId[$email] ?? null;
            if (!$familyId) {
                continue;
            }

            $frequency   = $family['frequency'] ?? 'monthly';
            $paymentMonth = (int)($family['payment_month'] ?? 12);
            $skipMonths  = $family['skip_months'] ?? [];
            $collWeek    = (int)($family['collection_week'] ?? 1);

            // Check whether this family gives during this month at all
            if (!$this->familyGivesThisMonth($frequency, $month, $paymentMonth)) {
                continue;
            }
            // Partial payers skip certain months
            if (in_array($month, $skipMonths, true)) {
                continue;
            }

            if ($closed) {
                // Closed deposit: one payment per family on the deposit date
                $payDate = $depositDate;
            } else {
                // Open deposit: payment only if the family's collection Sunday has passed
                $sundayIndex = $collWeek - 1; // 0-based
                if (!isset($passedSundays[$sundayIndex])) {
                    continue; // that Sunday hasn't arrived yet
                }
                $payDate = $passedSundays[$sundayIndex];
            }

            foreach ($family['payments'] ?? [] as $pd) {
                $fundName = $pd['fund'] ?? '';
                $fundId   = $fundMap[$fundName] ?? null;
                if (!$fundId) {
                    continue;
                }
                try {
                    $payment = new Pledge();
                    $payment->setFamId($familyId);
                    $payment->setFundId($fundId);
                    $payment->setFyId($fyId);
                    $payment->setDate($payDate);
                    $payment->setAmount((float)$pd['amount']);
                    $payment->setDepId($deposit->getId());
                    $payment->setMethod('CHECK');
                    $payment->setNondeductible(0.00);
                    $payment->setGroupKey('');
                    $payment->setPledgeOrPayment('Payment');
                    $payment->save();
                    $this->importResult['imported']['payments']++;
                } catch (Exception $e) {
                    $this->addWarning("Payment import failed for '{$email}' in {$year}-{$month}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Returns true if the family gives during the given calendar month.
     */
    private function familyGivesThisMonth(string $frequency, int $month, int $paymentMonth = 12): bool
    {
        return match ($frequency) {
            'monthly'   => true,
            'quarterly' => in_array($month, [1, 4, 7, 10], true),
            'annual'    => $month === $paymentMonth,
            default     => false,
        };
    }

    /**
     * Returns the day-of-month of the first Sunday in the given month.
     */
    private function firstSundayOfMonth(int $year, int $month): int
    {
        $date       = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $dayOfWeek  = (int)$date->format('w'); // 0 = Sunday
        $daysToAdd  = ($dayOfWeek === 0) ? 0 : (7 - $dayOfWeek);
        return 1 + $daysToAdd;
    }

    /**
     * Returns a list of Sunday date strings that have already passed (≤ today)
     * within the given month, up to a maximum of 3.
     *
     * @return string[] e.g. ['2026-02-02', '2026-02-09', '2026-02-16']
     */
    private function passedSundaysInMonth(int $year, int $month): array
    {
        $today   = new DateTime('today');
        $firstDay = $this->firstSundayOfMonth($year, $month);
        $date    = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $firstDay));
        $sundays = [];

        while ((int)$date->format('m') === $month && $date <= $today && count($sundays) < 3) {
            $sundays[] = $date->format('Y-m-d');
            $date->modify('+1 week');
        }

        return $sundays;
    }

    /**
     * Copy demo photo file to an entity (person|family) Images folder and refresh photo state.
     * Returns true on success, false otherwise.
     */
    private function importDemoPhotoForEntity(string $src, $entity, string $entityType): bool
    {
        if (!file_exists($src) || !is_file($src)) {
            return false;
        }

        try {
            $ext = pathinfo($src, PATHINFO_EXTENSION);
            $dst = SystemURLs::getImagesRoot() . '/' . $entityType . '/' . $entity->getId() . '.' . $ext;

            if (!FileSystemUtils::copyFile($src, $dst)) {
                return false;
            }

            // Refresh photo state
            try {
                $entity->getPhoto()->refresh();
            } catch (\Throwable $e) {
                // log but consider copy successful
                $this->addWarning("Photo copied but failed to refresh photo state for {$entityType} id {$entity->getId()}: {$e->getMessage()}");
            }

            return true;
        } catch (\Throwable $e) {
            $this->addWarning("Exception during photo import for {$entityType} id {$entity->getId()}: {$e->getMessage()}");
            return false;
        }
    }
}
