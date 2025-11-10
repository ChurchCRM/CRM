<?php

namespace ChurchCRM\Reports;

use ChurchCRM\Utils\LoggerUtils;

class PdfAttendance extends ChurchInfoReport
{
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->SetMargins(0, 0);

        $this->SetFont('Times', '', 14);
        $this->SetAutoPageBreak(false);
        $this->addPage();
    }

    public function drawAttendanceCalendar(
        $nameX,
        $yTop,
        array $aNames,
        $tTitle,
        $extraLines,
        $tFirstSunday,
        $tLastSunday,
        $tNoSchool1,
        $tNoSchool2,
        $tNoSchool3,
        $tNoSchool4,
        $tNoSchool5,
        $tNoSchool6,
        $tNoSchool7,
        $tNoSchool8,
        $rptHeader,
        array $imgs,
        $with_img
    ) {
        $logger = LoggerUtils::getAppLogger();
        $startMonthX = 60;
        $dayWid = 7;

        if ($with_img) {
            $yIncrement = 10;
        } else {
            $yIncrement = 6;
        }

        $yTitle = 20;
        $nameX = 10 + $yIncrement / 2;
        $numMembers = 0;
        $aNameCount = 0;

        $MaxLinesPerPage = -5 * $yIncrement + 66; // 36 lines for a yIncrement of 6, 16 lines for a yIncrement of 10, y=-5x+66

        $fontTitleTitle = 16;

        if ($with_img) {
            $fontTitleNormal = 11;
        } else {
            $fontTitleNormal = 10;
        }

        $aNoSchoolX = [];
        $noSchoolCnt = 0;

        // Determine how many pages will be includes in this report

        // First cull the input names array to remove duplicates, then extend the array to include the requested
        // number of blank lines
        $prevThisName = '';
        $aNameCount = 0;
        $NameList = [];
        $imgList = [];
        for ($row = 0; $row < count($aNames); $row++) {
            $person = $aNames[$row];
            $thisName = $person->getFullName();

            // Special handling for a person listed twice -- only show once in the Attendance Calendar
            // This happens when a child is listed in two different families (parents divorced and
            // both active in the church)
            if ($thisName !== $prevThisName) {
                $NameList[$aNameCount] = $thisName;
                $imgList[$aNameCount++] = $imgs[$row];
                $logger->debug("Adding {$thisName} to NameList at {$aNameCount}");
            }
            $prevThisName = $thisName;
        }

        // Add extra blank lines to the array
        for ($i = 0; $i < $extraLines; $i++) {
            $NameList[$aNameCount] = '   ';
            $imgList[$aNameCount++] = '';
        }

        $numMembers = count($NameList);
        $nPages = ceil($numMembers / $MaxLinesPerPage);
        $logger->debug("nPages = {$nPages}");

        $bottomY = 0;

        // Main loop which draws each page
        for ($p = 0; $p < $nPages; $p++) {
            // Paint the title section: class name and year on the top, then teachers/liaison
            if ($p > 0) {
                $this->addPage();
            }
            $this->SetFont('Times', 'B', $fontTitleTitle);
            $this->writeAt($nameX, $yTitle, $rptHeader);

            $this->SetLineWidth(0.5);
            $yMonths = $yTop;
            $yDays = $yTop + $yIncrement;
            $y = $yDays + $yIncrement;

            // Put title on the page
            $this->SetFont('Times', 'B', $fontTitleNormal);
            $this->writeAt($nameX, $yDays + 1, $tTitle);
            $this->SetFont('Times', '', $fontTitleNormal);

            // Calculate the starting and ending rows for the page
            $pRowStart = $p * $MaxLinesPerPage;
            $pRowEnd = min(($p + 1) * $MaxLinesPerPage, $numMembers);
            $logger->debug("pRowStart = {$pRowStart} and pRowEnd = {$pRowEnd}");

            // Write the names down the page and draw lines between
            $this->SetLineWidth(0.25);
            for ($row = $pRowStart; $row < $pRowEnd; $row++) {
                $this->writeAt($nameX, $y + (($with_img == true) ? 3 : 1), $NameList[$row]);

                if ($with_img == true) {
                    $this->Line($nameX - $yIncrement, $y, $nameX, $y);
                    $this->Line($nameX - $yIncrement, $y + $yIncrement, $nameX, $y + $yIncrement);
                    $this->Line($nameX - $yIncrement, $y, $nameX, $y);
                    $this->Line($nameX - $yIncrement, $y, $nameX - $yIncrement, $y + $yIncrement);

                    // We build the cross in case there's no photo
                    $this->Line($nameX - $yIncrement, $y + $yIncrement, $nameX, $y);
                    $this->Line($nameX - $yIncrement, $y, $nameX, $y + $yIncrement);

                    if ($NameList[$row] != '   ' && strlen($imgList[$row]) > 5 && is_file($imgList[$row])) {
                        [$width, $height] = getimagesize($imgList[$row]);
                        $factor = $yIncrement / $height;
                        $nw = $width * $factor;
                        $nh = $yIncrement;

                        // Detect image type from file extension (now supports PNG)
                        $imageType = strtoupper(pathinfo($imgList[$row], PATHINFO_EXTENSION));
                        if (!in_array($imageType, ['JPG', 'JPEG', 'PNG'])) {
                            $imageType = 'PNG'; // Default to PNG
                        }
                        
                        $this->Image($imgList[$row], $nameX - $nw, $y, $nw, $nh, $imageType);
                    }
                }

                $y += $yIncrement;
            }

            // Write a totals text at the bottom
            $this->SetFont('Times', 'B', $fontTitleNormal);
            $this->writeAt($nameX, $y + 1, gettext('Totals'));
            $this->SetFont('Times', '', $fontTitleNormal);

            $bottomY = $y + $yIncrement;

            // Paint the calendar grid
            $dayCounter = 0;
            $dayX = $startMonthX;
            $monthX = $startMonthX;
            $noSchoolCnt = 0;
            $heavyVerticalXCnt = 0;
            $lightVerticalXCnt = 0;
            $aLightVerticalX = [];

            $tWhichSunday = $tFirstSunday;
            $dWhichSunday = strtotime($tWhichSunday);

            $dWhichMonthDate = $dWhichSunday;
            $whichMonth = date('n', $dWhichMonthDate);

            $bInProgressFlag = true;
            while ($bInProgressFlag) {
                $dayListX[$dayCounter] = $dayX;

                $dayListNum[$dayCounter] = date('d', $dWhichSunday);

                if ($tWhichSunday == $tNoSchool1) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool2) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool3) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool4) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool5) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool6) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool7) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }
                if ($tWhichSunday == $tNoSchool8) {
                    $aNoSchoolX[$noSchoolCnt++] = $dayX;
                }

                // Finish the previous month
                if (date('n', $dWhichSunday) != $whichMonth) {
                    $this->writeAt($monthX, $yMonths + 1, mb_substr(gettext(date('F', $dWhichMonthDate)), 0, 3));
                    $aHeavyVerticalX[$heavyVerticalXCnt++] = $monthX;
                    $whichMonth = date('n', $dWhichSunday);
                    $dWhichMonthDate = $dWhichSunday;
                    $monthX = $dayX;
                } else {
                    $aLightVerticalX[$lightVerticalXCnt++] = $dayX;
                }
                $dayX += $dayWid;
                $dayCounter++;

                if (strtotime($tWhichSunday) >= strtotime($tLastSunday)) {
                    // Done - set flag for end of while loop
                    $bInProgressFlag = false;
                }

                // Increment the date by one week
                $sundayDay = date('d', $dWhichSunday);
                $sundayMonth = date('m', $dWhichSunday);
                $sundayYear = date('Y', $dWhichSunday);
                $dWhichSunday = mktime(0, 0, 0, $sundayMonth, $sundayDay + 7, $sundayYear);
                $tWhichSunday = date('Y-m-d', $dWhichSunday);
            }
            $aHeavyVerticalX[$heavyVerticalXCnt++] = $monthX;
            $this->writeAt($monthX, $yMonths + 1, substr(gettext(date('F', $dWhichMonthDate)), 0, 3));

            $rightEdgeX = $dayX;

            // Draw vertical lines now that we know how far down the list goes

            // Draw the left-most vertical line heavy, through the month row
            $this->SetLineWidth(0.5);
            $this->Line($nameX, $yMonths, $nameX, $bottomY);

            // Draw the left-most line between the people and the calendar
            $lineTopY = $yMonths;
            $this->Line($startMonthX, $lineTopY, $startMonthX, $bottomY);

            // Draw the vertical lines in the grid based on X coords stored above
            $this->SetLineWidth(0.5);
            for ($i = 0; $i < $heavyVerticalXCnt; $i++) {
                $this->Line($aHeavyVerticalX[$i], $lineTopY, $aHeavyVerticalX[$i], $bottomY);
            }

            $lineTopY = $yDays;
            $this->SetLineWidth(0.25);
            for ($i = 0; $i < $lightVerticalXCnt; $i++) {
                $this->Line($aLightVerticalX[$i], $lineTopY, $aLightVerticalX[$i], $bottomY);
            }

            // Draw the right-most vertical line heavy, through the month row
            $this->SetLineWidth(0.5);
            $this->Line($dayX, $yMonths, $dayX, $bottomY);

            // Fill the no-school days
            $this->SetFillColor(200, 200, 200);
            $this->SetLineWidth(0.25);
            for ($i = 0; $i < count($aNoSchoolX); $i++) {
                $this->Rect($aNoSchoolX[$i], $yDays, $dayWid, $bottomY - $yDays, 'FD');
            }

            for ($i = 0; $i < $dayCounter; $i++) {
                $this->writeAt($dayListX[$i], $yDays + 1, $dayListNum[$i]);
            }

            // Draw heavy lines to delimit the Months and totals
            $this->SetLineWidth(0.5);
            $this->Line($nameX, $yMonths, $rightEdgeX, $yMonths);
            $this->Line($nameX, $yMonths + $yIncrement, $rightEdgeX, $yMonths + $yIncrement);
            $this->Line($nameX, $yMonths + 2 * $yIncrement, $rightEdgeX, $yMonths + 2 * $yIncrement);
            $yBottom = $yMonths + (($numMembers + $extraLines + 2) * $yIncrement);
            $this->Line($nameX, $yBottom, $rightEdgeX, $yBottom);
            $this->Line($nameX, $yBottom + $yIncrement, $rightEdgeX, $yBottom + $yIncrement);

            // Add in horizontal lines between names
            $y = $yTop;
            for ($s = $pRowStart; $s < $pRowEnd + 4; $s++) {
                $this->Line($nameX, $y, $rightEdgeX, $y);
                $y += $yIncrement;
            }
        }

        return $bottomY;
    }
}
