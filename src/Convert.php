<?php

namespace Alva\CsvToSql;
/**
 * Class Convert
 *
 * @package Alva\CsvToSql
 */
class Convert
{
    /**
     * @var \StdClass
     */
    private $setting;
    /**
     * @var array
     */
    private $allowedExtension = ['csv'];
    /**
     * @var array
     */
    private $table = [
        'name'      => ''
        , 'primary' => '#name#_id'
        , 'columns' => [
            '#name#_id' => 'int UNSIGNED NOT NULL AUTO_INCREMENT',
        ],
    ];
    /**
     * @var int
     */
    private $maxInsertPacket = 500;
    /**
     * @var bool
     */
    private $issetTableColumns = false;
    /**
     * @var bool
     */
    private $issetDefaultTableColumns = false;
    /**
     * @var string
     */
    private $outputFile;
    /**
     * @var array
     */
    private $outputFiles;
    /**
     * @var int
     */
    private $countTable = 0;
    /**
     * @var string
     */
    private $nameTable;
    /**
     * @var array
     */
    private $insertPrefix = [];
    /**
     * @var int
     */
    private $insertNumber = 0;

    /**
     * Convert constructor.
     *
     * @param array  $files
     * @param string $outputDirectory
     * @param bool   $inOneTable
     * @param string $delimiterColumns
     */
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
    public function run(): array
    {
        return $this
            ->checkFiles()
            ->checkOutputDirectory()
            ->setDefaultTableName()
            ->replacePlaceholderPrimary()
            ->process()
            ->getConvertFiles();
    }

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

    /**
     * @param string $primary
     *
     * @return Convert
     */
    public function setTablePrimary(string $primary): self
    {
        $this->table['name'] = $primary;

        return $this;
    }

