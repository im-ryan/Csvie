# Csvie

Csvie is a simple CSV file parser made for Laravel. Csvie is based on LeagueCSV, and can quickly import data to, and export data from, a MySQL database. It also gives you a handy abstract class for quickly sanitizing and scrubbing your CSV files prior to insertion.

**WARNING:** Csvie is still under active development and unstable. It is currently not recommended to use this plugin until a stable channel is published, as breaking changes will be introduced in the near future.

## How it works

Csvie is meant to quickly load CSV files with more than a few thousand rows of data into a MySQL database. The idea behind how this works is simple:
1. You upload the CSV files onto your server.
2. Use Csvie to chunk the files into smaller pieces. Chunking will be done by rows of data, instead of file globs.
3. Write a custom CSV scrubber to clean data from the chunked files, then overwrite these files on the server.
   1. Note that you do not have to use the included HashCsvCleaner implementation. You are free to write your own using the Rhuett\Csvie\Contracts\CsvieCleaner interface.
4. Directly load the clean files into your MySQL database directly using the [Load Data statement](https://dev.mysql.com/doc/refman/8.0/en/load-data.html).

## Installation

Via Composer:

``` bash
$ composer require rhuett/csvie
$ php artisan vendor:publish --provider="Rhuett\Csvie\CsvieServiceProvider"
```

Make sure to add the following line to your app/config/database.php file:

``` php
'mysql' => [
    'driver' => 'mysql',
    // ...
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        // ...
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]) : [],
],
```
Once you have finished these configuration changes, don't forget to run:

``` bash
$ php artisan config:cache
```

## Usage

### Single CSV file import example, using a controller's store method:

``` php
public function store(Request $request)
{
    $csvie = new Csvie;                 // note: you can pass an array of config overrides if needed
    $modelInstance = new Model;
    $referenceData = 'Whatever I want'; // note: reference data is optional

    // Note: You can pass an array for both column and model UIDs if you need to verify against multiple columns
    $cleaner = new ModelCleaner(
        'ID',           // column name from CSV file to match
        'model_id',     // model ID to verify against column name
        $modelInstance,
        $referenceData  
    );
    
    $fileName = $request->file->store('/', 'uploads'); // move uploaded file from /temp into permanent storage
    $chunkedFiles = $csvie->chunkFiles(
        $csvie->getStorageDiskPath('uploads').$fileName
    );

    foreach($chunkedFiles as $chunk) {
        $chunkData = $csvie->readCsvFile($chunk);
        $cleanData = $cleaner->scrub($chunkData);
        $cleanFile = $csvie->saveCsvFile($chunk, $cleanData);

        $csvie->importCSV($cleanFile, $modelInstance);
    }
    $csvie->clearStorageDisk(); // clear out leftover uploaded file along with its chunks

    return view('view.index')->with([
        'models' => Model::all()
    ]);
}
```

### Making your own Csvie scrubber:

Simply run:

``` bash
$ php artisan make:cleaner ModelNameCleaner
```

...and you should get a new file that looks like the one below in the App\Services\CsvCleaners directory. Note that the extra comments displayed here will not be included in newly generated files.

```php
<?php
 
namespace App\Services\CsvCleaners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Rhuett\Csvie\Cleaners\HashCsvCleaner;

/**
 * Class ModelCleaner.
 * 
 * An abstract CsvieCleaner implementation using a custom scrubbing technique based on your needs.
 */
class ModelCleaner extends HashCsvCleaner
{
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
    protected function scrubber(array $rowData, ?Collection $foundModels, array $newModel, Carbon $date, $optionalData)
    {
        // Run checks on $rowData here. Validate, cleanse or completely change!
            // Use parent::updateValue() if you have many possible ways to update a single value within $rowData. Check the function for more information.
            // Return any changes, otherwise return $rowData to make no changes.
            // Return null if you want to remove the data completely.
            // Note: Duplicated headers will be automatically renamed in $rowData.
                // Ex: Header -> Header-1 -> Header-2 ...
            // Note: Since we are not using Eloquent, we will need to manage our timestamps manually.
                // This is why a Carbon datetime instance is passed as a parameter.
    }
}
```

Note: If you would like to change the CsvCleaner directory, you can edit the csvie config file in your app's config directory.

Don't forget to add the cleaners directory to your composer.json file (if needed):

```json
...
"autoload": {
        ...
        "classmap": [
            ...
            "app/Services"
        ]
    },
...
```

### Making your own CSV cleaner:

If you want a completely custom CSV Cleaner, then you can make your own implementation based on the Rhuett\Csvie\Contracts\CsvieCleaner contract like so:


```php
use Illuminate\Support\Collection;
use Rhuett\Csvie\Contracts\CsvieCleaner as CsvieCleanerContract;
use Rhuett\Csvie\Traits\CsvieHelpers;

/**
 * Class MyCsvCleaner.
 * 
 * An abstract CsvieCleaner implementation using a custom scrubbing technique based on your needs.
 */
abstract class MyCsvCleaner implements CsvieCleanerContract
{
    use CsvieHelpers;   // Not needed, review trait to see if it will help you.

    /**
     * Cleans the data within a CSV record to match what's expected by the database.
     * 
     * @param  \Illuminate\Support\Collection $data
     * @return \Illuminate\Support\Collection
     */
    public function scrub(Collection $data): Collection
    {
        // Clean all the data
    }
}
```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email im.ryan@protonmail.com instead of using the issue tracker.

## Credits

- [Ryan Huett][link-author]: Project Author
- [LeagueCSV][link-leaguecsv]: For making working with CSV's easy
- [Laravel Collection Macros][link-spatie]: For their succinct filterMap collection macro

## License

MPL-2.0. Please see the [license file](license.md) for more information.

[link-author]: https://github.com/im-ryan
[link-leaguecsv]: https://github.com/thephpleague/csv
[link-spatie]: https://github.com/spatie/laravel-collection-macros