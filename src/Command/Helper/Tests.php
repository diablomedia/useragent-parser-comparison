<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Exception;
use FilesystemIterator;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\ksort;
use function Safe\sort;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Tests extends Helper
{
    /**
     * @var string
     */
    private $testDir = __DIR__ . '/../../../data/test-runs';

    public function getName(): string
    {
        return 'tests';
    }

    public function getTest(InputInterface $input, OutputInterface $output): ?string
    {
        $rows  = [];
        $names = [];

        /** @var SplFileInfo $testDir */
        foreach (new FilesystemIterator($this->testDir) as $testDir) {
            if (!is_dir($testDir->getPathname())) {
                continue;
            }

            if (!file_exists($testDir->getPathname() . '/metadata.json')) {
                $output->writeln('<error>metadata file for test in ' . $testDir->getPathname() . ' does not exist</error>');
                continue;
            }

            try {
                $contents = file_get_contents($testDir->getPathname() . '/metadata.json');

                try {
                    $metadata = json_decode($contents, true);
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while parsing metadata for test ' . $testDir->getPathname() . '</error>');
                    continue;
                }
            } catch (Exception $e) {
                $output->writeln('<error>Could not read metadata file for test in ' . $testDir->getPathname() . '</error>');
                continue;
            }

            $countRows   = max(count($metadata['tests']), count($metadata['parsers']));
            $testNames   = array_keys($metadata['tests']);
            $parserNames = array_keys($metadata['parsers']);
            $valid       = true;

            if (0 === $countRows) {
                $valid = false;
            }

            if (empty($testNames)) {
                $valid = false;
            }

            if (empty($parserNames)) {
                $valid = false;
            }

            $runName = empty($metadata['date']) ? $testDir->getFilename() : date('Y-m-d H:i:s', $metadata['date']);

            $rows[] = [
                new TableCell(($valid ? '<fg=green;bg=black>' : '<fg=red;bg=black>') . $runName . '</>', ['rowspan' => $countRows]),
                new TableCell(empty($metadata['tests']) ? '' : $metadata['tests'][$testNames[0]]['metadata']['name']),
                new TableCell(empty($metadata['tests']) ? '' : ($metadata['tests'][$testNames[0]]['metadata']['version'] ?? 'n/a')),
                new TableCell(empty($metadata['parsers']) ? '' : $metadata['parsers'][$parserNames[0]]['metadata']['name']),
                new TableCell(empty($metadata['parsers']) ? '' : ($metadata['parsers'][$parserNames[0]]['metadata']['version'] ?? 'n/a')),
            ];

            if ($countRows > 1) {
                for ($i = 1, $max = $countRows; $i < $max; $i++) {
                    $rows[] = [
                        new TableCell((empty($metadata['tests']) || !array_key_exists($i, $testNames)) ? '' : $metadata['tests'][$testNames[$i]]['metadata']['name']),
                        new TableCell((empty($metadata['tests']) || !array_key_exists($i, $testNames)) ? '' : ($metadata['tests'][$testNames[$i]]['metadata']['version'] ?? 'n/a')),
                        new TableCell((empty($metadata['parsers']) || !array_key_exists($i, $parserNames)) ? '' : $metadata['parsers'][$parserNames[$i]]['metadata']['name']),
                        new TableCell((empty($metadata['parsers']) || !array_key_exists($i, $parserNames)) ? '' : ($metadata['parsers'][$parserNames[$i]]['metadata']['version'] ?? 'n/a')),
                    ];
                }
            }

            $rows[] = new TableSeparator();

            if ($valid) {
                $names[$runName] = $testDir->getFilename();
            }
        }

        if (count($rows) < 1) {
            return null;
        }

        $table = new Table($output);
        $table->setHeaders(
            [
                [new TableCell('Name / Date', ['rowspan' => 2]), new TableCell('Test Suites', ['colspan' => 2]), new TableCell('Parsers', ['colspan' => 2])],
                [new TableCell('Name'), new TableCell('Version'), new TableCell('Name'), new TableCell('Version')]
            ]
        );

        array_pop($rows);

        $table->setRows($rows);
        $table->render();

        $questions = array_keys($names);
        sort($questions, SORT_FLAG_CASE | SORT_NATURAL);

        $questionText = 'Select the test run to use';

        $question = new ChoiceQuestion(
            $questionText,
            $questions
        );

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->helperSet->get('question');
        $answer = $helper->ask($input, $output, $question);

        return $names[$answer];
    }
}
