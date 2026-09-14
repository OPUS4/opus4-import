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
use Opus\Import\Csv\CsvIdentifierProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvIdentifierProcessorTest extends TestCase
{
    /** @var CsvIdentifierProcessor */
    private $processor;

    public function setUp(): void
    {
        parent::setUp();

        $this->processor = new CsvIdentifierProcessor();
    }

    protected function getProcessor(): CsvIdentifierProcessor
    {
        return $this->processor;
    }

    public function testProcessShortcutType()
    {
        $processor = $this->getProcessor();
        $processor->setShortcutOption('old');
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['1234', '4321'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('1234', $doc->getIdentifierOld()[0]->getValue());

        $processor->setColumnNo(1);
        $doc = Document::new();

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('4321', $doc->getIdentifierOld()[0]->getValue());
    }

    public function testProcessShortcutTypeInvalid()
    {
        $processor = $this->getProcessor();
        $processor->setShortcutOption('old2');
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['1234'];

        $processor->process($row, $doc);

        $this->expectExceptionMessage('Data truncated');
        $doc->store();
    }

    public function testProcessMultiColumn()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['Type', 'Value']);

        $doc = Document::new();
        $row = ['old', '1234'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('old', $doc->getIdentifierOld()[0]->getType());
        $this->assertEquals('1234', $doc->getIdentifierOld()[0]->getValue());
    }

    public function testProcessMultiColumnReverse()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['Value', 'Type']);

        $doc = Document::new();
        $row = ['1234', 'old'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('old', $doc->getIdentifierOld()[0]->getType());
        $this->assertEquals('1234', $doc->getIdentifierOld()[0]->getValue());
    }

    public function testProcessFieldNamesCaseInsensitive()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['vAlue', 'tYpe']);

        $doc = Document::new();
        $row = ['1234', 'old'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('old', $doc->getIdentifierOld()[0]->getType());
        $this->assertEquals('1234', $doc->getIdentifierOld()[0]->getValue());
    }

    public function testProcessMultiValue()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['Type', 'Value']);
        $processor->setMultiValueEnabled(true);

        $doc = Document::new();
        $row = ['old || urn', '1234 || 5678'];
        $processor->process($row, $doc);

        $this->assertCount(2, $doc->getIdentifier());
        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertCount(1, $doc->getIdentifierUrn());
        $this->assertEquals('1234', $doc->getIdentifierOld()[0]->getValue());
        $this->assertEquals('5678', $doc->getIdentifierUrn()[0]->getValue());
    }

    public function testProcessMultiValueShortcutType()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setShortcutOption('old');
        $processor->setMultiValueEnabled(true);

        $doc = Document::new();
        $row = ['1234 || 5678'];
        $processor->process($row, $doc);

        $this->assertCount(2, $doc->getIdentifierOld());
        $this->assertEquals('1234', $doc->getIdentifierOld()[0]->getValue());
        $this->assertEquals('5678', $doc->getIdentifierOld()[1]->getValue());
    }

    public function testProcessMultiValueDisabled()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setShortcutOption('old');
        $processor->setMultiValueEnabled(false);

        $doc = Document::new();
        $row = ['1234 || 5678'];
        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('1234 || 5678', $doc->getIdentifierOld()[0]->getValue());
    }

    public function testProcessMultiColumnMultiValueMismatch()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['Type', 'Value']);
        $processor->setMultiValueEnabled(true);

        $doc = Document::new();
        $row = ['old', '1234 || 5678'];

        $this->expectExceptionMessage('multi value counts not matching');
        $processor->process($row, $doc);
    }

    public function testProcessMultiColumnBadType()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['Type', 'Value']);

        $doc = Document::new();
        $row = ['old2', '1234'];

        $processor->process($row, $doc);

        $this->expectExceptionMessage('Data truncated');
        $doc->store();
    }

    public function testProcessEmptyValue()
    {
        $processor = $this->getProcessor();
        $processor->setShortcutOption('old');
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = [''];

        $processor->process($row, $doc);

        $this->assertCount(0, $doc->getIdentifierOld());
    }

    public function testProcessMultiColumnEmptyValue()
    {
        $processor = $this->getProcessor();
        $processor->setColumnNo(0);
        $processor->setColumns(['Type', 'Value']);

        $doc = Document::new();
        $row = ['old', ''];

        $processor->process($row, $doc);

        $this->assertCount(0, $doc->getIdentifierOld());
    }

    public function testConstructColumnConfig()
    {
        $processor = new CsvIdentifierProcessor(['columns' => ['Type', 'Value']]);
        $processor->setColumnNo(2);

        $this->assertEquals(2, $processor->getFieldColumn('Type'));
        $this->assertEquals(3, $processor->getFieldColumn('Value'));
    }

    public function testConstructShortcutOption()
    {
        $processor = new CsvIdentifierProcessor(null, 'old');

        $this->assertEquals('old', $processor->getType());
    }

    public function testConstructUnknownField()
    {
        // $this->markTestSkipped('Multi column code not model aware yet');
        $processor = new CsvIdentifierProcessor(['columns' => ['Type', 'Value', 'Language']]);
        $processor->setColumnNo(2);
    }
}
