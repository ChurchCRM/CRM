<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\PersonQuery;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use PDO;
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Propel;

/**
 * Builds the people CSV export from the export form's fields.
 */
class CsvExportService
{
    /**
     * Choices the export form offers: classifications, family roles, groups and the custom fields the user may see.
     *
     * @return array{classifications: array<int, string>, familyRoles: array<int, string>, groups: array<int, string>, personCustomFields: list<array{field: string, name: string}>, familyCustomFields: list<array{field: string, name: string}>}
     */
    public static function formOptions(): array
    {
        $customFieldService = new CustomFieldService();
        $options = [
            'classifications' => [],
            'familyRoles' => [],
            'groups' => [],
            'personCustomFields' => [],
            'familyCustomFields' => [],
        ];

        foreach (ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find() as $option) {
            $options['classifications'][(int) $option->getOptionId()] = $option->getOptionName();
        }
        foreach (ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find() as $option) {
            $options['familyRoles'][(int) $option->getOptionId()] = $option->getOptionName();
        }
        foreach (GroupQuery::create()->orderByName()->find() as $group) {
            $options['groups'][(int) $group->getId()] = $group->getName();
        }
        foreach (PersonCustomMasterQuery::create()->orderByOrder()->find() as $field) {
            if ($customFieldService->canView((int) $field->getFieldSecurity())) {
                $options['personCustomFields'][] = ['field' => $field->getId(), 'name' => $field->getName()];
            }
        }
        foreach (FamilyCustomMasterQuery::create()->orderByOrder()->find() as $field) {
            if ($customFieldService->canView((int) $field->getFieldSecurity())) {
                $options['familyCustomFields'][] = ['field' => $field->getField(), 'name' => $field->getName()];
            }
        }

        return $options;
    }

    /** Limits the hand-built person_per query to the ids the ORM calls active. */
    private static function activePeopleSql(): string
    {
        $ids = array_map('intval', PersonQuery::create()->filterByActive()->select('Id')->find()->toArray());

        return $ids === [] ? ' AND 1 = 0 ' : ' AND per_ID IN (' . implode(',', $ids) . ') ';
    }

