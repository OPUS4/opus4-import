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
use Opus\Common\Note;

use function array_map;
use function count;
use function explode;
use function in_array;
use function strtolower;

class CsvNoteProcessor extends DefaultMultiColumnProcessor
{
    /** Visibility short cut setting */
    private ?string $visibility = null;

    protected function init(?array $columnConfig = null): void
    {
        $this->setModelType(Note::getModelType());
    }

    public function setShortcutOption(?string $shortcutOption): DefaultColumnProcessor
    {
        $this->setVisibility($shortcutOption);
        return $this;
    }

    public function process(array $row, DocumentInterface $document): void
    {
        $visibility = $this->getVisibility();

        $separator = $this->getMultiValueSeparator();

        $messages = array_map('trim', explode($separator, $row[$this->getFieldColumn('Message')]));

        if (null === $visibility) {
            $visibilities = array_map(
                'trim',
                explode($separator, $row[$this->getFieldColumn('Visibility')])
            );

            if (count($visibilities) !== count($messages)) {
                throw new Exception('Number of entries for Visibility and Message do not match');
            }
        } else {
            $visibilities = null;
        }

        $pos = 0;

        foreach ($messages as $message) {
            if (null !== $visibilities) {
                $visibility = $visibilities[$pos];
            }

            $visibility = strtolower($visibility);

            if (empty($visibility) || ! in_array($visibility, ['public', 'private'])) {
                // TODO log invalid visibility
                $visibility = 'private';
            }

            $note = Note::new();
            $note->setVisibility($visibility);
            $note->setMessage($message);
            $document->addNote($note);
            $pos++;
        }
    }

    public function setVisibility(?string $visibility = null): self
    {
        if (null !== $visibility) {
            $visibility = strtolower($visibility);
            if (! in_array($visibility, ['public', 'private'])) {
                throw new Exception('Visibility must be public or private');
            }
        }
        $this->visibility = $visibility;
        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }
}
