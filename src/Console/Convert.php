<?php

namespace Alva\CsvToSql\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Convert
 *
 * @package Alva\CsvToSql\Console
 */
class Convert extends Command
{
    /**
     *  Configure command
     */
    protected function configure()
    {
        $this
            ->setName('app:convert')
            ->setDescription('Convert file scv to sql')
            ->addArgument(
                'pathToCsv',
                InputArgument::REQUIRED,
                'full path to csv file or files with separator "|"'
            )
            ->addArgument(
                'inOneTable',
                InputArgument::OPTIONAL,
                'convert all to single table'
            )
            ->addArgument(
                'separatorColumns',
                InputArgument::OPTIONAL,
                'separator columns'
            )
            ->setHelp('How use (show list help)?');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Start</info>');

        $pathToCsv = $input->getArgument('pathToCsv');

        $inOneTable = $input->getArgument('inOneTable');
        $inOneTable = (null === $inOneTable) ? 1 : (int)$inOneTable;
        $inOneTable = (0 !== $inOneTable) ? true : false;

        $separatorColumns = $input->getArgument('separatorColumns');
        $separatorColumns = (null === $separatorColumns) ? ';' : $separatorColumns;
        $files            = $this->getFiles($pathToCsv);

        $output->writeln('');
        $output->writeln('<comment>Params:</comment>');
        $output->writeln('<info><pathToCsv></info>: - ' . $pathToCsv);
        $output->writeln('<info><inOneTable></info>: - ' . $inOneTable);
        $output->writeln('<info><separatorColumns></info>: - ' . $separatorColumns);
        $output->writeln('');

        $output->writeln('<comment>Convert files:</comment>');
        $output->writeln($files);
        $output->writeln('');

        $output->writeln('<info>Run ...</info>');

        try {
            $convertFiles = (new \Alva\CsvToSql\Convert(
                $files
                , OUTPUT_DIRECTORY
                , $inOneTable
                , $separatorColumns
            ))->run();

            $output->writeln('');
            $output->writeln('<comment>Sql files:</comment>');
            $output->writeln($convertFiles);
            $output->writeln('');

            $output->writeln('<info>Success</info>');
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * @param string $pathToCsv
     *
     * @return array
     */
    private function getFiles(string $pathToCsv): array
    {
        if (false !== \mb_strpos($pathToCsv, '|')) {
            $files = \explode('|', $pathToCsv);
        } else {
            $files[] = $pathToCsv;
        }

        return $files;
    }
}