<?php

return [

    /*
     * The directory where custom CSV cleaner classes are saved.
     *
     * Default: '\Services\CsvCleaner'
     */
    'cleaner_dir'              => '\Services\CsvCleaners',

    /*
     * The Laravel disk where uploaded files are stored. This will also be where chunked files are stored.
     *
     * Default: 'public'
     */
    'storage_disk'             => 'public',

    /*
    |--------------------------------------------------------------------------
    | MySQL File Import Options
    |--------------------------------------------------------------------------
    |
    | The following options are used for the import process. Below are the option names, defaults, and a quick description of
    | what each option does.
    |
    | 'file_charset'              => 'utf8'     // The file character set.
    | 'file_chunksize'            => '1000'     // The amount of CSV rows per chunked file (+1 for headers).
    | 'file_fields_enclosedby'    => '\"'       // Character that optionally encloses a field.
    | 'file_fields_escapedby'     => '\b'       // Character that escapes a field.
    | 'file_fields_terminatedby'  => ','        // Character that marks the end of a field.
    | 'file_lines_ignored'        => 1          // The number of lines to ignore. Usually to ignore the header line.
    | 'file_lines_terminatedby'   => '\n'       // Character that marks the end of a line.
    | 'file_macsupport'           => false      // Whether or not to support CSV files created by or uploaded by a Mac.
    |
    */

    'file_charset'             => 'utf8',
    'file_chunksize'           => 1000,
    'file_fields_enclosedby'   => '\"',
    'file_fields_escapedby'    => '\b',
    'file_fields_terminatedby' => ',',
    'file_lines_ignored'       => 1,
    'file_lines_terminatedby'  => '\n',
    'file_macsupport'          => false,
];
