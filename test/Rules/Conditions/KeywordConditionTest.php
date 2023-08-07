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

namespace OpusTest\Import\Rules\Conditions;

use Opus\Common\Document;
use Opus\Common\Subject;
use Opus\Import\Rules\Conditions\KeywordCondition;
use OpusTest\Import\TestAsset\TestCase;

class KeywordConditionTest extends TestCase
{
    public function testConstruct()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value'         => 'ccby',
                'remove'        => true,
                'caseSensitive' => 1,
                'type'          => 'uncontrolled',
            ],
        ]);

        $this->assertEquals('ccby', $condition->getExpectedKeyword());
        $this->assertTrue($condition->isCaseSensitive());
        $this->assertEquals('uncontrolled', $condition->getKeywordType());
        $this->assertTrue($condition->isRemoveEnabled());
    }

    public function testConstructSimpleConfig()
    {
        $condition = new KeywordCondition([
            'keyword' => 'ccby',
        ]);

        $this->assertEquals('ccby', $condition->getExpectedKeyword());
        $this->assertFalse($condition->isCaseSensitive());
        $this->assertNull($condition->getKeywordType());
        $this->assertFalse($condition->isRemoveEnabled());
    }

    public function testAppliesTrue()
    {
        $condition = new KeywordCondition([
            'keyword' => 'ccby',
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertTrue($condition->applies($doc));
    }

    public function testAppliesFalse()
    {
        $condition = new KeywordCondition([
            'keyword' => 'ccbyna',
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertFalse($condition->applies($doc));
    }

    public function testUsingKeywordType()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value' => 'ccby',
                'type'  => 'psyndex',
            ],
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertFalse($condition->applies($doc));

        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_PSYNDEX);

        $this->assertTrue($condition->applies($doc));
    }

    public function testNotCaseSensitive()
    {
        $condition = new KeywordCondition([
            'keyword' => 'ccby',
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('CCBY');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertTrue($condition->applies($doc));
    }

    public function testCaseSensitive()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value'         => 'ccby',
                'caseSensitive' => true,
            ],
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('CCBY');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertFalse($condition->applies($doc));
    }

    public function testDoNotRemoveKeyword()
    {
        $condition = new KeywordCondition([
            'keyword' => 'ccby',
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertTrue($condition->applies($doc));

        $this->assertCount(1, $doc->getSubject());
    }

    public function testDoNotRemoveKeywordIfConditionDoesNotApply()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value'  => 'ccby',
                'remove' => true,
            ],
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccbyna');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertFalse($condition->applies($doc));

        $this->assertCount(1, $doc->getSubject());
    }

    public function testRemoveKeyword()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value'  => 'ccby',
                'remove' => true,
            ],
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $this->assertTrue($condition->applies($doc));

        $this->assertCount(0, $doc->getSubject());
    }

    public function testRemoveOnlyKeywordWithMatchingType()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value'  => 'ccby',
                'type'   => Subject::TYPE_PSYNDEX,
                'remove' => true,
            ],
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_PSYNDEX);

        $this->assertTrue($condition->applies($doc));

        $subjects = $doc->getSubject();

        $this->assertCount(1, $subjects);
        $this->assertEquals(Subject::TYPE_UNCONTROLLED, $subjects[0]->getType());
    }

    public function testRemoveAllMatchingKeywords()
    {
        $condition = new KeywordCondition([
            'keyword' => [
                'value'  => 'ccby',
                'remove' => true,
            ],
        ]);

        $doc     = Document::new();
        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_UNCONTROLLED);

        $subject = $doc->addSubject();
        $subject->setValue('ccby');
        $subject->setType(Subject::TYPE_PSYNDEX);

        $this->assertTrue($condition->applies($doc));

        $subjects = $doc->getSubject();

        $this->assertCount(0, $subjects);
    }
}
