<?php
/*******************************************************************************
 *
 *  filename    : Include/Footer.php
 *  last change : 2002-04-22
 *  description : footer that appear on the bottom of all pages
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
?>

									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- global scripts -->
<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/demo-skin-changer.js"></script> <!-- only for demo -->

<script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js"></script>
<script type="text/javascript" src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
<script type="text/javascript" src="//code.jquery.com/ui/1.11.2/jquery-ui.min.js"></script>

<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/jquery.nanoscroller.min.js"></script>

<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/demo.js"></script> <!-- only for demo -->

<!-- this page specific scripts -->

<!-- theme scripts -->
<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/scripts.js"></script>
<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/pace.min.js"></script>

<!-- this page specific inline scripts -->

<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/SiteWidejQuery.js"></script>




</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();

// Reset the Global Message
$_SESSION['sGlobalMessage'] = "";

?>