    /**
     * Adds the people the form's filters select to the cart.
     *
     * @param array<string, mixed> $input the export form fields
     *
     * @throws \InvalidArgumentException when a date filter is not a valid date
     */
    public function addToCart(array $input): void
    {
        $sSource = strtolower((string) ($input['Source'] ?? ''));
        $sGroupBy = '';
        // Prepare the MySQL query
        $sJoinFamTable = ' LEFT JOIN family_fam ON per_fam_ID = fam_ID ';
        $sPerTable = 'person_per';
        $whereParams = [];

        // If our source is the cart contents, we don't need to build a WHERE filter string.
        // Cart IDs come from the session (trusted), so no parameterisation needed here.
        if ($sSource === 'cart') {
            $sWhereExt = 'AND per_ID IN (' . Cart::getCartIdString() . ')';
        } else {
            // If we're filtering by groups, include the p2g2r table
            if (!empty($input['GroupID'])) {
                $sPerTable = '(person_per, person2group2role_p2g2r)';
            }

            // Prepare any extensions to the WHERE clauses using ? placeholders (SQL injection prevention)
            $sWhereExt = '';
            if (SystemConfig::getBooleanValue('bHideDeceasedFromDirectory')) {
                $sWhereExt .= 'AND per_DateDeceased IS NULL ';
            }
            if (!empty($input['Classification'])) {
                $count = 0;
                $Class = [];
                foreach ($input['Classification'] as $Cls) {
                    $Class[$count++] = (int) InputUtils::legacyFilterInput($Cls, 'int');
                }
                if ($count === 1) {
                    if ($Class[0]) {
                        $sWhereExt .= 'AND per_cls_ID = ? ';
                        $whereParams[] = $Class[0];
                    }
                } else {
                    $placeholders = implode(',', array_fill(0, $count, '?'));
                    $sWhereExt .= 'AND per_cls_ID IN (' . $placeholders . ') ';
                    $whereParams = array_merge($whereParams, $Class);
                }
            }

            if (!empty($input['FamilyRole'])) {
                $count = 0;
                $Class = [];
                foreach ($input['FamilyRole'] as $Fmr) {
                    $Class[$count++] = (int) InputUtils::legacyFilterInput($Fmr, 'int');
                }
                if ($count === 1) {
                    if ($Class[0]) {
                        $sWhereExt .= 'AND per_fmr_ID = ? ';
                        $whereParams[] = $Class[0];
                    }
                } else {
                    $placeholders = implode(',', array_fill(0, $count, '?'));
                    $sWhereExt .= 'AND per_fmr_ID IN (' . $placeholders . ') ';
                    $whereParams = array_merge($whereParams, $Class);
                }
            }

            if (!empty($input['Gender'])) {
                $sWhereExt .= 'AND per_Gender = ? ';
                $whereParams[] = (int) InputUtils::legacyFilterInput($input['Gender'], 'int');
            }

            if (!empty($input['GroupID'])) {
                $count = 0;
                $Class = [];
                foreach ($input['GroupID'] as $Grp) {
                    $Class[$count++] = (int) InputUtils::legacyFilterInput($Grp, 'int');
                }
                if ($count === 1) {
                    if ($Class[0]) {
                        $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID = ? ';
                        $whereParams[] = $Class[0];
                    }
                } else {
                    $placeholders = implode(',', array_fill(0, $count, '?'));
                    $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID IN (' . $placeholders . ') ';
                    $whereParams = array_merge($whereParams, $Class);
                }

                // This is used for individual mode to remove duplicate rows from people assigned multiple groups.
                $sGroupBy = ' GROUP BY per_ID';
            } else {
                $sGroupBy = '';
            }

            $dates = [];
            foreach (['MembershipDate', 'BirthDate', 'AnniversaryDate', 'EnterDate'] as $field) {
                foreach (['1', '2'] as $end) {
                    $raw = trim((string) ($input[$field . $end] ?? ''));
                    $dates[$field . $end] = DateTimeUtils::normalizeDate($raw);
                    if ($raw !== '' && $dates[$field . $end] === '') {
                        throw new \InvalidArgumentException(gettext('Not a valid date') . ': ' . $raw);
                    }
                }
            }
            $today = date('Y-m-d');
            $birthExpr = "DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d')";

            $ranges = [
                'MembershipDate' => 'per_MembershipDate',
                'BirthDate' => $birthExpr,
                'EnterDate' => 'DATE(per_DateEntered)',
            ];
            foreach ($ranges as $field => $column) {
                if ($dates[$field . '1'] !== '') {
                    $sWhereExt .= "AND $column >= ? ";
                    $whereParams[] = $dates[$field . '1'];
                }
                if ($dates[$field . '2'] !== '' && $dates[$field . '2'] !== $today) {
                    $sWhereExt .= "AND $column <= ? ";
                    $whereParams[] = $dates[$field . '2'];
                }
            }

            if ($dates['AnniversaryDate1'] !== '') {
                $annivStart = getdate(strtotime($dates['AnniversaryDate1']));

                // Add year to query if not in future
                if ($annivStart['year'] < date('Y') || ($annivStart['year'] === date('Y') && $annivStart['mon'] <= date('m') && $annivStart['mday'] <= date('d'))) {
                    $sWhereExt .= 'AND fam_WeddingDate >= ? ';
                } else {
                    $sWhereExt .= 'AND DAYOFYEAR(fam_WeddingDate) >= DAYOFYEAR(?) ';
                }
                $whereParams[] = $dates['AnniversaryDate1'];
            }

            if ($dates['AnniversaryDate2'] !== '' && $dates['AnniversaryDate2'] !== $today) {
                $annivEnd = getdate(strtotime($dates['AnniversaryDate2']));

                // Add year to query if not in future
                if ($annivEnd['year'] < date('Y') || ($annivEnd['year'] === date('Y') && $annivEnd['mon'] <= date('m') && $annivEnd['mday'] <= date('d'))) {
                    $sWhereExt .= 'AND fam_WeddingDate <= ? ';
                } else {
                    $refDate = getdate(strtotime($dates['AnniversaryDate2']));
                    $sWhereExt .= 'AND DAYOFYEAR(fam_WeddingDate) <= DAYOFYEAR(?) ';
                }
                $whereParams[] = $dates['AnniversaryDate2'];
            }
        }

        $sWhereExt .= self::activePeopleSql();
        $rsLabelsToWrite = self::select(
            "SELECT per_ID FROM $sPerTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy ORDER BY per_LastName",
            $whereParams
        );
        while (($aRow = $rsLabelsToWrite->fetch(PDO::FETCH_ASSOC)) !== false) {
            Cart::addPerson((int) $aRow['per_ID']);
        }
    }

