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

use Exception;
use Opus\Common\DocumentInterface;
use Opus\Common\Title;

use function array_map;
use function count;
use function explode;
use function in_array;
use function strtolower;
use function ucfirst;

/**
 * Imports titles.
 *
 * TODO get Type as shortcut option
 */
class CsvTitleProcessor extends DefaultMultiColumnProcessor
{
    /** @var string[] Types of titles TODO get from model description */
    private $supportedTypes = [
        'main',
        'parent',
        'sub',
        'additional',
        'abstract',
    ];

    private ?string $type = null;

    protected function init(?array $columnConfig = null): void
    {
        $this->setModelType(Title::getModelType());
    }

    public function process(array $row, DocumentInterface $document): void
    {
        $type   = $this->getType();
        $type ??= $row[$this->getFieldColumn('Type')];
        $lang   = $row[$this->getFieldColumn('Language')];
        $value  = $row[$this->getFieldColumn('Value')];

        if ($this->isMultiValueEnabled()) {
            $separator = $this->getMultiValueSeparator();

            if (null !== $this->getType()) {
                $types = null;
            } else {
                $types = array_map('trim', explode($separator, $type));
            }

            $languages = array_map('trim', explode($separator, $lang));
            $values    = array_map('trim', explode($separator, $value));

            if (count($languages) !== count($values) || null !== $types && count($types) !== count($values)) {
                throw new Exception('Number of entries in columns do not match');
            }

            // TODO check counts are matching
            $pos = 0;

            foreach ($values as $value) {
                if (null !== $types) {
                    $type = $types[$pos];
                }

                $this->addTitle($document, $type, $languages[$pos], $value);
                $pos++;
            }
        } else {
            $this->addTitle($document, $type, $lang, $value);
        }
    }

    protected function addTitle(DocumentInterface $doc, string $type, string $lang, string $value): void
    {
        $title = Title::new();
        $title->setLanguage($lang);
        $title->setValue($value);
        $method = 'addTitle' . ucfirst($type);
        $doc->$method($title);
    }

    public function setShortcutOption(?string $shortcutOption): self
    {
        $this->setType($shortcutOption);
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $type = strtolower($type);

        if (! in_array($type, $this->supportedTypes)) {
            throw new Exception('Unknown title type');
        }

        $this->type = $type;

        return $this;
    }
}
