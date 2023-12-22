<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;

class PeopleCustomField
{
    private $name;
    private string $value;
    private $formattedValue;
    private ?string $link = null;
    private string $icon = 'fa fa-tag';
    private $displayValue;

    /**
     * PeopleCustomField constructor.
     *
     * @param $name
     */
    public function __construct($masterField, $value)
    {
        $this->value = trim($value);
        $this->formattedValue = $this->value;
        $this->displayValue = $masterField->getName();
        $masterField->getName();

        if ($masterField->getTypeId() == 9) {
            $this->icon = 'fa fa-user';
            $this->link = SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $this->value;
            $person = PersonQuery::create()->findPk($this->value);
            if ($person) {
                $this->formattedValue = $person->getFullName();
            } else {
                $this->formattedValue = gettext('Unexpected Person Id') . ' : ' . $this->value;
            }
        } elseif ($masterField->getTypeId() == 11) {
            //$custom_Special = $sPhoneCountry;
            $this->icon = 'fa fa-phone';
            $this->link = 'tel:' . $this->value;
        } elseif ($masterField->getTypeId() == 12) {
            $customOption = ListOptionQuery::create()->filterById($masterField->getCustomSpecial())->filterByOptionId($this->value)->findOne();
            if ($customOption != null) {
                $this->formattedValue = $customOption->getOptionName();
            } else {
                $this->formattedValue = $this->value . ' ( ' . gettext('Deleted') . ' )';
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
    public function getLink(): ?string
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
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function getIcon(): string
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
