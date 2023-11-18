<?php

namespace ChurchCRM\Dashboard;

class ClassificationDashboardItem implements DashboardItemInterface
{
    public static function getDashboardItemName(): string
    {
        return 'ClassificationBreakdown';
    }

    public static function getDashboardItemValue(): array
    {
        $data = [];
        $sSQL = 'select lst_OptionName as Classification, count(*) as count
                from person_per INNER JOIN list_lst ON  per_cls_ID = lst_OptionID
                LEFT JOIN family_fam ON family_fam.fam_ID = person_per.per_fam_ID
                WHERE lst_ID =1 and family_fam.fam_DateDeactivated is null
                group by per_cls_ID, lst_OptionName order by count desc;';
        $rsClassification = RunQuery($sSQL);
        while ($row = mysqli_fetch_array($rsClassification)) {
            $data[$row['Classification']] = $row['count'];
        }

        return $data;
    }

    public static function shouldInclude(string $PageName): bool
    {
        return $PageName == 'PeopleDashboard.php'; // this ID would be found on all pages.
    }
}
