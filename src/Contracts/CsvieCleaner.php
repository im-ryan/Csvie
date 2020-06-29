<?php

namespace Rhuett\Csvie\Contracts;

/**
 * Interface CsvieCleaner.
 *
 * The interface for making a CsvieCleaner implementation.
 */
interface CsvieCleaner
{
    /**
     * Cleans the data within a CSV record to match what's expected by the database.
     *
     * @param  \Illuminate\Support\Collection $data
     * @return \Illuminate\Support\Collection
     */
    public function scrub(\Illuminate\Support\Collection $data): \Illuminate\Support\Collection;
}
