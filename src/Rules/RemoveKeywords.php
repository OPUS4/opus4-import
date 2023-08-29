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

namespace Opus\Import\Rules;

use Opus\Common\DocumentInterface;

use function array_map;
use function is_array;
use function mb_split;

/**
 * TODO logging, error handling
 * TODO support list of keywords (or should that be a RemoveKeywords rule?)
 */
class RemoveKeywords extends AbstractImportRule
{
    /** @var string[] */
    private $keywords;

    /** @var string */
    private $keywordType;

    /** @var bool */
    private $caseSensitive = false;

    /**
     * @param DocumentInterface $document
     */
    public function apply($document)
    {
        $condition = $this->getCondition();
        if ($condition === null || $condition->applies($document)) {
            $keywords = $this->getKeywords();
            if ($keywords !== null) {
                $caseSensitive = $this->isCaseSensitive();
                $keywordType   = $this->getKeywordType();
                foreach ($this->getKeywords() as $keyword) {
                    $document->removeSubject($keyword, $keywordType, $caseSensitive);
                }
            }
        }
    }

    /**
     * @return string[]
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @param string|string[] $keywords
     * @return $this
     */
    public function setKeywords($keywords)
    {
        if (is_array($keywords) || $keywords === null) {
            $this->keywords = $keywords;
        } else {
            $this->keywords = array_map('trim', mb_split(',', $keywords));
        }
        return $this;
    }

    /**
     * @return null|string
     */
    public function getKeywordType()
    {
        return $this->keywordType;
    }

    /**
     * @param null|string $type
     * @return $this
     */
    public function setKeywordType($type)
    {
        $this->keywordType = $type;
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
     * @param bool $caseSensitive
     * @return $this
     */
    public function setCaseSensitive($caseSensitive)
    {
        $this->caseSensitive = $caseSensitive;
        return $this;
    }
}
