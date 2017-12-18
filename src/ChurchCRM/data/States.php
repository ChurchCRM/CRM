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
    private static $states = [
      'AL' => 'Alabama', 'AK' => 'Alaska', 
      'AZ' => 'Arizona', 'AR' => 'Arkansas', 
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
}