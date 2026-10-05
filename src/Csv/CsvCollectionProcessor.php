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
 * @copyright   Copyright (c) 2026, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import\Csv;

use Exception;
use Opus\Common\Collection;
use Opus\Common\CollectionRole;
use Opus\Common\CollectionRoleInterface;
use Opus\Common\DocumentInterface;
use Opus\Common\Model\NotFoundException;

use function array_map;
use function count;
use function explode;
use function is_numeric;

/**
 * TODO handle missing collection gracefully (exception optional)
 * TODO support using ROLENAME:NUMBER
 * TODO support quick option ROLE + NUMBER VALUES
 * TODO avoid multiple instantiations of Collection objects
 */
class CsvCollectionProcessor extends DefaultColumnProcessor
{
    /** @var ?CollectionRoleInterface Fixed collection role */
    private $role;

    public function init(?array $columnConfig = null): void
    {
        $this->setModelType(Collection::getModelType());
    }

    /**
     * Shortcut option could be role name or oainame.
     */
    public function setShortcutOption(?string $shortcutOption): self
    {
        if (null !== $shortcutOption) {
            $role = CollectionRole::fetchByName($shortcutOption);
            $this->setRole($role);
        }
        return $this;
    }

    public function process(array $row, DocumentInterface $document): void
    {
        $columnValue = $row[$this->getColumnNo()];
        $values      = array_map('trim', explode('||', $columnValue));

        foreach ($values as $value) {
            if (is_numeric($value)) {
                // Collection ID
                $this->addCollection((int) $value, $document);
            } else {
                // TODO error handling (unknown role, col)
                $role = $this->getRole();
                if (null === $role) {
                    [$roleName, $colName] = array_map('trim', explode(':', $value));
                    $role                 = CollectionRole::fetchByName($roleName);
                    $collections          = Collection::fetchCollectionsByRoleName($role->getId(), $colName);
                    if (count($collections) > 0) {
                        $this->addCollection($collections[0]->getId(), $document);
                    }
                    // TODO parse ROLEOAINAME:COLNAME|COLOAINAME
                } else {
                    // TODO parse COLOAINAME
                    $collections = Collection::fetchCollectionsByRoleName($role->getId(), $value);
                    if (count($collections) > 0) {
                        $this->addCollection($collections[0]->getId(), $document);
                    }
                }
            }
        }
    }

    protected function addCollection(int $colId, DocumentInterface $doc): void
    {
        try {
            $coll = Collection::get($colId);
            $doc->addCollection($coll);
        } catch (NotFoundException $nfe) {
            throw new Exception('collection id ' . $colId . ' does not exist');
        }
    }

    public function setRole(?CollectionRoleInterface $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getRole(): ?CollectionRoleInterface
    {
        return $this->role;
    }
}
