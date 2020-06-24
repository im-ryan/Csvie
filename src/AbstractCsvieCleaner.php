<?php
 
namespace Rhuett\Csvie;

use Illuminate\Support\Facades\Schema;

abstract class AbstractCsvieCleaner
{
    /**
     * Unique identifier used to find rows within the CSV file.
     * 
     * @var string
     */
    protected $csvUID;

    /**
     * Unique identifier used to match database records within the CSV file.
     * 
     * @var string
     */
    protected $modelUID;
    
    /**
     * Model used for insertion.
     * 
     * @var mixed
     */
    protected $modelInstance;

    /**
     * Initializes a new cleaner class.
     * 
     * @param string $csvUID
     * @param string $modelUID
     * @param mixed  $model
     */
    public function __construct(string $csvUID, string $modelUID, $model)
    {
        $this->csvUID = $csvUID;
        $this->modelUID = $modelUID;
        $this->modelInstance = $model;
    }

    /**
     * Builds an empty array indexed with column values taken from the database.
     * 
     * @return array
     */
    private function createEmptyModelArray(): array
    {
        $keys = Schema::getColumnListing($this->modelInstance->getTable());
        $values = array_fill(0, count($keys), null);

        return array_combine($keys, $values);
    }

    /**
     * Builds an empty array indexed with column values taken from the database.
     * 
     * @param  \Illuminate\Support\Collection           $data
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function hashDataByUID(\Illuminate\Support\Collection $data): \Illuminate\Database\Eloquent\Collection
    {
        $allModelIds = $data->pluck($this->csvUID)->ToArray();

        $hash = $this->modelInstance::whereIn($this->modelUID, $allModelIds)
            ->get()
            ->groupBy($this->modelUID);
        
        return $hash;
    }

    /**
     * Cleans the data within a CSV record to match what's expected by the database.
     * 
     * @param  \Illuminate\Support\Collection $data
     * @return \Illuminate\Support\Collection
     */
    public function scrub(\Illuminate\Support\Collection $data): \Illuminate\Support\Collection
    {
        $models = $this->hashDataByUID($data);
        $newModel = $this->createEmptyModelArray();
        $date = now();

        $data = $data->filterMap(function($row) use ($models, $newModel, $date) {
            $foundModels = $models->has($row[$this->csvUID]) ? 
                $models->get($row[$this->csvUID]) : 
                null;

            return $this->scrubber($row, $foundModels, $newModel, $date);            
        });
        
        return $data;
    }

    /**
     * Custom made function used to clean CSV data.
     * 
     * @param  array                      $rowData     - The current row of data pulled from your CSV.
     * @param  mixed                      $foundModels - Matched model(s) based on your CSV, otherwise contains null.
     * @param  array                      $newModel    - An empty model indexed with appropriate keys based on your model.
     * @param  \Illuminate\Support\Carbon $date        - The current date used for timestamps.
     * @return array|null
     */
    abstract protected function scrubber(array $rowData, $foundModels, array $newModel, \Illuminate\Support\Carbon $date);

    /**
     * Updates the current value with the first non-null value found between the new value and other possible values, unless the current value is overridden, in which case, this returns the current value.
     *
     * @param  mixed $currVal
     * @param  mixed $newValue
     * @param  bool  $hasOverride = false
     * @param  array $otherPossVals
     * @return mixed
     */
    protected function updateValue($currVal, $newValue, bool $hasOverride = false, array $posVals = array())
    {
        // Skip if value is overridden.
        if($hasOverride) {
            return $currVal;
        }

        // If newValue isn't null...
        if(!is_null($newValue)) {
            return $newValue;
        } else if(!empty($posVals)) { // otherwise, if we have other possible values...

            // ...check for first non-null value.
            foreach($posVals as $val) {
                if(!is_null($val)) {
                    return $val;
                }
            }

        } else { // if no other values could be found.
            return $currVal;
        }
    }
}