<?php

namespace ChurchCRM\Reports;
use ChurchCRM\Reports\PDF_Label;

class PDF_NewsletterLabels extends PDF_Label {

	// Constructor
	function PDF_NewsletterLabels($sLabelFormat) {
   	parent::__construct ($sLabelFormat);
      
	}
}

?>