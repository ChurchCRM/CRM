<?php

namespace ChurchCRM\Reports;

class PdfCanvassBriefingReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->SetFont('Times', '', 10);
        $this->SetMargins(0, 0);
        $this->SetAutoPageBreak(false);
        $this->addPage();
    }
}
