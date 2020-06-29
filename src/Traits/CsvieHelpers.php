<?php

namespace Rhuett\Csvie\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Trait CsvieHelpers.
 * 
 * Contains miscellaneous helpful functions for Csvie.
 *
 * @package Rhuett\Csvie\Traits;
 */
trait CsvieHelpers
{
    /**
     * Builds the key array needed for getHashKey(). 
     * 
     * @param  array $keys
     * @return array
     */
    private static function buildEmptyArray(array $keys): array
    {
        return array_fill_keys($keys, null);
    }

    /**
     * Builds an empty array indexed with column values taken from the database.
     * 
     * @param  mixed $modelInstance
     * @return array
     */
    private static function createEmptyModelArray($modelInstance): array
    {
        $keys = self::getTableCols($modelInstance->getTable());
        $values = array_fill_keys($keys, null);

        return array_combine($keys, $values);
    }

    /**
     * Returns the list of table names within a given connection. 
     * 
     * @param  string $connection = null
     * @return array
     */
    public static function getDbTableNames(string $connection = null): array
    {
        return DB::connection($connection)
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
        if(is_null($storage)) {
            return null;
        }

        if(gettype($storage) == 'string') {
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
        return Schema::getColumnListing($table);
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
        foreach($dirs as $folder) {
            $dir .= "${folder}/";
            
            if(!is_dir($dir)) {
                mkdir($dir);
            }
        }
    }
}