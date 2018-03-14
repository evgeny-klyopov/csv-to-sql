<?php
/**
 * Created by PhpStorm.
 * User: leado
 * Date: 13.03.2018
 * Time: 22:54
 */

namespace Alva\CsvToSql;



class Convert
{
    private $setting;
    private $allowedExtension = ['csv'];

    private $table = [
        'name'       => ''
        , 'primary' => '#name#_id'
        , 'columns'  => [
            '#name#_id' => 'int UNSIGNED NOT NULL AUTO_INCREMENT',
        ],
    ];
    private $maxInsertPacket = 500;

    /**
     * @param int $maxInsertPacket
     *
     * @return Convert
     */
    public function setMaxInsertPacket(int $maxInsertPacket): self
    {
        $this->maxInsertPacket = $maxInsertPacket;

        return $this;
    }

    public function setTablePrimary(string $primary): self
    {
        $this->table['name'] = $primary;

        return $this;
    }

    public function setTableName(string $table): self
    {
        $this->table['name'] = $table;

        return $this;
    }

    public function setTableColumns(array $columns): self
    {
        $this->table['columns']  = $columns;
        $this->issetTableColumns = true;

        return $this;
    }

    public function __construct(array $files, string $outputDirectory, bool $inOneTable = true, string $delimiterColumns = ';')
    {
        $this->setting                   = new \StdClass();
        $this->setting->files            = $files;
        $this->setting->inOneTable       = $inOneTable;
        $this->setting->outputDirectory  = $outputDirectory;
        $this->setting->delimiterColumns = $delimiterColumns;
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        $this
            ->checkFiles()
            ->checkOutputDirectory()
            ->setDefaultTableName()
            ->replacePlaceholderPrimary()
            ->process()
        ;
    }

    private $issetTableColumns = false;