    /**
     * The CSV headers and rows for the form's fields and filters.
     *
     * @param array<string, mixed> $input the export form fields
     *
     * @return array{headers: list<string>, rows: list<list<string>>}
     *
     * @throws \InvalidArgumentException when a date filter is not a valid date
     */
    public function export(array $input): array
    {
        $headers = [];
        $rows = [];
        // Get Source and Format from the request object and assign them locally
        $sSource = strtolower($input['Source']);
        $sFormat = strtolower($input['Format']);
        $bSkipIncompleteAddr = isset($input['SkipIncompleteAddr']);
        $bSkipNoEnvelope = isset($input['SkipNoEnvelope']);

        $personFields = [];
        $familyFields = [];
        if ($sFormat === 'default') {
            $personFields = self::select('SELECT * FROM person_custom_master ORDER BY custom_Order')->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($sFormat === 'default' || $sFormat === 'rollup') {
            $familyFields = self::select('SELECT * FROM family_custom_master ORDER BY fam_custom_Order')->fetchAll(PDO::FETCH_ASSOC);
        }

        //Get membership classes
        $memberClass = [0];
        foreach (Classification::getAll() as $Member) {
            $memberClass[] = $Member->getOptionName();
        }

        //Get family roles
        foreach (ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find() as $role) {
            $familyRoles[$role->getOptionId()] = $role->getOptionName();
            $roleSequence[$role->getOptionSequence()] = $role->getOptionId();
        }

        $customFieldService = new CustomFieldService();

        $sGroupBy = '';
        // Prepare the MySQL query
        $sJoinFamTable = ' LEFT JOIN family_fam ON per_fam_ID = fam_ID ';
        $sPerTable = 'person_per';
        $whereParams = [];

        // If our source is the cart contents, we don't need to build a WHERE filter string.
        // Cart IDs come from the session (trusted), so no parameterisation needed here.
        if ($sSource === 'cart') {
            $sWhereExt = 'AND per_ID IN (' . Cart::getCartIdString() . ')';
        } else {
            // If we're filtering by groups, include the p2g2r table
            if (!empty($input['GroupID'])) {
                $sPerTable = '(person_per, person2group2role_p2g2r)';
            }

            // Prepare any extensions to the WHERE clauses using ? placeholders (SQL injection prevention)
            $sWhereExt = '';
            if (SystemConfig::getBooleanValue('bHideDeceasedFromDirectory')) {
                $sWhereExt .= 'AND per_DateDeceased IS NULL ';
            }
            if (!empty($input['Classification'])) {
                $count = 0;
                $Class = [];
                foreach ($input['Classification'] as $Cls) {
                    $Class[$count++] = (int) InputUtils::legacyFilterInput($Cls, 'int');
                }
                if ($count === 1) {
                    if ($Class[0]) {
                        $sWhereExt .= 'AND per_cls_ID = ? ';
                        $whereParams[] = $Class[0];
                    }
                } else {
                    $placeholders = implode(',', array_fill(0, $count, '?'));
                    $sWhereExt .= 'AND per_cls_ID IN (' . $placeholders . ') ';
                    $whereParams = array_merge($whereParams, $Class);
                }
            }

            if (!empty($input['FamilyRole'])) {
                $count = 0;
                $Class = [];
                foreach ($input['FamilyRole'] as $Fmr) {
                    $Class[$count++] = (int) InputUtils::legacyFilterInput($Fmr, 'int');
                }
                if ($count === 1) {
                    if ($Class[0]) {
                        $sWhereExt .= 'AND per_fmr_ID = ? ';
                        $whereParams[] = $Class[0];
                    }
                } else {
                    $placeholders = implode(',', array_fill(0, $count, '?'));
                    $sWhereExt .= 'AND per_fmr_ID IN (' . $placeholders . ') ';
                    $whereParams = array_merge($whereParams, $Class);
                }
            }

            if (!empty($input['Gender'])) {
                $sWhereExt .= 'AND per_Gender = ? ';
                $whereParams[] = (int) InputUtils::legacyFilterInput($input['Gender'], 'int');
            }

            if (!empty($input['GroupID'])) {
                $count = 0;
                $Class = [];
                foreach ($input['GroupID'] as $Grp) {
                    $Class[$count++] = (int) InputUtils::legacyFilterInput($Grp, 'int');
                }
                if ($count === 1) {
                    if ($Class[0]) {
                        $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID = ? ';
                        $whereParams[] = $Class[0];
                    }
                } else {
                    $placeholders = implode(',', array_fill(0, $count, '?'));
                    $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID IN (' . $placeholders . ') ';
                    $whereParams = array_merge($whereParams, $Class);
                }

                // This is used for individual mode to remove duplicate rows from people assigned multiple groups.
                $sGroupBy = ' GROUP BY per_ID';
            } else {
                $sGroupBy = '';
            }

            $refDate = getdate(time());

            $dates = [];
            foreach (['MembershipDate', 'BirthDate', 'AnniversaryDate', 'EnterDate'] as $field) {
                foreach (['1', '2'] as $end) {
                    $raw = trim((string) ($input[$field . $end] ?? ''));
                    $dates[$field . $end] = DateTimeUtils::normalizeDate($raw);
                    if ($raw !== '' && $dates[$field . $end] === '') {
                        throw new \InvalidArgumentException(gettext('Not a valid date') . ': ' . $raw);
                    }
                }
            }
            $today = date('Y-m-d');
            $birthExpr = "DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d')";

            $ranges = [
                'MembershipDate' => 'per_MembershipDate',
                'BirthDate' => $birthExpr,
                'EnterDate' => 'DATE(per_DateEntered)',
            ];
            foreach ($ranges as $field => $column) {
                if ($dates[$field . '1'] !== '') {
                    $sWhereExt .= "AND $column >= ? ";
                    $whereParams[] = $dates[$field . '1'];
                }
                if ($dates[$field . '2'] !== '' && $dates[$field . '2'] !== $today) {
                    $sWhereExt .= "AND $column <= ? ";
                    $whereParams[] = $dates[$field . '2'];
                }
            }

            if ($dates['AnniversaryDate1'] !== '') {
                $annivStart = getdate(strtotime($dates['AnniversaryDate1']));

                // Add year to query if not in future
                if ($annivStart['year'] < date('Y') || ($annivStart['year'] === date('Y') && $annivStart['mon'] <= date('m') && $annivStart['mday'] <= date('d'))) {
                    $sWhereExt .= 'AND fam_WeddingDate >= ? ';
                } else {
                    $sWhereExt .= 'AND DAYOFYEAR(fam_WeddingDate) >= DAYOFYEAR(?) ';
                }
                $whereParams[] = $dates['AnniversaryDate1'];
            }

            if ($dates['AnniversaryDate2'] !== '' && $dates['AnniversaryDate2'] !== $today) {
                $annivEnd = getdate(strtotime($dates['AnniversaryDate2']));

                // Add year to query if not in future
                if ($annivEnd['year'] < date('Y') || ($annivEnd['year'] === date('Y') && $annivEnd['mon'] <= date('m') && $annivEnd['mday'] <= date('d'))) {
                    $sWhereExt .= 'AND fam_WeddingDate <= ? ';
                } else {
                    $refDate = getdate(strtotime($dates['AnniversaryDate2']));
                    $sWhereExt .= 'AND DAYOFYEAR(fam_WeddingDate) <= DAYOFYEAR(?) ';
                }
                $whereParams[] = $dates['AnniversaryDate2'];
            }
        }
        $sWhereExt .= ' AND per_NeedsReview = 0 ' . self::activePeopleSql();

            // Build the complete SQL statement

            if ($sFormat === 'rollup') {
                // UNION query uses $sWhereExt three times → bind params three times in sequence
                $rsLabelsToWrite = self::select(
                    "(SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID = 0 $sWhereExt)
                UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount = 1)
                UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount > 1) ORDER BY SortMe",
                    array_merge($whereParams, $whereParams, $whereParams)
                );
            } else {
                $rsLabelsToWrite = self::select(
                    "SELECT * FROM $sPerTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy ORDER BY per_LastName",
                    $whereParams
                );
            }

