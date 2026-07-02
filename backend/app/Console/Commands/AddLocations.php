<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:add';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add on DB all locations, cities, countries , states';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info('Command running at '.now());

        if (City::doesntExist()) {
            DB::unprepared(file_get_contents(base_path('dev/DatabaseWorld/world.sql')));
        }
    }
}
