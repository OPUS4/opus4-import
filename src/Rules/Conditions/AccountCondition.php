<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @copyright   Copyright (c) 2023, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import\Rules\Conditions;

use Opus\Import\ImportRuleConditionInterface;
use Zend_Auth; // TODO SECURITY depend on OPUS classes instead

class AccountCondition implements ImportRuleConditionInterface
{
    /** @var string|null */
    protected $expectedUser;

    /**
     * @param array|null $options
     */
    public function __construct($options = null)
    {
        $this->setOptions($options);
    }

    /**
     * @param array|null $options
     */
    public function setOptions($options)
    {
        if (isset($options['account'])) {
            $this->expectedUser = $options['account'];
        }
    }

    /**
     * @return bool
     */
    public function applies()
    {
        $currentUser = $this->getUserName();
        if ($this->expectedUser !== null && $currentUser !== null) {
            return strcasecmp($this->expectedUser, $currentUser) === 0;
        } else {
            return false;
        }
    }

    /**
     * @return string|null
     */
    protected function getUserName()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        if (isset($identity['username'])) {
            return $identity['username'];
        } else {
            return null;
        }
    }

    /**
     * @return string|null
     */
    public function getExpectedUser()
    {
        return $this->expectedUser;
    }

    /**
     * @param string|null $user
     */
    public function setExpectedUser($user)
    {
        $this->expectedUser = $user;
    }
}