    /**
     * @param string $table
     *
     * @return Convert
     */
    public function setTableName(string $table): self
    {
        $this->table['name'] = $table;

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return Convert
     */
    public function setTableColumns(array $columns): self
    {
        $this->table['columns']  = $columns;
        $this->issetTableColumns = true;

        return $this;
    }

    /**
     * @return array
     */
    private function getConvertFiles(): array
    {
        return $this->outputFiles;
    }

    /**
     * @return Convert
     * @throws \Exception
     */
    private function process(): self
    {
        $fileCreated = false;

        foreach ($this->setting->files as $file) {
            $reader = new Reader($file);

            if (false === $this->setting->inOneTable) {
                $fileCreated = false;
                ++$this->countTable;
            }

            if (false === $fileCreated) {
                $fileCreated = true;
                $this->createOutputFile();
            }

            foreach ($reader->iterate("Text") as $line) {
                $row = explode($this->setting->delimiterColumns, $line);
                foreach ($row as &$value) {
                    $value = preg_replace('/^("|\')(.+)("|\')$/', '$2', trim($value));
                }
                unset($value);

                if (empty($row) || empty(array_diff($row, ['']))) continue;

                if (false === $this->issetTableColumns) {
                    $this->setDefaultTableColumns($row);
                }

                $this->getQueryInsertTable($row);
            }

            if (false === $this->setting->inOneTable) {
                $this->writeLine($this->getQueryCreateTable(), true, true);
            }
        }

        if (true === $this->setting->inOneTable) {
            $this->writeLine($this->getQueryCreateTable(), true, true);
        }
        $this->writeLine(';', false);

        return $this;
    }

    /**
     * @return Convert
     * @throws \Exception
     */
    private function checkFiles(): self
    {
        $errorMessages    = [];
        $allowedExtension = $this->allowedExtension;

        \array_walk($this->setting->files, function ($file) use (&$errorMessages, $allowedExtension) {
            if (false === is_file($file)) {
                $errorMessages[] = 'Not found file - ' . $file;
            } elseif (true !== \in_array(pathinfo($file, PATHINFO_EXTENSION), $allowedExtension, false)) {
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

    /**
     * @return Convert
     */
    private function setDefaultTableName(): self
    {
        if (empty($this->table['name'])) {
            $this->table['name'] = $this->createName();;
        }

        return $this;
    }

    /**
     * @return string
     */
    private function createName(): string
    {
        return mb_strtolower(str_replace('\\', '_', __NAMESPACE__)) . '_' . time();
    }

    /**
     * @return Convert
     */
    private function replacePlaceholderPrimary(): self
    {
        if (empty($this->table['primary'])) {
            $this->table['primary'] = '#name#_id';
        }

        $primary                = $this->table['primary'];
        $this->table['primary'] = str_replace('#name#', $this->table['name'], $primary);

        if (!empty($this->table['columns'][$primary])) {
            $this->table['columns'][$this->table['primary']] = $this->table['columns'][$primary];
            unset($this->table['columns'][$primary]);
        }

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return Convert
     */
    private function setDefaultTableColumns(array $columns): self
    {
        foreach ($columns as $key => $column) {
            $type       = (255 < \mb_strlen($column)) ? 'text' : 'varchar(255)';
            $property   = $type . ' NULL DEFAULT NULL';
            $columnName = $column;
            if (true === $this->issetDefaultTableColumns) {
                $iterator = new \ArrayIterator($this->table['columns']);
                $iterator->seek($key + 1);
                $columnName = $iterator->key();
            }

            $this->table['columns'][$columnName] = $property;
        }
        $this->issetDefaultTableColumns = true;

        return $this;
    }

    /**
     * @throws \Exception
     */
    private function createOutputFile()
    {
        $suffix     = (1 < $this->countTable) ? '_' . $this->countTable : '';
        $outputFile = $this->setting->outputDirectory . $this->createName() . $suffix . '.sql';

        $this->outputFiles[] = $outputFile;
        $this->outputFile    = $outputFile;

        $this->writeLine('', false);
    }

    /**
     * @param      $line
     * @param bool $lineBreak
     * @param bool $prepend
     *
     * @throws \Exception
     */
    private function writeLine($line, bool $lineBreak = true, bool $prepend = false)
    {
        if (true === $lineBreak) $line .= "\n";

        if (true === $prepend) {
            $handle    = fopen($this->outputFile, "r+");
            $len       = strlen($line);
            $final_len = filesize($this->outputFile) + $len;
            $cache_old = fread($handle, $len);
            rewind($handle);
            $i = 1;
            while (ftell($handle) < $final_len) {
                fwrite($handle, $line);
                $line      = $cache_old;
                $cache_old = fread($handle, $len);
                fseek($handle, $i * $len);
                $i++;
            }
        } else {
            if (false === file_put_contents($this->outputFile, $line, FILE_APPEND)) {
                throw new \Exception('Can not create or write to file - ' . $this->outputFile);
            }
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getQueryCreateTable(): string
    {

        if (false === $this->setting->inOneTable) {
            if (1 === $this->countTable) {
                $this->nameTable = $this->table['name'];
            }
            $this->table['name'] = $this->nameTable . '_' . $this->countTable;
        }

        if (empty($this->table['columns'])) {
            throw new \Exception('Not found columns in table properties');
        }

        $columns = [];
        foreach ($this->table['columns'] as $field => $property) {
            $columns[] = '`' . $field . '` ' . $property;
        }

        $query = 'DROP TABLE IF EXISTS `' . $this->table['name'] . '`;' . "\n";
        $query .= 'CREATE TABLE `' . $this->table['name'] . '` ('
            . implode(', ', $columns) . ' , PRIMARY KEY (`' . $this->table['primary'] . '`));';

        return $query;
    }

    /**
     * @param array $data
     *
     * @throws \Exception
     */
    private function getQueryInsertTable(array $data)
    {
        $query   = '';
        $columns = $this->table['columns'];
        unset($columns[$this->table['primary']]);
        $columns = array_keys($columns);

        $values = [];
        foreach ($data as $value) {
            $values[] = '"' . $this->escape($value) . '"';
        }

        if (empty($this->insertPrefix[$this->table['name']])) {
            foreach ($columns as &$column) {
                $column = '`' . $column . '`';
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

    /**
     * @param string $string
     *
     * @return string
     */
    private function escape(string $string): string
    {
        return strtr(
            $string
            , [
                "\x00" => '\x00',
                "\n"   => '\n',
                "\r"   => '\r',
                "\\"   => '\\\\',
                "'"    => "\'",
                '"'    => '\"',
                "\x1a" => '\x1a',
            ]
        );
    }
}