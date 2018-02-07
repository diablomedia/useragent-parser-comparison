<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Seld\JsonLint\JsonParser;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use SplFileInfo;

class Parsers extends Helper
{
    private $parsersDir = __DIR__ . '/../../../parsers';

    public function getName()
    {
        return 'parsers';
    }

    public function getParsers(InputInterface $input, OutputInterface $output, bool $multiple = true): array
    {
        $jsonParser = new JsonParser();

        $rows  = [];
        $names = [];
        $parsers = [];

        foreach (scandir($this->parsersDir) as $dir) {
            if (in_array($dir, ['.', '..'])) {
                continue;
            }

            $parserDir = new SplFileInfo($this->parsersDir . '/' . $dir);

            if (file_exists($parserDir->getPathName() . '/metadata.json')) {
                $metadata = $jsonParser->parse(
                    file_get_contents($parserDir->getPathName() . '/metadata.json'),
                    JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                );
            } else {
                $metadata = [];
            }

            $parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathName(),
                'metadata' => $metadata,
                'parse'    => static function ($file, $benchmark = false) use ($parserDir, $jsonParser) {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if ($benchmark === true) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec($parserDir->getPathName() . '/parse.sh ' . implode(' ', $args));

                    if (null !== $result) {
                        $result = $jsonParser->parse(
                            trim($result),
                            JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                        );
                    }

                    return $result;
                },
                'warm-up' => static function () use ($parserDir, $jsonParser) {
                    $result = shell_exec($parserDir->getPathName() . '/warm-up.sh');

                    if (null !== $result) {
                        $result = $jsonParser->parse(
                            trim($result),
                            JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                        );
                    }

                    return $result;
                },
                'parse-ua'    => static function ($useragent, $benchmark = false) use ($parserDir, $jsonParser) {
                    $args = [
                        '--ua=' . escapeshellarg($useragent),
                    ];
                    if (true === $benchmark) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec($parserDir->getPathName() . '/parse-ua.sh ' . implode(' ', $args));

                    if (null !== $result) {
                        $result = $jsonParser->parse(
                            trim($result),
                            JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                        );
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

        $helper = $this->helperSet->get('question');

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

        $answers = $helper->ask($input, $output, $question);

        $answers = (array) $answers;
        $selectedParsers = [];

        foreach ($answers as $name) {
            if ('All Parsers' === $name) {
                $selectedParsers = $parsers;

                break;
            }

            $selectedParsers[$names[$name]] = $parsers[$names[$name]];
        }

        return $selectedParsers;
    }
}