            // Build headers array
            // Note: CsvExporter will handle gettext localization, charset translation, and formula injection escaping
            $headers[] = 'Family ID';
            
            if ($sFormat === 'rollup') {
                $headers[] = 'Name';
                $headers[] = 'Salutation';
            } else {
                $headers[] = 'Person Id';
                $headers[] = 'Last Name';
                if (!empty($input['Title'])) {
                    $headers[] = 'Title';
                }
                if (!empty($input['FirstName'])) {
                    $headers[] = 'First Name';
                }
                if (!empty($input['Suffix'])) {
                    $headers[] = 'Suffix';
                }
                if (!empty($input['MiddleName'])) {
                    $headers[] = 'Middle Name';
                }
            }

            if (!empty($input['Address1'])) {
                $headers[] = 'Address 1';
            }
            if (!empty($input['Address2'])) {
                $headers[] = 'Address 2';
            }
            if (!empty($input['City'])) {
                $headers[] = 'City';
            }
            if (!empty($input['State'])) {
                $headers[] = 'State';
            }
            if (!empty($input['Zip'])) {
                $headers[] = 'Zip';
            }
            if (!empty($input['Country'])) {
                $headers[] = 'Country';
            }
            // Optional second family address (#9743) — opt-in so existing exports keep
            // their column set byte-identical.
            if (!empty($input['SecondAddress'])) {
                $headers[] = 'Second Address 1';
                $headers[] = 'Second Address 2';
                $headers[] = 'Second City';
                $headers[] = 'Second State';
                $headers[] = 'Second Zip';
                $headers[] = 'Second Country';
                $headers[] = 'Mailing Address';
            }
            if (!empty($input['HomePhone'])) {
                $headers[] = 'Home Phone';
            }
            if (!empty($input['WorkPhone'])) {
                $headers[] = 'Work Phone';
            }
            if (!empty($input['CellPhone'])) {
                $headers[] = 'Cell Phone';
            }
            if (!empty($input['Email'])) {
                $headers[] = 'Email';
            }
            if (!empty($input['WorkEmail'])) {
                $headers[] = 'Work Email';
            }
            if (!empty($input['Envelope'])) {
                $headers[] = 'Envelope Number';
            }
            if (!empty($input['MembershipDate'])) {
                $headers[] = 'MembershipDate';
            }

