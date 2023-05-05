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

namespace Opus\Import\Rules\Options;

use Opus\Common\Collection;
use Opus\Common\CollectionInterface;
use Opus\Common\CollectionRole;
use Opus\Common\Model\NotFoundException;

use function count;
use function is_numeric;

/**
 * Supports different ways of configuring a collection.
 *
 * - `id` of collection
 * - `roleName` and `number` of collection
 * - `roleOaiName` and 'number` or `name` of collection
 *
 * TODO ignore case of option keys
 */
class CollectionOption
{
    /** @var string[] */
    private $options;

    /** @var CollectionInterface */
    private $collection;

    /**
     * @param string[]|null $options
     */
    public function __construct($options = null)
    {
        $this->setOptions($options);
    }

    /**
     * @param string[]|null $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @return CollectionInterface|null
     */
    public function getCollection()
    {
        if ($this->collection === null) {
            $this->collection = $this->loadCollection();
        }
        return $this->collection;
    }

    /**
     * @return CollectionInterface|null
     * @throws NotFoundException
     */
    protected function loadCollection()
    {
        // if collection ID is configured, load collection directly
        if (isset($this->options['id']) && is_numeric($this->options['id'])) {
            return Collection::get($this->options['id']);
        }

        $role = null;

        // try to get collection role ('roleName' or 'roleOaiName')
        if (isset($this->options['roleName'])) {
            $role = CollectionRole::fetchByName($this->options['roleName']);
        } elseif (isset($this->options['roleOaiName'])) {
            $role = CollectionRole::fetchByOaiName($this->options['roleOaiName']);
        }

        if ($role === null) {
            // TODO log, throw exception?
            return null;
        }

        $collections = [];

        // try to get collection ('number' or 'name')
        if (isset($this->options['number'])) {
            $collections = Collection::fetchCollectionsByRoleNumber($role->getId(), $this->options['number']);
        } elseif (isset($this->options['name'])) {
            $collections = Collection::fetchCollectionsByRoleName($role->getId(), $this->options['name']);
        }

        if (count($collections) > 0) {
            return $collections[0];
        }

        return null;
    }
}
