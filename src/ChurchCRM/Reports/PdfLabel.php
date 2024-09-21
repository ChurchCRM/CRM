<?php

////////////////////////////////////////////////////
// PdfLabel
//
// Class to print labels in Avery or custom formats
//
//
// Copyright (C) 2003 Laurent PASSEBECQ (LPA)
// Based on code by Steve Dillon (steved@mad.scientist.com)
//
//-------------------------------------------------------------------
// VERSIONS :
// 1.0  : Initial release
// 1.1  : +    : Added unit in the constructor
//          + : Now Positions start @ (1,1).. then the first image @top-left of a page is (1,1)
//          + : Added in the description of a label :
//                font-size     : default char size (can be changed by calling setCharSize(xx);
//                paper-size    : Size of the paper for this sheet (thanks to Al Canton)
//                metric        : type of unit used in this description
//                              You can define your label properties in inches by setting metric to 'in'
//                              and printing in millimeter by setting unit to 'mm' in constructor.
//              Added some labels :
//                5160, 5161, 5162, 5163,5164 : thanks to Al Canton : acanton@adams-blake.com
//                8600                         : thanks to Kunal Walia : kunal@u.washington.edu
//          + : Added 3mm to the position of labels to avoid errors
////////////////////////////////////////////////////

/**
 * PdfLabel - PDF label editing.
 *
 * @author Laurent PASSEBECQ <lpasseb@numericable.fr>
 * @copyright 2003 Laurent PASSEBECQ
 **/

/*
*  InfoCentral modifications:
*     adjustment of label format parameters: 5160,
*
*/

namespace ChurchCRM\Reports;

class PdfLabel extends ChurchInfoReport
{
    // Private properties
    public $_Avery_Name = '';        // Name of format
    public $_Margin_Left = 0;        // Left margin of labels
    public $_Margin_Top = 0;        // Top margin of labels
    public $_X_Space = 0;        // Horizontal space between 2 labels
    public $_Y_Space = 0;        // Vertical space between 2 labels
    public $_X_Number = 0;        // Number of labels horizontally
    public $_Y_Number = 0;        // Number of labels vertically
    public $_Width = 0;        // Width of label
    public $_Height = 0;        // Height of label
    public $_Char_Size = 10;        // Character size
    public $_Line_Height = 10;        // Default line height
    public $_Metric = 'mm';        // Type of metric.. Will help to calculate good values
    public $_Metric_Doc = 'mm';        // Type of metric for the doc..

    public $_COUNTX = 1;
    public $_COUNTY = 1;

    // List of all Avery formats
    public $_Avery_Labels = [
        'Tractor' => ['name' => 'Tractor', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 6.5, 'marginTop' => 5, 'NX' => 1, 'NY' => 10, 'SpaceX' => 3.175, 'SpaceY' => 0, 'width' => 120, 'height' => 26.5, 'font-size' => 12],
        '5160'    => ['name' => '5160', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 4, 'marginTop' => 11.5, 'NX' => 3, 'NY' => 10, 'SpaceX' => 3.175, 'SpaceY' => 0, 'width' => 66.675, 'height' => 25.4, 'font-size' => 11],
        '5161'    => ['name' => '5161', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 1, 'marginTop' => 10.7, 'NX' => 2, 'NY' => 10, 'SpaceX' => 3.967, 'SpaceY' => 0, 'width' => 101.6, 'height' => 25.4, 'font-size' => 11],
        '5162'    => ['name' => '5162', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 4, 'marginTop' => 20.224, 'NX' => 2, 'NY' => 7, 'SpaceX' => 4.762, 'SpaceY' => 0, 'width' => 100.807, 'height' => 34, 'font-size' => 12],
        '5163'    => ['name' => '5163', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 1.762, 'marginTop' => 10.7, 'NX' => 2, 'NY' => 5, 'SpaceX' => 3.175, 'SpaceY' => 0, 'width' => 101.6, 'height' => 50.8, 'font-size' => 8],
        '5164'    => ['name' => '5164', 'paper-size' => 'letter', 'metric' => 'in', 'marginLeft' => 0.148, 'marginTop' => 0.5, 'NX' => 2, 'NY' => 3, 'SpaceX' => 0.2031, 'SpaceY' => 0, 'width' => 4.0, 'height' => 3.33, 'font-size' => 12],
        '8600'    => ['name' => '8600', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 7.1, 'marginTop' => 19, 'NX' => 3, 'NY' => 10, 'SpaceX' => 9.5, 'SpaceY' => 3.1, 'width' => 66.6, 'height' => 25.4, 'font-size' => 8],
        '74536'   => ['name' => '74536(name tags)', 'paper-size' => 'letter', 'metric' => 'mm', 'marginLeft' => 7.0, 'marginTop' => 25, 'NX' => 2, 'NY' => 3, 'SpaceX' => 0, 'SpaceY' => 0, 'width' => 102, 'height' => 76, 'font-size' => 18],
        'L7163'   => ['name' => 'L7163', 'paper-size' => 'A4', 'metric' => 'mm', 'marginLeft' => 5, 'marginTop' => 15, 'NX' => 2, 'NY' => 7, 'SpaceX' => 2.5, 'SpaceY' => 0, 'width' => 99.1, 'height' => 38.1, 'font-size' => 10],
    ];

