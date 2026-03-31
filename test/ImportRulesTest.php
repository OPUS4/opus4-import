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

namespace OpusTest\Import;

use Opus\Common\Collection;
use Opus\Common\CollectionInterface;
use Opus\Common\CollectionRole;
use Opus\Import\ImportRules;
use Opus\Import\Rules\AddCollection;
use OpusTest\Import\TestAsset\TestCase;

/**
 * TODO sword.enableImportRules should not matter for ImportRules class
 */
class ImportRulesTest extends TestCase
{
    /** @var int */
    protected $colId;

    public function testInit()
    {
        $this->prepareCollections();

        $this->adjustConfiguration([
            'import' => [
                'rules'           => [
                    'addCol' => [
                        'type'       => 'AddCollection',
                        'collection' => [
                            'id' => $this->colId,
                        ],
                    ],
                ],
                'rulesConfigFile' => null,
            ],
            'sword'  => [
                'enableImportRules' => true,
            ],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $rules = $importRules->getRules();

        $this->assertCount(1, $rules);

        $rule = $rules[0];

        $this->assertInstanceOf(AddCollection::class, $rule);

        $col = $rule->getCollection();

        $this->assertInstanceOf(CollectionInterface::class, $col);
        $this->assertEquals('col1', $col->getName());
    }

    public function testConfigFullClassname()
    {
        $this->prepareCollections();

        $this->adjustConfiguration([
            'import' => [
                'rules'           => [
                    'addCol' => [
                        'type'       => AddCollection::class,
                        'collection' => [
                            'id' => $this->colId,
                        ],
                    ],
                ],
                'rulesConfigFile' => null,
            ],
            'sword'  => [
                'enableImportRules' => true,
            ],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $rules = $importRules->getRules();

        $this->assertCount(1, $rules);

        $rule = $rules[0];

        $this->assertInstanceOf(AddCollection::class, $rule);

        $col = $rule->getCollection();

        $this->assertInstanceOf(CollectionInterface::class, $col);
        $this->assertEquals('col1', $col->getName());
    }

    public function testLoadConfigIni()
    {
        $this->adjustConfiguration([
            'sword'  => ['enableImportRules' => true],
            'import' => ['rulesConfigFile' => APPLICATION_PATH . '/test/_files/import-rules.ini'],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $rules = $importRules->getRules();

        $this->assertCount(2, $rules);
        $this->assertInstanceOf(AddCollection::class, $rules[0]);
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
