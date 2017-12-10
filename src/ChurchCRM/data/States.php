<?php
/**
 * Created by Hand.
 * User: Philippe
 * Date: 9/12/2017
 * Time: 12:00 PM.
 */

namespace ChurchCRM\data;

class States
{
    private $state;
    private $id_input;
    private $selected_State;
    
    private static $states = ['AL' => 'Alabama', 'AK' => 'Alaska', 
      'AZ' => 'Arkansas', 'AR' => 'Arkansas', 
      'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 
      'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
      'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
      'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 
      'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 
      'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 
      'MN' => 'Minnesota', 'MS' => 'Mississippi', 
      'MO' => 'Missouri', 'MT' => 'Montana', 
      'NE' => 'Nebraska', 'NV' => 'Nevada', 
      'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 
      'NM' => 'New Mexico', 'NY' => 'New York', 
      'NC' => 'North Carolina', 'ND' => 'North Dakota', 
      'OH' => 'Ohio', 'OK' => 'Oklahoma', 
      'OR' => 'Oregon', 'PA' => 'Pennsylvania', 
      'RI' => 'Rhode Island', 'SC' => 'South Carolina', 
      'SD' => 'South Dakota', 'TN' => 'Tennessee', 
      'TX' => 'Texas', 'UT' => 'Utah', 
      'VT' => 'Vermont', 'VA' => 'Virginia', 
      'WA' => 'Washington', 'WV' => 'West Virginia', 
      'WI' => 'Wisconsin', 'WY' => 'Wyoming', 
      'WY' => 'Wyoming', 
      '' => '--------------------',
      'BC' => 'British Columbia', 
      'AB' => 'Alberta', 'SK' => 'Saskatchewan', 
      'MB' => 'Manitoba', 'ON' => 'Ontario', 
      'QC' => 'Quebec', 'NB' => 'New Brunswick', 
      'NS' => 'Nova Scotia', 'PE' => 'Prince Edward Island', 
      'NF' => 'Newfoundland', 'YT' => 'Yukon', 
      'NT' => 'Northwest Territories', 'NU' => 'Nunavut Territory'];
    
    function __construct($selected_state="",$statename= "State") {  
      $this->selected_State = $selected_state;
      $this->state = $statename;
      $this->id_input = strtolower($this->state)."-input";
    }

    public static function getNames()
    {
        return array_values(self::$states);
    }
    
    public static function getKeys()
    {
        return array_keys(self::$states);
    }

    public static function getAll()
    {
        return self::$states;
    }
    
    public function getDropDown()
    {
      echo '<select name="'.$this->state.'" class="form-control select2" id="'.$this->id_input.'" style="width:100%">';      
      echo '<option value="">'.gettext('Unassigned').'</option>';
      echo '<option value="" disabled>--------------------</option>';
        foreach (self::$states as $keystate => $itemstate) {
          echo '<option value="'.$keystate.'"';
          if ($this->selected_State == $itemstate) {
            echo 'selected';
          } 
          echo '>'.gettext($itemstate);
        }
      echo '</select>';
    }
}