    // convert units (in to mm, mm to in)
    // $src and $dest must be 'in' or 'mm'
    private function convertMetric($value, $src, $dest)
    {
        if ($src != $dest) {
            $tab['in'] = 39.37008;
            $tab['mm'] = 1000;

            return $value * $tab[$dest] / $tab[$src];
        } else {
            return $value;
        }
    }

    // Give the height for a char size given.
    private function getHeightChars($pt)
    {
        // Array matching character sizes and line heights
        $_Table_Hauteur_Chars = [6 => 2, 7 => 2.5, 8 => 3, 9 => 4, 10 => 5, 11 => 6, 12 => 7, 13 => 8, 14 => 7.5, 15 => 9, 16 => 8, 18 => 9];
        if (in_array($pt, array_keys($_Table_Hauteur_Chars))) {
            return $_Table_Hauteur_Chars[$pt];
        } else {
            return $pt * 0.352777778; // postscript points to mm
        }
    }

    public function setFormat(array $format): void
    {
        $this->_Metric = $format['metric'];
        $this->_Avery_Name = $format['name'];
        $this->_Margin_Left = $this->convertMetric($format['marginLeft'], $this->_Metric, $this->_Metric_Doc);
        $this->_Margin_Top = $this->convertMetric($format['marginTop'], $this->_Metric, $this->_Metric_Doc);
        $this->_X_Space = $this->convertMetric($format['SpaceX'], $this->_Metric, $this->_Metric_Doc);
        $this->_Y_Space = $this->convertMetric($format['SpaceY'], $this->_Metric, $this->_Metric_Doc);
        $this->_X_Number = $format['NX'];
        $this->_Y_Number = $format['NY'];
        $this->_Width = $this->convertMetric($format['width'], $this->_Metric, $this->_Metric_Doc);
        $this->_Height = $this->convertMetric($format['height'], $this->_Metric, $this->_Metric_Doc);
        $this->setCharSize($format['font-size']);
    }

    // Constructor
    public function __construct($format, $posX = 1, $posY = 1, $unit = 'mm')
    {
        if (is_array($format)) {
            // Custom format
            $Tformat = $format;
        } else {
            // Avery format
            $Tformat = $this->_Avery_Labels[$format];
        }

        parent::__construct('P', $unit, $Tformat['paper-size']);
        $this->SetMargins(0, 0);
        $this->SetAutoPageBreak(false);

        $this->_Metric_Doc = $unit;
        // Start at the given label position
        if ($posX > 0) {
            $posX--;
        } else {
            $posX = 0;
        }
        if ($posY > 0) {
            $posY--;
        } else {
            $posY = 0;
        }
        $this->_COUNTX = $posX;
        $this->_COUNTY = $posY;
        $this->setFormat($Tformat);
    }

    // Sets the character size
    // This changes the line height too
    public function setCharSize($pt): void
    {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->_Line_Height = $this->getHeightChars($pt);
            $this->SetFontSize($pt);
        }
    }

    // Print a label
    public function addPdfLabel($texte): void
    {
        // We are in a new page, then we must add a page
        if ($this->_COUNTX == 0 && $this->_COUNTY == 0) {
            $this->addPage();
        }

        $_PosX = $this->_Margin_Left + ($this->_COUNTX * ($this->_Width + $this->_X_Space));
        $_PosY = $this->_Margin_Top + ($this->_COUNTY * ($this->_Height + $this->_Y_Space));
        $this->SetXY($_PosX + 3, $_PosY + 3);
        $this->MultiCell($this->_Width, $this->_Line_Height, iconv('UTF-8', 'ISO-8859-1', $texte));
        $this->_COUNTY++;

        if ($this->_COUNTY == $this->_Y_Number) {
            // End of column reached, we start a new one
            $this->_COUNTX++;
            $this->_COUNTY = 0;
        }

        if ($this->_COUNTX == $this->_X_Number) {
            // Page full, we start a new one
            $this->_COUNTX = 0;
            $this->_COUNTY = 0;
        }
    }
}
