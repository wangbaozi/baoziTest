<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Http\Controllers\Home\RedownController;

class Redown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:redown';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $cron =  new RedownController();


        $cron->cron();


    }


}
