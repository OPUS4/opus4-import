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

use function yaml_parse_file;

/**
 * Configuration for CSV import/export.
 *
 * TODO support default mapping of configured columsn to processors
 * TODO allow configuring special processors for columns
 * TODO how to distinguish between single line and multi line processing
 */
class CsvConfig
{
    /** @var array */
    private $config;

    public function load()
    {
        $this->config = yaml_parse_file(__DIR__ . '/default.yaml');
    }

    public function getProcessors(): array
    {
        $count = 0;

        foreach ($this->config as $key => $value) {
            // TODO instantiate processors
            $count++;
        }

        $identifier = new CsvIdentifierProcessor();
        $identifier->setColumnNo(0);
        $identifier->setType('Old');

        $language = new CsvFieldProcessor();
        $language->setColumnNo(1);
        $language->setFieldName('Language');

        $type = new CsvFieldProcessor();
        $type->setColumnNo(2);
        $type->setFieldName('Type');

        $serverState = new CsvFieldProcessor();
        $serverState->setColumnNo(3);
        $serverState->setFieldName('ServerState');

        return [
            $identifier,
            $language,
            $type,
            $serverState,
        ];
    }
}
