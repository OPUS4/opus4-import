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

use function filter_var;
use function is_array;

use const FILTER_VALIDATE_BOOLEAN;

/**
 * Checks if a keyword/subject is present in a document.
 *
 * If no keyword/subject type is specified all keywords are checked.
 */
class KeywordCondition implements ImportRuleConditionInterface
{
    /** @var string|null */
    private $expectedKeyword;

    /** @var string|null  */
    private $keywordType;

    /** @var bool */
    private $removeKeyword = false;

    /** @var bool */
    private $caseSensitive = false;

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
            $keyword = $options['keyword'];
            if (is_array($keyword)) {
                $this->expectedKeyword = $keyword['value'] ?? null;
                $this->keywordType     = $keyword['type'] ?? null;
                if (isset($keyword['remove'])) {
                    $this->removeKeyword = filter_var($keyword['remove'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($keyword['caseSensitive'])) {
                    $this->caseSensitive = filter_var($keyword['caseSensitive'], FILTER_VALIDATE_BOOLEAN);
                }
            } else {
                $this->expectedKeyword = $options['keyword'];
            }
        }
    }

    /**
     * @param DocumentInterface $document
     * @return bool
     *
     * TODO remove keywords
     */
    public function applies($document)
    {
        $caseSensitive   = $this->isCaseSensitive();
        $expectedKeyword = $this->getExpectedKeyword();
        $keywordType     = $this->getKeywordType();

        if ($document->hasSubject($expectedKeyword, $keywordType, $caseSensitive)) {
            if ($this->isRemoveEnabled()) {
                $document->removeSubject($expectedKeyword, $keywordType, $caseSensitive);
            }
            return true;
        }

        return false;
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
     * @return $this
     */
    public function setExpectedKeyword($keyword)
    {
        $this->expectedKeyword = $keyword;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRemoveEnabled()
    {
        return $this->removeKeyword;
    }

    /**
     * @param bool $enabled
     * @return $this
     */
    public function setRemoveEnabled($enabled)
    {
        $this->removeKeyword = $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCaseSensitive()
    {
        return $this->caseSensitive;
    }

    /**
     * @param bool $enabled
     * @return $this
     */
    public function setCaseSensitive($enabled)
    {
        $this->caseSensitive = $enabled;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getKeywordType()
    {
        return $this->keywordType;
    }

    /**
     * @param string|null $type
     * @return $this
     */
    public function setKeywordType($type)
    {
        $this->keywordType = $type;
        return $this;
    }
}
