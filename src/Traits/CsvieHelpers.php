<?php

namespace Rhuett\Csvie\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;

/**
 * Trait CsvieHelpers.
 *
 * Contains miscellaneous helpful functions for Csvie.
 */
trait CsvieHelpers
{
    /**
     * Builds an empty indexed array from an array of keys.
     *
     * @param  array $keys
     * @return array
     */
    public static function buildEmptyArray(array $keys): array
    {
        return array_fill_keys($keys, null);
    }

    /**
     * Builds an empty array indexed with column values taken from the database.
     *
     * @param  mixed $modelInstance
     * @return array
     */
    public static function createEmptyModelArray($modelInstance): array
    {
        $keys = self::getTableCols($modelInstance->getTable());
        $values = array_fill_keys($keys, null);

        return array_combine($keys, $values);
    }

    /**
     * Generates a random file name along with the file's extension.
     *
     * @param  string $extension = '.csv'
     * @return string
     */
    public static function generateUniqueFileName(string $extension = '.csv'): string
    {
        return md5(Str::random(40).time()).$extension;
    }

    /**
     * Returns the list of table names within a given connection.
     *
     * @return array
     */
    public static function getDbTableNames(): array
    {
        return Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableNames();
    }

    /**
     * Returns null if the given storage is null, otherwise gets the system path for a given Filesystem Adapter instance, or the name of the storage disk.
     *
     * @param  \Illuminate\Filesystem\FilesystemAdapter|string $storage
     * @return string|null
     */
    public static function getStorageDiskPath($storage): ?string
    {
        if (is_null($storage)) {
            return null;
        }

        if (gettype($storage) == 'string') {
            $storage = Storage::disk($storage);
        }

        return $storage
            ->getDriver()
            ->getAdapter()
            ->getPathPrefix();
    }

    /**
     * Returns the list of column names for a given table.
     *
     * @param  string $table
     * @return array
     */
    public static function getTableCols(string $table): array
    {
        return array_keys(
            Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableColumns($table)
        );
    }

    /**
     * Makes a given CSV file downloadable.
     *
     * @param  string $pathToFile
     * @param  string $downloadName = null
     * @return void
     */
    public static function makePathDownloadable(string $pathToFile, string $downloadName = null)
    {
        $baseName = pathinfo($pathToFile)['basename'];
        $mimeType = mime_content_type($pathToFile);

        header("Content-Type: ${mimeType}; charset=UTF-8");
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=\"${baseName}\"");

        $csv = Reader::createFromPath($pathToFile);
        $name = $downloadName ?? $baseName;

        $csv->output($name);

        // https://csv.thephpleague.com/9.0/connections/output/
        // Note: If you just need to make the CSV downloadable, end your script with a call to
        //       exit just after the output method. You should not return the method returned value.
        exit;
    }

    /**
     * If the given path has any missing directories, make them.
     *
     * @param  string $path
     * @param  bool   $skipLastDir = false
     * @return void
     */
    public static function makePath(string $path, bool $skipLastDir = false): void
    {
        $path = $skipLastDir
            ? pathinfo($path)['dirname']    // grab all dirs except the last one
            : $path;
        $dirs = explode('/', $path);        // deconstruct the path
        $dir = '/';                         // start rebuilding the path at root

        unset($dirs[0]);                    // skip initial dir, as it's empty due to explode()

        // Rebuild the path, verifying that all directories are created.
        foreach ($dirs as $folder) {
            $dir .= "${folder}/";

            if (! is_dir($dir)) {
                mkdir($dir);
            }
        }
    }
}
