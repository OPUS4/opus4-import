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
 * @copyright   Copyright (c) 2026, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import\Csv;

use Opus\Common\Subject;

/**
 * Processing subject data in CSV columns.
 *
 * Supported variants:
 * - Type defined in config/header
 * - Comma separated list for multiple subjects
 * - Type defined in separate column?
 * - Support external key
 * - Support language, GND subjects always German
 * - Types are gnd, psyndex, uncontrolled
 *
 * | Subject-Gnd |
 * | SUBJECT:EXTERNAL_KEY|
 *
 * | Subject-Uncontrolled |
 * | SUBJECT |
 * | LANGUAGE:SUBJECT |
 * | :SUBJECT:EXTERNAL_KEY |
 * | LANGUAGE:SUBJECT:EXTERNAL_KEY |
 *
 * TODO support escaping colon in subjects or external keys
 * TODO How to distinguish between LANGUAGE and SUBJECT?
 * TODO handle language for GND subjects
 * TODO support "tag" as alias for "uncontrolled"?
 * TODO support "keyword" as alias for "subject"?
 */
class CsvSubjectProcessor extends DefaultMultiColumnProcessor
{
    /** @var string Type of subject */
    private string $type = 'uncontrolled';

    protected function init(?array $columnConfig = null): void
    {
        $this->setModelType(Subject::getModelType());
    }

    public function setShortcutOption(?string $shortcutOption): self
    {
        $this->setType($shortcutOption);
        return $this;
    }

    /**
     * TODO check if $type is valid
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
