<?php

namespace Rhuett\Csvie\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\GeneratorCommand;

class CleanerMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:cleaner
                            {name : The name of the CSV cleaner class with optional directory separator of \'/\'}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new CSV cleaner class.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'CSV Cleaner';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/cleaner.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . config('csvie.cleaner_dir');
    }
}