            if ($sFormat === 'default') {
                if (!empty($input['BirthdayDate'])) {
                    $headers[] = 'Birth Date';
                }
                if (!empty($input['Age'])) {
                    $headers[] = 'Age';
                }
                if (!empty($input['PrintMembershipStatus'])) {
                    $headers[] = 'Classification';
                }
                if (!empty($input['PrintFamilyRole'])) {
                    $headers[] = 'Family Role';
                }
                if (!empty($input['PrintGrade'])) {
                    $headers[] = 'Grade';
                }
                if (!empty($input['PrintGender'])) {
                    $headers[] = 'Gender';
                }
            } else {
                if (!empty($input['BirthdayDate'])) {
                    $headers[] = 'AnnivDate';
                }
                if (!empty($input['Age'])) {
                    $headers[] = 'Anniv';
                }
            }

            // Add any custom field names to the header, unless using family roll-up mode
            $bUsedCustomFields = false;
            if ($sFormat === 'default') {
                foreach ($personFields as $aRow) {
                    extract($aRow);
                    if (isset($input["$custom_Field"])) {
                        $bUsedCustomFields = true;
                        $headers[] = $custom_Name;
                    }
                }
                foreach ($familyFields as $aFamRow) {
                    extract($aFamRow);
                    if ($customFieldService->canView((int) $fam_custom_FieldSec)) {
                        if (isset($input["$fam_custom_Field"])) {
                            $bUsedCustomFields = true;
                            $headers[] = $fam_custom_Name;
                        }
                    }
                }
            }
            // Add any family custom fields names to the header
            if ($sFormat === 'rollup') {
                foreach ($familyFields as $aFamRow) {
                    extract($aFamRow);
                    if ($customFieldService->canView((int) $fam_custom_FieldSec)) {
                        if (isset($input["$fam_custom_Field"])) {
                            $bUsedCustomFields = true;
                            $headers[] = $fam_custom_Name;
                        }
                    }
                }
            }


