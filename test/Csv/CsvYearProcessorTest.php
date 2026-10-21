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
use Opus\Import\Csv\CsvYearProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvYearProcessorTest extends TestCase
{
    /** @var CsvYearProcessor */
    private $processor;

    public function setUp(): void
    {
        parent::setUp();
        $this->processor = new CsvYearProcessor();
    }

    public function testProcess()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Type', 'Value']);

        $doc = Document::new();
        $row = ['published', '2023'];
        $processor->process($row, $doc);

        $this->assertEquals(2023, $doc->getPublishedYear());
    }

    public function testProcessTypeCaseInsensitive()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['COMPLETED', '2017'];
        $processor->process($row, $doc);

        $this->assertEquals(2017, $doc->getCompletedYear());
    }

    public function testProcessInvalidType()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['embargo', '2017'];

        $this->expectExceptionMessage('Invalid type');
        $processor->process($row, $doc);
    }

    public function testProcessNoType()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['', '2017'];

        $this->expectExceptionMessage('not specified');
        $processor->process($row, $doc);
    }

    public function testProcessInvalidYear()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['published', 'next'];

        $this->expectExceptionMessage('not a valid year');
        $processor->process($row, $doc);
    }

    public function testProcessColumnsReversed()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setColumns(['Value', 'Type']);

        $doc = Document::new();
        $row = ['2023', 'published'];
        $processor->process($row, $doc);

        $this->assertEquals(2023, $doc->getPublishedYear());
    }
}
