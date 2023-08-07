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
 * @copyright   Copyright (c) 2023, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import;

use Opus\Common\ConfigTrait;
use Opus\Common\DocumentInterface;
use Zend_Config_Ini;

use function class_exists;
use function filter_var;
use function is_array;
use function is_readable;

use const FILTER_VALIDATE_BOOLEAN;

class ImportRules
{
    use ConfigTrait;

    public const IMPORT_RULE_CLASS_PREFIX = 'Opus\\Import\\Rules\\';

    /** @var ImportRuleInterface[] */
    private $rules = [];

    /**
     * Loads rules from configuration.
     */
    public function init()
    {
        $config = $this->getConfig();

        if (
            ! isset($config->sword->enableImportRules) ||
            ! filter_var($config->sword->enableImportRules, FILTER_VALIDATE_BOOLEAN)
        ) {
            // TODO does this belong here? There should not be anything SWORD specific here!
            return; // don't load any rules
        }

        $rulesConfig = null;

        if (isset($config->import->rulesConfigFile)) {
            $rulesConfigFile = $config->import->rulesConfigFile;
            if (is_readable($rulesConfigFile)) {
                $rulesConfig = new Zend_Config_Ini($rulesConfigFile);
                $rulesConfig = $rulesConfig->toArray();
            }
        }

        // Get rules from main configuration as fallback
        if ($rulesConfig === null && isset($config->import->rules)) {
            $rulesConfig = $config->import->rules->toArray();
        }

        if (is_array($rulesConfig)) {
            foreach ($rulesConfig as $name => $options) {
                $type = $options['type'];

                $rule = $this->createRule($type, $options);

                if ($rule !== null) {
                    $this->rules[] = $rule;
                }
            }
        }
    }

    /**
     * @return ImportRuleInterface[]
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param string $type
     * @param array  $options
     * @return ImportRuleInterface|null
     */
    public function createRule($type, $options)
    {
        if (class_exists($type)) {
            $ruleClass = $type;
        } else {
            $ruleClass = self::IMPORT_RULE_CLASS_PREFIX . $type;
            if (! class_exists($ruleClass)) {
                // TODO throw exception
                return null;
            }
        }

        $rule = new $ruleClass();
        $rule->setOptions($options);

        return $rule;
    }

    /**
     * @param DocumentInterface $document
     */
    public function apply($document)
    {
        foreach ($this->rules as $rule) {
            $rule->apply($document);
        }
    }
}
