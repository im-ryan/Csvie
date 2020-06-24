<?php

namespace Rhuett\Csvie;

use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Statement;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Csvie
{
    /**
     * The amount of CSV rows per chunked file (+1 for headers).
     * 
     * @var int
     */
    protected $fileChunkSize;

    /**
     * The file character set.
     * 
     * @var string
     */
    protected $fileCharSet;

    /**
     * Character that optionally encloses a field.
     * 
     * @var string
     */
    protected $fileEnclosedBy;

    /**
     * Character that escapes a field.
     * 
     * @var string
     */
    protected $fileEscapedBy;

    /**
     * The number of lines to ignore. Usually to ignore the header line.
     * 
     * @var int
     */
    protected $fileIgnoredLines;

    /**
     * Character that marks the end of a line.
     * 
     * @var string
     */
    protected $fileLinesTerminatedBy;

    /**
     * Character that marks the end of a field.
     * 
     * @var string
     */
    protected $fileTerminatedBy;

    /**
     * Whether or not to provide Mac support when reading files.
     * 
     * @var bool
     */
    protected $hasMacSupport;

    /**
     * The initial ini setting for auto_detect_line_endings.
     * 
     * @var int
     */
    protected $iniInitialSetting;

    /**
     * The Laravel disk where uploaded files are stored. This will also be where chunked files are stored.
     * 
     * @var string
     */
    protected $storageDisk;

    /**
     * Initialize csvie with the storage disk location and file chunk size.
     * 
     * @param  array $options (see csvie config file for array keys)
     */
    public function __construct(array $options = array())
    {
        $this->setClassVars($options);
    }

    /**
     * Chunks one or more files in the specified disk. Assumes files have a header. Returns array of new file paths.
     * 
     * @param  array $filePaths
     * @return array
     */
    public function chunkFiles(array $filePaths): array
    {
        $this->useMacSupportIfNeeded();
        $newFilePaths = array();

        foreach($filePaths as $path) {

            // Grab headers and make them unique
            $csvReader = Reader::createFromPath($path, 'r');
            $headers = $this->generateUniqueHeaders($csvReader->fetchOne());
            $headerStr = implode(',', $headers); // for later comparisons
            
            // Grab the rest of the file content if the file is not empty
            if(filesize($path) > 0) {
                $stmt = (new Statement())->offset(1)->limit($this->fileChunkSize);
                $count = 0;
                $fileNotEmpty = true; // whether or not the current output file is empty after records have been inserted

                while($fileNotEmpty) {

                    // Create new file
                    $newFile = $this->createNewFile($path);
                    $csvWriter = Writer::createFromFileObject($newFile);
                    $records = $stmt->process($csvReader)->getRecords($headers);

                    $dirs = explode('/', $newFile->getPath());
                    $filePath = $dirs[count($dirs) - 1] . '/' . $newFile->getFilename();

                    // Fill file
                    $csvWriter->insertOne($headers);
                    $csvWriter->insertAll($records);

                    // Verify current file isn't empty
                    $fileContent = str_replace('"', "", $csvWriter->getContent()); // remove quotes for string compare
                    $fileNotEmpty = !(substr($fileContent, 0, strlen($fileContent) - 1) == $headerStr); // remove ending newline character

                    // Get next chunk of content if needed, otherwise delete current file
                    if($fileNotEmpty) {
                        $count++;
                        $stmt = (new Statement())->offset($this->fileChunkSize * $count)->limit($this->fileChunkSize);
                        array_push($newFilePaths, $filePath);
                    } else {
                        unlink($newFile->getPathname());
                        $fileNotEmpty = false;
                    }

                }

            }
        }

        $this->unsetMacSupportIfNeeded();
        return $newFilePaths;
    }

    /**
     * Attempts to clear the storage disk of any leftover files, returns true if successful.
     * 
     * @return bool
     */
    public function clearStorageDisk(): bool
    {
        $storage = Storage::disk($this->storageDisk);
        $files = $storage->allFiles();
        $dirs = $storage->allDirectories();

        try {
            $storage->delete($files);
            foreach($dirs as $dir) {
                $storage->deleteDirectory($dir);
            }
            
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Creates a new file in a directory based on the file path.
     * 
     * @param  string $path     = null
     * @param  string $disk     = null
     * @param  string $filename = null
     * @param  string $mode     = 'r+'
     * @return \SplFileObject
     */
    protected function createNewFile(string $path = null, string $disk = null, string $filename = null, string $mode = 'r+'): \SplFileObject
    {
        $disk = !is_null($disk) ? $disk : $this->storageDisk;
        $storage = Storage::disk($disk);
        $currPath = $this->getStorageDiskPath($storage);
        $dir = !is_null($path) ? pathinfo($path)['filename'] . '/' : null;
        $name = is_null($filename) ? $filename : $filename . '_';
        
        if(!$storage->exists($dir)) {
            $storage->makeDirectory($dir);
        }

        $fileName = $dir . $name . $this->generateUniqueFileName();
        $storage->put($fileName, null);
        
        return new \SplFileObject($currPath . $fileName, $mode);
    }

    /**
     * Disables Mac support.
     * 
     * @return void
     */
    public function disableMacSupport(): void
    {
        $this->hasMacSupport = false;
    }

    /**
     * Enables Mac support.
     * 
     * @return void
     */
    public function enableMacSupport(): void
    {
        $this->hasMacSupport = true;
    }

    /**
     * Exports entire database, one table per CSV file adn returns the zipped file location.
     * 
     * @param  array  $excludedTables = array()
     * @param  string $filepath       = null
     * @param  string $disk           = null
     * @return string
     */
    public function exportDatabaseToCSVs(array $excludedTables = array(), string $filepath = null, string $disk = null): string
    {
        $zip = new \ZipArchive();
        $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        $disk = is_null($disk) ? $this->storageDisk : $disk;
        $filepath = is_null($filepath) ? $filepath : $filepath . '/';
        $filename = $this->getStorageDiskPath($disk) . $filepath . $this->generateUniqueFileName('.zip');
        $scrapFiles = array();

        // Make sure we can open the zip
        if($zip->open($filename, \ZipArchive::CREATE) !== TRUE) {
            return 'Error: Unable to create zip file for database export.';
        }

        // Export all wanted database tables
        foreach($tables as $table) {
            if(empty($excludedTables) || !in_array($table, $excludedTables)) {
                $file = $this->exportModelToCSV($table, $filepath, $disk);
                $zip->addFile($file, basename($file));
                array_push($scrapFiles, $file);
            }
        }
        $zip->close(); // zip file written to memory, can now delete leftover files

        // Remove exported files
        foreach($scrapFiles as $file) {
            unlink($file);
        }

        return $filename;
    }

    /**
     * This function exports all data from a table to a specified file. Takes in a model or table name.
     * 
     * @param  mixed  $model
     * @param  string $filePath = null
     * @param  string $disk     = null
     * @return string
     */
    public function exportModelToCSV($model, string $filePath = null, string $disk = null): string
    {
        $table = (gettype($model) === 'string') ? $model : $model->getTable();
        $cols = Schema::getColumnListing($table);
        $rows = DB::select("SELECT * FROM ${table}");
        $file = $this->createNewFile($filePath, $disk, $model);

        // $rows does not return an array of arrays, so we fix that here
        $rows = array_map(function($row) {
            return (array)$row;
        }, $rows);

        $csv = Writer::createFromFileObject($file);
        $csv->insertOne($cols);
        $csv->insertAll($rows);
        
        return $file->getPathname();
    }

    /**
     * MySQL doesn't handle null values predictably when importing from a CSV file. This function forces MySQL to treat null CSV values as null.
     *
     * @param  string $tableName
     * @return string
     */
    protected function generateMysqlEmptyStringOverwrite(string $tableName): string
    {
        $columns = Schema::getColumnListing($tableName);
        $columnLength = count($columns) - 1;
        $columnHeaders = '(';
        $nullOverwrite = ' SET ';

        foreach($columns as $key => $column) {

            // Generate MYSQL header data
            $columnHeaders .= ($key === 0) ? 
                '@' . $column :
                ',@' . $column;
            
             // Generate null overwrite commands, assuming any data that is empty should be null.
            $nullOverwrite .= $column . ' = nullif(@' . $column . ',\'\')';
            $nullOverwrite .= ($key === $columnLength) ? ';' : ', ';

        }
        $columnHeaders .= ')';

        return $columnHeaders . $nullOverwrite;
    }

    /**
     * Generates a random file name along with the file's extension.
     * 
     * @param  string $extension = '.csv'
     * @return string
     */
    protected function generateUniqueFileName(string $extension = '.csv'): string
    {
        return md5(Str::random(40) . time()) . $extension;
    }

    /**
     * Takes in a list of CSV headers and appends numbers to the end if they're duplicates.
     * 
     * @param  array $headers
     * @return array
     */
    protected function generateUniqueHeaders(array $headers): array
    {
        $uniqueHeaders = array();

        foreach($headers as $header) {
            $count = 0;
            $value = $original = trim($header);
            
            while(in_array($value, $uniqueHeaders)) {
                $value = $original . '-' . ++$count;
            }
    
            $uniqueHeaders[] = $value; 
        }

        return $uniqueHeaders;
    }

    /**
     * Gets the current file chunk size.
     * 
     * @return int
     */
    public function getFileChunkSize(): int
    {
        return $this->fileChunkSize;
    }

    /**
     * Returns true if Mac support is enabled, false otherwise.
     * 
     * @return bool
     */
    public function getHasMacSupport(): bool
    {
        return $this->fileFromMac;
    }

    /**
     * Gets the current storage disk location.
     * 
     * @return string
     */
    public function getStorageDisk(): string
    {
        return $this->storageDisk;
    }

    /**
     * Returns null if the given storage is null, otherwise gets the system path for a given Filesystem Adapter instance, or the name of the storage disk.
     * 
     * @param  \Illuminate\Filesystem\FilesystemAdapter|string $storage
     * @return string|null
     */
    public function getStorageDiskPath($storage)
    {
        if(is_null($storage)) {
            return null;
        }

        if(gettype($storage) == 'string') {
            $storage = Storage::disk($storage);
        }

        return $storage->getDriver()->getAdapter()->getPathPrefix();
    }

    /**
     * Imports a CSV file into the database, using a model as a reference. Returns true if all records were successfully inserted.
     * 
     * @param  string $filePath
     * @param  mixed  $model
     * @param  array  $options  = array() (see csvie config file for array keys)
     * @return bool
     */
    public function importCSV(string $filePath, $model, array $options = array()): bool
    {
        if(!empty($options)) {
            $this->setClassVars($options);
        }

        $filePath = $this->getStorageDiskPath($this->storageDisk) . $filePath;
        $table = (gettype($model) == 'string') ? $model : $model->getTable();
        $fileCharSet = $this->fileCharSet;
        $fileEnclosedBy = $this->fileEnclosedBy;
        $fileEscapedBy = $this->fileEscapedBy;
        $fileIgnoredLines = $this->fileIgnoredLines;
        $fileLinesTerminatedBy = $this->fileLinesTerminatedBy;
        $fileTerminatedBy = $this->fileTerminatedBy;

        // Get number of rows in CSV file without loading contents into memory
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);          // Find the highest row possible
        $numRowsOfData = $file->key() - 1; // Get the highest row, excluding header row

        $query = "LOAD DATA LOCAL INFILE '${filePath}'
            INTO TABLE ${table}
                CHARACTER SET ${fileCharSet}
                FIELDS
                    TERMINATED by '${fileTerminatedBy}'
                    OPTIONALLY ENCLOSED BY '${fileEnclosedBy}'
                    ESCAPED BY '${fileEscapedBy}'
                LINES
                    TERMINATED BY '${fileLinesTerminatedBy}'
                IGNORE ${fileIgnoredLines} LINES " .
                $this->generateMysqlEmptyStringOverwrite($table);
                
        $numRowsInserted = DB::connection()->getPdo()->exec($query);
        
        return $numRowsOfData === $numRowsInserted;
    }

    /**
     * Reads all data from a CSV file and returns the contents in an indexed array.
     * 
     * @param  string $filePath
     * @param  string $disk     = null
     * @return array
     */
    public function readCsvFile(string $filePath, string $disk = null): array
    {
        $disk = is_null($disk) ? $this->storageDisk : $disk;
        $csv = Reader::createFromPath($this->getStorageDiskPath($disk) . $filePath, 'r');
        $headers = $this->generateUniqueHeaders($csv->fetchOne());
        $records = (new Statement())->offset(1)->process($csv)->getRecords($headers);
        $content = array();

        foreach($records as $record) {
            array_push($content, $record);
        }

        return $content;
    }

    /**
     * Restores the database given the path to an export zip file.
     * 
     * @param  string $zipFile
     * @return bool
     */
    public function restoreDatabase(string $zipFile): bool
    {
        $zip = new \ZipArchive();
        $pathInfo = pathinfo($zipFile);
        $dirPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '/';

        // Make directory if needed
        if(!is_dir($dirPath)) {
            mkdir($dirPath);
        }

        // Extract all files
        $zip->open($zipFile);
        $zip->extractTo($dirPath);
        $zip->close();

        // Grab list of extracted files and import them
        $resFiles = array_diff(scandir($dirPath), array('..', '.'));
        foreach($resFiles as $file) {
            $successfulRestore = $this->restoreModel($dirPath . $file);

            // If there was an error, stop importing and return false
            if(!$successfulRestore) {
                return false;
            }
        }

        return true;
    }

    /**
     * Restores a certain model given the path to an export CSV file.
     * 
     * @param  string $filePath
     * @return bool
     */
    public function restoreModel(string $filePath): bool
    {
        $pathInfo = pathinfo($filePath);
        $dirPath = $pathInfo['dirname'];
        $dirName = substr($dirPath, strrpos($dirPath, '/') + 1, strlen($dirPath));
        $fileName = $pathInfo['filename'];

        $table = substr($fileName, 0, strrpos($fileName, '_'));
        DB::table($table)->truncate();

        $fileName = $dirName . '/' . $pathInfo['basename'];
        return $this->importCSV($fileName, $table);
    }

    /**
     * Saves a CSV file, returns true if file was saved successfully.
     * 
     * @param  string $filePath
     * @param  array  $content
     * @param  string $disk     = null
     * @return bool
     */
    public function saveCsvFile(string $filePath, array $content, string $disk = null): bool
    {
        $disk = is_null($disk) ? $this->storageDisk : $disk;
        $storage = Storage::disk($disk);
        $absPath = $this->getStorageDiskPath($disk) . $filePath;

        if(!$storage->exists($filePath) || !array_key_exists(0, $content)) {
            return false;
        }

        // Delete old file and create a new, empty file
        $storage->delete($filePath);
        $storage->put($filePath, '');

        $csvWriter = Writer::createFromPath($absPath);
        $headers = array_keys($content[0]);

        $csvWriter->insertOne($headers);
        $csvWriter->insertAll($content);

        return true;
    }

    /**
     * Sets up the class variables given an array of options.
     * 
     * @param  array $options (see csvie config file for array keys)
     * @return void
     */
    private function setClassVars(array $options): void
    {
        if(empty($options)) {
            $this->fileCharSet = config('csvie.file_charset');
            $this->fileChunkSize = config('csvie.file_chunksize');
            $this->fileEnclosedBy = config('csvie.file_fields_enclosedby');
            $this->fileEscapedBy = config('csvie.file_fields_escapedby');
            $this->fileIgnoredLines = config('csvie.file_lines_ignored');
            $this->fileLinesTerminatedBy = config('csvie.file_lines_terminatedby');
            $this->fileTerminatedBy = config('csvie.file_fields_terminatedby');
            $this->hasMacSupport = config('csvie.file_macsupport');
            $this->storageDisk = config('csvie.storage_disk');
        } else {
            $this->fileCharSet = array_key_exists('file_charset', $options) ? $options['file_charset'] : config('csvie.file_charset');
            $this->fileChunkSize = array_key_exists('file_chunksize', $options) ? $options['file_chunksize'] : config('csvie.file_chunksize');
            $this->fileEnclosedBy = array_key_exists('file_fields_enclosedby', $options) ? $options['file_fields_enclosedby'] : config('csvie.file_fields_enclosedby');
            $this->fileEscapedBy = array_key_exists('file_fields_escapedby', $options) ? $options['file_fields_escapedby'] : config('csvie.file_fields_escapedby');
            $this->fileIgnoredLines = array_key_exists('file_lines_ignored', $options) ? $options['file_lines_ignored'] : config('csvie.file_lines_ignored');
            $this->fileLinesTerminatedBy = array_key_exists('file_lines_terminatedby', $options) ? $options['file_lines_terminatedby'] : config('csvie.file_lines_terminatedby');
            $this->fileTerminatedBy = array_key_exists('file_fields_terminatedby', $options) ? $options['file_fields_terminatedby'] : config('csvie.file_fields_terminatedby');
            $this->hasMacSupport = array_key_exists('file_macsupport', $options) ? $options['file_macsupport'] : config('csvie.file_macsupport');
            $this->storageDisk = array_key_exists('disk', $options) ? $options['storage_disk'] : config('csvie.storage_disk');
        }
    }

    /**
     * Set the current file chunk size.
     * 
     * @param  int $fileChunkSize
     * @return void
     */
    public function setFileChunkSize(int $fileChunkSize): void
    {
        $this->fileChunkSize = $fileChunkSize;
    }

    /**
     * Set the current storage disk location.
     * 
     * @param  string $storageDisk
     * @return void
     */
    public function setStorageDisk(string $storageDisk): void
    {
        $this->storageDisk = $storageDisk;
    }

    /**
     * Stores files in chunks onto the local server.
     * 
     * @return void
     */
    protected function useMacSupportIfNeeded(): void
    {
        $this->iniInitialSetting = ini_get("auto_detect_line_endings");

        if($this->hasMacSupport && !$this->iniInitialSetting) {
            ini_set("auto_detect_line_endings", '1');
        }
    }

    /**
     * Reset Mac support if required.
     * 
     * @return void
     */
    protected function unsetMacSupportIfNeeded(): void
    {
        if($this->iniInitialSetting != ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", $this->iniInitialSetting);
        }
    }
}