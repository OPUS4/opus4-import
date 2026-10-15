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
use Opus\Import\Csv\CsvTitleProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvTitleProcessorTest extends TestCase
{
    /** @var CsvTitleProcessor */
    private $processor;

    public function setUp(): void
    {
        parent::setUp();

        $this->processor = new CsvTitleProcessor();
    }

    public function testProcessTitleMain()
    {
        $this->processor->setColumnNo(0);
        $this->processor->setType('Main');
        $this->processor->setColumns([
            'Language',
            'Value',
        ]);
        $this->processor->setFieldname('TitleMain');

        $doc = Document::new();
        $row = ['eng', 'Title1'];

        $this->processor->process($row, $doc);

        $titles = $doc->getTitleMain();

        $this->assertCount(1, $titles);
        $this->assertEquals('eng', $titles[0]->getLanguage());
        $this->assertEquals('Title1', $titles[0]->getValue());
    }

    public function testProcessTitleAbstract()
    {
        $this->processor->setColumnNo(0);
        $this->processor->setColumns([
            'Type',
            'Language',
            'Value',
        ]);

        $doc = Document::new();
        $row = ['Abstract', 'deu', 'Abstract1'];

        $this->processor->process($row, $doc);

        $titles = $doc->getTitleAbstract();

        $this->assertCount(1, $titles);
        $this->assertEquals('deu', $titles[0]->getLanguage());
        $this->assertEquals('Abstract1', $titles[0]->getValue());
    }

    public function testProcessTitles()
    {
        $this->processor->setColumnNo(0);
        $this->processor->setColumns([
            'Type',
            'Language',
            'Value',
        ]);

        $doc = Document::new();
        $row = ['Parent', 'deu', 'ParentTitle1'];

        $this->processor->process($row, $doc);

        $titles = $doc->getTitleParent();

        $this->assertCount(1, $titles);
        $this->assertEquals('deu', $titles[0]->getLanguage());
        $this->assertEquals('ParentTitle1', $titles[0]->getValue());
    }

    public function testProcessMultipleValues()
    {
        $this->processor->setColumnNo(0);
        $this->processor->setColumns([
            'Type',
            'Language',
            'Value',
        ]);
        $this->processor->setMultiValueEnabled(true);

        $doc = Document::new();
        $row = ['Parent || Additional', 'deu || eng', 'ParentTitle1 || AdditionalTitle1'];

        $this->processor->process($row, $doc);

        $titles = $doc->getTitleParent();

        $this->assertCount(1, $titles);
        $this->assertEquals('deu', $titles[0]->getLanguage());
        $this->assertEquals('ParentTitle1', $titles[0]->getValue());

        $titles = $doc->getTitleAdditional();

        $this->assertCount(1, $titles);
        $this->assertEquals('eng', $titles[0]->getLanguage());
        $this->assertEquals('AdditionalTitle1', $titles[0]->getValue());
    }

    public function testProcessMultipleValuesShortcutType()
    {
        $this->processor->setColumnNo(0);
        $this->processor->setColumns([
            'Language',
            'Value',
        ]);
        $this->processor->setMultiValueEnabled(true);
        $this->processor->setType('Sub');

        $doc = Document::new();
        $row = ['deu || eng', 'SubTitle1 || SubTitle2'];

        $this->processor->process($row, $doc);

        $titles = $doc->getTitleSub();

        $this->assertCount(2, $titles);
        $this->assertEquals('deu', $titles[0]->getLanguage());
        $this->assertEquals('SubTitle1', $titles[0]->getValue());
        $this->assertEquals('eng', $titles[1]->getLanguage());
        $this->assertEquals('SubTitle2', $titles[1]->getValue());
    }

    public function testProcessMultipleValuesMissmatchedCount()
    {
        $this->processor->setColumnNo(0);
        $this->processor->setColumns([
            'Language',
            'Value',
        ]);
        $this->processor->setMultiValueEnabled(true);
        $this->processor->setType('Sub');

        $doc = Document::new();
        $row = ['deu', 'SubTitle1 || SubTitle2'];

        $this->expectExceptionMessage('Number of entries in columns do not match');
        $this->processor->process($row, $doc);
    }

    public function testConfigUnknownType()
    {
        $this->expectExceptionMessage('Unknown title type');
        $this->processor->setType('Unknown');
    }
}
