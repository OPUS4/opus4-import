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
use Opus\Common\Date;
use Opus\Common\DocumentInterface;

use function in_array;
use function preg_match;
use function strtolower;
use function ucfirst;

/**
 * Process date and year values.
 *
 * Columns are not corresponding to actual fields for Document or a Date
 * object.
 *
 * If a column is used for a specific date or year field, the basic
 * DefaultColumnProcessor can handle it.
 *
 * - CompletedDate
 * - CompletedYear
 * - PublishedDate
 * - PublishedYear
 * - EmbargoDate
 *
 * This class has no support for multiple values.
 */
class CsvYearProcessor extends DefaultMultiColumnProcessor
{
    protected function init(?array $columnConfig = null): void
    {
        $this->setModelType(Date::getModelType());
        $this->setColumns(['Type', 'Value']);
    }

    public function process(array $row, DocumentInterface $document): void
    {
        $type  = $row[$this->getFieldColumn('Type')];
        $value = $row[$this->getFieldColumn('Value')];

        if (empty($type)) {
            throw new Exception('type of year value not specified');
        }

        $type = strtolower($type);

        if (! preg_match("/^[0-9]{4}$/", $value)) {
            throw new Exception("Value {$value} is not a valid year");
        }

        if (in_array($type, ['published', 'completed'])) {
            $method = 'set' . ucfirst($type) . 'Year'; // TODO could be 'Date'
            $document->$method($value);
        } else {
            throw new Exception('Invalid type of year field');
        }
    }

    public function getModelFields(): array
    {
        return ['Type', 'Value'];
    }
}
