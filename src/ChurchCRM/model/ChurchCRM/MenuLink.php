<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\MenuLink as BaseMenuLink;

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
            // Strip HTML tags and decode entities to prevent XSS
            $v = strip_tags($v);
            $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
            // Strip HTML tags to prevent XSS in URI
            $v = strip_tags($v);
            $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return parent::setUri($v);
    }
}

