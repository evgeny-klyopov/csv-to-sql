# csv-to-sql
###Console
```bash
./convert "path/to/file1.csv|path/to/file2.csv"
```
help
```bash
./convert -help
```

###PHP
```php
$object = new \Alva\CsvToSql\Convert(
    $files
    , OUTPUT_DIRECTORY
    , $inOneTable
    , $separatorColumns
);
$convertFiles = $object->run();
```