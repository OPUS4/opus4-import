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
 * @copyright   Copyright (c) 2025, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import;

use Opus\Common\Document;
use Opus\Common\Enrichment;
use Opus\Import\AdditionalEnrichments;
use Opus\Import\ImportEnrichmentInterface;
use OpusTest\Import\TestAsset\TestCase;

class AdditionalEnrichmentsTest extends TestCase
{
    /** @var string */
    protected $additionalResources = 'database';

    /** @var AdditionalEnrichments */
    private $enrichments;

    public function setUp(): void
    {
        parent::setUp();

        $this->enrichments = new AdditionalEnrichments();
    }

    public function testGetImportDate()
    {
        $importDate = $this->enrichments->getValue(ImportEnrichmentInterface::OPUS_IMPORT_DATE);
        $this->assertNotNull($importDate);
        $this->assertIsString($importDate);
    }

    public function testAddEnrichment()
    {
        $this->enrichments->addEnrichment('myKey', 'myValue');
        $this->assertEquals('myValue', $this->enrichments->getValue('myKey'));
    }

    /**
     * This test makes sure we can use the import enrichments without having to register them ahead of
     * time. the foreign key relationship for enrichments in the database, was removed a while ago.
     */
    public function testStoringUnregisteredEnrichments()
    {
        $doc        = Document::new();
        $enrichment = Enrichment::new();
        $enrichment->setKeyName('UnknownEnrichment');
        $enrichment->setValue('TestValue1');
        $doc->addEnrichment($enrichment);
        $docId = $doc->store();

        $doc = Document::get($docId);

        $this->assertCount(1, $doc->getEnrichment());
        $this->assertEquals('TestValue1', $doc->getEnrichment(0)->getValue());
    }
}
