<?php

namespace ChurchCRM\Reports;

class PdfCertificatesReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(15, 25);

        $this->SetAutoPageBreak(true, 25);
    }

    public function addPage($orientation = '', $format = '', $rotation = 0): void
    {
        global $fr_title, $fr_description, $curY;

        parent::addPage($orientation, $format, $rotation);

        $this->SetFont('Times', 'B', 16);
        $this->Write(8, $fr_title . "\n");
        $curY += 8;
        $this->Write(8, $fr_description . "\n\n");
        $curY += 8;
        $this->SetFont('Times', 'B', 36);
        $this->Write(8, gettext('Certificate of Ownership') . "\n\n");
        $curY += 8;
        $this->SetFont('Times', '', 10);
    }
}
