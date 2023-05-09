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

namespace OpusTest\Import\Rules;

use Opus\Common\Collection;
use Opus\Common\CollectionRole;
use Opus\Common\Document;
use Opus\Import\ImportRules;
use Opus\Import\Rules\AddCollection;
use OpusTest\Import\TestAsset\MockAuthAdapter;
use OpusTest\Import\TestAsset\TestCase;
use Zend_Auth;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;

class AddCollectionTest extends TestCase
{
    /** @var Zend_Auth_Storage_Interface */
    private $authStorage;

    public function setUp(): void
    {
        parent::setUp();

        $this->authStorage = new Zend_Auth_Storage_NonPersistent();
        Zend_Auth::getInstance()->setStorage($this->authStorage);
    }

    public function tearDown(): void
    {
        Zend_Auth::getInstance()
            ->setStorage($this->authStorage)
            ->clearIdentity();
        parent::tearDown();
    }

    public function testAddCollection()
    {
        $this->prepareCollections();

        $this->adjustConfiguration([
            'import' => [
                'rules' => [
                    'addCol' => [
                        'type'       => AddCollection::class,
                        'collection' => [
                            'id' => $this->colId,
                        ],
                    ],
                ],
            ],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $doc = Document::new();

        $importRules->apply($doc);

        $collections = $doc->getCollection();

        $this->assertCount(1, $collections);
        $this->assertEquals($this->colId, $collections[0]->getId());
    }

    public function testAddCollectionForAccount()
    {
        $this->prepareCollections();

        $col1 = Collection::get($this->colId);
        $role = $col1->getRole();

        $col1id = $col1->getId();

        $col2 = Collection::new();
        $col2->setName('col1');
        $col2->setNumber('col1number');
        $role->getRootCollection()->addFirstChild($col2);
        $role->store();

        $col2id = $col2->getId();

        $this->adjustConfiguration([
            'import' => [
                'rules' => [
                    'addCol1' => [
                        'type'       => 'AddCollection',
                        'condition'  => [
                            'account' => 'sword1',
                        ],
                        'collection' => [
                            'id' => $this->colId,
                        ],
                    ],
                    'addCol2' => [
                        'type'       => 'AddCollection',
                        'condition'  => [
                            'account' => 'sword2',
                        ],
                        'collection' => [
                            'id' => $col2id,
                        ],
                    ],
                ],
            ],
        ]);

        $rules = new ImportRules();
        $rules->init();

        Zend_Auth::getInstance()->authenticate(new MockAuthAdapter('sword1'));

        $doc = Document::new();

        $rules->apply($doc);

        $collections = $doc->getCollection();

        $this->assertCount(1, $collections);
        $this->assertEquals($col1id, $collections[0]->getId());
    }

    protected function prepareCollections()
    {
        $role = CollectionRole::new();
        $role->setName('import');
        $role->setOaiName('oaiImport');
        $role->addRootCollection();

        $col = Collection::new();
        $col->setName('col1');
        $col->setNumber('col1number');
        $role->getRootCollection()->addFirstChild($col);
        $role->store();

        $this->colId = $col->getId();
    }
}
