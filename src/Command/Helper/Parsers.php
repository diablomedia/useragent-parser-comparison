<?php

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Parsers extends Helper
{
    protected $parsers         = [];
    protected $selectedParsers = [];
    protected $parsersDir      = __DIR__ . '/../../../parsers';

    public function getName()
    {
        return 'parsers';
    }

    public function getParsers(InputInterface $input, OutputInterface $output, $multiple = true)
    {
        foreach (new \FilesystemIterator($this->parsersDir) as $parserDir) {
            if (file_exists($parserDir->getPathName() . '/metadata.json')) {
                $metadata = json_decode(file_get_contents($parserDir->getPathName() . '/metadata.json'), true);
            } else {
                $metadata = [];
            }

            $this->parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathName(),
                'metadata' => $metadata,
                'parse'    => function ($file, $benchmark = false) use ($parserDir) {
                    $args = [
                        escapeshellarg($file)
                    ];
                    if ($benchmark === true) {
                        $args[] = '--benchmark';
                    }

                    $file = realpath(getcwd() . '/' . $file);

                    $result = trim(shell_exec($parserDir->getPathName() . '/parse ' . implode(' ', $args)));

                    $result = json_decode($result, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return null;
                    }

                    return $result;
                }
            ];
        }

        $rows  = [];
        $names = [];

        foreach ($this->parsers as $name => $data) {
            $rows[] = [
                isset($data['metadata']['name']) ? $data['metadata']['name'] : $name,
                isset($data['metadata']['language']) ? $data['metadata']['language'] : '',
                isset($data['metadata']['data_source']) ? $data['metadata']['data_source'] : '',
            ];
            $names[isset($data['metadata']['name']) ? $data['metadata']['name'] : $name] = $name;
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

        foreach ($answers as $name) {
            if ($name == 'All Parsers') {
                $this->selectedParsers = $this->parsers;
                break;
            }

            $this->selectedParsers[$names[$name]] = $this->parsers[$names[$name]];
        }

        return $this->selectedParsers;
    }
}
