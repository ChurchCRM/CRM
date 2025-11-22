<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarEvent;
use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\Utils\LoggerUtils;
use DateTime;
use Exception;
use Propel\Runtime\Propel;

class DemoDataService
{
    private const DATA_PATH = __DIR__ . '/../../admin/demo';

    private array $importResult = [
        'success' => false,
        'imported' => [
            'calendars' => 0,
            'donation_funds' => 0,
            'groups' => 0,
            'families' => 0,
            'people' => 0,
            'notes' => 0,
            'person2group2role' => 0,
            'events' => 0,
            'event_attendance' => 0,
            'pledges' => 0,
            'calendar_events' => 0
        ],
        'warnings' => [],
        'errors' => [],
        'startTime' => null,
        'endTime' => null
    ];

    private array $familyMap = [];
    private array $personMap = [];
    private array $groupMap = [];
    private array $eventMap = [];
    private array $fundMap = [];

    public function importDemoData(bool $includeFinancial = false, bool $includeEvents = false): array
    {
        $this->importResult['startTime'] = microtime(true);
        $logger = LoggerUtils::getAppLogger();

        try {
            $connection = Propel::getConnection();
            $connection->beginTransaction();
            // If a simplified demo JSON exists under admin demo, import from it
            $simplifiedFile = self::DATA_PATH . '/people.json';
            if (file_exists($simplifiedFile)) {
                $this->importFromSimplified($simplifiedFile);
            } else {
                // Load core demo data (groups, families, people, notes, memberships)
                $this->importGroups();
                $this->importFamilies();
                $this->importPeople();
                $this->importNotes();
                $this->importPerson2GroupRole();
            }

            // Optionally load events-related data (calendars, events, attendance)
            if ($includeEvents) {
                $this->importCalendars();
                $this->importEvents();
                $this->importEventAttendance();
                $this->importCalendarEvents();
            }

            // Optionally load financial data (donation funds, pledges)
            if ($includeFinancial) {
                $this->importDonationFunds();
                $this->importPledges();
            }

            $connection->commit();

            $this->importResult['success'] = true;
            $this->importResult['endTime'] = microtime(true);
            $duration = $this->importResult['endTime'] - $this->importResult['startTime'];

            $logger->info('Demo data import completed successfully', [
                'duration' => $duration,
                'imported' => $this->importResult['imported'],
                'warnings' => count($this->importResult['warnings'])
            ]);

            return $this->importResult;

        } catch (Exception $e) {
            $connection = Propel::getConnection();
            $connection->rollBack();

            $this->importResult['success'] = false;
            $this->importResult['errors'][] = $e->getMessage();
            $this->importResult['endTime'] = microtime(true);

            $logger->error('Demo data import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->importResult;
        }
    }

    /**
     * Import events and financial related demo data separately.
     * This allows UI to offer a separate "financial/events" seed option.
     */
    public function importFinancialData(): array
    {
        $this->importResult['startTime'] = microtime(true);
        $logger = LoggerUtils::getAppLogger();

        try {
            $connection = Propel::getConnection();
            $connection->beginTransaction();

            // Financial imports (funds & pledges) - events/calendars are handled separately
            $this->importDonationFunds();
            $this->importPledges();

            $connection->commit();

            $this->importResult['success'] = true;
            $this->importResult['endTime'] = microtime(true);
            $duration = $this->importResult['endTime'] - $this->importResult['startTime'];

            $logger->info('Demo financial data import completed successfully', [
                'duration' => $duration,
                'imported' => $this->importResult['imported'],
                'warnings' => count($this->importResult['warnings'])
            ]);

            return $this->importResult;
        } catch (Exception $e) {
            $connection = Propel::getConnection();
            $connection->rollBack();

            $this->importResult['success'] = false;
            $this->importResult['errors'][] = $e->getMessage();
            $this->importResult['endTime'] = microtime(true);

            $logger->error('Demo financial data import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->importResult;
        }
    }

    /**
     * Import events and calendar related demo data separately.
     * This method focuses on calendars, events, and attendance associations.
     */
    public function importEventsAndCalendars(): array
    {
        $this->importResult['startTime'] = microtime(true);
        $logger = LoggerUtils::getAppLogger();

        try {
            $connection = Propel::getConnection();
            $connection->beginTransaction();

            $this->importCalendars();
            $this->importEvents();
            $this->importEventAttendance();
            $this->importCalendarEvents();

            $connection->commit();

            $this->importResult['success'] = true;
            $this->importResult['endTime'] = microtime(true);
            $duration = $this->importResult['endTime'] - $this->importResult['startTime'];

            $logger->info('Demo events/calendar import completed successfully', [
                'duration' => $duration,
                'imported' => $this->importResult['imported'],
                'warnings' => count($this->importResult['warnings'])
            ]);

            return $this->importResult;
        } catch (Exception $e) {
            $connection = Propel::getConnection();
            $connection->rollBack();

            $this->importResult['success'] = false;
            $this->importResult['errors'][] = $e->getMessage();
            $this->importResult['endTime'] = microtime(true);

            $logger->error('Demo events/calendar import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->importResult;
        }
    }

    private function importCalendars(): void
    {
        $data = $this->loadJsonFile('calendars.json');
        if (!$data) return;

        foreach ($data as $calendarData) {
            try {
                $calendar = new Calendar();
                $calendar->setId((int) $calendarData['calendar_id']);
                $calendar->setName($calendarData['name']);
                $calendar->setForegroundColor($calendarData['foregroundColor']);
                $calendar->setBackgroundColor($calendarData['backgroundColor']);
                $calendar->save();

                $this->importResult['imported']['calendars']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Calendar {$calendarData['calendar_id']}: {$e->getMessage()}";
            }
        }
    }

    private function importDonationFunds(): void
    {
        $data = $this->loadJsonFile('donationfund_fun.json');
        if (!$data) return;

        foreach ($data as $fundData) {
            try {
                $fund = new DonationFund();
                $fund->setId((int) $fundData['fun_ID']);
                $fund->setName($fundData['fun_Name']);
                $fund->setDescription($fundData['fun_Description']);
                $fund->setActive((bool) $fundData['fun_Active']);
                $fund->save();

                $this->fundMap[(int) $fundData['fun_ID']] = $fund;
                $this->importResult['imported']['donation_funds']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Donation Fund {$fundData['fun_ID']}: {$e->getMessage()}";
            }
        }
    }

    /**
     * Import simplified demo format (people.json) created under src/admin/demo
     */
    private function importFromSimplified(string $filePath): void
    {
        $json = json_decode(file_get_contents($filePath), true);
        if (!$json) {
            $this->importResult['warnings'][] = 'Invalid simplified demo JSON';
            return;
        }

        $families = $json['families'] ?? $json['data'] ?? [];

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

                if (!empty($famData['weddingDate'])) {
                    try {
                        $family->setWeddingDate(new DateTime($famData['weddingDate']));
                    } catch (Exception $e) {
                        // ignore invalid wedding date
                    }
                }

                if (!empty($famData['createdAt'])) {
                    try {
                        $family->setDateEntered(new DateTime($famData['createdAt']));
                    } catch (Exception $e) {
                    }
                }

                $family->setSendNewsletter(isset($famData['sendNewsletter']) && $famData['sendNewsletter']);
                $family->save();

                $this->familyMap[] = $family;
                $this->importResult['imported']['families']++;

                // members
                $members = $famData['members'] ?? [];
                foreach ($members as $m) {
                    try {
                        $person = new Person();
                        $person->setFamilyId($family->getId());
                        $person->setFirstName($m['firstName'] ?? null);
                        $person->setLastName($m['lastName'] ?? null);
                        $person->setMiddleName($m['middleName'] ?? null);
                        if (!empty($m['birthYear']) && !empty($m['birthMonth']) && !empty($m['birthDay'])) {
                            try {
                                $person->setBirthDate((int)$m['birthYear'], (int)$m['birthMonth'], (int)$m['birthDay']);
                            } catch (Exception $e) {}
                        }
                        if (!empty($m['gender'])) {
                            $person->setGender(strtolower($m['gender']) === 'male' ? 1 : (strtolower($m['gender']) === 'female' ? 2 : 0));
                        }
                        $person->setEmail($m['email'] ?? null);
                        $person->setHomePhone($m['phone'] ?? null);
                        if (!empty($m['createdAt'])) {
                            try { $person->setDateEntered(new DateTime($m['createdAt'])); } catch (Exception $e) {}
                        }
                        $person->save();
                        $this->personMap[] = $person;
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
                                $this->importResult['warnings'][] = "Person note import failed: {$e->getMessage()}";
                            }
                        }
                    } catch (Exception $e) {
                        $this->importResult['warnings'][] = "Member import failed: {$e->getMessage()}";
                    }
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
                        $this->importResult['warnings'][] = "Family note import failed: {$e->getMessage()}";
                    }
                }

            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Family import failed: {$e->getMessage()}";
            }
        }
    }

    private function importGroups(): void
    {
        $data = $this->loadJsonFile('group_grp.json');
        if (!$data) return;

        foreach ($data as $groupData) {
            try {
                $group = new Group();
                $group->setId((int) $groupData['grp_ID']);
                $group->setType((int) $groupData['grp_Type']);
                $group->setRoleListId((int) $groupData['grp_RoleListID']);
                $group->setDefaultRole((int) $groupData['grp_DefaultRole']);
                $group->setName($groupData['grp_Name']);
                $group->setDescription($groupData['grp_Description']);
                $group->setHasSpecialProps((bool) $groupData['grp_hasSpecialProps']);
                $group->setActive((bool) $groupData['grp_active']);
                $group->setIncludeEmailExport((bool) $groupData['grp_include_email_export']);
                $group->save();

                $this->groupMap[(int) $groupData['grp_ID']] = $group;
                $this->importResult['imported']['groups']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Group {$groupData['grp_ID']}: {$e->getMessage()}";
            }
        }
    }

    private function importFamilies(): void
    {
        $data = $this->loadJsonFile('family_fam.json');
        if (!$data) return;

        foreach ($data as $familyData) {
            try {
                $family = new Family();
                $family->setId((int) $familyData['fam_ID']);
                $family->setName($familyData['fam_Name']);
                $family->setAddress1($familyData['fam_Address1']);
                $family->setAddress2($familyData['fam_Address2']);
                $family->setCity($familyData['fam_City']);
                $family->setState($familyData['fam_State']);
                $family->setZip($familyData['fam_Zip']);
                $family->setCountry($familyData['fam_Country']);
                $family->setHomePhone($familyData['fam_HomePhone']);
                $family->setWorkPhone($familyData['fam_WorkPhone']);
                $family->setCellPhone($familyData['fam_CellPhone']);
                $family->setEmail($familyData['fam_Email']);

                if ($familyData['fam_WeddingDate']) {
                    $family->setWeddingDate(new DateTime($familyData['fam_WeddingDate']));
                }

                $family->setDateEntered(new DateTime($familyData['fam_DateEntered']));
                $family->setEnteredBy((int) $familyData['fam_EnteredBy']);
                $family->setSendNewsletter($familyData['fam_SendNewsLetter'] === 'TRUE');

                if ($familyData['fam_DateDeactivated']) {
                    $family->setDateDeactivated(new DateTime($familyData['fam_DateDeactivated']));
                }

                $family->setEnvelope((int) $familyData['fam_Envelope']);
                $family->save();

                $this->familyMap[(int) $familyData['fam_ID']] = $family;
                $this->importResult['imported']['families']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Family {$familyData['fam_ID']}: {$e->getMessage()}";
            }
        }
    }

    private function importPeople(): void
    {
        $data = $this->loadJsonFile('person_per.json');
        if (!$data) return;

        foreach ($data as $personData) {
            try {
                // Validate family exists
                $familyId = (int) $personData['per_fam_ID'];
                if ($personData['per_fam_ID'] && !isset($this->familyMap[$familyId])) {
                    $this->importResult['warnings'][] = "Person {$personData['per_ID']}: Family {$familyId} not found, skipping";
                    continue;
                }

                $person = new Person();
                $person->setId((int) $personData['per_ID']);

                if ($personData['per_fam_ID']) {
                    $person->setFamilyId($familyId);
                }

                $person->setFirstName($personData['per_FirstName']);
                $person->setLastName($personData['per_LastName']);
                $person->setMiddleName($personData['per_MiddleName']);

                if ($personData['per_BirthYear'] && $personData['per_BirthMonth'] && $personData['per_BirthDay']) {
                    try {
                        $person->setBirthDate(
                            (int) $personData['per_BirthYear'],
                            (int) $personData['per_BirthMonth'],
                            (int) $personData['per_BirthDay']
                        );
                    } catch (Exception $e) {
                        // Invalid date, skip
                    }
                }

                $person->setGender($personData['per_Gender'] ?? '');
                $person->setEmail($personData['per_Email']);
                $person->setHomePhone($personData['per_HomePhone']);
                $person->setCellPhone($personData['per_CellPhone']);
                $person->setWorkPhone($personData['per_WorkPhone']);
                $person->setFacebook($personData['per_Facebook']);
                $person->setTwitter($personData['per_Twitter']);
                $person->setLinkedin($personData['per_LinkedIn']);

                if ($personData['per_MembershipDate']) {
                    $person->setMembershipDate(new DateTime($personData['per_MembershipDate']));
                }

                $person->setFamilyRole((int) ($personData['per_fam_ID'] ? 1 : 0));
                $person->setClsId((int) $personData['per_cls_ID']);
                $person->save();

                $this->personMap[(int) $personData['per_ID']] = $person;
                $this->importResult['imported']['people']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Person {$personData['per_ID']}: {$e->getMessage()}";
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

    private function importPerson2GroupRole(): void
    {
        $data = $this->loadJsonFile('person2group2role_p2g2r.json');
        if (!$data) return;

        foreach ($data as $membershipData) {
            try {
                $personId = (int) $membershipData['p2g2r_per_ID'];
                $groupId = (int) $membershipData['p2g2r_grp_ID'];

                // Validate person and group exist
                if (!isset($this->personMap[$personId])) {
                    $this->importResult['warnings'][] = "Membership: Person {$personId} not found, skipping";
                    continue;
                }

                if (!isset($this->groupMap[$groupId])) {
                    $this->importResult['warnings'][] = "Membership: Group {$groupId} not found, skipping";
                    continue;
                }

                $membership = new Person2group2roleP2g2r();
                $membership->setPersonId($personId);
                $membership->setGroupId($groupId);
                $membership->setRoleId((int) $membershipData['p2g2r_rle_ID']);
                $membership->save();

                $this->importResult['imported']['person2group2role']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Membership {$membershipData['p2g2r_per_ID']}/{$membershipData['p2g2r_grp_ID']}: {$e->getMessage()}";
            }
        }
    }

    private function importEvents(): void
    {
        $data = $this->loadJsonFile('events_event.json');
        if (!$data) return;

        foreach ($data as $eventData) {
            try {
                $event = new Event();
                $event->setId((int) $eventData['event_id']);
                $event->setType((int) $eventData['event_type']);
                $event->setTitle($eventData['event_title']);
                $event->setDesc($eventData['event_desc'] ?? '');

                if ($eventData['event_start_datetime']) {
                    $event->setStartDateTime(new DateTime($eventData['event_start_datetime']));
                }

                if ($eventData['event_end_datetime']) {
                    $event->setEndDateTime(new DateTime($eventData['event_end_datetime']));
                }

                $event->setURL($eventData['event_url']);
                $event->save();

                $this->eventMap[(int) $eventData['event_id']] = $event;
                $this->importResult['imported']['events']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Event {$eventData['event_id']}: {$e->getMessage()}";
            }
        }
    }

    private function importEventAttendance(): void
    {
        $data = $this->loadJsonFile('event_attend.json');
        if (!$data) return;

        foreach ($data as $attendanceData) {
            try {
                $personId = (int) $attendanceData['person_id'];
                $eventId = (int) $attendanceData['event_id'];

                // Validate person and event exist
                if (!isset($this->personMap[$personId])) {
                    continue; // Skip silently for bulk data
                }

                if (!isset($this->eventMap[$eventId])) {
                    continue;
                }

                $attendance = new EventAttend();
                $attendance->setPersonId($personId);
                $attendance->setEventId($eventId);

                if ($attendanceData['checkin_id']) {
                    $attendance->setCheckinId((int) $attendanceData['checkin_id']);
                }

                if ($attendanceData['checkin_datetime']) {
                    $attendance->setCheckinDate(new DateTime($attendanceData['checkin_datetime']));
                }

                if ($attendanceData['checkout_id']) {
                    $attendance->setCheckoutId((int) $attendanceData['checkout_id']);
                }

                if ($attendanceData['checkout_datetime']) {
                    $attendance->setCheckoutDate(new DateTime($attendanceData['checkout_datetime']));
                }

                $attendance->save();
                $this->importResult['imported']['event_attendance']++;
            } catch (Exception $e) {
                // Skip attendance errors silently
            }
        }
    }

    private function importPledges(): void
    {
        $data = $this->loadJsonFile('pledge_plg.json');
        if (!$data) return;

        foreach ($data as $pledgeData) {
            try {
                $familyId = (int) $pledgeData['plg_FamID'];
                $fundId = (int) $pledgeData['plg_fundID'];

                // Validate family exists
                if (!isset($this->familyMap[$familyId])) {
                    $this->importResult['warnings'][] = "Pledge: Family {$familyId} not found, skipping";
                    continue;
                }

                // Fund might not exist if import failed, skip
                if ($fundId && !isset($this->fundMap[$fundId])) {
                    continue;
                }

                $pledge = new Pledge();
                $pledge->setId((int) $pledgeData['plg_plgID']);
                $pledge->setFamilyId($familyId);
                $pledge->setFyId((int) $pledgeData['plg_FYID']);

                if ($pledgeData['plg_date']) {
                    $pledge->setDate(new DateTime($pledgeData['plg_date']));
                }

                $pledge->setAmount((float) $pledgeData['plg_amount']);
                $pledge->setSchedule($pledgeData['plg_schedule']);
                $pledge->setMethod($pledgeData['plg_method']);
                $pledge->setComment($pledgeData['plg_comment']);

                if ($pledgeData['plg_DateLastEdited']) {
                    $pledge->setDateLastEdited(new DateTime($pledgeData['plg_DateLastEdited']));
                }

                $pledge->setEditedBy((int) $pledgeData['plg_EditedBy']);
                $pledge->setPledgeOrPayment($pledgeData['plg_PledgeOrPayment']);

                if ($fundId) {
                    $pledge->setFundId($fundId);
                }

                if ($pledgeData['plg_CheckNo']) {
                    $pledge->setCheckNo($pledgeData['plg_CheckNo']);
                }

                $pledge->save();
                $this->importResult['imported']['pledges']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Pledge {$pledgeData['plg_plgID']}: {$e->getMessage()}";
            }
        }
    }

    private function importCalendarEvents(): void
    {
        $data = $this->loadJsonFile('calendar_events.json');
        if (!$data) return;

        foreach ($data as $calEventData) {
            try {
                $calendarId = (int) $calEventData['calendar_id'];
                $eventId = (int) $calEventData['event_id'];

                // Validate calendar and event exist
                if (!isset($this->eventMap[$eventId])) {
                    $this->importResult['warnings'][] = "Calendar Event: Event {$eventId} not found, skipping";
                    continue;
                }

                $calEvent = new CalendarEvent();
                $calEvent->setCalendarId($calendarId);
                $calEvent->setEventId($eventId);
                $calEvent->save();

                $this->importResult['imported']['calendar_events']++;
            } catch (Exception $e) {
                $this->importResult['warnings'][] = "Calendar Event {$calEventData['calendar_id']}/{$calEventData['event_id']}: {$e->getMessage()}";
            }
        }
    }

    private function loadJsonFile(string $filename): ?array
    {
        $filepath = self::DATA_PATH . '/' . $filename;

        if (!file_exists($filepath)) {
            $this->importResult['errors'][] = "Data file not found: {$filename}";
            return null;
        }

        $json = file_get_contents($filepath);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            $this->importResult['errors'][] = "Invalid JSON in file: {$filename}";
            return null;
        }

        return $data;
    }
}
