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
use Opus\Common\Model\NotFoundException;
use Opus\Import\AbstractImporter;
use Opus\Import\ImportFormatInterface;
use Opus\Import\StoreDocument;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * Imports OPUS-XML on command line.
 *
 * TODO describe behaviour of console import
 *
 * TODO Do we need a separate reject log? NO remove
 * TODO how to do reject log (independent of if it is really needed)?
 * TODO support configurable DocumentProcessor chain
 * TODO should this implement ImportErrorHandlerInterface?
 */
class MetadataImport extends AbstractImporter
{
    /** @var OutputInterface */
    private $rejectOutput;

    /** @var ProgressBar */
    private $progressBar;

    /** @var ?string[] */
    private $fieldsToKeepOnUpdate;

    /**
     * @throws MetadataImportSkippedDocumentsException
     *
     * TODO review $parser as parameter
     */
    protected function process(ImportFormatInterface $parser): void
    {
        $output = $this->getOutput();

        $processor = new StoreDocument();
        $processor->setOutput($output);

        // TODO setup progress bar and such
        $importedCount = 0;
        $skippedCount  = 0;

        $this->importStarted($parser->getDocumentCount());

        do {
            $document = null;

            // TODO how to get line no, how to get old ID or doc ID if not found (part of exception?)

            try {
                // $this->getOutput()->writeln("Start processing of record #{$oldId} (line {$lineNo})...");
                $document = $parser->next();
            } catch (NotFoundException $nfe) {
                // $oldId = $nfe->getModelId();
                $this->getOutput()->writeln(
                    "<error>Error updating document: " . $nfe->getMessage() . "</error>"
                );
                $this->appendDocIdToRejectList($parser->getCurrentLineNo());
                $skippedCount++;
                continue;
            } catch (Exception $ex) {
                $this->getOutput()->writeln(
                    "<error>Error processing document: " . $ex->getMessage() . "</error>"
                );
                $this->appendDocIdToRejectList($parser->getCurrentLineNo());
                $skippedCount++;
                continue;
            }

            if (null === $document) {
                // Import finished
                break;
            }

            try {
                $processor->processDocument($document);
            } catch (Exception $ex) {
                $output->writeln("<error>Error saving imported document #{$oldId}: " . $ex->getMessage() . "</error>");
                $this->appendDocIdToRejectList($parser->getCurrentLineNo());
                continue;
            }

            $importedCount++;
            $docId = $document->getId();
            $this->getOutput()->writeln("... Document {$docId} imported successfully"); // TODO mention oldId?
            $this->importDocumentSuccess($docId);
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

    public function importDocumentSkipped(int $lineNo): void
    {
        $this->progressBar->advance();
    }

    protected function getParser(?string $format = null): ImportFormatInterface
    {
        $parser = parent::getParser($format);
        $parser->setFieldsToKeepOnUpdate($this->getFieldsToKeepOnUpdate());
        return $parser;
    }

    public function getOutput(): OutputInterface
    {
        $output = $this->getOutput();

        if ($output === null) {
            $output = new ConsoleOutput();
            $this->setOutput($output);
        }

        return $output;
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

    public function setFieldsToKeepOnUpdate(?array $fieldsToKeepOnUpdate): self
    {
        $this->fieldsToKeepOnUpdate = $fieldsToKeepOnUpdate;
        return $this;
    }

    public function getFieldsToKeepOnUpdate(): ?array
    {
        return $this->fieldsToKeepOnUpdate;
    }

    protected function appendDocIdToRejectList(int $docId): void
    {
        $this->getOutput()->writeln("Record #{$docId} SKIPPED");
        $this->getRejectOutput()->writeln($docId);
    }
}
