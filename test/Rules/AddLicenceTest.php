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

use Opus\Common\Document;
use Opus\Common\Licence;
use Opus\Common\Subject;
use Opus\Import\ImportRules;
use Opus\Import\Rules\AddLicence;
use OpusTest\Import\TestAsset\TestCase;

class AddLicenceTest extends TestCase
{
    /** @var int */
    private $licenceId1;

    /** @var int */
    private $licenceId2;

    public function setUp(): void
    {
        parent::setUp();

        $this->adjustConfiguration([
            'sword' => [
                'enableImportRules' => true,
            ],
        ]);
    }

    public function testAddLicence()
    {
        $this->prepareLicences();

        $this->adjustConfiguration([
            'import' => [
                'rules' => [
                    'addLicence' => [
                        'type'      => AddLicence::class,
                        'licenceId' => $this->licenceId1,
                    ],
                ],
            ],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $doc = Document::new();

        $importRules->apply($doc);

        $licences = $doc->getLicence();

        $this->assertCount(1, $licences);
        $this->assertEquals($this->licenceId1, $licences[0]->getModel()->getId());
    }

    public function testAddLicenceForKeyword()
    {
        $this->prepareLicences();

        $this->adjustConfiguration([
            'import' => [
                'rules' => [
                    'addLicence1' => [
                        'type'      => 'AddLicence',
                        'licenceId' => $this->licenceId1,
                        'condition' => [
                            'keyword' => 'ccby',
                        ],
                    ],
                    'addLicence2' => [
                        'type'      => 'AddLicence',
                        'licenceId' => $this->licenceId2,
                        'condition' => [
                            'keyword' => 'ccbyna',
                        ],
                    ],
                ],
            ],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccbyna');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $importRules->apply($doc);

        $licences = $doc->getLicence();

        $this->assertCount(1, $licences);
        $this->assertEquals($this->licenceId2, $licences[0]->getModel()->getId());
    }

    public function testAddMultipleLicences()
    {
        $this->prepareLicences();

        $this->adjustConfiguration([
            'import' => [
                'rules' => [
                    'addLicence1' => [
                        'type'      => 'AddLicence',
                        'licenceId' => $this->licenceId1,
                        'condition' => [
                            'keyword' => 'ccby',
                        ],
                    ],
                    'addLicence2' => [
                        'type'      => 'AddLicence',
                        'licenceId' => $this->licenceId2,
                        'condition' => [
                            'keyword' => 'ccbyna',
                        ],
                    ],
                ],
            ],
        ]);

        $importRules = new ImportRules();
        $importRules->init();

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccbyna');
        $subject->setType(Subject::TYPE_UNCONTROLLED);
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_PSYNDEX);

        $importRules->apply($doc);

        $licences = $doc->getLicence();

        $this->assertCount(2, $licences);
        $this->assertNotEquals($licences[0]->getModel()->getId(), $licences[1]->getModel()->getId());
        $this->assertContains($licences[0]->getModel()->getId(), [$this->licenceId1, $this->licenceId2]);
        $this->assertContains($licences[1]->getModel()->getId(), [$this->licenceId1, $this->licenceId2]);
    }

    protected function prepareLicences()
    {
        $licence = Licence::fromArray([
            'Name'        => 'CC BY',
            'NameLong'    => 'Test Licence 1',
            'LinkLicence' => 'https://www.kobv.de/licence1',
        ]);

        $this->licenceId1 = $licence->store();

        $licence = Licence::fromArray([
            'name'        => 'CC BY NA',
            'NameLong'    => 'Test Licence 2',
            'LinkLicence' => 'https://www.kobv.de/licence2',
        ]);

        $this->licenceId2 = $licence->store();
    }
}