    /**
     * @return Convert
     * @throws \Exception
     */
    private function process(): self
    {
        $fileCreated = false;
//        $tableCreated = false;

        foreach ($this->setting->files as $file) {
            $reader = new Reader($file);
            if (false === $this->setting->inOneTable) {
                $fileCreated = false;
                ++$this->countTable;
//                $tableCreated = false;
            }

            if (false === $fileCreated) {
                $this->createOutputFile();
            }



            foreach ($reader->iterate("Text") as $line) {
                $row = explode($this->setting->delimiterColumns, $line);
                foreach ($row as &$value) {
                    $value = preg_replace('/^("|\')(.+)("|\')$/', '$2', trim($value));
                }
                unset($value);

                if (empty($row)) continue;

                if (false === $this->issetTableColumns) {
                    $this->setDefaultTableColumns($row);
                }

//                $appendInsert = false;
//                if (0 === $insert % 500) {
//                    $appendInsert = true;
//                    $insert = 0;
//                }

                $this->getQueryInsertTable($row);
//                $this->writeLine($this->getQueryInsertTable($row));

//                $this->writeLine($this->getQueryInsertTable($row));

//                if (false === $tableCreated) {
//                    $this->writeLine($this->getQueryTable());
//                }


//                die();
            }

            if (false === $this->setting->inOneTable) {
                $this->getQueryCreateTable();
            }

            die();
        }
    }
    /**
     * @return Convert
     * @throws \Exception
     */
    private function checkFiles(): self
    {
        $errorMessages = [];
        $allowedExtension = $this->allowedExtension;

        \array_walk($this->setting->files, function($file) use (&$errorMessages, $allowedExtension) {
            if (false === is_file($file)) {
                $errorMessages[] = 'Not found file - ' . $file;
            } else if (true !== \in_array(pathinfo($file, PATHINFO_EXTENSION), $allowedExtension, false)) {
                $errorMessages[] = 'Not allowed extension for file - ' . $file;
            }
        });

        if (!empty($errorMessages)) {
            throw new \Exception(implode("\n", $errorMessages));
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    private function checkOutputDirectory(): self
    {
        if (false === is_dir($this->setting->outputDirectory)) {
            if (!mkdir($this->setting->outputDirectory) && !is_dir($this->setting->outputDirectory)) {
                throw new \Exception(sprintf('Directory "%s" was not created', $this->setting->outputDirectory));
            }
        }

        return $this;
    }

    private function setDefaultTableName(): self
    {
        if (empty($this->table['name'])) {
            $this->table['name'] = $this->createName();
            ;
        }

        return $this;
    }

    private function createName(): string
    {
        return mb_strtolower(str_replace('\\', '_', __NAMESPACE__)) . '_' . time();
    }

    private function replacePlaceholderPrimary(): self
    {
        if (empty($this->table['primary'])) {
            $this->table['primary'] = '#name#_id';
        }

        $primary = $this->table['primary'];
        $this->table['primary'] = str_replace('#name#', $this->table['name'], $primary);

        if (!empty($this->table['columns'][$primary])) {
            $this->table['columns'][$this->table['primary']] = $this->table['columns'][$primary];
            unset($this->table['columns'][$primary]);
        }

        return $this;
    }

    private function setDefaultTableColumns(array $columns): self
    {
        foreach ($columns as $column) {
            $type = (255 < \mb_strlen($column)) ? 'text' : 'varchar(255)';

            $this->table['columns'][$column] = $type . ' NULL DEFAULT NULL';
        }

        return $this;
    }

    private $outputFile;

    /**
     * @throws \Exception
     */
    private function createOutputFile()
    {
        $this->outputFile = $this->setting->outputDirectory . $this->createName() . '.sql';
        $this->writeLine('', false);
    }

    /**
     * @param      $line
     * @param bool $lineBreak
     *
     * @throws \Exception
     */
    private function writeLine($line, bool $lineBreak = true)
    {
        if (true === $lineBreak) $line .= "\n";

        if (false === file_put_contents($this->outputFile, $line, FILE_APPEND)) {
           throw new \Exception('Can not create or write to file - ' . $this->outputFile);
        }
    }

    private $countTable = 1;

    /**
     * @return string
     * @throws \Exception
     */
    private function getQueryCreateTable(): string
    {
        if (false === $this->setting->inOneTable) {
            $this->table['name'] .= '_' . $this->countTable;
        }

        if (empty($this->table['columns'])) {
            throw new \Exception('Not found columns in table properties');
        }

        $columns = [];
        foreach ($this->table['columns'] as $field => $property) {
            $columns[] = '`' . $field . '` ' . $property;
        }

        $query = 'DROP TABLE `' . $this->table['name'] . '`;' . "\n";
        $query .= 'CREATE TABLE `' . $this->table['name'] . '` ('
            . implode(', ', $columns) . ' , PRIMARY KEY (`' . $this->table['primary'] . '`));'
        ;

        return $query;
    }

    private $insertPrefix = [];
    private $insertNumber = 0;

    private function getQueryInsertTable(array $data)
    {
        $query = '';
        $columns = $this->table['columns'];
        unset($columns[$this->table['primary']]);
        $columns = array_keys($columns);


        $values = [];
        foreach ($data as $value) {
            $values[] = '"' . $this->escape($value) . '"';
        }

        if (empty($this->insertPrefix[$this->table['name']])) {
            foreach ($columns as &$column) {
                $column =  '`' . $column . '`';
            }
            unset($column);

            $this->insertPrefix[$this->table['name']] = 'INSERT INTO ' . $this->table['name'] . ' (' . implode(', ', $columns) . ') VALUES ';
        }

        if (0 === $this->insertNumber % $this->maxInsertPacket) {
            if (0 < $this->insertNumber) {
                $this->writeLine(';');
            }
            $query .= $this->insertPrefix[$this->table['name']];
        } else {
            $query .= ', ';
        }

        $query .= '(' . implode(',', $values) . ')';


        $this->writeLine($query, false);
        ++$this->insertNumber;
    }

    private function escape(string $string): string
    {
        return strtr(
            $string
            , [
                "\x00"=>'\x00',
                "\n"=>'\n',
                "\r"=>'\r',
                "\\"=>'\\\\',
                "'"=>"\'",
                '"'=>'\"',
                "\x1a"=>'\x1a'
            ]
        );
    }


    //CREATE TABLE `NewTable` (
//`id`  int UNSIGNED NOT NULL AUTO_INCREMENT ,
//`file`  varchar(255) NULL DEFAULT NULL ,
//PRIMARY KEY (`id`)
//)
//;
//
}