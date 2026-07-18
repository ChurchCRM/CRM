<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
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
            $this->link = Person::getViewURIForId((int) $this->value);
            $person = PersonQuery::create()->findPk($this->value);
            if ($person) {
                $this->formattedValue = $person->getFullName();
            } else {
                $this->formattedValue = gettext('Unexpected Person Id') . ' : ' . $this->value;
            }
        } elseif ($masterField->getTypeId() == 11) {
            //$custom_Special = $sPhoneCountry;
            $this->icon = 'fa fa-phone';
            // Sanitize to only phone-safe characters before building the tel: URI.
            // This protects against already-stored malicious values that pre-date
            // input-side sanitization (fix for GHSA-frj8-mpcx-44g9).
            $safePhone = preg_replace('/[^0-9+\-().\sxX#*]/', '', $this->value);
            $this->link = 'tel:' . $safePhone;
        } elseif ($masterField->getTypeId() == 12) {
            $customOption = ListOptionQuery::create()->filterById($masterField->getCustomSpecial())->filterByOptionId($this->value)->findOne();
            if ($customOption !== null) {
                $this->formattedValue = $customOption->getOptionName();
            } else {
                $this->formattedValue = $this->value . ' ( ' . gettext('Deleted') . ' )';
            }
        }
    }

    /**
     * Returns the display-ready value for this custom field.
     *
     * **Security contract (issue #9199 audit):**
     * For every supported type_ID (1–12) this method returns PLAIN, unescaped
     * text — never HTML markup:
     *
     *  type 1  (True/False)          → raw stored value ('true'/'false'/'')
     *  type 2  (Date)                → raw stored date string
     *  type 3  (Text 50 char)        → raw stored value
     *  type 4  (Text 100 char)       → raw stored value
     *  type 5  (Text long)           → raw stored value
     *  type 6  (Year)                → raw stored value
     *  type 7  (Season)              → raw stored value
     *  type 8  (Number)              → raw stored value
     *  type 9  (Person from Group)   → Person::getFullName() — plain text
     *  type 10 (Money)               → raw stored value
     *  type 11 (Phone)               → raw stored value; hyperlink via getLink()
     *  type 12 (Custom Drop-Down)    → ListOption::getOptionName() — plain text
     *
     * Links (person profile URL, tel: href) are exposed separately via
     * {@see getLink()} and MUST be escaped with InputUtils::escapeAttribute().
     *
     * **Callers rendering to an HTML/browser context MUST wrap this value
     * in InputUtils::escapeHTML() before output.** Do NOT escape inside this
     * method: the same value also feeds CSV and PDF contexts where HTML
     * encoding is wrong.
     *
     * @return string
     */
    public function getFormattedValue(): string
    {
        return $this->formattedValue;
    }

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

    public function getValue(): string
    {
        return $this->value;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Returns the human-readable label (field name) for this custom field.
     *
     * **Security contract (issue #9199 audit):**
     * Always returns the admin-configured field name as PLAIN, unescaped text.
     * Callers rendering to an HTML/browser context MUST wrap the return value
     * in InputUtils::escapeHTML() before output.
     *
     * @return string
     */
    public function getDisplayValue(): string
    {
        return $this->displayValue;
    }
}
