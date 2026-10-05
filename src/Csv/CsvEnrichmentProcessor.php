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
use Opus\Common\Enrichment;

use function array_map;
use function count;
use function explode;
use function preg_match;

/**
 * TODO support simple values
 * TODO support multiple values
 * TODO support legacy values "{availability: PDF-file / PDF-Datei}"
 * TODO option for legacy value processing
 *
 * Single column usage:
 *   Enrichment-keyName
 *   Multiple values using ||
 *
 * Single column
 *   {KEYNAME: VALUE}
 *   Multiple values using ||
 */
class CsvEnrichmentProcessor extends DefaultMultiColumnProcessor
{
    private ?string $keyName = null;

    protected function init(?array $columnConfig = null): void
    {
        $this->setModelType(Enrichment::getModelType());
    }

    public function setShortcutOption(?string $shortcutOption): self
    {
        $this->setKeyName($shortcutOption);
        return $this;
    }

    public function process(array $row, DocumentInterface $document): void
    {
        $keyName = $this->getKeyName();

        if (null !== $keyName) {
            $value = $row[$this->getColumnNo()];
        } else {
            $value = $row[$this->getFieldColumn('Value')];
            if ($this->getColumnCount() > 1) {
                $keyName = $row[$this->getFieldColumn('KeyName')];
            } else {
                preg_match('/^{([A-Za-z]+): (.+)}$/', $value, $matches);
                $keyName = $matches[1];
                $value   = $matches[2];
            }
        }

        if (null === $keyName || null === $value) {
            // TODO something is wrong -> log OR throw exception
            return;
        }

        if ($this->isMultiValueEnabled()) {
            $separator = $this->getMultiValueSeparator();

            if (null === $this->getKeyName()) {
                $keyNames = array_map('trim', explode($separator, $keyName));
            } else {
                $keyNames = null;
            }
            $values = array_map('trim', explode($separator, $value));

            if ($keyNames !== null && count($keyNames) !== count($values)) {
                // TODO use specific exception with more information
                throw new Exception('multi value counts not matching');
            }

            $pos = 0;

            foreach ($values as $value) {
                if (null !== $keyNames) {
                    $keyName = $keyNames[$pos];
                }
                $this->addEnrichment($document, $keyName, $value);
                $pos++;
            }
        } else {
            $this->addEnrichment($document, $keyName, $value);
        }
    }

    /**
     * TODO handle empty value
     */
    protected function addEnrichment(DocumentInterface $document, string $keyName, string $value): void
    {
        $enrichment = Enrichment::new();
        $enrichment->setKeyName($keyName);
        $enrichment->setValue($value);
        $document->addEnrichment($enrichment);
    }

    public function setKeyName(string $keyName): self
    {
        $this->keyName = $keyName;
        return $this;
    }

    public function getKeyName(): ?string
    {
        return $this->keyName;
    }
}
