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

use Opus\Common\Document;

use function array_key_exists;
use function count;
use function explode;
use function yaml_parse_file;

/**
 * Configuration for CSV import/export.
 *
 * TODO support default mapping of configured columsn to processors
 * TODO allow configuring special processors for columns
 * TODO how to distinguish between single line and multi line processing
 *
 * TODO provide total configured rows
 * TODO increase row number even if column config is ignored
 * TODO create a CsvColumnConfig class with common code?
 */
class CsvConfigYaml implements CsvConfigInterface
{
    /** @var array */
    private $config;

    /** @var CsvFieldProcessor[] */
    private $processors;

    /** @var string[] TODO mapping would also be needed for CsvConfigHeader */
    private $mapping = [
        'default'    => CsvFieldProcessor::class,
        'Collection' => CsvCollectionProcessor::class,
        'Identifier' => CsvIdentifierProcessor::class,
    ];

    public function load()
    {
        $this->config = yaml_parse_file(__DIR__ . '/default.yaml');
    }

    public function getProcessors(): array
    {
        $columnNo = 0;

        $processors = [];

        foreach ($this->config as $columnName => $columnConfig) {
            $columnInc = 1;

            [$fieldName, $shortcutOption] = explode('-', $columnName, 2);

            $processor = null;

            if (array_key_exists($fieldName, $this->mapping)) {
                $fieldProcessorClass = $this->mapping[$fieldName];
            } else {
                $fieldProcessorClass = $this->mapping['default'];
                // TODO check if field exists and only use for simple fields
                $field = Document::new()->getField($fieldName);
                if ($field === null || $field->getValueModelClass() !== null) {
                    $fieldProcessorClass = null;
                }
            }

            if ($fieldProcessorClass !== null) {
                $processor = new $fieldProcessorClass($columnConfig, $shortcutOption);
                $processor->setColumnNo($columnNo);

                switch ($fieldProcessorClass) {
                    case CsvFieldProcessor::class:
                        $processor->setFieldName($fieldName);
                        break;
                    default:
                        break;
                }

                $columnInc = $processor->getColumnCount();
            } else {
                if (isset($columnConfig['columns'])) {
                    $columnInc = count($columnConfig['columns']);
                }
            }

            // TODO distinguish between just field and field with '-' extensiun
            // TODO recognise multi column field

            $columnNo += $columnInc;

            if (null !== $processor) {
                $processors[] = $processor;
            }
        }

        return $processors;
    }

    public function getColumnCount(): int
    {
        return 0; // TODO implement
    }
}
