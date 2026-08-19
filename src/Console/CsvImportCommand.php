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
use Opus\Common\UserRole;
use Opus\Import\CsvImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function is_readable;
use function sprintf;

/**
 * Imports CSV as OPUS 4 documents.
 *
 * TODO argument CSV file
 * TODO
 */
class CsvImportCommand extends Command
{
    const OPTION_NO_HEADER     = 'no-header';
    const OPTION_FULLTEXT_PATH = 'fulltext-path';
    const ARGUMENT_FILE        = 'file';

    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $help = <<<EOT
The <fg=green>import:csv</> command can be used to import documents
from CSV files. 
EOT;

        $this->setName('import:csv')
            ->setDescription('Import CSV as documents')
            ->setHelp($help)
            ->addOption(
                self::OPTION_NO_HEADER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Do not ignore first line of CSV'
            )
            ->addOption(
                self::OPTION_FULLTEXT_PATH,
                null,
                InputOption::VALUE_OPTIONAL,
                'Location of fulltext files'
            )
            ->addArgument(
                self::ARGUMENT_FILE,
                InputArgument::REQUIRED,
                'CSV file to import'
            );
    }

    /**
     * @throws NotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csvFilePath = $input->getArgument(self::ARGUMENT_FILE);

        if (! file_exists($csvFilePath)) {
            $output->writeln(sprintf('<error>File %s does not exists.</error>', $csvFilePath));
            return Command::FAILURE;
        }

        $importer = new CsvImporter();
        $importer->setOutput($output);
        $importer->setIgnoreHeader(! $input->getOption(self::OPTION_NO_HEADER));

        $fulltextPath = $input->getOption(self::OPTION_FULLTEXT_PATH);

        if ($fulltextPath !== null) {
            if (! is_readable($fulltextPath)) {
                $output->writeln('<error>Path ' . $fulltextPath . ' is not readable -- check path or permissions.</error>');
            } else {
                $importer->setFulltextPath($fulltextPath);
                $importer->setGuestRole(UserRole::fetchByName('guest'));
            }
        }

        $importer->import($csvFilePath);

        return self::SUCCESS;
    }
}
