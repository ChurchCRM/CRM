<?php

// Utility functions used to process MICR data

class MICRReader {

   var $CHECKNO_FIRST = 1;
   var $ROUTE_FIRST = 2;
   var $NOT_RECOGNIZED = 3;

   function IdentifyFormat ($micr)
   {
      $firstChar = substr ($micr, 0, 1);
      if ($firstChar == "o") {
         return ($this->CHECKNO_FIRST);
      } else if ($firstChar == "t") {
         $firstSmallO = strpos ($micr, "o");
         $len = strlen ($micr);
         if ($len - $firstSmallO > 12)
            return ($this->NOT_RECOGNIZED);
         else
            return ($this->ROUTE_FIRST);
      }
   }

	function FindRoute ($micr)
	{
		$routeAndAccount = $this->FindRouteAndAccount ($micr);
		$breakChar = strpos ($routeAndAccount, "t", 1);
		return (substr ($micr, 1, $breakChar - 1));
	}

	function FindAccount ($micr)
	{
		$routeAndAccount = $this->FindRouteAndAccount ($micr);
		$breakChar = strpos ($routeAndAccount, "t", 1);
		return (substr ($routeAndAccount, $breakChar + 1, strlen ($micr) - $breakChar));
	}

   function FindRouteAndAccount ($micr)
   {
      $formatID = $this->IdentifyFormat ($micr);
      if ($formatID == $this->CHECKNO_FIRST) {
         $firstSmallT = strpos ($micr, "t");
         return (substr ($micr, $firstSmallT, strlen ($micr) - $firstSmallT));
      } else if ($formatID == $this->ROUTE_FIRST) {
         $firstSmallO = strpos ($micr, "o");
         return (substr ($micr, 0, $firstSmallO));
      } else {
         return ("");
      }
   }

   function FindCheckNo ($micr)
   {
      $formatID = $this->IdentifyFormat ($micr);
      if ($formatID == $this->CHECKNO_FIRST) {
         $micrWithoutFirstO = substr ($micr, 1, strlen ($micr) - 1);
         $secondSmallO = strpos ($micrWithoutFirstO, "o");
         return (substr ($micrWithoutFirstO, 0, $secondSmallO));
      } else if ($formatID == $this->ROUTE_FIRST) {
         $firstSmallO = strpos ($micr, "o");
         return (substr ($micr, $firstSmallO + 1, strlen ($micr) - $firstSmallO - 1));
      } else {
         return ("");
      }
   }
}
?>
