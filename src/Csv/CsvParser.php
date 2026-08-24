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
use SplFileObject;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

use function fgetcsv;
use function fopen;

use const PHP_INT_MAX;

/**
 * Configurable parser for OPUS 4 CSV files.
 *
 * TODO should implement ImportFormatInterface (new with issue #36)
 * TODO support auto configuration based on column headers
 * TODO ignoring first line as header should be optional (configurable)
 * TODO support single and multi column identifier
 */
class CsvParser
{
    /** @var CsvConfig */
    private $config;

    /** @var ?string  Path to fulltext files for documents. */
    private $fulltextPath;

    /** @var ?string Path to CSV file */
    private $filePath;

    /** @var resource */
    private $csvFile;

    /** @var string */
    private $separator = "\t"; // TODO configurable

    /** @var int */
    private $lineCount = 0;

    public function parseFile(string $path)
    {
        $this->filePath = $path;

        $file = fopen($path, 'r');

        if (! $file) {
            throw new FileNotFoundException('CSV file not found');
        }

        $this->config = new CsvConfigYaml(); // TODO configurable
        $this->config->load();

        $this->csvFile = $file;
    }

    public function next(): ?DocumentInterface
    {
        if ($this->lineCount === 0) {
            fgetcsv($this->csvFile, 0, "\t", '"', '\\');
            $this->lineCount++;
        }

        $row = fgetcsv($this->csvFile, 0, "\t", '"', '\\');

        if ($row !== false) {
            $document = $this->processRow($row);
        } else {
            $document = null;
        }

        return $document;
    }

    /**
     * TODO check if last line is empty (do not count)
     */
    public function getLineCount(): int
    {
        $file = new SplFileObject($this->filePath, 'r');
        $file->seek(PHP_INT_MAX);
        return $file->key() + 1;
    }

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
}
