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
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import\Xml;

use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMNode;
use DOMNodeList;
use Exception;
use Opus\Common\Collection;
use Opus\Common\DnbInstitute;
use Opus\Common\Document;
use Opus\Common\EnrichmentKey;
use Opus\Common\Licence;
use Opus\Common\LoggingTrait;
use Opus\Common\Model\NotFoundException;
use Opus\Common\Person;
use Opus\Common\Series;
use Opus\Common\Subject;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function array_diff;
use function substr;
use function trim;
use function ucfirst;

use const PHP_EOL;

/**
 * TODO consolidate with class Importer
 * TODO 3 logger? OPUS log, console, reject log (optional)
 * TODO use class for strings and files? should file loading be separated? (see Importer)
 */
class MetadataImport
{
    use LoggingTrait;

    /** @var OutputInterface */
    private $output;

    /** @var OutputInterface */
    private $rejectOutput;

    /** @var string */
    private $xmlFile;

    /**
     * TODO cleanup variable names, $xml
     */
    public function import(string $xml, bool $isFile = false)
    {
        if ($isFile) {
            $this->xmlFile = $xml;
        } else {
            $this->xmlString = $xml;
        }

        $this->xmlDocument = new XmlDocument();

        $output = $this->getOutput();

        $this->xml = $this->loadXML();

        $this->validateXml();

        $numOfDocsImported = 0;
        $numOfSkippedDocs  = 0;

        foreach ($this->xml->getElementsByTagName('opusDocument') as $opusDocumentElement) {
            // save oldId for later referencing of the record under consideration
            $oldId = $opusDocumentElement->getAttribute('oldId');
            $opusDocumentElement->removeAttribute('oldId');

            // TODO there is not always an old ID

            $output->writeln("Start processing of record #{$oldId} ...");

            /*
             * @var Document
             */
            $doc = null;
            if ($opusDocumentElement->hasAttribute('docId')) {
                // perform metadata update on given document
                $docId = $opusDocumentElement->getAttribute('docId');
                try {
                    $doc = Document::get($docId);
                    $opusDocumentElement->removeAttribute('docId');
                } catch (NotFoundException $e) {
                    $output->writeln("<error>Could not load document #{$docId} from database: " . $e->getMessage() . "</error>");
                    $this->appendDocIdToRejectList($oldId);
                    $numOfSkippedDocs++;
                    continue;
                }

                $this->resetDocument($doc);
            } else {
                // create new document
                $doc = Document::new();
            }

            try {
                $this->processAttributes($opusDocumentElement->attributes, $doc);
                $this->processElements($opusDocumentElement->childNodes, $doc);
            } catch (Exception $e) {
                $output->writeln("<error>Error processing document #{$oldId}: " . $e->getMessage() . "</error>");
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            }

            try {
                $docId = $doc->store();
            } catch (Exception $e) {
                $output->writeln("<error>Error saving imported document #{$oldId}: " . $e->getMessage() . "</error>");
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            }

            $numOfDocsImported++;
            $output->writeln("... Document {$docId} imported successfully"); // TODO mention oldId?
        }

        if ($numOfSkippedDocs === 0) {
            $output->writeln("Import finished successfully. {$numOfDocsImported} documents were imported.");
        } else {
            $output->writeln("Import finished. $numOfDocsImported documents were imported. $numOfSkippedDocs documents were skipped.");
            throw new MetadataImportSkippedDocumentsException("Documents ({$numOfSkippedDocs}) skipped during import");
        }
    }

    /**
     * @return DOMDocument
     *
     * TODO load XML from file
     */
    private function loadXML()
    {
        $output = $this->getOutput();

        $output->write('Loading XML ... ');

        try {
            if ($this->xmlFile !== null) {
                $xml = $this->xmlDocument->load($this->xmlFile);
            } else {
                $xml = $this->xmlDocument->loadXML($this->xmlString);
            }

            $output->writeln('OK');
            return $xml;
        } catch (MetadataImportInvalidXmlException $exception) {
            $output->writeln(PHP_EOL . "<ERROR>Cannot load XML document "
                . ($this->xmlFile ? $this->xmlFile : "")
                . ": make sure it is well-formed."
                . $this->xmlDocument->getErrorsPrettyPrinted());
            throw new MetadataImportInvalidXmlException('XML is not well-formed.');
        }
    }

    private function validateXml()
    {
        $output = $this->getOutput();

        $output->write('Validating XML ... ');

        try {
            $this->xmlDocument->validate();
            $output->writeln('OK');
        } catch (MetadataImportInvalidXmlException $exception) {
            $output->writeln('<error>XML document is not valid:</error>');
            throw $exception;
        }
    }

    /**
     * @param int $docId
     */
    private function appendDocIdToRejectList($docId)
    {
        $this->getOutput()->writeln("Record #{$docId} SKIPPED");
        $this->getRejectOutput()->writeln($docId);
    }

    /**
     * Allows certain fields to be kept on update.
     *
     * @param array $fields DescriptionArray of fields to keep on update
     */
    public function keepFieldsOnUpdate($fields)
    {
        $this->fieldsToKeepOnUpdate = $fields;
    }

    public function setOutput(OutputInterface|null $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function getOutput(): OutputInterface
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    public function setRejectOutput(OutputInterface|null $output): self
    {
        $this->rejectOutput = $output;
        return $this;
    }

    public function getRejectOutput(): OutputInterface
    {
        if ($this->rejectOutput === null) {
            $this->rejectOutput = new NullOutput();
        }
        return $this->rejectOutput;
    }
}
