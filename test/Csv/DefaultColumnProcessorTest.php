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
use Opus\Import\Csv\DefaultColumnProcessor;
use OpusTest\Import\TestAsset\TestCase;
use ReflectionClass;

class DefaultColumnProcessorTest extends TestCase
{
    /** @var DefaultColumnProcessor */
    private $processor;

    public function setUp(): void
    {
        parent::setUp();
        $this->processor = new DefaultColumnProcessor();
    }

    public function testSetColumnNo()
    {
        $this->processor->setColumnNo(10);
        $this->assertEquals(10, $this->processor->getColumnNo());
    }

    public function testSetMultiValueSeparator()
    {
        $processor = $this->processor;
        $this->assertEquals('||', $processor->getMultiValueSeparator());

        $processor->setMultiValueSeparator('::');
        $this->assertEquals('::', $processor->getMultiValueSeparator());
    }

    public function testSetModelType()
    {
        $processor = $this->processor;
        $processor->setModelType('Title');

        $modelFields = $processor->getModelFields();
        $this->assertEqualsCanonicalizing([
            'Language',
            'Value',
            'Type',
        ], $modelFields);
    }

    public function testFieldExists()
    {
        $this->processor->setModelType('Title');
        $model  = new ReflectionClass(DefaultColumnProcessor::class);
        $method = $model->getMethod('fieldExists');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->processor, 'Language'));
        $this->assertFalse($method->invoke($this->processor, 'Unknown'));
    }

    public function testFieldExistsDefaultModelIsDocument()
    {
        $model  = new ReflectionClass(DefaultColumnProcessor::class);
        $method = $model->getMethod('fieldExists');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->processor, 'Volume'));
        $this->assertFalse($method->invoke($this->processor, 'Developer'));
    }

    public function testGetModelFields()
    {
        $modelFields = $this->processor->getModelFields();

        $this->assertCount(63, $modelFields);

        // just some Document model fields
        $this->assertContains('TitleMain', $modelFields);
        $this->assertContains('Language', $modelFields);
        $this->assertContains('Patent', $modelFields);
    }

    public function testSetFieldName()
    {
        $processor = $this->processor;
        $processor->setFieldName('TitleMain');
        $this->assertEquals('TitleMain', $processor->getFieldName());
    }

    public function testSetFieldNameCaseSensitive()
    {
        $this->expectExceptionMessage('unknown field');
        $this->processor->setFieldName('titlemain');
    }

    public function testSetFieldNameUnknown()
    {
        $this->expectExceptionMessage('unknown field');
        $this->processor->setFieldName('Title');
    }

    public function testSetFieldNameAutoUcFirst()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setFieldName('volume');

        $doc = Document::new();
        $row = ['vol1'];
        $processor->process($row, $doc);

        $this->assertEquals('vol1', $doc->getVolume());
    }

    public function testProcess()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setFieldName('Volume');

        $doc = Document::new();
        $row = ['vol1'];
        $processor->process($row, $doc);

        $this->assertEquals('vol1', $doc->getVolume());
    }

    public function testProcessDate()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setFieldName('CompletedDate');

        $doc = Document::new();
        $row = ['2025-10-21'];
        $processor->process($row, $doc);

        $this->assertEquals('2025-10-21', $doc->getCompletedDate());
    }

    public function testProcessYear()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);
        $processor->setFieldName('CompletedYear');

        $doc = Document::new();
        $row = ['2024'];
        $processor->process($row, $doc);

        $this->assertEquals('2024', $doc->getCompletedYear());
    }
}
