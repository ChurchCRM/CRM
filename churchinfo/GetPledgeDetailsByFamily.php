<?php 

$sSQL = "SELECT plg_fundID, plg_amount from pledge_plg where plg_famID=\"" . $iFamily . "\" AND plg_PledgeOrPayment=\"Pledge\" AND plg_FYID=\"" . $iFYID . "\";";
//echo "sSQL: " . $sSQL . "\n";
		$rsPledge = RunQuery($sSQL);
		$totalPledgeAmount = 0;
		while ($row = mysql_fetch_array($rsPledge)) {
			$fundID = $row["plg_fundID"];
			$plgAmount = $row["plg_amount"];
			$fundID2Pledge[$fundID] = $plgAmount;
			$totalPledgeAmount = $totalPledgeAmount + $plgAmount;
		} // end while
		if ($fundID2Pledge) {
			// division rounding can cause total of calculations to not equal total.  Keep track of running total, and asssign any rounding error to 'default' fund
			$calcTotal = 0;
			$calcOtherFunds = 0;
			foreach ($fundID2Pledge as $fundID => $plgAmount) {
				$calcAmount = round($iTotalAmount * ($plgAmount / $totalPledgeAmount), 2);

				$nAmount[$fundID] = number_format($calcAmount, 2, ".", "");
				if ($fundID <> $defaultFundID) {
					$calcOtherFunds = $calcOtherFunds + $calcAmount;
				}

				$calcTotal += $calcAmount;
			}
			if ($calcTotal <> $iTotalAmount) {
				$nAmount[$defaultFundID] = number_format($iTotalAmount - $calcOtherFunds, 2, ".", "");
			}
		} else {
			$nAmount[$defaultFundID] = number_format($iTotalAmount, 2, ".", "");
		}
?>