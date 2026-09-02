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

namespace Opus\Import;

use DOMDocument;
use Opus\Common\DocumentInterface;
use Opus\Common\LoggingTrait;
use Opus\Import\Xml\OpusXmlParser;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractImporter
{
    use LoggingTrait;

    private ?OutputInterface $output = null;

    private bool $updateExistingDocuments = true;

    /**
     * TODO support $format parameter
     */
    protected function getParser(?string $format = null): ImportFormatInterface
    {
        return new OpusXmlParser();
    }

    protected function postStore(DocumentInterface $doc): void
    {
    }

    public function getOutput(): OutputInterface
    {
        if ($this->output === null) {
            $this->output = new NullOutput();
        }

        return $this->output;
    }

    public function setOutput(?OutputInterface $output = null): self
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Imports documents from file.
     */
    public function importFile(string $path): void
    {
        $parser = $this->getParser();
        $parser->parseFile($path);
        $this->process($parser);
    }

    /**
     * Imports documents from XML string.
     */
    public function importXml(string|DOMDocument $xml): void
    {
        $parser = $this->getParser();
        $parser->parseFile($xml);
        $this->process($parser);
    }

    protected function process(ImportFormatInterface $parser): void
    {
        // TODO basic import code
    }

    public function setUpdateExistingDocuments(bool $updateDocuments): self
    {
        $this->updateExistingDocuments = $updateDocuments;
        return $this;
    }

    public function isUpdateExistingDocuments(): bool
    {
        return $this->updateExistingDocuments;
    }
}
