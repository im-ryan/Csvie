<?php

namespace Rhuett\Csvie\Cleaners;

use Illuminate\Database\Eloquent\Collection as DatabaseCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Rhuett\Csvie\Contracts\CsvieCleaner as CsvieCleanerContract;
use Rhuett\Csvie\Traits\CsvieHelpers;

/**
 * Class AbstractCsvieCleaner.
 *
 * An abstract CsvieCleaner implementation using a hashing method to quickly search through found models matched from a CSV file. This implementation relies on the abstract scrubber function to be able to clean different CSV files.
 */
abstract class AbstractCsvieCleaner implements CsvieCleanerContract
{
    use CsvieHelpers;

    /**
     * Unique identifier used to find rows within the CSV file.
     *
     * @var array
     */
    protected $csvUIDs;

    /**
     * Model that will be used for insertion.
     *
     * @var array
     */
    protected $emptyModel;

    /**
     * Unique identifier used to match database records within the CSV file.
     *
     * @var array
     */
    protected $modelUIDs;

    /**
     * Model that will be searched for in the database.
     *
     * @var mixed
     */
    protected $modelInstance;

    /**
     * Optional data that can be passed directly to the scrubber.
     *
     * @var mixed
     */
    protected $optionalData;

    /**
     * Initializes a new cleaner class.
     *
     * @param array|string $csvUIDs
     * @param array|string $modelUIDs
     * @param mixed        $model
     * @param mixed        $emptyModelOverride
     * @param mixed        $optionalData       = null
     */
    public function __construct($csvUIDs, $modelUIDs, $model, $emptyModelOverride, $optionalData = null)
    {
        $this->csvUIDs = is_array($csvUIDs)
            ? $csvUIDs
            : [$csvUIDs];
        $this->emptyModel = (is_null($emptyModelOverride) || empty($emptyModelOverride))
                ? $model
                : $emptyModelOverride;
        $this->modelUIDs = is_array($modelUIDs)
            ? $modelUIDs
            : [$modelUIDs];
        $this->modelInstance = $model;
        $this->optionalData = $optionalData;
    }

    /**
     * Builds the needed key to find the row in the hashed dataset.
     *
     * @param  array  $row
     * @param  array  $keys
     * @return string
     */
    private function getHashKey(array $row, array $keys)
    {
        // Turn the given array into a string with no separator
        return implode(
            '',
            array_intersect_key($row, $keys) // Set the given array equal to only the UID/value pairs we care about
        );
    }

    /**
     * Builds an empty array indexed with column values taken from the database.
     *
     * @param  \Illuminate\Support\Collection           $data
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function hashDataByUID(Collection $data): DatabaseCollection
    {
        $csvUids = $this->csvUIDs;
        $modelUids = $this->modelUIDs;
        $models = $this->modelInstance;

        // Loop through each given model UID
        for ($i = 0, $count = count($modelUids); $i < $count; $i++) {

            // Collect all unique values from the CSV file
            $allModelIds = $data
                ->pluck($csvUids[$i])
                ->ToArray();

            // Add a whereIn clause to our query builder to search for the above values
            $models = $models->whereIn($modelUids[$i], $allModelIds);
        }
        $modelUids = self::buildEmptyArray($modelUids);

        // Search the database once, then build the new hash
        return $models
            ->get()
            ->groupBy(function ($item) use ($modelUids) {
                return $this->getHashKey($item->ToArray(), $modelUids);
            });
    }

    public function scrub(Collection $data): Collection
    {
        $models = $this->hashDataByUID($data);                         // Hashed data set from database
        $newModel = self::createEmptyModelArray($this->emptyModel);    // Pre-built model skeleton
        $date = now();                                                 // Pre-made carbon date instance
        $csvUids = self::buildEmptyArray($this->csvUIDs);              // Pre-built array keys for getHashKey()
        $optionalData = $this->optionalData;

        // Filter the CSV file
        $data = $data->filterMap(function ($row) use ($models, $newModel, $date, $csvUids, $optionalData) {
            $key = $this->getHashKey($row, $csvUids);
            $foundModels = $models->has($key)
                ? $models->get($key)
                : null;

            return $this->scrubber($row, $foundModels, $newModel, $date, $optionalData);
        })->values(); // re-index values from 0

        return $data;
    }

    /**
     * Custom made function used to clean CSV data.
     *
     * @param  array                           $rowData      - The current row of data pulled from your CSV.
     * @param  ?\Illuminate\Support\Collection $foundModels  - Matched model(s) based on your CSV, otherwise contains null.
     * @param  array                           $newModel     - An empty model indexed with appropriate keys based on your model.
     * @param  \Illuminate\Support\Carbon      $date         - The current date used for timestamps.
     * @param  mixed                           $optionalData - Any custom data that you want to reference in the scrubber.
     * @return array|null
     */
    abstract protected function scrubber(array $rowData, ?Collection $foundModels, array $newModel, Carbon $date, $optionalData);

    /**
     * Updates the current value with the first non-null value found between the new value and other possible values, unless the current value is overridden, in which case, this returns the current value.
     *
     * @param  mixed $currVal
     * @param  mixed $newValue
     * @param  bool  $hasOverride = false
     * @param  array $otherPossVals
     * @return mixed
     */
    protected function updateValue($currVal, $newValue, bool $hasOverride = false, array $posVals = [])
    {
        // Skip if value is overridden.
        if ($hasOverride) {
            return $currVal;
        }

        // If newValue isn't null...
        if (! is_null($newValue)) {
            return $newValue;
        } elseif (! empty($posVals)) { // otherwise, if we have other possible values...

            // ...check for first non-null value.
            foreach ($posVals as $val) {
                if (! is_null($val)) {
                    return $val;
                }
            }
        } else { // if no other values could be found.
            return $currVal;
        }
    }
}
