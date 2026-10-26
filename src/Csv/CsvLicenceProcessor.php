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
use Opus\Common\DocumentInterface;
use Opus\Common\Licence;
use Opus\Common\Model\NotFoundException;

use function array_map;
use function explode;
use function is_numeric;

/**
 * Supports one or more licence IDs in a column.
 *
 * TODO support default licence (column is yes/no)
 * TODO support licence based on Name
 * TODO legacy implementation supports looking at different column (Enrichment 'format' to determine licence)
 *      This should be done using import rules after the parsing the CSV.
 */
class CsvLicenceProcessor extends DefaultColumnProcessor
{
    public function process(array $row, DocumentInterface $document): void
    {
        $columnValue = $row[$this->getColumnNo()];

        $values = array_map('trim', explode('||', $columnValue));

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $this->addLicence((int) $value, $document);
            }
        }
    }

    protected function addLicence(int $licenceId, DocumentInterface $doc): void
    {
        try {
            $licence = Licence::get($licenceId);
            $doc->addLicence($licence);
        } catch (NotFoundException $nfe) {
            throw new Exception('licence id ' . $licenceId . ' does not exist: ' . $nfe->getMessage());
        }
    }
}
