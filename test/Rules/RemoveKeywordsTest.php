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
use Opus\Import\ImportRules;
use Opus\Import\Rules\RemoveKeywords;
use OpusTest\Import\TestAsset\TestCase;

class RemoveKeywordsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->adjustConfiguration([
            'sword' => ['enableImportRules' => true],
        ]);
    }

    public function testRemoveKeywordUsingCondition()
    {
        $this->adjustConfiguration([
            'import' => [
                'rules'           => [
                    'keyword1' => [
                        'type'      => 'RemoveKeywords',
                        'condition' => [
                            'keyword' => [
                                'value'  => 'RemoveMe',
                                'remove' => true,
                            ],
                        ],
                    ],
                ],
                'rulesConfigFile' => null,
            ],
        ]);

        $doc     = Document::new();
        $keyword = $doc->addSubject();
        $keyword->setValue('RemoveMe');
        $keyword->setType('uncontrolled');
        $keyword->setLanguage('eng');

        $rules = new ImportRules();
        $rules->init();

        $rules->apply($doc);

        $keywords = $doc->getSubject();

        $this->assertCount(0, $keywords);
    }

    public function testSetKeywordsSingleValue()
    {
        $rule = new RemoveKeywords();
        $rule->setKeywords('RemoveMe');
        $this->assertEquals(['RemoveMe'], $rule->getKeywords());
    }

    public function testSetKeywordsNull()
    {
        $rule = new RemoveKeywords();
        $rule->setKeywords(null);
        $this->assertNull($rule->getKeywords());
    }

    public function testSetKeywordsCsv()
    {
        $rule = new RemoveKeywords();
        $rule->setKeywords('keyword1,keyword2,keyword3');
        $this->assertEquals(['keyword1', 'keyword2', 'keyword3'], $rule->getKeywords());
    }

    public function testSetKeywordsArray()
    {
        $rule = new RemoveKeywords();
        $rule->setKeywords(['keyword1', 'keyword2', 'keyword3']);
        $this->assertEquals(['keyword1', 'keyword2', 'keyword3'], $rule->getKeywords());
    }

    public function testRemoveKeyword()
    {
        $doc     = Document::new();
        $keyword = $doc->addSubject();
        $keyword->setValue('RemoveMe');
        $keyword->setType('uncontrolled');
        $keyword->setLanguage('eng');

        $rule = new RemoveKeywords();
        $rule->setKeywords('RemoveMe');

        $rule->apply($doc);

        $keywords = $doc->getSubject();

        $this->assertCount(0, $keywords);
    }

    public function testRemoveMultipleKeywords()
    {
        $doc = Document::fromArray([
            'Subject' => [
                [
                    'Language' => 'eng',
                    'Value'    => 'key1',
                    'Type'     => 'uncontrolled',
                ],
                [
                    'Language' => 'eng',
                    'Value'    => 'key2',
                    'Type'     => 'psyndex',
                ],
            ],
        ]);

        $this->assertCount(2, $doc->getSubject());

        $rule = new RemoveKeywords();
        $rule->setCaseSensitive(false);
        $rule->setKeywords(['key1', 'KEY2']);

        $rule->apply($doc);

        $this->assertCount(0, $doc->getSubject());
    }

    public function testRemoveKeywordsUsingType()
    {
        $doc = Document::fromArray([
            'Subject' => [
                [
                    'Language' => 'eng',
                    'Value'    => 'key1',
                    'Type'     => 'uncontrolled',
                ],
                [
                    'Language' => 'eng',
                    'Value'    => 'key2',
                    'Type'     => 'psyndex',
                ],
            ],
        ]);

        $this->assertCount(2, $doc->getSubject());

        $rule = new RemoveKeywords();
        $rule->setCaseSensitive(false);
        $rule->setKeywords(['key1', 'KEY2']);
        $rule->setKeywordType('psyndex');

        $rule->apply($doc);

        $this->assertCount(1, $doc->getSubject());
        $this->assertEquals('key1', $doc->getSubject(0)->getValue());
    }

    public function testRemoveKeywordsCaseSensitive()
    {
        $doc = Document::fromArray([
            'Subject' => [
                [
                    'Language' => 'eng',
                    'Value'    => 'key1',
                    'Type'     => 'uncontrolled',
                ],
                [
                    'Language' => 'eng',
                    'Value'    => 'key2',
                    'Type'     => 'psyndex',
                ],
            ],
        ]);

        $this->assertCount(2, $doc->getSubject());

        $rule = new RemoveKeywords();
        $rule->setCaseSensitive(true);
        $rule->setKeywords(['key1', 'KEY2']);

        $rule->apply($doc);

        $this->assertCount(1, $doc->getSubject());
        $this->assertEquals('key2', $doc->getSubject(0)->getValue());
    }
}
