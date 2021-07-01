<?php

namespace ChurchCRM\dto;

use ChurchCRM\PersonQuery;
use ChurchCRM\ListOptionQuery;

Class PeopleCustomField {

    private $name;
    private $value;
    private $formattedValue;
    private $link;
    private $icon = "fa fa-tag";
    private $displayValue;

    /**
     * PeopleCustomField constructor.
     * @param $name
     */
    public function __construct($masterField, $value)
    {
        $this->value = trim($value);
        $this->formattedValue = $this->value;
        $this->displayValue = $masterField->getName();
        $masterField->getName();

        if ($masterField->getTypeId() == 9) {
            $this->icon = "fa fa-user";
            $this->link = SystemURLs::getRootPath() .'/PersonView.php?PersonID=' . $this->value;
            $person = PersonQuery::create()->findPk($this->value);
            if ($person) {
                $this->formattedValue = $person->getFullName();
            } else {
                $this->formattedValue = gettext("Unexpected Person Id"). " : " . $this->value;
            }
        } elseif ($masterField->getTypeId()  == 11) {
            //$custom_Special = $sPhoneCountry;
            $this->icon = "fa fa-phone";
            $this->link = "tel:".$this->value;
        } elseif ($masterField->getTypeId() == 12) {
            $customOption = ListOptionQuery::create()->filterById($masterField->getCustomSpecial())->filterByOptionId($this->value)->findOne();
            if ($customOption != null) {
                $this->formattedValue =  $customOption->getOptionName();
            } else {
                $this->formattedValue = $this->value . " ( ". gettext("Deleted") ." )";
            }
        }
    }

    /**
     * @return mixed
     */
    public function getFormattedValue()
    {
        return $this->formattedValue;
    }

    /**
     * @return mixed
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return mixed
     */
    public function getDisplayValue()
    {
        return $this->displayValue;
    }



}