            while (($aRow = $rsLabelsToWrite->fetch(PDO::FETCH_ASSOC)) !== false) {
                $per_Title = '';
                $per_FirstName = '';
                $per_MiddleName = '';
                $per_LastName = '';
                $per_Suffix = '';
                $per_Address1 = '';
                $per_Address2 = '';
                $per_City = '';
                $per_State = '';
                $per_Zip = '';
                $per_Country = '';
                $per_HomePhone = '';
                $per_WorkPhone = '';
                $per_CellPhone = '';
                $per_Email = '';
                $per_WorkEmail = '';
                $fam_Envelope = '';
                $per_MembershipDate = '';

                $per_BirthDay = '';
                $per_BirthMonth = '';
                $per_BirthYear = '';

                $fam_Address1 = '';
                $fam_Address2 = '';
                $fam_City = '';
                $fam_State = '';
                $fam_Zip = '';
                $fam_Country = '';
                $fam_HomePhone = '';
                $fam_WeddingDate = '';

                $sCountry = '';

                extract($aRow);
                $person = PersonQuery::create()->findOneById($per_ID);

                // Use Person entity methods with family fallback (issue #7937)
                // Person entity provides resolved address fields that automatically fall back to family data
                // Family relationship is lazy-loaded when accessed
                if ($person) {
                    $sHomePhone = $person->getResolvedHomePhone();
                    $sWorkPhone = $per_WorkPhone ?? '';
                    $sCellPhone = $per_CellPhone ?? '';
                    $sCountry = $person->getResolvedCountry();
                    $sAddress1 = $person->getResolvedAddress1();
                    $sAddress2 = $person->getResolvedAddress2();
                    $sCity = $person->getResolvedCity();
                    $sState = $person->getResolvedState();
                    $sZip = $person->getResolvedZip();
                    $sEmail = $person->getEmail();
                } else {
                    // Fallback if person not found (use raw variables from extract)
                    $sHomePhone = $per_HomePhone ?? '';
                    $sWorkPhone = $per_WorkPhone ?? '';
                    $sCellPhone = $per_CellPhone ?? '';
                    $sCountry = $per_Country ?? '';
                    $sAddress1 = $per_Address1 ?? '';
                    $sAddress2 = $per_Address2 ?? '';
                    $sCity = $per_City ?? '';
                    $sState = $per_State ?? '';
                    $sZip = $per_Zip ?? '';
                    $sEmail = $per_Email ?? '';
                }

                // Check if we're filtering out people with incomplete addresses
                if (!($bSkipIncompleteAddr && (strlen($sCity) === 0 || strlen($sState) === 0 || strlen($sZip) === 0 || (strlen($sAddress1) === 0 && strlen($sAddress2) === 0)))) {
                    // Check if we're filtering out people with no envelope number assigned
                    // ** should move this to the WHERE clause
                    if (!($bSkipNoEnvelope && (strlen($fam_Envelope) === 0))) {
                        // Build row as array
                        $row = [];
                        
                        // Family ID
                        $row[] = $fam_ID ? (string) $fam_ID : '';
                        
                        if ($sFormat === 'default') {
                            $row[] = (string) $per_ID;
                            $row[] = $per_LastName;
                            if (isset($input['Title'])) {
                                $row[] = $per_Title;
                            }
                            if (isset($input['FirstName'])) {
                                $row[] = $per_FirstName;
                            }
                            if (isset($input['Suffix'])) {
                                $row[] = $per_Suffix;
                            }
                            if (isset($input['MiddleName'])) {
                                $row[] = $per_MiddleName;
                            }
                        } elseif ($sFormat === 'rollup') {
                            $family = FamilyQuery::create()->findPk($fam_ID);
                            if ($memberCount > 1) {
                                $row[] = self::sortableFamilyName($family);
                                $row[] = $family->getFirstNameSalutation();
                            } else {
                                $row[] = self::sortableName((string) $per_LastName, (string) $per_FirstName);
                                $row[] = $per_FirstName;
                            }
                        }

                        if (isset($input['Address1'])) {
                            $row[] = $sAddress1;
                        }
                        if (isset($input['Address2'])) {
                            $row[] = $sAddress2;
                        }
                        if (isset($input['City'])) {
                            $row[] = $sCity;
                        }
                        if (isset($input['State'])) {
                            $row[] = $sState;
                        }
                        if (isset($input['Zip'])) {
                            $row[] = $sZip;
                        }
                        if (isset($input['Country'])) {
                            $row[] = $sCountry;
                        }
                        if (!empty($input['SecondAddress'])) {
                            // The export query already LEFT JOINs family_fam, so the
                            // family is built from this row: no per-person family
                            // query. The second address lives on the family only (no
                            // person-level override), and a person with no family has
                            // NULL family columns, which export as blank cells.
                            $rowFamily = Family::readOnlyFromRow($aRow);
                            $secondParts = $rowFamily->getSecondaryAddressParts();
                            $row[] = $secondParts['Address1'];
                            $row[] = $secondParts['Address2'];
                            $row[] = $secondParts['City'];
                            $row[] = $secondParts['State'];
                            $row[] = $secondParts['Zip'];
                            $row[] = $secondParts['Country'];
                            $row[] = $rowFamily->isSecondAddressMailing() ? 'Yes' : 'No';
                        }
                        if (isset($input['HomePhone'])) {
                            $row[] = $sHomePhone;
                        }
                        if (isset($input['WorkPhone'])) {
                            $row[] = $sWorkPhone;
                        }
                        if (isset($input['CellPhone'])) {
                            $row[] = $sCellPhone;
                        }
                        if (isset($input['Email'])) {
                            $row[] = $sEmail;
                        }
                        if (isset($input['WorkEmail'])) {
                            $row[] = $per_WorkEmail;
                        }
                        if (isset($input['Envelope'])) {
                            $row[] = $fam_Envelope;
                        }
                        if (isset($input['MembershipDate'])) {
                            $row[] = $per_MembershipDate;
                        }

                        if ($sFormat === 'default') {
                            if (isset($input['BirthdayDate'])) {
                                $birthDate = '';
                                if ($per_BirthYear !== '') {
                                    $birthDate = $per_BirthYear . '-';
                                }
                                $birthDate .= $per_BirthMonth . '-' . $per_BirthDay;
                                $row[] = $birthDate;
                            }

                            if (isset($input['Age'])) {
                                if (isset($per_BirthYear)) {
                                    $age = MiscUtils::formatAge($per_BirthMonth, $per_BirthDay, $per_BirthYear);
                                } else {
                                    $age = '';
                                }
                                $row[] = $age;
                            }

                            if (isset($input['PrintMembershipStatus'])) {
                                $row[] = $memberClass[$per_cls_ID];
                            }
                            if (isset($input['PrintFamilyRole'])) {
                                $row[] = $familyRoles[$per_fmr_ID];
                            }
                            if (isset($input['PrintGrade'])) {
                                $row[] = $person?->getGradeName() ?? '';
                            }
                            if (isset($input['PrintGender'])) {
                                $row[] = $person->getGenderName();
                            }
                        } else {
                            if (isset($input['BirthdayDate'])) {
                                $row[] = $fam_WeddingDate;
                            }

                            if (isset($input['Age'])) {
                                if (isset($fam_WeddingDate)) {
                                    $annivDate = getdate(strtotime($fam_WeddingDate));
                                    $age = $refDate['year'] - $annivDate['year'] - ($annivDate['mon'] > $refDate['mon'] || ($annivDate['mon'] === $refDate['mon'] && $annivDate['mday'] > $refDate['mday']));
                                } else {
                                    $age = '';
                                }
                                $row[] = (string) $age;
                            }
                        }

                        if ($bUsedCustomFields && ($sFormat === 'default')) {
                            $aCustomData = self::select('SELECT * FROM person_custom WHERE per_ID = ?', [(int) $per_ID])->fetch(PDO::FETCH_ASSOC);

                            if ($aCustomData !== false) {
                                foreach ($personFields as $aCustomField) {
                                    $custom_Field = '';
                                    $custom_Special = '';
                                    $type_ID = '';

                                    extract($aCustomField);
                                    if ($customFieldService->canView((int) $custom_FieldSec)) {
                                        if (isset($input["$custom_Field"])) {
                                            if ((int)$type_ID === 11) {
                                                $custom_Special = $sCountry;
                                            }
                                            $row[] = CustomFieldUtils::display($type_ID, trim($aCustomData[$custom_Field]), $custom_Special);
                                        }
                                    }
                                }
                            }

                            $aFamCustomData = self::select('SELECT * FROM family_custom WHERE fam_ID = ?', [(int) $per_fam_ID])->fetch(PDO::FETCH_ASSOC);

                            if ($aFamCustomData !== false) {
                                foreach ($familyFields as $aFamCustomField) {
                                    $fam_custom_Field = '';
                                    $fam_custom_Special = '';
                                    $type_ID = '';

                                    extract($aFamCustomField);
                                    if (isset($input["$fam_custom_Field"])) {
                                        if ((int)$type_ID === 11) {
                                            $fam_custom_Special = $sCountry;
                                        }
                                        $row[] = CustomFieldUtils::display($type_ID, trim($aFamCustomData[$fam_custom_Field]), $fam_custom_Special);
                                    }
                                }
                            }
                        }

                        if ($bUsedCustomFields && ($sFormat === 'rollup')) {
                            $aFamCustomData = self::select('SELECT * FROM family_custom WHERE fam_ID = ?', [(int) $per_fam_ID])->fetch(PDO::FETCH_ASSOC);

                            if ($aFamCustomData !== false) {
                                foreach ($familyFields as $aFamCustomField) {
                                    $fam_custom_Field = '';
                                    $fam_custom_Special = '';
                                    $type_ID = '';

                                    extract($aFamCustomField);
                                    if (isset($input["$fam_custom_Field"])) {
                                        if ((int)$type_ID === 11) {
                                            $fam_custom_Special = $sCountry;
                                        }
                                        $row[] = CustomFieldUtils::display($type_ID, trim($aFamCustomData[$fam_custom_Field]), $fam_custom_Special);
                                    }
                                }
                            }
                        }

                        // Add row to collection
                        $rows[] = $row;
                    }
                }
            }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @param list<int|string> $params */
    private static function select(string $sql, array $params = []): StatementInterface
    {
        $statement = Propel::getConnection()->prepare($sql);
        foreach ($params as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement;
    }

    private static function sortableName(string $lastName, string $firstNames): string
    {
        return $firstNames === '' ? $lastName : $lastName . ', ' . $firstNames;
    }

    private static function sortableFamilyName(Family $family): string
    {
        $adults = $family->getAdults();
        if (count($adults) === 1) {
            return self::sortableName((string) $adults[0]->getLastName(), (string) $adults[0]->getFirstName());
        }
        if (count($adults) === 2) {
            [$first, $second] = $adults;
            if ($first->getLastName() === $second->getLastName()) {
                return self::sortableName((string) $first->getLastName(), $first->getFirstName() . ' & ' . $second->getFirstName());
            }

            return self::sortableName((string) $first->getLastName(), (string) $first->getFirstName())
                . ' & ' . self::sortableName((string) $second->getLastName(), (string) $second->getFirstName());
        }

        return $family->getSalutation();
    }
}
