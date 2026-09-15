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

namespace OpusTest\Import\Csv;

use Opus\Import\Csv\CsvCollectionProcessor;
use Opus\Import\Csv\CsvConfigYaml;
use Opus\Import\Csv\CsvDateProcessor;
use Opus\Import\Csv\CsvEnrichmentProcessor;
use Opus\Import\Csv\CsvFileProcessor;
use Opus\Import\Csv\CsvIdentifierProcessor;
use Opus\Import\Csv\CsvLicenceProcessor;
use Opus\Import\Csv\CsvNoteProcessor;
use Opus\Import\Csv\CsvPersonProcessor;
use Opus\Import\Csv\CsvSeriesProcessor;
use Opus\Import\Csv\CsvTitleProcessor;
use Opus\Import\Csv\DefaultColumnProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvConfigYamlTest extends TestCase
{
    /** @var CsvConfigYaml */
    private $yamlConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->yamlConfig = new CsvConfigYaml();
        $this->yamlConfig->load();
    }

    public function testLoad()
    {
        $processors = $this->yamlConfig->getProcessors();

        $this->assertCount(24, $processors);
        $this->assertInstanceOf(CsvIdentifierProcessor::class, $processors[0]);
        $this->assertInstanceOf(DefaultColumnProcessor::class, $processors[1]);
        $this->assertInstanceOf(DefaultColumnProcessor::class, $processors[2]);
        $this->assertInstanceOf(DefaultColumnProcessor::class, $processors[3]);
        $this->assertInstanceOf(CsvTitleProcessor::class, $processors[4]);
        $this->assertInstanceOf(CsvTitleProcessor::class, $processors[5]);
        $this->assertInstanceOf(CsvTitleProcessor::class, $processors[6]);
        $this->assertInstanceOf(CsvPersonProcessor::class, $processors[7]);
        $this->assertInstanceOf(CsvDateProcessor::class, $processors[8]);
        $this->assertInstanceOf(CsvIdentifierProcessor::class, $processors[9]);
        $this->assertInstanceOf(CsvNoteProcessor::class, $processors[10]);
        $this->assertInstanceOf(CsvCollectionProcessor::class, $processors[11]);
        $this->assertInstanceOf(CsvSeriesProcessor::class, $processors[12]);
        $this->assertInstanceOf(DefaultColumnProcessor::class, $processors[13]);
        $this->assertInstanceOf(CsvLicenceProcessor::class, $processors[14]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[15]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[16]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[17]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[18]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[19]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[20]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[21]);
        $this->assertInstanceOf(CsvEnrichmentProcessor::class, $processors[22]);
        $this->assertInstanceOf(CsvFileProcessor::class, $processors[23]);
    }

    public function testLoadIdentifierProcessor()
    {
        $processors = $this->yamlConfig->getProcessors();

        $identifierProcessor = $processors[0];

        $this->assertEquals('old', $identifierProcessor->getType());
        $this->assertEquals(0, $identifierProcessor->getColumnNo());
    }
}
