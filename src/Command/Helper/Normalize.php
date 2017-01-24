<?php

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Normalize extends Helper
{
    protected $mapDir = __DIR__ . '/../../../mappings';

    public function getName()
    {
        return 'normalize';
    }

    public function normalize($parsed, $source)
    {
        $normalized = [];

        $mappings = [];

        if (!empty($source)) {
            if (file_exists($this->mapDir . '/' . $source . '.php')) {
                $mappings = include $this->mapDir . '/' . $source . '.php';

                $newMappings = [];
                // change the keys in the mappings file to be word boundary regular expressions
                // so that we can do an exact match on the search string, not parial
                foreach ($mappings as $section => $properties) {
                    $newMappings[$section] = [];

                    foreach ($properties as $property => $replacements) {
                        $keys = array_keys($mappings[$section][$property]);
                        array_walk($keys, function (&$search) {
                            $search = '/\b' . preg_quote($search) . '\b/u';
                        });
                        $values = array_values($mappings[$section][$property]);

                        $newMappings[$section][$property] = array_combine($keys, $values);
                    }
                }

                $mappings = $newMappings;
                unset($newMappings);
            }
        }

        foreach ($parsed as $section => $properties) {
            $normalized[$section] = [];

            foreach ($properties as $key => $value) {
                if ($value !== null) {
                    if ($key == 'version') {
                        $value = $this->truncateVersion(strtolower($value));
                    } else {
                        $value = preg_replace('|[^0-9a-z]|', '', strtolower($value));
                    }

                    // Special Windows normalization for parsers that don't differntiate the version of windows
                    // in the name, but use the version.
                    if ($section == 'platform' && $key == 'name' && $value == 'windows') {
                        if (!empty($parsed['platform']['version'])) {
                            $value .= preg_replace('|[^0-9a-z.]|', '', strtolower($parsed['platform']['version']));
                        }
                    }

                    if (isset($mappings[$section][$key])) {
                        $value = preg_replace(
                            array_keys($mappings[$section][$key]),
                            array_values($mappings[$section][$key]),
                            $value
                        );
                    }
                }

                $normalized[$section][$key] = $value;
            }
        }

        return $normalized;
    }

    protected function truncateVersion($version)
    {
        $versionParts = explode('.', $version);
        $versionParts = array_slice($versionParts, 0, 2);

        return implode('.', $versionParts);
    }
}
