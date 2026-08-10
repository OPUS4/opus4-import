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

use Exception;
use Opus\Common\Console\Helper\ProgressBar;
use Opus\Common\LoggingTrait;
use Opus\Common\Model\NotFoundException;
use Opus\Import\ImportFormatInterface;
use Opus\Import\StoreDocument;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * Imports OPUS-XML on command line.
 *
 * TODO Do we need a separate reject log? NO remove
 * TODO support additional formats
 * TODO how to do reject log (independent of if it is really needed)?
 */
class MetadataImport
{
    use LoggingTrait;

    /** @var OutputInterface */
    private $output;

    /** @var OutputInterface */
    private $rejectOutput;

    /** @var ProgressBar */
    private $progressBar;

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
    public function import(string $data): void
    {
        $parser = $this->getParser();
        $parser->parse($data);
        $this->process($parser);
    }

    /**
     * @throws MetadataImportSkippedDocumentsException
     *
     * TODO support configurable DocumentProcessor chain
     * TODO review $parser as parameter
     */
    protected function process(ImportFormatInterface $parser)
    {
        $output = $this->getOutput();

        $processor = new StoreDocument();

        // TODO setup progress bar and such
        $importedCount = 0;
        $skippedCount  = 0;

        $this->importStarted($parser->getDocumentCount());

        do {
            $document = null;

            // TODO how to get line no, how to get old ID or doc ID if not found (part of exception?)

            try {
                $document = $parser->next();
            } catch (NotFoundException $nfe) {
                $oldId = $nfe->getModelId();
                $this->getOutput()->writeln(
                    "<error>Error updating document: " . $nfe->getMessage() . "</error>"
                );
                $this->appendDocIdToRejectList($parser->getCurrentLineNo());
                continue;
            } catch (Exception $ex) {
                $this->getOutput()->writeln(
                    "<error>Error processing document: " . $ex->getMessage() . "</error>"
                );
                $this->appendDocIdToRejectList($parser->getCurrentLineNo());
                continue;
            }

            try {
                $processor->processDocument($document);
            } catch (Exception $ex) {
                $output->writeln("<error>Error saving imported document #{$oldId}: " . $ex->getMessage() . "</error>");
                $this->appendDocIdToRejectList($parser->getCurrentLineNo());
                continue;
            }

            if (null !== $document) {
                $importedCount++;
                $docId = $document->getId();
                $this->importDocumentSuccess($docId);
            } else {
                $this->getOutput()->writeln("... Document {$docId} imported successfully"); // TODO mention oldId?
                $skippedCount++;
                $this->importDocumentSkipped(null); // TODO  $parser->getCurrentLineNo()
            }
        } while ($document !== null);

        $this->importFinished();

        if ($skippedCount === 0) {
            $output->writeln("Import finished successfully. {$importedCount} documents were imported.");
        } else {
            $output->writeln(sprintf(
                "Import finished. %s documents were imported. %s documents were skipped.",
                $importedCount,
                $skippedCount,
            ));
            throw new MetadataImportSkippedDocumentsException("Documents ({$skippedCount}) skipped during import");
        }
    }

    public function importStarted(int $docCount): void
    {
        $this->progressBar = new ProgressBar($this->getOutput(), $docCount);
        $this->progressBar->start();
    }

    public function importFinished(): void
    {
        $this->progressBar->finish();
    }

    public function importDocumentSuccess(int $docId): void
    {
        $this->progressBar->advance();
    }

    public function importDocumentSkipped(int $docId): void
    {
        $this->progressBar->advance();
    }

    /**
     * TODO support $format parameter
     */
    protected function getParser(?string $format = null): ImportFormatInterface
    {
        return new OpusXmlParser();
    }

    public function setOutput(?OutputInterface $output): self
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

    public function setRejectOutput(?OutputInterface $output): self
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

    protected function eventStartProcessingDocument(?string $oldId, ?int $lineNo)
    {
        // TODO there is not always an old ID
        $this->getOutput()->writeln("Start processing of record #{$oldId} (line {$lineNo})...");
    }

    protected function appendDocIdToRejectList(int $docId): void
    {
        $this->getOutput()->writeln("Record #{$docId} SKIPPED");
        $this->getRejectOutput()->writeln($docId);
    }
}
