<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;

class Normalize extends Helper
{
    private $mapDir = __DIR__ . '/../../../mappings';

    public function getName()
    {
        return 'normalize';
    }

    public function normalize(array $parsed, string $source): array
    {
        $normalized = [];

        $mappings = [];

        if (!empty($source)) {
            if (file_exists($this->mapDir . '/' . $source . '.php')) {
                $mappings = include $this->mapDir . '/' . $source . '.php';
            }
        }

        foreach ($parsed as $section => $properties) {
            $normalized[$section] = [];

            foreach ($properties as $key => $value) {
                if ($value !== null) {
                    if ($key === 'version') {
                        $value = $this->truncateVersion(mb_strtolower((string) $value));
                    } elseif ($value === false) {
                        $value = '';
                    } elseif ($value === true) {
                        $value = '1';
                    } else {
                        $value = preg_replace('|[^0-9a-z]|', '', mb_strtolower((string) $value));
                    }

                    // Special Windows normalization for parsers that don't differntiate the version of windows
                    // in the name, but use the version.
                    if ($section === 'platform' && $key === 'name' && $value === 'windows') {
                        if (!empty($parsed['platform']['version'])) {
                            $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['platform']['version']));
                        }
                    }

                    if (isset($mappings[$section][$key])) {
                        if (isset($mappings[$section][$key][$value])) {
                            $value = $mappings[$section][$key][$value];
                        }
                    }
                }

                $normalized[$section][$key] = $value;
            }
        }

        return $normalized;
    }

    private function truncateVersion(string $version): string
    {
        $version      = str_replace('_', '.', $version);
        $versionParts = explode('.', $version);
        $versionParts = array_slice($versionParts, 0, 2);

        return implode('.', $versionParts);
    }
}
