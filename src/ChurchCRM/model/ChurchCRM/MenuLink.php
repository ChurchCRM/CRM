<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\MenuLink as BaseMenuLink;
use ChurchCRM\Utils\InputUtils;

/**
 * Skeleton subclass for representing a row from the 'menu_links' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class MenuLink extends BaseMenuLink
{
    /**
     * Override setName to sanitize input and prevent XSS
     *
     * @param string|null $v New value
     * @return $this The current object (for fluent API support)
     */
    public function setName($v)
    {
        if ($v !== null) {
            // Sanitize to prevent XSS - remove all HTML tags
            $v = InputUtils::sanitizeText($v);
        }
        return parent::setName($v);
    }

    /**
     * Override setUri to sanitize input and prevent XSS
     *
     * @param string|null $v New value
     * @return $this The current object (for fluent API support)
     */
    public function setUri($v)
    {
        if ($v !== null) {
            // Sanitize to prevent XSS - remove all HTML tags
            $v = InputUtils::sanitizeText($v);
        }
        return parent::setUri($v);
    }
}

