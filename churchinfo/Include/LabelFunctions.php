<?php
/*******************************************************************************
*
*  filename    : /Include/LabelFunctions.php
*  website     : http://www.churchdb.org
*
*  Contributors:
*  2006 Ed Davis
*
*
*  Copyright 2006 Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

// This file contains functions specifically related to address labels

function FontSelect($fieldname)
{
    global $sFPDF_PATH;
    
    $d = dir($sFPDF_PATH."/font/");
    $fontnames = array();
    $family = " ";
    while (false !== ($entry = $d->read())) 
    {
        $len = strlen($entry);
        if($len > 3){
            if(strtoupper(substr($entry, $len-3))=='PHP')
            { // php files only
                $filename = substr($entry, 0, $len-4);
                if(substr($filename, 0, strlen($family)) != $family)  
                    $family = $filename;
                $fontnames[] = FilenameToFontname($filename, $family);                    
            }
        }
    }
    
	echo "<tr>";
	echo "<td class=\"LabelColumn\">" . gettext("Font:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<select name=\"$fieldname\">";
    foreach($fontnames as $n)
    {
        $sel = "";
        if($_COOKIE[$fieldname] == $n) 
            $sel = " selected";
		echo "<option value=\"".$n."\"".$sel.">".gettext("$n")."</option>";
	}
	echo "</select>";
	echo "</td>";
	echo "</tr>";
}

function FontSizeSelect($fieldname)
{
    $sizes = array("default", 6, 7, 8, 9, 10, 11, 12, 14, 16, 18);
	echo "<tr>";
	echo "<td class=\"LabelColumn\"> " . gettext("Font Size:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<select name=\"$fieldname\">";
    foreach($sizes as $s)
    {
        $sel = "";
        if($_COOKIE[$fieldname] == $s) 
            $sel = " selected";
		echo "<option value=\"".$s."\"".$sel.">".gettext("$s")."</option>";
    }
	echo "</select>";
	echo "</td>";
	echo "</tr>";
}

function LabelSelect($fieldname)
{
    $labels = array("Tractor", "5160", "5161", "5162", "5163", "5164", "8600", "L7163");
	echo "<tr>";
	echo "<td class=\"LabelColumn\">" . gettext("Label Type:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<select name=\"$fieldname\">";
    foreach($labels as $l)
    {
        $sel = "";
        if($_COOKIE[$fieldname] == $l) 
            $sel = " selected";
        echo "<option value=\"".$l."\"".$sel.">".gettext("$l")."</option>";
    }
	echo "</select>";
	echo "</td>";
	echo "</tr>";
}

function LabelGroupSelect($fieldname)
{
	echo "<tr><td class=\"LabelColumn\">" . gettext("Label Grouping") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"indiv\" ";

	if ($_COOKIE[$fieldname] != "fam")
		echo "checked";
	
	echo ">" . gettext("All Individuals") . "<br>";
	echo "<input name=\"$fieldname\" type=\"radio\" value=\"fam\" ";

	if ($_COOKIE[$fieldname] == "fam")
		echo "checked";

	echo ">" . gettext("Grouped by Family") . "<br></td></tr>";
}


function ToParentsOfCheckBox($fieldname)
{
	echo "<tr><td class=\"LabelColumn\">" . gettext("To the parents of:") . "</td>";
	echo "<td class=\"TextColumn\">";
	echo "<input name=\"$fieldname\" type=\"checkbox\" ";
	echo "id=\"ToParent\" value=\"1\" ";

	if ($_COOKIE[$fieldname])
		echo "checked";
	
	echo "><br></td></tr>";
}

function StartRowStartColumn()
{
	echo '	
	<tr>
	<td class="LabelColumn">'. gettext("Start Row:") . '
	</td>
	<td class="TextColumn">
	<input type="text" name="startrow" id="startrow" maxlength="2" size="3" value="1">
	</td>
	</tr>
	<tr>
	<td class="LabelColumn">'. gettext("Start Column:") . '
	</td>
	<td class="TextColumn">
	<input type="text" name="startcol" id="startcol" maxlength="2" size="3" value="1">
	</td>
	</tr>';
}

function IgnoreIncompleteAddresses()
{
	echo '
	<tr>
	<td class="LabelColumn">' . gettext("Ignore Incomplete<br>Addresses:") . '
	</td>
	<td class="TextColumn">
	<input type="checkbox" name="onlyfull" id="onlyfull" value="1" checked>
	</td>
	</tr>';
}

function LabelFileType()
{
	echo '
	<tr>
		<td class="LabelColumn">' . gettext("File Type:") . '
		</td>
		<td class="TextColumn">
			<select name="filetype">
				<option value="PDF">PDF</option>
				<option value="CSV">CSV</option>
			</select>
		</td>
	</tr>';
}

?>
