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
use Opus\Common\Identifier;

use function array_map;
use function count;
use function explode;
use function strtolower;
use function trim;
use function ucfirst;

/**
 * TODO support configuration from header
 * TODO support multi value
 * TODO error handling
 * TODO no validation of type - add?
 * TODO addIdentifierOpac method corresponds to type 'opac-id' (Fromm uses 'Opac') - How to handle both?
 */
class CsvIdentifierProcessor extends DefaultMultiColumnProcessor
{
    /** @var ?string Identifier type */
    private $type;

    public function setShortcutOption(?string $shortcutOption): self
    {
        $this->setType($shortcutOption);
        return $this;
    }

    public function process(array $row, DocumentInterface $doc): void
    {
        $type = $this->getType();

        if (null !== $type) {
            $value = $row[$this->getColumnNo()];
        } else {
            $type  = $row[$this->getFieldColumn('Type')];
            $value = $row[$this->getFieldColumn('Value')];
        }

        if ($this->isMultiValueEnabled()) {
            $separator = $this->getMultiValueSeparator();

            if (null === $this->getType()) {
                $types = array_map('trim', explode($separator, $type));
            } else {
                $types = null;
            }
            $values = array_map('trim', explode($separator, $value));

            if ($types !== null && count($types) !== count($values)) {
                // TODO use specific exception with more information
                throw new Exception('multi value counts not matching');
            }

            $pos = 0;

            foreach ($values as $value) {
                if (null !== $types) {
                    $type = $types[$pos];
                }
                $this->addIdentifier($doc, $value, $type);
                $pos++;
            }
        } else {
            $this->addIdentifier($doc, $value, $type);
        }
    }

    public function setType(string $type): self
    {
        $this->type = strtolower($type);
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    protected function addIdentifier(DocumentInterface $document, string $value, string $type): void
    {
        $value = trim($value);

        if ('' === $value) {
            return;
        }

        $identifier = Identifier::new();
        $identifier->setValue($value);
        $method = 'addIdentifier' . ucfirst($type);
        $document->$method($identifier);
    }
}
