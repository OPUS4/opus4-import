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

use Opus\Common\Collection;
use Opus\Common\CollectionInterface;
use Opus\Common\CollectionRole;
use Opus\Common\CollectionRoleInterface;
use Opus\Common\Document;
use Opus\Import\Csv\CsvCollectionProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvCollectionProcessorTest extends TestCase
{
    /** @var CsvCollectionProcessor */
    private $processor;

    /** @var CollectionRoleInterface */
    private $colRole;

    /** @var CollectionInterface */
    private $col;

    public function setUp(): void
    {
        parent::setUp();

        $this->clearDatabase();

        $this->processor = new CsvCollectionProcessor();

        $role = CollectionRole::new();
        $role->setName('role');
        $role->setOaiName('roleoainame');
        $root          = $role->addRootCollection();
        $this->colRole = $role;

        $col = Collection::new();
        $col->setName('col1');
        $col->setNumber('col1number');
        $root->addLastChild($col);
        $role->store();
        $this->col = $col;
    }

    public function testProcessCollectionId()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = [$this->col->getId()];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getCollection());
        $this->assertEquals($this->col->getId(), $doc->getCollection()[0]->getId());
    }

    public function testProcessMultipleCollectionId()
    {
        $root = $this->colRole->getRootCollection();
        $col2 = Collection::new();
        $col2->setName('col2');
        $col2->setNumber('col2number');
        $root->addLastChild($col2);
        $this->colRole->store();

        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = [$this->col->getId() . ' || ' . $col2->getId()];

        $processor->process($row, $doc);

        $this->assertCount(2, $doc->getCollection());
        $this->assertEquals($this->col->getId(), $doc->getCollection()[0]->getId());
        $this->assertEquals($col2->getId(), $doc->getCollection()[1]->getId());
    }

    public function testProcessUnknownCollectionId()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = [9999];

        $this->expectExceptionMessage('does not exist');
        $processor->process($row, $doc);
    }

    public function testProcessRoleAndNameString()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['role:col1'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getCollection());
        $this->assertEquals($this->col->getId(), $doc->getCollection()[0]->getId());
    }

    public function testProcessShortcutOptionRole()
    {
        $processor = $this->processor;
        $processor->setShortcutOption('role');
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['col1'];

        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getCollection());
        $this->assertEquals($this->col->getId(), $doc->getCollection()[0]->getId());
    }

    public function testConfigShortcutOptionRoleOaiName()
    {
    }

    public function testConfigColValue()
    {
    }
}
