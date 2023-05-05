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
 * @copyright   Copyright (c) 2023, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import\Rules\Options;

use Opus\Common\Collection;
use Opus\Common\CollectionRole;
use Opus\Import\Rules\Options\CollectionOption;
use OpusTest\Import\TestAsset\TestCase;

class CollectionOptionTest extends TestCase
{
    /** @var int */
    protected $roleId;

    /** @var int */
    protected $colId;

    public function testConfigCollectionId()
    {
        $this->prepareCollections();

        $colId = $this->colId;

        $option = new CollectionOption();
        $option->setOptions([
            'id' => $colId,
        ]);

        $col = $option->getCollection();

        $this->assertNotNull($col);
        $this->assertEquals($colId, $col->getId());
    }

    public function testConfigRoleNameColNumber()
    {
        $this->prepareCollections();

        $option = new CollectionOption();
        $option->setOptions([
            'roleName' => 'import',
            'number'   => 'col1number',
        ]);

        $col = $option->getCollection();

        $this->assertNotNull($col);
        $this->assertEquals($this->colId, $col->getId());
    }

    public function testConfigRoleNameColName()
    {
        $this->prepareCollections();

        $option = new CollectionOption();
        $option->setOptions([
            'roleName' => 'import',
            'name'     => 'col1',
        ]);

        $col = $option->getCollection();

        $this->assertNotNull($col);
        $this->assertEquals($this->colId, $col->getId());
    }

    public function testConfigRoleOaiNameColNumber()
    {
        $this->prepareCollections();

        $option = new CollectionOption();
        $option->setOptions([
            'roleOaiName' => 'oaiImport',
            'number'      => 'col1number',
        ]);

        $col = $option->getCollection();

        $this->assertNotNull($col);
        $this->assertEquals($this->colId, $col->getId());
    }

    public function testConfigRoleOaiNameColName()
    {
        $this->prepareCollections();

        $option = new CollectionOption();
        $option->setOptions([
            'roleOaiName' => 'oaiImport',
            'name'        => 'col1',
        ]);

        $col = $option->getCollection();

        $this->assertNotNull($col);
        $this->assertEquals($this->colId, $col->getId());
    }

    protected function prepareCollections()
    {
        $role = CollectionRole::new();
        $role->setName('import');
        $role->setOaiName('oaiImport');
        $role->addRootCollection();
        $this->roleId = $role->store();

        $col = Collection::new();
        $col->setName('col1');
        $col->setNumber('col1number');
        $role->getRootCollection()->addFirstChild($col);
        $role->store();

        $this->colId = $col->getId();
    }
}
