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
use Opus\Import\Csv\CsvNoteProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvNoteProcessorTest extends TestCase
{
    /** @var CsvNoteProcessor */
    private $processor;

    public function setUp(): void
    {
        parent::setUp();

        $this->processor = new CsvNoteProcessor();
    }

    public function testProcess()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Visibility', 'Message']);

        $doc = Document::new();
        $row = ['public', 'public note'];

        $processor->process($row, $doc);

        $notes = $doc->getNote();

        $this->assertCount(1, $notes);
        $this->assertEquals('public', $notes[0]->getVisibility());
        $this->assertEquals('public note', $notes[0]->getMessage());
    }

    public function testProcessMultipleValues()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Visibility', 'Message']);

        $doc = Document::new();
        $row = ['public || private', 'public note || private note'];

        $processor->process($row, $doc);

        $notes = $doc->getNote();

        $this->assertCount(2, $notes);
        $this->assertEquals('public', $notes[0]->getVisibility());
        $this->assertEquals('public note', $notes[0]->getMessage());
        $this->assertEquals('private', $notes[1]->getVisibility());
        $this->assertEquals('private note', $notes[1]->getMessage());
    }

    public function testProcessShortcutPublic()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setShortcutOption('public');

        $doc = Document::new();
        $row = ['public note'];

        $processor->process($row, $doc);

        $notes = $doc->getNote();

        $this->assertCount(1, $notes);
        $this->assertEquals('public', $notes[0]->getVisibility());
        $this->assertEquals('public note', $notes[0]->getMessage());
    }

    public function testProcessMultipleValuesShortcutPrivate()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setShortcutOption('Private');

        $doc = Document::new();
        $row = ['private note1 || private note2'];

        $processor->process($row, $doc);

        $notes = $doc->getNote();

        $this->assertCount(2, $notes);
        $this->assertEquals('private', $notes[0]->getVisibility());
        $this->assertEquals('private note1', $notes[0]->getMessage());
        $this->assertEquals('private', $notes[1]->getVisibility());
        $this->assertEquals('private note2', $notes[1]->getMessage());
    }

    public function testProcessMultipleValuesMismatch()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Visibility', 'Message']);

        $doc = Document::new();
        $row = ['public || private', 'public note'];

        $this->expectExceptionMessage('do not match');
        $processor->process($row, $doc);
    }

    public function testConfigShortcutVisibilityInvalid()
    {
        $this->expectExceptionMessage('Visibility must be public or private');
        $this->processor->setShortcutOption('unknown');
    }

    public function testProcessInvalidVisibility()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Visibility', 'Message']);

        $doc = Document::new();
        $row = ['sneaky', 'invalid visibility note'];

        // $this->expectExceptionMessage('Invalid visibility');
        $processor->process($row, $doc);

        $notes = $doc->getNote();

        $this->assertCount(1, $notes);
        $this->assertEquals('private', $notes[0]->getVisibility());
        $this->assertEquals('invalid visibility note', $notes[0]->getMessage());
    }

    public function testProcessEmptyVisibility()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Visibility', 'Message']);

        $doc = Document::new();
        $row = ['', 'empty visibility note'];

        $processor->process($row, $doc);

        $notes = $doc->getNote();

        $this->assertCount(1, $notes);
        $this->assertEquals('private', $notes[0]->getVisibility());
        $this->assertEquals('empty visibility note', $notes[0]->getMessage());
    }
}
