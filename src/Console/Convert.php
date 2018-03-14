<?php

namespace Alva\CsvToSql\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class Convert extends Command
{
    private $allowedExtension = ['csv'];

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
                'full path to csv file'
            )
            ->addArgument(
                'startRow',
                InputArgument::OPTIONAL,
                'convert all to single file'
            )
            ->addArgument(
                'inOneFile',
                InputArgument::OPTIONAL,
                'convert all to single file'
            )
            ->addArgument(
                'separatorColumns',
                InputArgument::OPTIONAL,
                'separator columns'
            )
            ->setHelp('How use (show list help)?')
        ;
    }



    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pathToCsv = $input->getArgument('pathToCsv');

        $inOneFile = $input->getArgument('inOneFile');
        $inOneFile = (null === $inOneFile) ? 1 : (int)$inOneFile;
        $inOneFile = (0 !== $inOneFile) ? true : false;

        $separatorColumns = $input->getArgument('separatorColumns');
        $separatorColumns = (null === $separatorColumns) ? ';' : $separatorColumns;

//        $convertFiles = $this->getFiles($pathToCsv);
//
//        var_dump($pathToCsv);
//        var_dump($inOneFile);
//        var_dump($separatorColumns);
        try {
            (new \Alva\CsvToSql\Convert(
                $this->getFiles($pathToCsv)
                , OUTPUT_DIRECTORY
                , $inOneFile
                , $separatorColumns
            ))->run();
        } catch(\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }



        return ;
        // get argument
        $pathToCsv = $input->getArgument('pathToCsv');
        $inOneFile = $input->getArgument('inOneFile');

        $inOneFile = (null === $inOneFile) ? 1 : (int) $inOneFile;
        $inOneFile = (0 !== $inOneFile) ? 1 : 0;

        $separatorColumns = $input->getArgument('separatorColumns');
        $separatorColumns = (null === $separatorColumns) ? ';' : $separatorColumns;

        $convertFiles = $this->getFiles($pathToCsv);

        $this
            ->checkFiles($convertFiles)
            ->checkOutputDirectory()
            ->convertToSql($convertFiles, $inOneFile, $separatorColumns)

        ;
    }

    /**
     * @param string $pathToCsv
     *
     * @return array
     */
    private function getFiles(string $pathToCsv) : array
    {
        if (false !== \mb_strpos($pathToCsv, '|')) {
            $files = \explode('|', $pathToCsv);
        } else {
            $files[] = $pathToCsv;
        }

        return $files;
    }

    /**
     * @param array $files
     *
     * @return $this
     * @throws \RuntimeException
     */
    private function checkFiles(array $files): self
    {
        $errorMessages = [];
        $allowedExtension = $this->allowedExtension;

        \array_walk($files, function($file) use (&$errorMessages, $allowedExtension) {
            if (false === is_file($file)) {
                $errorMessages[] = 'Not found file - ' . $file;
            } else if (true !== \in_array(pathinfo($file, PATHINFO_EXTENSION), $allowedExtension, false)) {
                $errorMessages[] = 'Not allowed extension for file - ' . $file;
            }
        });

        if (!empty($errorMessages)) {
            throw new \RuntimeException(implode("\n", $errorMessages));
        }

        return $this;
    }

    private function convertToSql(array $files, int $inOneFile, string $separatorColumns): self
    {
        foreach ($files as $file) {
            $reader = new \Alva\CsvToSql\Reader($file);

            foreach ($reader->iterate("Text") as $line) {
                $row = explode($separatorColumns, $line);

                echo '<pre>';
                    print_r($row);
                echo '</pre>';
                die();

            }

die();
        }


        return $this;
    }

    /**
     * @return $this
     * @throws \RuntimeException
     */
    private function checkOutputDirectory(): self
    {
        if (false === is_dir(OUTPUT_DIRECTORY)) {
            if (!mkdir(OUTPUT_DIRECTORY) && !is_dir(OUTPUT_DIRECTORY)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', OUTPUT_DIRECTORY));
            }
        }

        return $this;
    }
}