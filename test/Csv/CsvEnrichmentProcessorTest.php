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

use Opus\Common\Document;
use Opus\Import\Csv\CsvEnrichmentProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvEnrichmentProcessorTest extends TestCase
{
    /** @var CsvEnrichmentProcessor */
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new CsvEnrichmentProcessor();
    }

    public function testSingleColumn()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setKeyName('availability');

        $doc = Document::new();
        $row = ['PDF-file / PDF-Datei'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getEnrichment());
        $this->assertEquals('availability', $doc->getEnrichment()[0]->getKeyName());
        $this->assertEquals('PDF-file / PDF-Datei', $doc->getEnrichment()[0]->getValue());
    }

    public function testProcessSingleColumnMultipleValues()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setKeyName('availability');

        $doc = Document::new();
        $row = ['value1 || value2'];

        $processor->process($row, $doc);

        $this->assertCount(2, $doc->getEnrichment());
        $this->assertEquals('availability', $doc->getEnrichment()[0]->getKeyName());
        $this->assertEquals('value1', $doc->getEnrichment()[0]->getValue());
        $this->assertEquals('availability', $doc->getEnrichment()[1]->getKeyName());
        $this->assertEquals('value2', $doc->getEnrichment()[1]->getValue());
    }

    public function testProcessSingleColumnLegacyValues()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['{availability: PDF-file / PDF-Datei}'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getEnrichment());
        $this->assertEquals('availability', $doc->getEnrichment()[0]->getKeyName());
        $this->assertEquals('PDF-file / PDF-Datei', $doc->getEnrichment()[0]->getValue());
    }

    public function testProcessMultiColumn()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['KeyName', 'Value']);

        $doc = Document::new();
        $row = ['availability', 'PDF-file / PDF-Datei'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getEnrichment());
        $this->assertEquals('availability', $doc->getEnrichment()[0]->getKeyName());
        $this->assertEquals('PDF-file / PDF-Datei', $doc->getEnrichment()[0]->getValue());
    }

    public function testProcessMultiColumnMultipleValues()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['KeyName', 'Value']);
        $processor->setMultiValueEnabled(true);

        $doc = Document::new();
        $row = ['key1 || key2', 'value1 || value2'];

        $processor->process($row, $doc);

        $this->assertCount(2, $doc->getEnrichment());
        $this->assertEquals('key1', $doc->getEnrichment()[0]->getKeyName());
        $this->assertEquals('value1', $doc->getEnrichment()[0]->getValue());
        $this->assertEquals('key2', $doc->getEnrichment()[1]->getKeyName());
        $this->assertEquals('value2', $doc->getEnrichment()[1]->getValue());
    }

    public function testProcessShortcutOptionAndLegacyValues()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setKeyName('key1');

        $doc = Document::new();
        $row = ['{availability: PDF-file / PDF-Datei}'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getEnrichment());
        $this->assertEquals('key1', $doc->getEnrichment()[0]->getKeyName());
        $this->assertEquals('{availability: PDF-file / PDF-Datei}', $doc->getEnrichment()[0]->getValue());
    }
}
