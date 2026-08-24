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

use function count;

/**
 * TODO there probably should be an Interface as well
 */
abstract class AbstractMultiColumnProcessor extends AbstractColumnProcessor
{
    /** @var array */
    private $columnOffset = [];

    public function __construct(?array $columnConfig, ?string $shortcutOption)
    {
        if (null !== $columnConfig && isset($columnConfig['columns'])) {
            $this->setColumns($columnConfig['columns']);
        }

        if (null !== $shortcutOption) {
            $this->setShortcutOption($shortcutOption);
        }
    }

    /**
     * Can be overwritten to set a model field from the header.
     *
     * Example header or configuration entry:
     *   Identifier-OldId
     *
     * 'OldId' would be the shortcut option and could be used to set
     * the Type field of Identifier.
     */
    public function setShortcutOption(?string $shortcutOption): self
    {
        return $this;
    }

    public function setColumns(?array $columns): self
    {
        if (null === $columns) {
            $this->columnOffset = [];
            return $this;
        }

        $offset = 0;
        foreach ($columns as $fieldName => $fieldConfig) {
            // TODO check if field exists
            $this->columnOffset[$fieldName] = $offset;
            $offset++;
        }
        return $this;
    }

    public function getFieldColumn(string $fieldName): int
    {
        return $this->getColumnNo() + $this->columnOffset[$fieldName];
    }

    public function getColumnCount(): int
    {
        $columnCount = count($this->columnOffset);
        return $columnCount > 0 ? $columnCount : 1;
    }
}
