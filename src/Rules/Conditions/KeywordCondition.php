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

namespace Opus\Import\Rules\Conditions;

use Opus\Common\DocumentInterface;
use Opus\Import\ImportRuleConditionInterface;

use function strcasecmp;

/**
 * Checks if a keyword/subject is present in a document.
 *
 * If no keyword/subject type is specified all keywords are checked.
 */
class KeywordCondition implements ImportRuleConditionInterface
{
    /** @var string|null */
    protected $expectedKeyword;

    /**
     * @param array|null $options
     */
    public function __construct($options = null)
    {
        $this->setOptions($options);
    }

    /**
     * @param array|null $options
     */
    public function setOptions($options)
    {
        if (isset($options['keyword'])) {
            $this->expectedUser = $options['keyword'];
        }
    }

    /**
     * @param DocumentInterface $document
     * @return bool
     */
    public function applies($document)
    {
        $keywords = $this->getKeywords($document);

        if ($this->expectedKeyword !== null && $keywords !== null) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($this->expectedKeyword, $keyword) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param DocumentInterface $document
     * @return string[]
     */
    protected function getKeywords($document)
    {
        $keywords = [];
        $subjects = $document->getSubject();

        foreach ($subjects as $subject) {
            $keywords[] = $subject->getValue();
        }

        return $keywords;
    }

    /**
     * @return string|null
     */
    public function getExpectedKeyword()
    {
        return $this->expectedKeyword;
    }

    /**
     * @param string|null $keyword
     */
    public function setExpectedKeyword($keyword)
    {
        $this->expectedKeyword = $keyword;
    }
}
