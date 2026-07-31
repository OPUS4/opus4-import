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

namespace Opus\Import\Console;

use Opus\Common\Model\NotFoundException;
use Opus\Import\Xml\MetadataImport;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

use function file_exists;
use function fopen;
use function sprintf;

/**
 * Imports document metadata from OPUS XML file.
 *
 * TODO output info on console
 * TODO write some info to optional log file (rejected documents)
 * TODO verbosity level?
 * TODO progress bar
 * TODO support dryRun mode?
 *
 * $consoleConf = ['lineFormat' => '[%1$s] %4$s'];
 * $logfileConf = ['append' => false, 'lineFormat' => '%4$s'];
 */
class ImportCommand extends Command
{
    const OPTION_REJECT_FILE = 'reject-log';

    const ARGUMENT_FILE = 'file';

    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $help = <<<EOT
The <fg=green>import:import</> command can be used to import document metadata from OPUS XML files.
EOT;

        $this->setName('import:import')
            ->setDescription('Import OPUS XML file')
            ->setHelp($help)
            ->addOption(
                self::OPTION_REJECT_FILE,
                null,
                InputOption::VALUE_OPTIONAL,
                'File for logging rejected documents'
            )
            ->addArgument(
                self::ARGUMENT_FILE,
                InputArgument::REQUIRED,
                'File to import'
            );
    }

    /**
     * @throws NotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $xmlFile = $input->getArgument(self::ARGUMENT_FILE);

        if (! file_exists($xmlFile)) {
            $output->writeln('<error>XML file not found</error>');
            return self::FAILURE;
        }

        $importer = new MetadataImport();
        $importer->setOutput($output);

        $rejectLogPath = $input->getOption(self::OPTION_REJECT_FILE);
        if ($rejectLogPath !== null) {
            $rejectLog = new StreamOutput(fopen($rejectLogPath, 'w', false));
            $importer->setRejectOutput($rejectLog);
        }

        try {
            $importer->import($xmlFile, true);
        } catch (MetadataImportSkippedDocumentsException $ex) {
            $output->writeln(sprintf('<error>%s</error>', $ex->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
