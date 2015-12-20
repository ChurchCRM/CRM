<?php

class PersonService
{

    function photo($id)
    {
        $sSQL = 'SELECT per_ID, per_FirstName, per_LastName, per_Gender, per_Email FROM person_per WHERE per_ID =' . $id;
        $person = RunQuery($sSQL);
        extract(mysql_fetch_array($person));

        if ($per_ID != "") {

            $photo = "";
            if ($per_Email != "") {
                $photo = $this->getGravatar($per_Email);
            }

            if ($photo == "") {
                $photo = $this->getLocalPhoto($per_ID, $per_Gender, "Child");
            }

            echo $photo;
        } else {
            echo "{ error: person not found for id ".$id. "}";
        }

    }

    private
    function getLocalPhoto($personId, $gender, $famRole)
    {
        $validextensions = array("jpeg", "jpg", "png");
        $hasFile = false;
        while (list(, $ext) = each($validextensions)) {
            $photoFile = "../Images/Person/thumbnails/" . $personId . "." . $ext;
            if (file_exists($photoFile)) {
                $hasFile = true;
                $photoFile = "Images/Person/thumbnails/" . $personId . "." . $ext;
                break;
            }
        }

        if (!$hasFile) {
            if ($gender == 1 && $famRole == "Child") {
                $photoFile = "img/kid_boy-128.png";
            } else if ($gender == 2 && $famRole != "Child") {
                $photoFile = "img/woman-128.png";
            } else if ($gender == 2 && $famRole == "Child") {
                $photoFile = "img/kid_girl-128.png";
            } else {
                $photoFile = "img/man-128.png";
            }
        }
        return $photoFile;
    }

    private
    function getGravatar($email, $s = 60, $d = '404', $r = 'g', $img = false, $atts = array())
    {
        $url = 'http://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";

        $headers = @get_headers($url);
        if (strpos($headers[0], '404') === false) {
            return $url;
        } else {
            return "";
        }
    }

    function search($searchTerm)
    {
        $fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM person_per WHERE per_FirstName LIKE \'%' . $searchTerm . '%\' OR per_LastName LIKE \'%' . $searchTerm . '%\' OR per_Email LIKE \'%' . $searchTerm . '%\' OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%' . $searchTerm . '%\' LIMIT 15';
        $result = mysql_query($fetch);

        $return = array();
        while ($row = mysql_fetch_array($result)) {
            $values['id'] = $row['per_ID'];
            $values['famID'] = $row['per_fam_ID'];
            $values['per_FirstName'] = $row['per_FirstName'];
            $values['per_LastName'] = $row['per_LastName'];
            $values['value'] = $row['per_FirstName'] . " " . $row['per_LastName'];

            array_push($return, $values);
        }

        echo '{"persons": ' . json_encode($return) . '}';
    }

}

?>