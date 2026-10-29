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
use Opus\Common\DocumentInterface;

/**
 * Configurable parser for OPUS 4 CSV files.
 *
 * Exceptions importing a row, a document should not stop the entire import
 * process.
 *
 * TODO should implement ImportFormatInterface (new with issue #36)
 * TODO support auto configuration based on column headers
 * TODO ignoring first line as header should be optional (configurable)
 * TODO support single and multi column identifier
 * TODO support dryrun, parsing CSV file without saving documents
 */
abstract class AbstractCsvParser
{
    /** @var CsvConfigInterface */
    private $config;

    /** @var ?string  Path to fulltext files for documents. */
    private $fulltextPath;

    /** @var string */
    private $separator = "\t"; // TODO configurable

    /** @var int */
    private $lineCount = 0;

    abstract public function parse(string $csv): void;

    public function next(): ?DocumentInterface
    {
        if ($this->lineCount === 0) {
            $row = $this->getNextRow();
            $this->lineCount++;
        }

        $row = $this->getNextRow();

        if ($row !== null) {
            $document = $this->processRow($row);
        } else {
            $document = null;
        }

        return $document;
    }

    abstract protected function getNextRow(): ?array;

    /**
     * Returns number of lines in CSV data.
     */
    abstract public function getLineCount(): int;

    protected function processRow(array $row): ?DocumentInterface
    {
        $document = Document::new();

        $processors = $this->getProcessors();

        foreach ($processors as $processor) {
            $processor->process($row, $document);
        }

        return $document;
    }

    protected function getProcessors(): array
    {
        return $this->config->getProcessors();
    }

    public function setConfig(CsvConfigInterface $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function getConfig(): CsvConfigInterface
    {
        return $this->config;
    }
}
