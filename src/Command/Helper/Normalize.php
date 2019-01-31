<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;

class Normalize extends Helper
{
    /**
     * @var string
     */
    private const MAP_FILE = __DIR__ . '/../../../mappings/mappings.php';

    public function getName(): string
    {
        return 'normalize';
    }

    public function normalize(array $parsed): array
    {
        $normalized = [];
        $mappings   = [];

        if (file_exists(self::MAP_FILE)) {
            $mappings = include self::MAP_FILE;
        }

        $sections = ['browser', 'platform', 'device'];

        foreach ($sections as $section) {
            if (!array_key_exists($section, $parsed)) {
                continue;
            }

            $normalized[$section] = [];
            $properties           = $parsed[$section];

            foreach ($properties as $key => $value) {
                if ($value === null) {
                    $normalized[$section][$key] = $value;
                    continue;
                }

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

                if ($section === 'platform' && $key === 'name' && $value === 'windowsphone') {
                    if (!empty($parsed['platform']['version'])) {
                        $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['platform']['version']));
                    }
                }

                if (isset($mappings[$section][$key])
                    && is_array($mappings[$section][$key])
                ) {
                    $v = $mappings[$section][$key];
                } else {
                    $v = [];
                }

                if (is_array($v) && is_string($value) && array_key_exists($value, $v)) {
                    $value = $v[$value];
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
