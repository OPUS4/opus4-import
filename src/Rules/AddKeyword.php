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
use Opus\Common\Subject;
use Opus\Common\SubjectInterface;

use function is_array;

/**
 * TODO logging, error handling
 * TODO allow configuring type and language
 */
class AddKeyword extends AbstractImportRule
{
    /** @var SubjectInterface */
    private $subject;

    /** @var string */
    private $subjectType = Subject::TYPE_UNCONTROLLED;

    /** @var string */
    private $language = 'deu';

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if (isset($options['keyword'])) {
            $config = $options['keyword'];

            if (is_array($config)) {
                $keyword           = $config['value'] ?? null;
                $this->subjectType = $config['type'] ?? Subject::TYPE_UNCONTROLLED;
                $this->language    = $config['lang'] ?? 'deu';
            } else {
                $keyword = $config;
            }

            if (! empty($keyword)) {
                $subject = Subject::new();
                $subject->setValue($keyword);
                $subject->setType($this->subjectType);
                $subject->setLanguage($this->language);
                $this->subject = $subject;
            }
        }
    }

    /**
     * @return SubjectInterface|null
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param DocumentInterface $document
     */
    public function apply($document)
    {
        $condition = $this->getCondition();
        if ($condition === null || $condition->applies($document)) {
            $subject = $this->getSubject();
            if ($subject !== null) {
                $document->addSubject($subject);
            }
        }
    }
}
