# Csvie

Csvie is a simple CSV file parser made for Laravel. Csvie is based on LeagueCSV, and can quickly import data to, and export data from, a MySQL database. It also gives you a handy abstract class for quickly sanitizing and scrubbing your CSV files prior to insertion.

## How it works

Csvie is meant to quickly load CSV files with more than a few thousand rows of data into a MySQL database. The idea behind how this works is simple:
1. You upload the CSV files onto your server.
2. Use csvie to chunk the files into smaller pieces. Chunking will be done by rows of data, instead of file globs.
3. Write a custom CSV scrubber to clean data from the chunked files, then overwrite these files on the server.
   1. Note that you do not have to use the included scrubber. You are free to write your own for even quicker validation checks.
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
Once you have finished these configuration changes, dont forget to run:

``` bash
$ php artisan config:cache
```

## Usage

### Single CSV file import example, using a controller's store method:

``` php
public function store(Request $request)
{
    $csvie = new csvie; // Create new csvie instance with default configuration
    $cleaner = new ModelCleaner('ID', 'model_id', new Model); // Initiate custom cleaner based on AbstractCsvieCleaner

    // Store uploaded file (moving from temp directory into permanent storage)
    $fileName = $request->file->store('/', 'uploads');

    // Chunk file
    $chunkedFiles = $csvie->chunkFiles([
        $csvie->getStorageDiskPath('uploads') . $fileName
    ]);


    // For each chunked file...
    foreach($chunkedFiles as $chunk) {

        // ...clean data within chunked file
        $cleanData = $cleaner->scrub(
            collect($csvie->readCsvFile($chunk))
        );

        // ...overwrite the changes
        $fileCleaned = $csvie->saveCsvFile($chunk, $cleanData->ToArray());

        // ...import file
        if($fileCleaned) {
            $isDataImported = $csvie->importCSV($chunk, new Student);
        }

    }

    // Clear out leftover uploaded file and chunked files
    $csvie->clearStorageDisk();

    // Return view with newly inserted/updated models
    return view('view.index')->with([
        'models' => Model::all()
    ]);
}
```

### Making your own CSV cleaner:

Simply run:

``` bash
$ php artisan make:cleaner ModelName
```

...and you should get a new file that looks like the one below in the App\Services\CsvCleaners directory. Note that the extra comments displayed here will not be included in newly generated files.

```php
<?php
 
namespace Services\CsvCleaners;

use Rhuett\csvie\AbstractCsvieCleaner;

class ModelCleaner extends AbstractCsvieCleaner
{
    /**
     * Custom made function used to clean CSV data.
     * 
     * @param  array                      $rowData     - The current row of data pulled from your CSV.
     * @param  mixed                      $foundModels - Matched model(s) based on your CSV, otherwise contains null.
     * @param  array                      $newModel    - An empty model indexed with appropriate keys based on your model.
     * @param  \Illuminate\Support\Carbon $date        - The current date used for timestamps.
     * @return array|null
     */
    protected function scrubber(array $rowData, $foundModels, array $newModel, \Illuminate\Support\Carbon $date)
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

[ico-version]: https://img.shields.io/packagist/v/rhuett/csvie.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/rhuett/csvie.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/rhuett/csvie/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/rhuett/csvie
[link-downloads]: https://packagist.org/packages/rhuett/csvie
[link-travis]: https://travis-ci.org/rhuett/csvie
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/im-ryan
[link-leaguecsv]: https://github.com/thephpleague/csv
[link-spatie]: https://github.com/spatie/laravel-collection-macros