<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Exception;
use FilesystemIterator;
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

class Parsers extends Helper
{
    /**
     * @var string
     */
    private $parsersDir = __DIR__ . '/../../../parsers';

    public function getName(): string
    {
        return 'parsers';
    }

    public function getParsers(InputInterface $input, OutputInterface $output, bool $multiple = true): array
    {
        $rows    = [];
        $names   = [];
        $parsers = [];

        /** @var SplFileInfo $parserDir */
        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            $metadata = [];

            if (file_exists($parserDir->getPathname() . '/metadata.json')) {
                try {
                    $contents = file_get_contents($parserDir->getPathname() . '/metadata.json');

                    try {
                        $metadata = json_decode($contents, true);
                    } catch (Exception $e) {
                        $output->writeln('<error>An error occured while parsing metadata for parser ' . $parserDir->getPathname() . '</error>');
                    }
                } catch (Exception $e) {
                    $output->writeln('<error>Could not read metadata file for parser in ' . $parserDir->getPathname() . '</error>');
                }
            }

            $parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathname(),
                'metadata' => $metadata,
                'parse'    => static function (string $file, bool $benchmark = false) use ($parserDir, $output): ?array {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if ($benchmark === true) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec($parserDir->getPathname() . '/parse.sh ' . implode(' ', $args));

                    if ($result !== null) {
                        $result = trim($result);

                        try {
                            $result = json_decode($result, true);
                        } catch (Exception $e) {
                            $output->writeln('<error>' . $result . $e . '</error>');

                            return null;
                        }
                    }

                    return $result;
                },
            ];

            $rows[] = [
                $metadata['name'] ?? $parserDir->getFilename(),
                $metadata['language'] ?? '',
                $metadata['data_source'] ?? '',
            ];

            $names[$metadata['name'] ?? $parserDir->getFilename()] = $parserDir->getFilename();
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Language', 'Data Source']);
        $table->setRows($rows);
        $table->render();

        $questions = array_keys($names);
        sort($questions);

        if ($multiple === true) {
            $questions[] = 'All Parsers';
        }

        if ($multiple === true) {
            $questionText = 'Choose which parsers to use, separate multiple with commas (press enter to use all)';
            $default      = count($questions) - 1;
        } else {
            $questionText = 'Select the parser to use';
            $default      = null;
        }

        $question = new ChoiceQuestion(
            $questionText,
            $questions,
            $default
        );

        if ($multiple === true) {
            $question->setMultiselect(true);
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper  = $this->helperSet->get('question');
        $answers = $helper->ask($input, $output, $question);

        $answers         = (array) $answers;
        $selectedParsers = [];

        foreach ($answers as $name) {
            if ($name === 'All Parsers') {
                $selectedParsers = $parsers;

                break;
            }

            $selectedParsers[$names[$name]] = $parsers[$names[$name]];
        }

        ksort($selectedParsers);

        return $selectedParsers;
    }
}
