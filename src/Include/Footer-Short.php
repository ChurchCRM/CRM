<?php
/*******************************************************************************
 *
 *  filename    : Include/Footer-Short.php
 *  last change : 2002-04-22
 *  description : footer that appear on the bottom of all pages
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
  *
 ******************************************************************************/
 
        use ChurchCRM\dto\SystemURLs;

        ?>
					</td>
				</tr>
			</table>

		</td>
	</tr>
</table>

</body>

</html>
<?php

// Turn OFF output buffering
ob_end_flush();

// Reset the Global Message
$_SESSION['sGlobalMessage'] = '';

?>
