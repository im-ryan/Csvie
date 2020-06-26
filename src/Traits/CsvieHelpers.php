<?php

namespace Rhuett\Csvie\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait CsvieHelpers
{
    /**
     * Returns the list of table names within a given connection. 
     * 
     * @param  string $connection = null
     * @return array
     */
    public static function getDbTableNames(string $connection = null)
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
    public static function getStorageDiskPath($storage)
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
     * If the given path has any missing directories, make them.
     * 
     * @param  string $path
     * @param  bool   $skipLastDir = false
     * @return void
     */
    public static function makePath(string $path, bool $skipLastDir = false)
    {
        $path = $skipLastDir
            ? pathinfo($path)['dirname']    // Grab all dirs except the last one
            : $path;
        $dirs = explode('/', $path);        // deconstruct the path
        unset($dirs[0]);                    // skip initial dir, as it's empty
        $dir = '/';                         // start rebuilding the path at root

        // Rebuild the path, verifying that all directories are created.
        foreach($dirs as $folder) {
            $dir .= "${folder}/";
            
            if(!is_dir($dir)) {
                mkdir($dir);
            }
        }
    }
}