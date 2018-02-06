<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Compare extends Command
{
    protected function configure(): void
    {
        $this->setName('compare')
            ->setDescription('Runs tests, normalizes the results then analyzes the results')
            ->addOption('run', 'r', InputOption::VALUE_OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to a file to use as the source of useragents rather than test suites')
            ->addOption('single-ua', null, InputOption::VALUE_NONE, 'parses one useragent after another')
            ->setHelp('This command is a "meta" command that will execute the Test, Normalize and Analyze commands in order');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        // Prepare our test directory to store the data from this run
        $name = $input->getOption('run');

        $application = $this->getApplication();

        if ($application === null) {
            throw new Exception('Could not retrieve Symfony Application, aborting');
        }

        if (empty($name)) {
            $name = date('YmdHis');
        }

        if ($file) {
            $command   = $application->find('parse');
            $arguments = [
                'command'     => 'parse',
                'file'        => $file,
                'run'         => $name,
                '--no-output' => true,
            ];

            if ($input->getOption('single-ua')) {
                $arguments['--single-ua'] = true;
            }

            $parseInput = new ArrayInput($arguments);
            $returnCode = $command->run($parseInput, $output);

            if ($returnCode > 0) {
                $output->writeln('<error>There was an error executing the "parse" command, cannot continue.</error>');

                return $returnCode;
            }
        } else {
            $command   = $application->find('test');
            $arguments = [
                'command' => 'test',
                'run'     => $name,
            ];

            if ($input->getOption('single-ua')) {
                $arguments['--single-ua'] = true;
            }

            $testInput  = new ArrayInput($arguments);
            $returnCode = $command->run($testInput, $output);

            if ($returnCode > 0) {
                $output->writeln('<error>There was an error executing the "test" command, cannot continue.</error>');

                return $returnCode;
            }
        }

        $command   = $application->find('normalize');
        $arguments = [
            'command' => 'normalize',
            'run'     => $name,
        ];

        $normalizeInput = new ArrayInput($arguments);
        $returnCode     = $command->run($normalizeInput, $output);

        if ($returnCode > 0) {
            $output->writeln('<error>There was an error executing the "normalize" command, cannot continue.</error>');

            return $returnCode;
        }

        $command   = $application->find('analyze');
        $arguments = [
            'command' => 'analyze',
            'run'     => $name,
        ];

        $analyzeInput = new ArrayInput($arguments);
        $returnCode   = $command->run($analyzeInput, $output);

        if ($returnCode > 0) {
            $output->writeln('<error>There was an error executing the "analyze" command, cannot continue.</error>');

            return $returnCode;
        }

        return 0;
    }
}
